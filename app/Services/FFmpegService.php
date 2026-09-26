<?php

namespace App\Services;

use RuntimeException;

class FFmpegService
{
    private const MAX_CUE_WORDS = 4;    // words per subtitle cue (TikTok-style short lines)
    private const CUE_BREAK_GAP = 0.6;  // seconds of silence that starts a new cue
    private const CUE_HOLD      = 0.25; // seconds a cue lingers after its last word

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

    /**
     * @param float|null $start When given (with $end), trim the input in the same
     *                          re-encode pass. Seeking while transcoding is
     *                          frame-accurate, unlike cut()'s stream copy which
     *                          snaps back to the previous keyframe and shifts
     *                          the clip's t=0 away from $start (subtitle drift).
     */
    public function applyLayout(string $input, string $layoutType, string $output, ?float $start = null, ?float $end = null): void
    {
        // Map new layout IDs to VideoLayoutService's filter method
        $layoutMap = [
            'reframe'  => 'auto_reframe',
            'gaussian' => 'gaussian_blur'
        ];

        $mode = $layoutMap[$layoutType] ?? 'gaussian_blur';
        $filterLabel = '[out]';
        $filterGraph = $this->layout->layoutFilter($mode, $filterLabel);

        $trimIn  = '';
        $trimOut = '';
        if ($start !== null && $end !== null) {
            $trimIn  = ' -ss ' . escapeshellarg(sprintf('%.3f', $start));
            $trimOut = ' -t ' . escapeshellarg(sprintf('%.3f', $end - $start));
        }

        $cmd = sprintf(
            '%s -y%s -i %s%s -filter_complex %s -map %s -map 0:a? -c:v libx264 -preset slow -crf 18 -pix_fmt yuv420p -c:a aac -b:a 192k -movflags +faststart %s 2>&1',
            $this->bin,
            $trimIn,
            escapeshellarg($input),
            $trimOut,
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
            '%s -y -i %s -vf %s -c:v libx264 -preset slow -crf 18 -pix_fmt yuv420p -c:a copy -movflags +faststart %s 2>&1',
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

        // Segments with word timing (ASR captions / Groq) are cued from the real
        // word timestamps; the rest fall back to proportional splitting.
        $timedWords = [];
        $untimed    = [];
        foreach ($segments as $seg) {
            if (!empty($seg['words'])) {
                array_push($timedWords, ...$seg['words']);
            } else {
                $untimed[] = $seg;
            }
        }

        $cues = array_merge(
            $this->wordCues($timedWords, $clipStart, $hookDuration),
            $this->proportionalCues($untimed, $clipStart, $hookDuration)
        );
        foreach ($cues as $cue) {
            $ass .= $this->highlightDialogues($cue, $hlColor);
        }

        return $ass;
    }

    private function dialogue(float $start, float $end, string $text): string
    {
        return 'Dialogue: 0,' . $this->toAssTime($start) . ',' . $this->toAssTime($end) . ",Default,,0,0,0,,{$text}\n";
    }

    /**
     * One event per word: the whole cue stays on screen and only the word being
     * spoken takes the highlight colour; words before and after it stay the
     * base text colour (matches the studio preview). Events are back to back,
     * so the line never flickers or stacks.
     *
     * @param array{start: float, end: float, words: array<int, array{text: string, start: float}>} $cue
     */
    private function highlightDialogues(array $cue, string $hlColor): string
    {
        $words  = $cue['words'];
        $hl     = '{\\1c&H' . substr($hlColor, 4) . '&}'; // &H00BBGGRR -> \1c&HBBGGRR&
        $out    = '';

        foreach ($words as $i => $active) {
            $from = $i === 0 ? $cue['start'] : $active['start'];
            $to   = $words[$i + 1]['start'] ?? $cue['end'];
            if ($to <= $from) {
                continue;
            }

            $text = implode(' ', array_map(
                fn($w, $j) => $j === $i ? $hl . $w['text'] . '{\\r}' : $w['text'],
                $words,
                array_keys($words)
            ));
            $out .= $this->dialogue($from, $to, $text);
        }

        return $out;
    }

    /**
     * Group timed words into short cues. A cue breaks at MAX_CUE_WORDS, at a
     * pause longer than CUE_BREAK_GAP, or after sentence-ending punctuation.
     * Each word keeps its real start, so the highlight follows the speaker.
     *
     * @return array<int, array{start: float, end: float, words: array}>
     */
    private function wordCues(array $words, float $clipStart, float $hookDuration): array
    {
        $rel = [];
        foreach ($words as $w) {
            $start = (float) $w['start'] - $clipStart;
            $end   = (float) $w['end'] - $clipStart;
            $text  = trim($w['text'] ?? '');
            if ($text === '' || $end <= $hookDuration) {
                continue; // before the clip or hidden behind the hook
            }
            $rel[] = ['start' => max($start, $hookDuration), 'end' => $end, 'text' => $text];
        }
        usort($rel, fn($a, $b) => $a['start'] <=> $b['start']);

        $groups = [];
        $group  = [];
        foreach ($rel as $w) {
            if ($group) {
                $prev  = $group[count($group) - 1];
                $break = count($group) >= self::MAX_CUE_WORDS
                    || $w['start'] - $prev['end'] > self::CUE_BREAK_GAP
                    || preg_match('/[.?!]$/u', $prev['text']);
                if ($break) {
                    $groups[] = $group;
                    $group    = [];
                }
            }
            $group[] = $w;
        }
        if ($group) {
            $groups[] = $group;
        }

        $cues = [];
        foreach ($groups as $gi => $g) {
            $start     = $g[0]['start'];
            $nextStart = $groups[$gi + 1][0]['start'] ?? INF;
            // Hold briefly after the last word so short cues stay readable,
            // but never into the next cue (ASS would stack them as two rows).
            $end = min(max($g[count($g) - 1]['end'] + self::CUE_HOLD, $start + 0.3), $nextStart);

            $cues[] = ['start' => $start, 'end' => $end, 'words' => $g];
        }

        return $cues;
    }

    /** Fallback for segments without word timing: split evenly by word count. */
    private function proportionalCues(array $segments, float $clipStart, float $hookDuration): array
    {
        $cues = [];
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

            $perWord = $dur / $total;
            foreach (array_chunk($words, self::MAX_CUE_WORDS) as $chunk) {
                $cueStart = $cursor;
                $cueWords = [];
                foreach ($chunk as $word) {
                    $cueWords[] = ['text' => $word, 'start' => $cursor];
                    $cursor    += $perWord;
                }
                $cues[] = ['start' => $cueStart, 'end' => $cursor, 'words' => $cueWords];
            }
        }

        return $cues;
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
            '%s -y -i %s -vf %s -c:v libx264 -preset slow -crf 18 -pix_fmt yuv420p -c:a copy -movflags +faststart %s 2>&1',
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
            '%s -y -f concat -safe 0 -i %s -c:v libx264 -preset slow -crf 18 -pix_fmt yuv420p -c:a aac -b:a 192k -movflags +faststart %s 2>&1',
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
        // Round once on the total: rounding only the fraction turned 1.996s into "0:00:01.100"
        $total = (int) round(max($seconds, 0) * 100);
        $h  = intdiv($total, 360000);
        $m  = intdiv($total % 360000, 6000);
        $s  = intdiv($total % 6000, 100);
        $cs = $total % 100;
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
}
