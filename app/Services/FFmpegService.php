<?php

namespace App\Services;

use RuntimeException;

class FFmpegService
{
    private const MAX_CUE_WORDS = 4; // words per subtitle cue (TikTok-style short lines)

    private string $bin;
    private VideoLayoutService $layout;

    public function __construct()
    {
        $this->bin    = config('services.ffmpeg.path', 'ffmpeg');
        $this->layout = new VideoLayoutService();
    }

    public function cut(string $input, float $start, float $end, string $output): void
    {
        $duration = $end - $start;
        $cmd = sprintf(
            '%s -y -ss %s -i %s -t %s -c copy -avoid_negative_ts make_zero %s 2>&1',
            $this->bin,
            escapeshellarg((string) $start),
            escapeshellarg($input),
            escapeshellarg((string) $duration),
            escapeshellarg($output)
        );

        $this->exec($cmd, 'cut');
    }

    public function applyLayout(string $input, string $layoutType, string $output): void
    {
        // Map new layout IDs to VideoLayoutService's filter method
        $layoutMap = [
            'reframe'  => 'auto_reframe',
            'gaussian' => 'gaussian_blur'
        ];

        $mode = $layoutMap[$layoutType] ?? 'gaussian_blur';
        $filterLabel = '[out]';
        $filterGraph = $this->layout->layoutFilter($mode, $filterLabel);

        $cmd = sprintf(
            '%s -y -i %s -filter_complex %s -map %s -map 0:a? -c:v libx264 -c:a aac -movflags +faststart %s 2>&1',
            $this->bin,
            escapeshellarg($input),
            escapeshellarg($filterGraph),
            escapeshellarg($filterLabel),
            escapeshellarg($output)
        );

        $this->exec($cmd, 'applyLayout');
    }

    public function burnSubtitles(string $input, string $assPath, string $output): void
    {
        $fontsdir = $this->layout->fontsDir();
        $assFilter = 'ass=' . $assPath . ':fontsdir=' . $fontsdir;

        $cmd = sprintf(
            '%s -y -i %s -vf %s -c:v libx264 -c:a copy -movflags +faststart %s 2>&1',
            $this->bin,
            escapeshellarg($input),
            escapeshellarg($assFilter),
            escapeshellarg($output)
        );

        $this->exec($cmd, 'burnSubtitles');
    }

