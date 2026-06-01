<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiService
{
    private string $apiKey;

    private const BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

    private array $models = [
        // self::BASE . 'gemini-2.5-pro:generateContent',
        self::BASE . 'gemini-2.5-flash:generateContent',
        self::BASE . 'gemini-2.5-flash-lite:generateContent',
    ];

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', '');
    }

    public function analyzeVideo(string $youtubeUrl, int $clipCount, string $layoutMode): array
    {
        $meta   = $this->fetchVideoMeta($youtubeUrl);
        $prompt = $this->buildPrompt($meta, $clipCount, $layoutMode);

        $lastError = null;

        foreach ($this->models as $modelUrl) {
            $modelName = explode('/', parse_url($modelUrl, PHP_URL_PATH))[5] ?? $modelUrl;

            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $response = Http::timeout(90)->post("{$modelUrl}?key={$this->apiKey}", [
                        'contents'         => [['parts' => [['text' => $prompt]]]],
                        'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.3],
                    ]);

                    echo "Attempt {$attempt} on {$modelName}: HTTP {$response->status()}\n";

                    if ($response->status() === 429) {
                        $wait      = $this->parseRetryDelay($response->body());
                        $lastError = "429 on {$modelName} (retryDelay: {$wait}s)";

                        if ($attempt === 1 && $wait > 0 && $wait <= 120) {
                            echo "Rate limited on {$modelName}, waiting {$wait}s…\n";
                            sleep($wait + 1);
                            continue;
                        }
                        break;
                    }

                    if (!$response->ok()) {
                        $lastError = "HTTP {$response->status()} on {$modelName}";
                        break;
                    }

                    $text     = $response->json('candidates.0.content.parts.0.text') ?? '';
                    $clean    = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text));
                    $segments = json_decode($clean, true);

                    if (!is_array($segments) || empty($segments)) {
                        $lastError = "Invalid JSON from {$modelName}";
                        break;
                    }

                    return array_slice($segments, 0, $clipCount);
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                    break;
                }
            }
        }

        throw new \RuntimeException('Gemini analisa gagal (semua model): ' . $lastError);
    }

    private function parseRetryDelay(string $body): int
    {
        $data = json_decode($body, true);
        $delay = $data['error']['details'][2]['retryDelay'] ?? '0s';
        return (int) preg_replace('/[^0-9]/', '', $delay);
    }

    private function fetchVideoMeta(string $url): array
    {
        $ytdlp   = $this->findBinary('yt-dlp');
        $urlArg  = escapeshellarg($url);
        $cookies = $this->cookiesArg();

        exec("$ytdlp $cookies --dump-json --no-download $urlArg 2>/dev/null", $out, $code);

        if ($code !== 0 || empty($out)) {
            return ['title' => 'Unknown', 'description' => '', 'duration' => 0, 'chapters' => [], 'tags' => []];
        }

        $json = json_decode(implode('', $out), true) ?? [];

        $chapters = array_slice($json['chapters'] ?? [], 0, 8);
        $chapters = array_map(fn($c) => [
            'title' => mb_substr($c['title'] ?? '', 0, 60),
            'start' => (int) ($c['start_time'] ?? 0),
        ], $chapters);

        return [
            'title'       => mb_substr($json['title'] ?? 'Unknown', 0, 150),
            'description' => mb_substr($json['description'] ?? '', 0, 300),
            'duration'    => (int) ($json['duration'] ?? 0),
            'chapters'    => $chapters,
            'tags'        => array_slice($json['tags'] ?? [], 0, 5),
        ];
    }

    private function buildPrompt(array $meta, int $clipCount, string $layoutMode): string
    {
        $title    = $meta['title'];
        $duration = $meta['duration'];
        $desc     = $meta['description'];
        $chapters = $meta['chapters'] ? json_encode($meta['chapters']) : 'none';
        $tags     = $meta['tags'] ? implode(', ', $meta['tags']) : 'none';

        $layoutHint = match ($layoutMode) {
            'auto_magic'    => 'Puncak emosi, pengungkapan mengejutkan, penyampaian nilai yang jelas.',
            'auto_split'    => 'Momen dengan dua scene berbeda atau perbandingan sebelum/sesudah.',
            'gaussian_blur' => 'Pembicara tunggal yang jelas atau subjek fokus.',
            'auto_reframe'  => 'Subjek pusat yang dominan cocok untuk crop vertikal.',
            default         => '',
        };

        return <<<PROMPT
                Kurator YouTube Shorts. Pilih {$clipCount} segmen terbaik dari video ini.

                Judul: {$title}
                Durasi: {$duration}s
                Deskripsi: {$desc}
                Bab: {$chapters}
                Tag: {$tags}
                Hint Layout: {$layoutHint}

                Aturan: 45-65s setiap segmen, tidak ada tumpang tindih, pemikiran lengkap per segmen. Hook maksimal 10 kata, sesuai bahasa video.

                Kembalikan array JSON, tepat {$clipCount} item, yang terbaik duluan:
                [{"start_seconds":120,"end_seconds":180,"hook":"...","caption":"...","hashtags":["#a","#b","#c"]}]
                PROMPT;
    }

    private function formatDuration(int $secs): string
    {
        $h = intdiv($secs, 3600);
        $m = intdiv($secs % 3600, 60);
        return $h > 0 ? "{$h}h {$m}m" : "{$m}m";
    }

    private function findBinary(string $name): string
    {
        foreach (["/usr/local/bin/$name", "/opt/homebrew/bin/$name", "/usr/bin/$name"] as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return $name;
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
}
