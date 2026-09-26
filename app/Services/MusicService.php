<?php

namespace App\Services;

/**
 * Background-music library on disk: one folder per mood, e.g.
 * storage/app/music/energetic/track.mp3. Only use tracks you have the
 * rights to publish (royalty-free / licensed).
 */
class MusicService
{
    public const MOODS = ['energetic', 'chill', 'inspiring', 'dramatic', 'funny', 'sad'];

    private const EXTENSIONS = ['mp3', 'm4a', 'aac', 'wav', 'ogg'];

    private string $root;

    public function __construct(?string $root = null)
    {
        $this->root = rtrim($root ?? config('services.music.path', storage_path('app/music')), '/');
    }

    public static function normalizeMood(?string $mood): ?string
    {
        $mood = strtolower(trim((string) $mood));
        return in_array($mood, self::MOODS, true) ? $mood : null;
    }

    /**
     * Pick a track for the mood; falls back to the whole library when that
     * mood's folder is empty. $seed (e.g. the clip id) keeps the choice stable
     * across re-renders while spreading tracks over different clips.
     */
    public function pickTrack(?string $mood, string $seed): ?string
    {
        $tracks = $mood ? $this->tracksIn($this->root . '/' . $mood) : [];
        if (empty($tracks)) {
            $tracks = [];
            foreach (self::MOODS as $m) {
                array_push($tracks, ...$this->tracksIn($this->root . '/' . $m));
            }
            array_push($tracks, ...$this->tracksIn($this->root));
        }
        if (empty($tracks)) {
            return null;
        }

        sort($tracks);
        return $tracks[crc32($seed) % count($tracks)];
    }

    private function tracksIn(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach (scandir($dir) ?: [] as $f) {
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (in_array($ext, self::EXTENSIONS, true) && is_file("{$dir}/{$f}")) {
                $files[] = "{$dir}/{$f}";
            }
        }
        return $files;
    }
}
