<?php

namespace App\Services\Transcribers;

trait SrtHelper
{
    private function srtTimeToMs(string $time): int
    {
        [$hms, $ms] = explode(',', $time);
        [$h, $m, $s] = explode(':', $hms);
        return ((int)$h * 3600 + (int)$m * 60 + (int)$s) * 1000 + (int)$ms;
    }

    private function msToSrtTime(int $ms): string
    {
        $h   = intdiv($ms, 3600000);
        $ms %= 3600000;
        $m   = intdiv($ms, 60000);
        $ms %= 60000;
        $s   = intdiv($ms, 1000);
        $ms %= 1000;
        return sprintf('%02d:%02d:%02d,%03d', $h, $m, $s, $ms);
    }
}
