<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        $lastError  = null;
        $log        = Log::channel('clipper_jobs');
        $modelIndex = 0;

        foreach ($this->models as $modelUrl) {
            $modelName  = explode('/', parse_url($modelUrl, PHP_URL_PATH))[5] ?? $modelUrl;
            $modelIndex++;
            $isPrimary  = $modelIndex === 1;
            $label      = $isPrimary ? 'PRIMARY' : 'FALLBACK';

            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $log->info("[GEMINI:analyzeVideo] Trying {$label}: {$modelName}", ['attempt' => $attempt]);
                    $response = Http::timeout(90)->post("{$modelUrl}?key={$this->apiKey}", [
                        'contents'         => [['parts' => [['text' => $prompt]]]],
                        'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.3],
                    ]);

                    if ($response->status() === 429) {
                        $wait      = $this->parseRetryDelay($response->body());
                        $lastError = "429 on {$modelName} (retryDelay: {$wait}s)";
                        $log->warning("[GEMINI:analyzeVideo] 429 rate-limit on {$label}: {$modelName}", ['wait' => $wait]);
                        if ($attempt === 1 && $wait > 0 && $wait <= 120) {
                            sleep($wait + 1);
                            continue;
                        }
                        break;
                    }

                    if ($response->status() === 503 && $attempt === 1) {
                        $lastError = "503 on {$modelName}, retrying…";
                        $log->warning("[GEMINI:analyzeVideo] 503 on {$label}: {$modelName}, retrying in 5s");
                        sleep(5);
                        continue;
                    }

                    if (!$response->ok()) {
                        $lastError = "HTTP {$response->status()} on {$modelName}";
                        $log->error("[GEMINI:analyzeVideo] {$label} failed: {$lastError}");
                        break;
                    }

                    $text     = $response->json('candidates.0.content.parts.0.text') ?? '';
                    $clean    = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text));
                    $segments = json_decode($clean, true);

                    if (!is_array($segments) || empty($segments)) {
                        $lastError = "Invalid JSON from {$modelName}";
                        $log->error("[GEMINI:analyzeVideo] {$label} invalid JSON: {$modelName}");
                        break;
                    }

                    $log->info("[GEMINI:analyzeVideo] SUCCESS with {$label}: {$modelName}", [
                        'attempt' => $attempt,
                        'clips'   => count($segments),
                    ]);
                    return array_slice($segments, 0, $clipCount);
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                    $log->error("[GEMINI:analyzeVideo] {$label} exception: {$modelName}", ['error' => $lastError]);
                    break;
                }
            }
        }

        throw new \RuntimeException('Gemini analisa gagal (semua model): ' . $lastError);
    }

    public function analyzeWithTranscript(
        array $transcript,
        int $clipCount,
        string $layoutType,
        string $durationMode = 'auto',
        int $minDuration = 15,
        int $maxDuration = 60
    ): array {
        $prompt = $this->buildPromptFromTranscript($transcript, $clipCount, $layoutType, $durationMode, $minDuration, $maxDuration);

        $lastError  = null;
        $log        = Log::channel('clipper_jobs');
        $modelIndex = 0;

        foreach ($this->models as $modelUrl) {
            $modelName  = explode('/', parse_url($modelUrl, PHP_URL_PATH))[5] ?? $modelUrl;
            $modelIndex++;
            $label      = $modelIndex === 1 ? 'PRIMARY' : 'FALLBACK';

            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $log->info("[GEMINI:analyzeTranscript] Trying {$label}: {$modelName}", ['attempt' => $attempt]);
                    $response = Http::timeout(300)->post("{$modelUrl}?key={$this->apiKey}", [
                        'contents'         => [['parts' => [['text' => $prompt]]]],
                        'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.3],
                    ]);

                    if ($response->status() === 429) {
                        $wait      = $this->parseRetryDelay($response->body());
                        $lastError = "429 on {$modelName}";
                        $log->warning("[GEMINI:analyzeTranscript] 429 rate-limit on {$label}: {$modelName}", ['wait' => $wait]);
                        if ($attempt === 1 && $wait > 0 && $wait <= 120) {
                            sleep($wait + 1);
                            continue;
                        }
                        break;
                    }

                    if ($response->status() === 503 && $attempt === 1) {
                        $lastError = "503 on {$modelName}, retrying…";
                        $log->warning("[GEMINI:analyzeTranscript] 503 on {$label}: {$modelName}, retrying in 5s");
                        sleep(5);
                        continue;
                    }

                    if (!$response->ok()) {
                        $lastError = "HTTP {$response->status()} on {$modelName}";
                        $log->error("[GEMINI:analyzeTranscript] {$label} failed: {$lastError}");
                        break;
                    }

                    $text  = $response->json('candidates.0.content.parts.0.text') ?? '';
                    $clean = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text));
                    $clips = json_decode($clean, true);

                    if (!is_array($clips) || empty($clips)) {
                        $lastError = "Invalid JSON from {$modelName}";
                        $log->error("[GEMINI:analyzeTranscript] {$label} invalid JSON: {$modelName}");
                        break;
                    }

                    $log->info("[GEMINI:analyzeTranscript] SUCCESS with {$label}: {$modelName}", [
                        'attempt' => $attempt,
                        'clips'   => count($clips),
                    ]);
                    return array_slice($clips, 0, $clipCount);
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                    $log->error("[GEMINI:analyzeTranscript] {$label} exception: {$modelName}", ['error' => $lastError]);
                    break;
                }
            }
        }

        throw new \RuntimeException('Gemini analisa gagal: ' . $lastError);
    }

    public function generateCaption(string $topic, string $hookText, array $subtitleSegments, string $language = 'id'): array
    {
        $segText = implode(' ', array_column($subtitleSegments, 'text'));
        $segText = mb_substr($segText, 0, 500);

        $prompt = <<<PROMPT
        Kamu adalah copywriter TikTok profesional. Buat caption viral untuk konten TikTok ini:

        Topik: {$topic}
        Hook pembuka: {$hookText}
        Isi konten: {$segText}
        Bahasa output: {$language}

        Buat:
        1. Caption menarik 2-3 kalimat, pakai emoji relevan, akhiri dengan ajakan interaksi (pertanyaan atau CTA)
        2. 6-8 hashtag (campuran trending dan niche, relevan dengan topik)

        Kembalikan JSON:
        {"caption": "...", "hashtags": ["#tag1", "#tag2"]}
        PROMPT;

        foreach ($this->models as $modelUrl) {
            for ($attempt = 1; $attempt <= 2; $attempt++) {
                try {
                    $response = Http::timeout(30)->post("{$modelUrl}?key={$this->apiKey}", [
                        'contents'         => [['parts' => [['text' => $prompt]]]],
                        'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.7],
                    ]);

                    if ($response->status() === 429) {
                        $wait = $this->parseRetryDelay($response->body());
                        if ($attempt === 1 && $wait > 0 && $wait <= 60) {
                            sleep($wait + 1);
                            continue;
                        }
                        break;
                    }

                    if ($response->status() === 503 && $attempt === 1) {
                        sleep(5);
                        continue;
                    }

                    if (!$response->ok()) break;

                    $text  = $response->json('candidates.0.content.parts.0.text') ?? '';
                    $clean = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($text));
                    $data  = json_decode($clean, true);

                    if (isset($data['caption']) && isset($data['hashtags'])) {
                        return [
                            'caption'  => (string) $data['caption'],
                            'hashtags' => array_values(array_filter((array) $data['hashtags'], 'is_string')),
                        ];
                    }
                } catch (\Throwable) {
                    break;
                }
            }
        }

        return ['caption' => '', 'hashtags' => []];
    }

    private function buildPromptFromTranscript(
        array $transcript,
        int $clipCount,
        string $layoutType,
        string $durationMode,
        int $minDuration,
        int $maxDuration
    ): string {
        $langHint = $transcript['language'] ?? 'id';
        $segments = $transcript['segments'] ?? [];

        // Sample evenly to max 1000 segments — keeps full coverage, reduces prompt from ~200KB to ~80KB
        if (count($segments) > 1000) {
            $total  = count($segments);
            $step   = $total / 1000;
            $picked = [];
            for ($i = 0; $i < 1000; $i++) {
                $picked[] = $segments[(int) round($i * $step)];
            }
            $segments = $picked;
        }

        $transcriptText = '';
        foreach ($segments as $seg) {
            $transcriptText .= sprintf("[%.1f-%.1f] %s\n", $seg['start'], $seg['end'], $seg['text']);
        }

        $durHint = $durationMode === 'manual'
            ? "Durasi per clip: {$minDuration}–{$maxDuration} detik."
            : 'Durasi per clip: 30–90 detik (pilih optimal per momen, boleh lebih panjang jika topik belum selesai).';

        $layoutHint = match ($layoutType) {
            'reframe'  => 'Subjek pusat/wajah dominan, cocok crop vertikal.',
            'gaussian' => 'Pembicara tunggal atau subjek fokus.',
            default    => '',
        };

        return <<<PROMPT
        Kamu adalah editor TikTok senior dengan rekam jejak video viral jutaan views. Berikut transcript video YouTube dengan timestamp (bahasa: {$langHint}).

        {$transcriptText}

        == TUGAS ==
        Pilih TEPAT {$clipCount} segmen dengan potensi viral TERTINGGI untuk TikTok/Reels/Shorts.
        {$durHint}
        Layout hint: {$layoutHint}

        == KRITERIA VIRAL (urutkan prioritas) ==
        1. HOOK KUAT di detik pertama — kalimat pembuka yang langsung menarik perhatian, buat penonton penasaran atau terkejut
        2. MOMEN EMOSI PUNCAK — tawa, kaget, marah, haru, euforia — emosi = retention
        3. INSIGHT atau FAKTA MENGEJUTKAN — informasi yang membuat orang berpikir "wow, aku tidak tahu ini"
        4. KONFLIK atau KETEGANGAN — permasalahan, perdebatan, momen dramatis
        5. NILAI PRAKTIS PADAT — tips/cara/solusi yang langsung bisa diterapkan, tanpa basa-basi
        6. QUOTABLE MOMENT — kalimat singkat bertenaga yang layak jadi caption atau subtitle highlight

        == ATURAN KRITIS — WAJIB DIPATUHI ==
        - DILARANG KERAS memotong di tengah kalimat, di tengah argumen, atau di tengah topik yang belum selesai
        - end_seconds HARUS di titik jeda alami: setelah kalimat selesai, setelah poin tuntas, setelah momen klimaks berakhir
        - Jika sebuah momen bagus tapi topiknya baru selesai di luar durasi maksimal, PANJANGKAN end_seconds sampai topik benar-benar tuntas (lebih baik clip panjang sempurna daripada clip pendek terpotong)
        - start_seconds HARUS tepat sebelum hook/kalimat pembuka segmen (bukan di tengah kalimat)
        - Antar clip TIDAK boleh tumpang tindih timestamp
        - Hanya pilih segmen dengan viral_score ≥ 7
        - Urutkan dari viral_score tertinggi ke terendah

        == FORMAT OUTPUT ==
        Kembalikan array JSON {$clipCount} item:
        [
          {
            "ranking": int (1=terbaik),
            "start_seconds": float,
            "end_seconds": float,
            "topic": string (judul singkat topik segmen),
            "reason": string (mengapa segmen ini viral — sebutkan sinyal spesifik dari transcript),
            "viral_score": int (1-10, berdasarkan kriteria di atas),
            "viral_potential": "tinggi"|"sedang"|"rendah",
            "hook_text": string (kalimat hook max 100 karakter bahasa {$langHint}, harus dari kalimat pembuka segmen atau adaptasinya),
            "completion_check": string (konfirmasi bahwa topik selesai di end_seconds, bukan terpotong),
            "music_mood": "energetic"|"chill"|"inspiring"|"dramatic"|"funny"|"sad" (suasana musik latar yang paling cocok dengan emosi segmen),
            "subtitle_segments": [{"start": float, "end": float, "text": string}]
          }
        ]

        Kembalikan HANYA array JSON, tanpa teks tambahan.
        PROMPT;
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
                Kamu adalah kurator YouTube Shorts/TikTok senior. Pilih {$clipCount} segmen dengan potensi viral TERTINGGI dari video ini.

                Judul: {$title}
                Durasi total: {$duration}s
                Deskripsi: {$desc}
                Bab: {$chapters}
                Tag: {$tags}
                Hint Layout: {$layoutHint}

                == KRITERIA VIRAL (prioritas utama) ==
                1. Hook kuat di detik pertama — langsung menarik, buat penonton tidak mau skip
                2. Momen emosi puncak — kaget, haru, tawa, euforia
                3. Insight atau fakta mengejutkan yang belum banyak diketahui
                4. Konflik, ketegangan, atau momen dramatis
                5. Tips/solusi praktis padat, langsung bisa diterapkan
                6. Kalimat bertenaga yang layak jadi viral quote

                == ATURAN WAJIB ==
                - Durasi per segmen: 45–90 detik (panjangkan jika topik belum selesai, jangan potong di tengah)
                - end_seconds harus di titik alami: setelah kalimat selesai, setelah poin tuntas
                - start_seconds harus tepat sebelum kalimat pembuka hook
                - Tidak boleh tumpang tindih antar segmen
                - Hook maksimal 10 kata, sesuai bahasa video

                Kembalikan array JSON, tepat {$clipCount} item, terbaik duluan:
                [{"start_seconds":120,"end_seconds":185,"hook":"...","caption":"...","hashtags":["#a","#b","#c"],"viral_score":9}]
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
