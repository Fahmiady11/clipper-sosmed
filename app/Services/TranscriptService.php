<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class TranscriptService
{
    public function fetch(string $youtubeUrl, ?string $videoPath = null): array
    {
        $videoId = $this->extractVideoId($youtubeUrl);

        if (!$videoId) {
            throw new RuntimeException("Cannot extract video ID from: {$youtubeUrl}");
        }

        $log = Log::channel('clipper_jobs');

        // Primary: yt-dlp handles YouTube anti-bot (pot token / signature).
        // Direct timedtext scraping returns 200 + empty body since ~2024.
        $log->info('[TRANSCRIPT] Trying primary: yt-dlp subtitles', ['video_id' => $videoId]);
        $result = $this->fetchViaYtDlp($youtubeUrl, $videoId);

        if (!empty($result['items'])) {
            $log->info('[TRANSCRIPT] PRIMARY used: yt-dlp subtitles', [
                'language' => $result['language'],
                'segments' => count($result['items']),
            ]);
            return ['language' => $result['language'], 'segments' => $result['items']];
        }

        $log->warning('[TRANSCRIPT] PRIMARY failed (no subtitles), trying FALLBACK: Groq Whisper');

        // Fallback: Groq Whisper on the downloaded video file
        if ($videoPath && file_exists($videoPath)) {
            $groqResult = $this->fallbackGroq($videoPath);
            if (!empty($groqResult['segments'])) {
                $log->info('[TRANSCRIPT] FALLBACK used: Groq whisper-large-v3-turbo', [
                    'language' => $groqResult['language'],
                    'segments' => count($groqResult['segments']),
                ]);
            } else {
                $log->error('[TRANSCRIPT] FALLBACK also failed: Groq returned no segments');
            }
            return $groqResult;
        }

        $log->error('[TRANSCRIPT] All methods failed: no subtitles, no video file for Groq');
        return ['language' => 'id', 'segments' => []];
    }

    private function fetchViaYtDlp(string $url, string $videoId): array
    {
        set_time_limit(0); // yt-dlp subtitle fetch must not hit PHP max_execution_time
        $bin     = config('services.ytdlp.path', 'yt-dlp');
        $browser = config('services.ytdlp.cookies_from_browser', '');
        $cookie  = $browser ? ' --cookies-from-browser ' . escapeshellarg($browser) : '';
        $tmpDir  = storage_path('app/temp/subs_' . $videoId);
        @mkdir($tmpDir, 0755, true);

        // Clean stale subtitle files from prior runs
        foreach (glob("{$tmpDir}/*.json3") ?: [] as $f) {
            @unlink($f);
        }

        // yt-dlp inserts the lang code: out template "sub.%(ext)s" -> "sub.id.json3"
        $outTpl = $tmpDir . '/sub.%(ext)s';
        $cmd = sprintf(
            '%s%s --skip-download --write-auto-subs --write-subs --sub-langs %s'
            . ' --sub-format json3 --no-playlist -o %s %s 2>&1',
            $bin,
            $cookie,
            escapeshellarg('id,en'),
            escapeshellarg($outTpl),
            escapeshellarg($url)
        );

        // Don't hard-fail on non-zero exit: a 429 on the 2nd lang still leaves
        // the 1st lang's file written. We validate by looking for output files.
        exec($cmd, $output, $code);
        Log::channel('clipper_jobs')->info('yt-dlp subtitle fetch', ['code' => $code, 'out' => implode("\n", $output)]);

        // Prefer Indonesian, then English, then anything written
        $file = null;
        $lang = 'id';
        foreach (['id', 'en'] as $l) {
            $hit = glob("{$tmpDir}/*.{$l}.json3") ?: [];
            if (!empty($hit)) {
                $file = $hit[0];
                $lang = $l;
                break;
            }
        }
        if (!$file) {
            $any = glob("{$tmpDir}/*.json3") ?: [];
            if (!empty($any)) {
                $file = $any[0];
                if (preg_match('/\.([a-z-]+)\.json3$/', $file, $m)) {
                    $lang = $m[1];
                }
            }
        }

        if (!$file) {
            return [];
        }

        $data  = json_decode(file_get_contents($file), true) ?: [];
        $items = $this->parseJson3($data);

        // Cleanup
        foreach (glob("{$tmpDir}/*.json3") ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($tmpDir);

        return ['language' => $lang, 'items' => $items];
    }

    private function parseJson3(array $data): array
    {
        $events   = $data['events'] ?? [];
        $segments = [];

        foreach ($events as $event) {
            if (!isset($event['segs'])) {
                continue;
            }
            $text = collect($event['segs'])->pluck('utf8')->join('');
            $text = trim(preg_replace('/\s+/', ' ', $text));
            if (!$text) {
                continue;
            }

            $segments[] = [
                'start' => round(($event['tStartMs'] ?? 0) / 1000, 2),
                'end'   => round((($event['tStartMs'] ?? 0) + ($event['dDurationMs'] ?? 3000)) / 1000, 2),
                'text'  => $text,
            ];
        }

        return $segments;
    }

    private function fallbackGroq(string $videoPath): array
    {
        $log = Log::channel('clipper_jobs');

        $groqKey = config('services.groq.api_key');
        if (!$groqKey) {
            $log->warning('fallbackGroq: GROQ_API_KEY not set');
            return ['language' => 'id', 'segments' => []];
        }

        $ffmpeg   = config('services.ffmpeg.path', 'ffmpeg');
        // MP3 at 32kbps mono ≈ 14MB/hr — fits Groq's 25MB limit even for long videos
        $tmpAudio = sys_get_temp_dir() . '/ts_audio_' . uniqid() . '.mp3';

        $log->info('fallbackGroq: extracting audio', ['video' => $videoPath, 'tmp' => $tmpAudio]);
        exec(sprintf('%s -i %s -ar 16000 -ac 1 -b:a 32k %s -y 2>&1',
            $ffmpeg, escapeshellarg($videoPath), escapeshellarg($tmpAudio)), $out, $code);

        if ($code !== 0 || !file_exists($tmpAudio)) {
            $log->error('fallbackGroq: ffmpeg failed', ['code' => $code, 'out' => implode("\n", $out)]);
            return ['language' => 'id', 'segments' => []];
        }

        $audioSize = filesize($tmpAudio);
        $log->info('fallbackGroq: sending to Groq', ['audio_size_mb' => round($audioSize / 1048576, 1)]);

        if ($audioSize > 24 * 1024 * 1024) {
            $log->error('fallbackGroq: audio too large for Groq API', ['size_mb' => round($audioSize / 1048576, 1)]);
            @unlink($tmpAudio);
            return ['language' => 'id', 'segments' => []];
        }

        $ch = curl_init('https://api.groq.com/openai/v1/audio/transcriptions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'file'                      => new \CURLFile($tmpAudio, 'audio/mpeg', 'audio.mp3'),
                'model'                     => 'whisper-large-v3-turbo',
                'response_format'           => 'verbose_json',
                'timestamp_granularities[]' => 'segment',
                'language'                  => 'id',
            ],
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $groqKey],
            CURLOPT_TIMEOUT    => 300,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        @unlink($tmpAudio);

        if ($httpCode !== 200) {
            $log->error('fallbackGroq: Groq API error', ['http' => $httpCode, 'body' => $response]);
            return ['language' => 'id', 'segments' => []];
        }

        $data     = json_decode($response, true);
        $segments = collect($data['segments'] ?? [])
            ->map(fn($seg) => [
                'start' => round((float) $seg['start'], 2),
                'end'   => round((float) $seg['end'], 2),
                'text'  => trim($seg['text']),
            ])
            ->filter(fn($s) => $s['text'] !== '')
            ->values()
            ->all();

        $log->info('fallbackGroq: done', ['segments' => count($segments)]);

        return [
            'language' => $data['language'] ?? 'id',
            'segments' => $segments,
        ];
    }

    private function extractVideoId(string $url): ?string
    {
        if (preg_match('/(?:watch\?v=|youtu\.be\/|shorts\/|embed\/)([A-Za-z0-9_-]{6,})/', $url, $m)) {
            return $m[1];
        }
        return null;
    }
}
