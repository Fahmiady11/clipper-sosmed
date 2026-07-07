<?php

namespace App\Services;

class VideoLayoutService
{
    /**
     * Single source of truth for all font settings.
     * Edit here to change font across ClipVideoJob and ClipController.
     */
    public function fontConfig(): array
    {
        return [
            'path'           => $this->resolveFontPath(),
            'size_subtitle'  => 10,   // ASS points (scaled by player, 24 ≈ readable on 1080x1920)
            'size_overlay'   => 40,   // drawtext pixels on 1080x1920 canvas
            'color'          => 'white',  // text color — used by both drawtext & subtitleStyle
            'outline_size'   => 2,
            'shadow'         => 4,        // ASS Shadow depth (px)
            'shadow_x'       => 4,        // drawtext shadowx
            'shadow_y'       => 4,        // drawtext shadowy
            'shadow_opacity' => 0.85,
        ];
    }

    /**
     * Convert a color name + opacity to ASS &HAABBGGRR format.
     * ASS uses BGR (not RGB) and inverted alpha (00=opaque, FF=transparent).
     *
     * @param float $opacity 0.0 (transparent) to 1.0 (opaque)
     */
    public function toAssColor(string $color, float $opacity = 1.0): string
    {
        $palette = [
            'white'   => [255, 255, 255],
            'black'   => [0,   0,   0],
            'red'     => [255, 0,   0],
            'green'   => [0,   255, 0],
            'blue'    => [0,   0,   255],
            'yellow'  => [255, 255, 0],
            'cyan'    => [0,   255, 255],
            'magenta' => [255, 0,   255],
        ];

        [$r, $g, $b] = $palette[$color] ?? $palette['white'];
        $alpha = (int) round((1.0 - $opacity) * 255);

        return sprintf('&H%02X%02X%02X%02X', $alpha, $b, $g, $r);
    }

    /**
     * Returns filter_complex graph for the given layout mode.
     * $outputLabel: stream label for the final video output, e.g. '[out]' or '[pre_text]'.
     */
    public function layoutFilter(string $layoutMode, string $outputLabel = '[out]'): string
    {
        return match ($layoutMode) {
            'auto_reframe' =>
            "[0:v]crop=ih*(9/16):ih:(iw-ih*(9/16))/2:0,scale=1080:1920{$outputLabel}",
            'auto_split' =>
            '[0:v]split=2[s1][s2];' .
                '[s1]crop=in_w:trunc(in_h*0.55):0:0,scale=1080:960[top];' .
                '[s2]crop=in_w:trunc(in_h*0.55):0:trunc(in_h*0.45),scale=1080:960[bot];' .
                "[top][bot]vstack=inputs=2{$outputLabel}",
            default => // gaussian_blur, auto_magic
            '[0:v]split=2[s1][s2];' .
                '[s1]scale=1080:1920:force_original_aspect_ratio=increase,crop=1080:1920,boxblur=20:3,eq=saturation=1.5:brightness=-0.1[bg];' .
                '[s2]scale=1080:1920:force_original_aspect_ratio=decrease[fg];' .
                "[bg][fg]overlay=(W-w)/2:(H-h)/2{$outputLabel}",
        };
    }

    /**
     * Returns a drawtext filter segment (no leading semicolon).
     * Uses fontConfig() for font path and size.
     * Append to an existing filter_complex with ';'.
     */
    public function drawtextFilter(
        string $inputLabel,
        string $text,
        string $outputLabel = '[out]'
    ): string {
        $cfg     = $this->fontConfig();
        $fontEsc = $this->escapePath($cfg['path']);
        $textEsc = $this->escapeText($text);

        return "{$inputLabel}drawtext=" .
            "fontfile='{$fontEsc}'" .
            ":text='{$textEsc}'" .
            ":fontsize={$cfg['size_overlay']}" .
            ":fontcolor={$cfg['color']}" .
            // ":shadowcolor=black@{$cfg['shadow_opacity']}:shadowx={$cfg['shadow_x']}:shadowy={$cfg['shadow_y']}" .
            ':x=(w-text_w)/2:y=h-th-120' .
            $outputLabel;
    }

    /**
     * Returns ASS force_style string for SRT subtitle burn (subtitles filter).
     * Uses fontConfig() for sizes and colors.
     */
    public function subtitleStyle(): string
    {
        $cfg = $this->fontConfig();

        return implode(',', [
            "FontSize={$cfg['size_subtitle']}",
            'PrimaryColour=' . $this->toAssColor($cfg['color']),
            'Bold=0',
            'Outline=0',
            // "Shadow={$cfg['shadow']}",
            // 'BackColour=' . $this->toAssColor('black', $cfg['shadow_opacity']),
        ]);
    }

    public function findBinary(string $name): string
    {
        foreach (["/usr/local/bin/$name", "/opt/homebrew/bin/$name", "/usr/bin/$name"] as $p) {
            if (file_exists($p)) {
                return $p;
            }
        }
        return $name;
    }

    public function escapeText(string $text): string
    {
        return str_replace(['\\', "'", ':', '%'], ['\\\\', "\\'", '\\:', '\\%'], $text);
    }

    public function escapePath(string $path): string
    {
        return str_replace(['\\', ':'], ['\\\\', '\\:'], $path);
    }

    public function fontsDir(): string
    {
        return resource_path('fonts');
    }

    public function resolveFontByFamily(string $family): string
    {
        $map = [
            'Montserrat'  => 'Montserrat-Bold.ttf',
            'Roboto'      => 'Roboto-Bold.ttf',
            'Oswald'      => 'Oswald-Bold.ttf',
            'Bebas Neue'  => 'BebasNeue-Regular.ttf',
            'Arial'       => 'Arial-Bold.ttf',
        ];

        $file = $map[$family] ?? 'Montserrat-Bold.ttf';
        $path = $this->fontsDir() . '/' . $file;

        if (file_exists($path)) {
            return $path;
        }

        return $this->resolveFontPath();
    }

    private function resolveFontPath(): string
    {
        $bundled = $this->fontsDir() . '/Montserrat-Bold.ttf';
        if (file_exists($bundled)) {
            return $bundled;
        }

        $candidates = [
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            '/Library/Fonts/Arial.ttf',
            '/Library/Fonts/Arial Unicode.ttf',
            '/System/Library/Fonts/Helvetica.ttc',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        ];
        foreach ($candidates as $f) {
            if (file_exists($f)) {
                return $f;
            }
        }
        throw new \RuntimeException('No system font found');
    }
}