    /**
     * @param float $clipStart    Absolute start of the clip in the source video.
     *                            subtitle_json holds absolute times; subtract this
     *                            so subtitles land at clip-relative t (starting 0).
     * @param float $hookDuration Seconds the hook overlay covers at the start.
     *                            Segments fully inside the hook window are dropped;
     *                            overlapping ones are clamped to start after it.
     */
    public function buildAssFile(array $segments, array $subtitleSettings, float $clipStart = 0.0, float $hookDuration = 0.0): string
    {
        $fontFamily  = $subtitleSettings['font_family'] ?? 'Montserrat';
        $fontSize    = (int) ($subtitleSettings['font_size'] ?? 42);
        $textColor   = $this->hexToAss($subtitleSettings['text_color'] ?? '#ffffff');
        $hlColor     = $this->hexToAss($subtitleSettings['highlight_color'] ?? '#facc15');
        $position    = $subtitleSettings['position'] ?? 'bottom';
        $bgStyle     = $subtitleSettings['background_style'] ?? 'semi';

        $alignment = match ($position) {
            'top'    => 8,
            'center' => 5,
            default  => 2,
        };

        // Fix 3: bottom margin raised so subtitles clear TikTok UI (~200px from bottom)
        $marginV = match ($position) {
            'top'    => 60,
            'center' => 0,
            default  => 200,
        };

        // Fix 5: BorderStyle=3 box mode; Shadow=0 removes double-box; Outline=18 gives padding
        $boxStyle = match ($bgStyle) {
            'semi' => 3,
            'full' => 3,
            default => 0,
        };
        $outlineW = $bgStyle !== 'none' ? 18 : 2;
        $shadowW  = $bgStyle !== 'none' ? 0  : 2;
        $backClr  = match ($bgStyle) {
            'semi'  => '&H99000000', // ~60% opaque black
            'full'  => '&HFF000000', // fully opaque black
            default => '&H00000000',
        };

        $ass  = "[Script Info]\n";
        $ass .= "ScriptType: v4.00+\n";
        $ass .= "PlayResX: 1080\nPlayResY: 1920\n";
        $ass .= "WrapStyle: 0\n\n"; // Fix 2: smart wrap prevents overflow
        $ass .= "[V4+ Styles]\n";
        $ass .= "Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding\n";
        // Fix 2: MarginL/R 40→60; Fix 5: Outline/Shadow updated; Fix 3: marginV per position
        $ass .= "Style: Default,{$fontFamily},{$fontSize},{$textColor},{$hlColor},&H00000000,{$backClr},-1,0,0,0,100,100,0,0,{$boxStyle},{$outlineW},{$shadowW},{$alignment},60,60,{$marginV},1\n";
        $ass .= "Style: Highlight,{$fontFamily},{$fontSize},{$hlColor},{$textColor},&H00000000,{$backClr},-1,0,0,0,100,100,0,0,{$boxStyle},{$outlineW},{$shadowW},{$alignment},60,60,{$marginV},1\n\n";
        $ass .= "[Events]\n";
        $ass .= "Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text\n";

        // Normalize: clip-relative times + hook-window clamp
        $norm = [];
        foreach ($segments as $seg) {
            $relStart = $seg['start'] - $clipStart;
            $relEnd   = $seg['end'] - $clipStart;

            if ($relEnd <= $hookDuration) {
                continue; // fully hidden behind hook
            }
            if ($relStart < $hookDuration) {
                $relStart = $hookDuration;
            }
            if ($relEnd <= $relStart) {
                continue;
            }
            $norm[] = ['start' => $relStart, 'end' => $relEnd, 'text' => trim($seg['text'])];
        }

        // Prevent overlap: a segment must end before the next begins, else
        // ASS stacks both events into separate rows (the "berantakan" bug).
        $n = count($norm);
        for ($i = 0; $i < $n - 1; $i++) {
            if ($norm[$i]['end'] > $norm[$i + 1]['start']) {
                $norm[$i]['end'] = $norm[$i + 1]['start'];
            }
        }

        // Split each segment into short single-line cues (≤ MAX_CUE_WORDS),
        // timed proportionally to word count so captions track the speech and
        // never sprawl across multiple rows.
        foreach ($norm as $seg) {
            $words = preg_split('/\s+/', $seg['text'], -1, PREG_SPLIT_NO_EMPTY);
            $total = count($words);
            if ($total === 0) {
                continue;
            }

            $dur    = $seg['end'] - $seg['start'];
            $cursor = $seg['start'];

            foreach (array_chunk($words, self::MAX_CUE_WORDS) as $chunk) {
                $w       = count($chunk);
                $cEnd    = $cursor + $dur * ($w / $total);
                $start   = $this->toAssTime($cursor);
                $end     = $this->toAssTime($cEnd);
                $text    = $this->buildKaraokeText(implode(' ', $chunk), $cursor, $cEnd, $hlColor);
                $ass    .= "Dialogue: 0,{$start},{$end},Default,,0,0,0,,{$text}\n";
                $cursor  = $cEnd;
            }
        }

        return $ass;
    }

    /**
     * Burn subtitles (ASS) and overlay the hook text onto the video in a single
     * pass. Hook is layer 2 drawn over the video (layer 1) for the first
     * $hookDuration seconds via enable='lt(t,dur)'. Audio is stream-copied so it
     * is never lost (the old concat-with-silent-clip path dropped audio).
     *
     * @param string|null $assPath null/empty to skip subtitles
     * @param string|null $hookText null/empty to skip hook overlay
     */
    public function burnSubtitlesAndHook(
        string $input,
        ?string $assPath,
        ?string $hookText,
        float $hookDuration,
        array $hookSettings,
        string $output
    ): void {
        $filters = [];

        if ($assPath) {
            $fontsdir  = $this->layout->fontsDir();
            $filters[] = 'ass=' . $assPath . ':fontsdir=' . $fontsdir;
        }
        if ($hookText !== null && $hookText !== '') {
            $filters[] = $this->hookDrawtext($hookText, $hookDuration, $hookSettings);
        }

        if (empty($filters)) {
            // Nothing to burn — just re-mux video as-is
            $cmd = sprintf(
                '%s -y -i %s -c copy -movflags +faststart %s 2>&1',
                $this->bin, escapeshellarg($input), escapeshellarg($output)
            );
            $this->exec($cmd, 'burnSubtitlesAndHook');
            return;
        }

        $cmd = sprintf(
            '%s -y -i %s -vf %s -c:v libx264 -c:a copy -movflags +faststart %s 2>&1',
            $this->bin,
            escapeshellarg($input),
            escapeshellarg(implode(',', $filters)),
            escapeshellarg($output)
        );

        $this->exec($cmd, 'burnSubtitlesAndHook');
    }

