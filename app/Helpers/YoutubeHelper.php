<?php

namespace App\Helpers;

class YoutubeHelper
{
    public static function extractVideoId(string $url): ?string
    {
        try {
            $parts = parse_url($url);
            $host  = $parts['host'] ?? '';

            if (str_contains($host, 'youtu.be')) {
                $id = ltrim($parts['path'] ?? '', '/');
                return $id ?: null;
            }

            if (str_contains($host, 'youtube.com')) {
                $path = $parts['path'] ?? '';
                if (str_starts_with($path, '/shorts/')) {
                    return explode('/', trim($path, '/'))[1] ?? null;
                }
                parse_str($parts['query'] ?? '', $q);
                return $q['v'] ?? null;
            }
        } catch (\Throwable) {}

        return null;
    }
}
