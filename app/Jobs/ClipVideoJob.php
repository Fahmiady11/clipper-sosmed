<?php

namespace App\Jobs;

use App\Models\ClipJob;
use App\Services\Transcribers\GeminiTranscriber;
use App\Services\Transcribers\GroqTranscriber;
use App\Services\Transcribers\SrtHelper;
use App\Services\Transcribers\WhisperTranscriber;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ClipVideoJob implements ShouldQueue
{
    use Queueable, SrtHelper;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(public string $jobId) {}

    public function handle(): void
    {
        $clip = ClipJob::findOrFail($this->jobId);
        $clip->update(['status' => 'processing']);

        $clipsDir   = Storage::path('clips');
        $outputFile = $clipsDir . '/' . $this->jobId . '.mp4';

        if (!is_dir($clipsDir)) {
            mkdir($clipsDir, 0755, true);
        }

        $ytdlp  = $this->findBinary('yt-dlp');
        $ffmpeg = $this->findBinary('ffmpeg');

        $start    = (int) $clip->start_time;
        $end      = (int) $clip->end_time;
        $duration = $end - $start;

        $url         = escapeshellarg($clip->youtube_url);
        $tmpTemplate = escapeshellarg('/tmp' . '/' . $this->jobId . '_raw.%(ext)s');
        $section     = escapeshellarg("*{$start}-{$end}");
        $cookies     = $this->cookiesArg();

        $downloadCmd = "$ytdlp $cookies -f 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/best[ext=mp4]/best' --download-sections $section -o $tmpTemplate $url 2>&1";
        exec($downloadCmd, $dlOutput, $dlCode);

        if ($dlCode !== 0) {
            throw new \RuntimeException('yt-dlp failed: ' . implode("\n", $dlOutput));
        }

        $rawFile = $this->findDownloadedFile($this->jobId);
        if (!$rawFile) {
            throw new \RuntimeException('Downloaded file not found in /tmp');
        }

        $rawArg      = escapeshellarg($rawFile);
        $ffprobe     = $this->findBinary('ffprobe');
        exec("$ffprobe -v quiet -show_entries format=duration -of csv=p=0 -i $rawArg 2>&1", $durOut);
        $rawDuration = (float)($durOut[0] ?? 0);
        $needsSeek   = $rawDuration > ($duration * 1.5 + 5);

        // When --download-sections cuts at keyframe, raw file may have extra prefix before $start.
        // Detect and skip it so FFmpeg cut aligns exactly with $start.
        $seekStart = $needsSeek
            ? $start
            : max(0, (int)floor($rawDuration - $duration));

        $verticalFile = $this->applyLayout($ffmpeg, $rawFile, $seekStart, $duration, $clip->layout_mode ?? 'gaussian_blur');
        if ($verticalFile) {
            @unlink($rawFile);
            $rawFile   = $verticalFile;
            $seekStart = 0;
        }

        if ($clip->auto_caption) {
            $this->processWithAutoCaption($ffmpeg, $rawFile, $outputFile, $seekStart, $duration, $clip);
        } else {
            $input  = escapeshellarg($rawFile);
            $output = escapeshellarg($outputFile);
            $ss     = $seekStart > 0 ? "-ss $seekStart" : '';
            exec("$ffmpeg $ss -i $input -t $duration -c copy $output -y 2>&1", $out, $code);
            if ($code !== 0) {
                throw new \RuntimeException('FFmpeg failed: ' . implode("\n", $out));
            }
        }

        @unlink($rawFile);

        $clip->update([
            'status'    => 'done',
            'file_path' => 'clips/' . $this->jobId . '.mp4',
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    private function applyLayout(
        string $ffmpeg,
        string $rawFile,
        int $seekStart,
        int $duration,
        string $layoutMode
    ): ?string {
        $outFile     = '/tmp/' . $this->jobId . '_vertical.mp4';
        $input       = escapeshellarg($rawFile);
        $output      = escapeshellarg($outFile);
        $ss          = $seekStart > 0 ? "-ss $seekStart" : '';
        $filterGraph = (new \App\Services\VideoLayoutService)->layoutFilter($layoutMode);

        $cmd = "$ffmpeg $ss -i $input -t $duration " .
            "-filter_complex \"{$filterGraph}\" -map '[out]' -map '0:a?' " .
            "-c:v libx264 -preset fast -crf 23 -c:a aac -b:a 128k " .
            "$output -y 2>&1";

        exec($cmd, $out, $code);

        if ($code !== 0 || !file_exists($outFile)) {
            echo "TikTok layout failed (code $code), using original.\n";
            return null;
        }

        return $outFile;
    }

    private function processWithAutoCaption(
        string $ffmpeg,
        string $rawFile,
        string $outputFile,
        int $start,
        int $duration,
        ClipJob $clip
    ): void {
        $tmpDir  = '/tmp';
        $cutFile = "$tmpDir/{$this->jobId}_cut.mp4";
        $srtFile = "$tmpDir/{$this->jobId}_caption.srt";

        // Pass 1: cut video (input seek + duration → timestamps reset to near-0)
        $rawArg = escapeshellarg($rawFile);
        $cutArg = escapeshellarg($cutFile);
        $ss     = $start > 0 ? "-ss $start" : '';
        exec("$ffmpeg $ss -i $rawArg -t $duration -c:v copy -c:a aac -avoid_negative_ts make_zero $cutArg -y 2>&1", $o, $c);
        if ($c !== 0) {
            throw new \RuntimeException('FFmpeg cut failed: ' . implode("\n", $o));
        }

        // Audio stream in raw file may have positive PTS offset vs video (A/V misalign from yt-dlp merge).
        // Probe audio start time now so we can shift SRT timestamps to compensate.
        $audioDelayMs = $this->probeAudioDelay($cutFile);
        echo "Audio stream delay in cut file: {$audioDelayMs}ms\n";

        $lang = $clip->caption_language ?: 'id';

        // try {
        //     (new GroqTranscriber($ffmpeg, $this->jobId))->transcribe($cutArg, $srtFile, $lang);
        // } catch (\Throwable) {
        //     (new WhisperTranscriber($ffmpeg, $this->jobId))->transcribe($cutArg, $srtFile, $lang);
        // }

        (new GroqTranscriber($ffmpeg, $this->jobId))->transcribe($cutArg, $srtFile, $lang);

        $this->splitSrtByWords($srtFile, 4);

        // Shift SRT timestamps to align with actual audio start in container
        if ($audioDelayMs > 50) {
            $this->shiftSrtTimestamps($srtFile, $audioDelayMs);
        }

        // Pass 2: burn SRT into cut clip (timestamps already 0-based)
        $output     = escapeshellarg($outputFile);
        $srtEscaped = $this->escapeSrtPath($srtFile);


        $style = (new \App\Services\VideoLayoutService)->subtitleStyle();
        exec("$ffmpeg -i $cutArg -vf \"subtitles='$srtEscaped':force_style='{$style}'\" -c:v libx264 -preset fast -c:a aac $output -y 2>&1", $fo, $fc);

        @unlink($srtFile);
        @unlink($cutFile);

        if ($fc !== 0) {
            throw new \RuntimeException('FFmpeg subtitle burn failed: ' . implode("\n", $fo));
        }

        // Trim 2 detik pertama dari output final (subtitle sudah hardcoded, ikut ke-trim)
        $trimmedFile = $outputFile . '.trim.mp4';
        $trimOut     = escapeshellarg($trimmedFile);
        exec("$ffmpeg -ss 2.5 -i $output -c copy $trimOut -y 2>&1", $to, $tc);
        if ($tc === 0 && file_exists($trimmedFile)) {
            rename($trimmedFile, $outputFile);
        }
    }

    public function failed(Throwable $e): void
    {
        ClipJob::where('id', $this->jobId)->update([
            'status'    => 'failed',
            'error_msg' => $e->getMessage(),
        ]);

        foreach (glob('/tmp' . '/' . $this->jobId . '_*') as $f) {
            @unlink($f);
        }
    }

    private function escapeSrtPath(string $path): string
    {
        return str_replace(['\\', ':', "'"], ['\\\\', '\\:', "\\'"], $path);
    }

    private function isSrtOnlyPlaceholders(string $srtFile): bool
    {
        $content = file_get_contents($srtFile);
        $text    = preg_replace('/\d+\n\d{2}:\d{2}:\d{2},\d{3} --> \d{2}:\d{2}:\d{2},\d{3}\n/m', '', $content);
        $text    = preg_replace('/[\[\(][^\]\)]+[\]\)]/u', '', $text);
        return trim(preg_replace('/\s+/', '', $text)) === '';
    }

    private function fetchYoutubeSrt(
        string $url,
        int $start,
        int $duration,
        string $lang,
        string $destSrt
    ): bool {
        echo "Attempting to fetch YouTube auto-captions for language '$lang'\n";
        $ytdlp    = $this->findBinary('yt-dlp');
        $tmpDir   = '/tmp';
        $outTpl   = escapeshellarg("$tmpDir/{$this->jobId}_ytsub");
        $urlArg   = escapeshellarg($url);

        // Try original lang first, then 'id', then 'en'
        $langs = array_unique([$lang, 'id', 'en']);

        foreach ($langs as $l) {
            $langArg = escapeshellarg($l);
            exec("$ytdlp {$this->cookiesArg()} --write-auto-subs --sub-langs $langArg --sub-format srt --skip-download -o $outTpl $urlArg 2>&1", $o, $c);

            $downloaded = glob("$tmpDir/{$this->jobId}_ytsub.*.srt");
            if ($downloaded) {
                $rawSrt = $downloaded[0];
                $this->trimSrtToClip($rawSrt, $start, $duration, $destSrt);
                @unlink($rawSrt);
                if (file_exists($destSrt) && filesize($destSrt) > 10 && !$this->isSrtOnlyPlaceholders($destSrt)) {
                    return true;
                }
                @unlink($destSrt);
            }
        }

        return false;
    }

    private function trimSrtToClip(string $srcSrt, int $startSec, int $duration, string $destSrt): void
    {
        $startMs  = $startSec * 1000;
        $endMs    = ($startSec + $duration) * 1000;
        $clipMs   = $duration * 1000;
        $content  = file_get_contents($srcSrt);
        $blocks   = preg_split('/\n\s*\n/', trim($content));
        $raw      = [];

        foreach ($blocks as $block) {
            $lines = array_values(array_filter(explode("\n", trim($block))));
            if (count($lines) < 2) continue;
            if (!preg_match('/(\d{2}:\d{2}:\d{2},\d{3})\s*-->\s*(\d{2}:\d{2}:\d{2},\d{3})/', $lines[1], $m)) continue;

            $bStart = $this->srtTimeToMs($m[1]);
            $bEnd   = $this->srtTimeToMs($m[2]);
            if ($bEnd <= $startMs || $bStart >= $endMs) continue;

            $text = implode(' ', array_slice($lines, 2));
            $text = trim(preg_replace('/<[^>]+>/', '', $text));
            if ($text === '') continue;

            $raw[] = [
                'start' => max(0, $bStart - $startMs),
                'end'   => min($clipMs, $bEnd - $startMs),
                'text'  => $text,
            ];
        }

        // YouTube auto-subs use rolling/overlapping windows — deduplicate:
        // Sort by start, then for entries with same start keep last; trim end to next start.
        usort($raw, fn($a, $b) => $a['start'] <=> $b['start']);

        $deduped = [];
        foreach ($raw as $entry) {
            if ($deduped && abs($entry['start'] - end($deduped)['start']) < 200) {
                // Same start window — replace with latest (has newer text)
                array_pop($deduped);
            }
            $deduped[] = $entry;
        }

        // Trim each entry's end to the next entry's start (no overlap)
        $entries = [];
        foreach ($deduped as $i => $e) {
            $nextStart = isset($deduped[$i + 1]) ? $deduped[$i + 1]['start'] : $e['end'];
            $end = min($e['end'], $nextStart);
            if ($end <= $e['start']) continue;
            $entries[] = ['start' => $e['start'], 'end' => $end, 'text' => $e['text']];
        }

        $out = '';
        foreach ($entries as $i => $e) {
            $out .= ($i + 1) . "\n";
            $out .= $this->msToSrtTime($e['start']) . ' --> ' . $this->msToSrtTime($e['end']) . "\n";
            $out .= $e['text'] . "\n\n";
        }

        file_put_contents($destSrt, trim($out));
    }

    private function splitSrtByWords(string $srtFile, int $maxWords): void
    {
        $content = file_get_contents($srtFile);
        $blocks  = preg_split('/\n\s*\n/', trim($content));
        $entries = [];

        foreach ($blocks as $block) {
            $lines = array_values(array_filter(explode("\n", trim($block))));
            if (count($lines) < 2) continue;

            // Parse timestamp line
            if (!preg_match('/(\d{2}:\d{2}:\d{2},\d{3})\s*-->\s*(\d{2}:\d{2}:\d{2},\d{3})/', $lines[1], $m)) continue;

            $startMs = $this->srtTimeToMs($m[1]);
            $endMs   = $this->srtTimeToMs($m[2]);

            // Collect text (lines after timestamp)
            $text  = implode(' ', array_slice($lines, 2));
            $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

            if (count($words) <= $maxWords) {
                $entries[] = ['start' => $startMs, 'end' => $endMs, 'text' => trim($text)];
                continue;
            }

            // Split into chunks of $maxWords words, distribute time proportionally
            $chunks   = array_chunk($words, $maxWords);
            $total    = count($words);
            $duration = $endMs - $startMs;
            $cursor   = $startMs;

            foreach ($chunks as $i => $chunk) {
                $chunkWords  = count($chunk);
                $chunkMs     = (int) round(($chunkWords / $total) * $duration);
                $chunkEnd    = ($i === count($chunks) - 1) ? $endMs : $cursor + $chunkMs;
                $entries[]   = ['start' => $cursor, 'end' => $chunkEnd, 'text' => implode(' ', $chunk)];
                $cursor      = $chunkEnd;
            }
        }

        // Write new SRT
        $out = '';
        foreach ($entries as $i => $e) {
            $out .= ($i + 1) . "\n";
            $out .= $this->msToSrtTime($e['start']) . ' --> ' . $this->msToSrtTime($e['end']) . "\n";
            $out .= $e['text'] . "\n\n";
        }

        file_put_contents($srtFile, trim($out));
    }

    private function probeAudioDelay(string $cutFile): int
    {
        $ffprobe = $this->findBinary('ffprobe');
        exec("$ffprobe -v quiet -select_streams a:0 -show_entries stream=start_time -of csv=p=0 " . escapeshellarg($cutFile) . " 2>&1", $out);
        $startSec = (float)($out[0] ?? 0);
        return (int)round(max(0.0, $startSec) * 1000);
    }

    private function shiftSrtTimestamps(string $srtFile, int $offsetMs): void
    {
        $content = file_get_contents($srtFile);
        $blocks  = preg_split('/\n\s*\n/', trim($content));
        $entries = [];
        $idx     = 1;

        foreach ($blocks as $block) {
            $lines = array_values(array_filter(explode("\n", trim($block))));
            if (count($lines) < 2) continue;
            if (!preg_match('/(\d{2}:\d{2}:\d{2},\d{3})\s*-->\s*(\d{2}:\d{2}:\d{2},\d{3})/', $lines[1], $m)) continue;

            $startMs = $this->srtTimeToMs($m[1]) + $offsetMs;
            $endMs   = $this->srtTimeToMs($m[2]) + $offsetMs;
            $text    = implode("\n", array_slice($lines, 2));
            if (!trim($text)) continue;

            $entries[] = $idx++ . "\n" . $this->msToSrtTime($startMs) . ' --> ' . $this->msToSrtTime($endMs) . "\n" . $text;
        }

        file_put_contents($srtFile, implode("\n\n", $entries));
    }

    private function escapeDrawtext(string $text): string
    {
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace("'", "\\'", $text);
        $text = str_replace(':', '\\:', $text);
        $text = str_replace('%', '\\%', $text);
        return $text;
    }

    private function findFont(): string
    {
        $candidates = [
            '/Library/Fonts/Arial.ttf',
            '/Library/Fonts/Arial Unicode.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
            '/System/Library/Fonts/Geneva.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        ];
        foreach ($candidates as $font) {
            if (file_exists($font)) {
                return $font;
            }
        }
        throw new \RuntimeException('No system font found for drawtext.');
    }

    private function cookiesArg(): string
    {
        $candidates = [
            env('YTDLP_COOKIES', ''),
            ($_SERVER['HOME'] ?? '') . '/yt-cookies.txt',
        ];
        foreach ($candidates as $path) {
            if ($path && file_exists($path)) {
                return '--cookies ' . escapeshellarg($path);
            }
        }
        return '';
    }

    private function findBinary(string $name): string
    {
        $paths = ["/usr/local/bin/$name", "/opt/homebrew/bin/$name", "/usr/bin/$name"];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return $name;
    }

    private function findDownloadedFile(string $jobId): ?string
    {
        $files = glob('/tmp' . '/' . $jobId . '_raw.*');
        return $files ? $files[0] : null;
    }

    private function overlayPosition(string $position): array
    {
        return match ($position) {
            'top_left'     => ['10', '10'],
            'top_right'    => ['w-tw-10', '10'],
            'bottom_left'  => ['10', 'h-th-10'],
            'bottom_right' => ['w-tw-10', 'h-th-10'],
            'center'       => ['(w-tw)/2', '(h-th)/2'],
            default        => ['w-tw-10', 'h-th-10'],
        };
    }
}