    private function hookDrawtext(string $text, float $duration, array $settings): string
    {
        $textColor  = $settings['text_color'] ?? '#ffffff';
        $bgStyle    = $settings['background_style'] ?? 'none';
        $position   = $settings['position'] ?? 'center';
        $fontFamily = $settings['font_family'] ?? 'Montserrat';
        $fontSize   = max(24, min(80, (int) ($settings['font_size'] ?? 60)));

        $fontPath  = $this->layout->resolveFontByFamily($fontFamily);
        $fontColor = '0x' . ltrim($textColor, '#');

        $yPos = match ($position) {
            'top'    => '(h*0.18)',
            'bottom' => '(h*0.72)',
            default  => '(h-text_h)/2',
        };

        $box = match ($bgStyle) {
            'semi'  => ':box=1:boxcolor=0x000000aa:boxborderw=24',
            'full'  => ':box=1:boxcolor=0x000000ff:boxborderw=24',
            default => ':shadowcolor=0x000000aa:shadowx=3:shadowy=3',
        };

        $fontEsc = str_replace(['\\', "'"], ['\\\\', "\\'"], $fontPath);
        $wrapped = $this->wrapHookText($text, $fontSize);
        $textEsc = str_replace(['\\', "'", ':', '%'], ['\\\\', "\\'", '\\:', '\\%'], $wrapped);

        return sprintf(
            "drawtext=fontfile='%s':text='%s':fontsize=%d:fontcolor=%s"
            . ":x=(w-text_w)/2:y=%s:line_spacing=8:fix_bounds=1%s"
            . ":enable='lt(t,%s)'",
            $fontEsc,
            $textEsc,
            $fontSize,
            $fontColor,
            $yPos,
            $box,
            $duration
        );
    }

    private function wrapHookText(string $text, int $fontSize): string
    {
        // Approximate chars per line based on font size on 1080px canvas
        // fontSize 60 → ~22 chars; fontSize 40 → ~32 chars; fontSize 80 → ~16 chars
        $maxChars = (int) round(1320 / $fontSize);

        $words = explode(' ', $text);
        $lines = [];
        $line  = '';
        foreach ($words as $word) {
            if ($line !== '' && strlen($line) + 1 + strlen($word) > $maxChars) {
                $lines[] = $line;
                $line    = $word;
            } else {
                $line = $line === '' ? $word : "{$line} {$word}";
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        // FFmpeg drawtext uses \n literal for newline inside text value
        return implode('\n', $lines);
    }

    public function concatVideos(array $inputs, string $output): void
    {
        $listFile = tempnam(sys_get_temp_dir(), 'ffconcat_');
        $content  = '';
        foreach ($inputs as $f) {
            $content .= "file '" . addslashes($f) . "'\n";
        }
        file_put_contents($listFile, $content);

        $cmd = sprintf(
            '%s -y -f concat -safe 0 -i %s -c:v libx264 -c:a aac -movflags +faststart %s 2>&1',
            $this->bin,
            escapeshellarg($listFile),
            escapeshellarg($output)
        );

        try {
            $this->exec($cmd, 'concatVideos');
        } finally {
            @unlink($listFile);
        }
    }

    private function exec(string $cmd, string $context): void
    {
        set_time_limit(0); // ffmpeg encode can exceed PHP max_execution_time
        exec($cmd, $output, $code);
        if ($code !== 0) {
            throw new RuntimeException("FFmpeg {$context} failed:\n" . implode("\n", $output));
        }
    }

    private function toAssTime(float $seconds): string
    {
        $whole = (int) $seconds;
        $h  = intdiv($whole, 3600);
        $m  = intdiv($whole % 3600, 60);
        $s  = $whole % 60;
        $cs = (int) round(fmod($seconds, 1) * 100);
        return sprintf('%d:%02d:%02d.%02d', $h, $m, $s, $cs);
    }

    private function hexToAss(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        return sprintf('&H00%02X%02X%02X', $b, $g, $r);
    }

    private function buildKaraokeText(string $text, float $start, float $end, string $hlAssColor): string
    {
        $words = explode(' ', $text);
        $count = count($words);
        if ($count === 0) return $text;

        $dur       = $end - $start;
        $perWord   = ($dur / $count) * 100; // centiseconds

        // {\kXX} per word = karaoke sweep using the Style's Primary/Secondary
        // colours (un-sung = Secondary, sung = Primary). No manual \c override —
        // it persists across words and forced the whole line one colour.
        $out = '';
        foreach ($words as $word) {
            $k = (int) round($perWord);
            $out .= "{\\k{$k}}{$word} ";
        }
        return rtrim($out);
    }
}
