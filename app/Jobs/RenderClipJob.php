<?php

namespace App\Jobs;

use App\Models\GeneratedClip;
use App\Services\FFmpegService;
use App\Services\MusicService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RenderClipJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 2;
    public int $timeout = 1800; // two libx264 "slow" passes on long clips can take >10 min

    public function __construct(public string $clipId) {}

    /**
     * Never run the same clip twice in parallel: a stale copy handed out
     * again while the original is still working is dropped instead of restarting.
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('render:' . $this->clipId))->expireAfter($this->timeout)->dontRelease()];
    }

    public function handle(FFmpegService $ffmpeg, MusicService $music): void
    {
        $log  = Log::channel('clipper_jobs');
        $clip = GeneratedClip::with(['clipProject.subtitleSetting', 'clipProject.hookSetting', 'clipProject.transcript'])->findOrFail($this->clipId);
        $project = $clip->clipProject;

        $clip->update(['status' => 'processing']);

        $log->info('RenderClipJob started', [
            'clip_id'    => $this->clipId,
            'project_id' => $project->id,
            'layout'     => $project->layout_type,
            'start'      => $clip->start_seconds,
            'end'        => $clip->end_seconds,
        ]);

        $tempDir  = storage_path('app/temp/' . $project->id);
        $srcVideo = $tempDir . '/video.mp4';

        if (!file_exists($srcVideo)) {
            throw new \RuntimeException("Source video not found: {$srcVideo}");
        }

        $step2 = $tempDir . '/layout_' . $this->clipId . '.mp4';

        // 1+2. Cut + layout in one re-encode pass. A stream-copy cut snaps to the
        // previous keyframe, so the clip started up to a few seconds early and
        // subtitles (timed from start_seconds) landed late by a varying amount.
        $log->info('Cutting + applying layout', [
            'layout' => $project->layout_type,
            'start'  => $clip->start_seconds,
            'end'    => $clip->end_seconds,
        ]);
        $ffmpeg->applyLayout($srcVideo, $project->layout_type, $step2, (float) $clip->start_seconds, (float) $clip->end_seconds);

        $current = $step2;

        // 3. Resolve hook first — its duration drives subtitle suppression
        $hook         = $project->hookSetting;
        $hookText     = null;
        $hookDuration = 0.0;
        $hookSettings = [];
        if ($hook && $hook->enabled) {
            $text = $hook->is_ai_generated ? ($clip->hook_text ?? $hook->hook_text) : $hook->hook_text;
            if ($text) {
                $hookText     = $text;
                $hookDuration = (float) $hook->duration_seconds;
                $hookSettings = [
                    'text_color'       => $hook->text_color,
                    'background_style' => $hook->background_style,
                    'position'         => $hook->position,
                    // Use subtitle font family for consistency; hook has no own font setting yet
                    'font_family'      => $project->subtitleSetting?->font_family ?? 'Montserrat',
                    'font_size'        => 60,
                ];
            }
        }

        // 4. Build subtitle ASS using real transcript timestamps (Fix 4)
        $assPath  = null;
        $subtitle = $project->subtitleSetting;
        if ($subtitle && $subtitle->enabled) {
            // Use DB transcript segments (real YouTube caption timestamps) instead of
            // Gemini-generated subtitle_json (AI approximation — causes timing drift).
            $transcript   = $project->transcript;
            $rawSegments  = $transcript?->content ?? $clip->subtitle_json ?? [];

            // Filter to segments overlapping this clip's time window
            $clipSegments = array_values(array_filter($rawSegments, fn($s) =>
                ($s['end']   ?? 0) > $clip->start_seconds &&
                ($s['start'] ?? 0) < $clip->end_seconds
            ));

            if (!empty($clipSegments)) {
                $log->info('Building subtitles', [
                    'font'     => $subtitle->font_family,
                    'position' => $subtitle->position,
                    'source'   => $transcript ? 'transcript' : 'subtitle_json',
                    'segments' => count($clipSegments),
                ]);
                $assPath    = $tempDir . '/sub_' . $this->clipId . '.ass';
                $assContent = $ffmpeg->buildAssFile($clipSegments, [
                    'font_family'      => $subtitle->font_family,
                    'font_size'        => $subtitle->font_size,
                    'text_color'       => $subtitle->text_color,
                    'highlight_color'  => $subtitle->highlight_color,
                    'position'         => $subtitle->position,
                    'background_style' => $subtitle->background_style,
                ], (float) $clip->start_seconds, $hookDuration);
                file_put_contents($assPath, $assContent);
            } else {
                $log->debug('Subtitles skipped — no segments in clip range', [
                    'enabled'  => $subtitle->enabled,
                    'has_json' => !empty($clip->subtitle_json),
                ]);
            }
        } else {
            $log->debug('Subtitles skipped', ['enabled' => $subtitle?->enabled]);
        }

        // 5. Single pass: burn subtitles + overlay hook (layer 2), audio preserved
        if ($assPath || $hookText) {
            $log->info('Burning overlay', ['hook' => $hookText, 'hook_dur' => $hookDuration, 'subs' => (bool) $assPath]);
            $final = $tempDir . '/final_' . $this->clipId . '.mp4';
            $ffmpeg->burnSubtitlesAndHook($current, $assPath, $hookText, $hookDuration, $hookSettings, $final);
            $current = $final;
        }

        // 6. Background music (ducked under the voice)
        $musicTrack = null;
        if ($project->music_enabled) {
            $mood  = $project->music_mood ?? $clip->music_mood;
            $track = $music->pickTrack($mood, $clip->id);
            if ($track) {
                $log->info('Mixing music', ['mood' => $mood, 'track' => basename($track), 'volume' => $project->music_volume]);
                $withMusic = $tempDir . '/music_' . $this->clipId . '.mp4';
                $ffmpeg->mixMusic($current, $track, (int) $project->music_volume, $withMusic);
                $current = $withMusic;
                $musicTrack = basename($track);
            } else {
                $log->warning('Music enabled but library is empty — skipped', ['path' => config('services.music.path')]);
            }
        }

        // 7. Move to output
        $outDir  = 'clips/' . $project->user_id . '/' . $project->id;
        Storage::makeDirectory($outDir);
        $outPath = $outDir . '/' . $this->clipId . '.mp4';
        Storage::put($outPath, file_get_contents($current));

        $this->cleanupTemp($tempDir, $this->clipId);

        $clip->update(['output_path' => $outPath, 'status' => 'done', 'music_track' => $musicTrack]);

        $log->info('RenderClipJob done', [
            'clip_id'    => $this->clipId,
            'output'     => $outPath,
            'size_mb'    => round(Storage::size($outPath) / 1048576, 2),
        ]);
    }

    public function failed(Throwable $e): void
    {
        Log::channel('clipper_jobs')->error('RenderClipJob failed', [
            'clip_id' => $this->clipId,
            'error'   => $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
        ]);

        GeneratedClip::where('id', $this->clipId)->update([
            'status'    => 'failed',
            'error_msg' => $e->getMessage(),
        ]);
    }

    private function cleanupTemp(string $dir, string $clipId): void
    {
        foreach (['cut_', 'layout_', 'subtitled_', 'hook_', 'final_', 'sub_', 'music_'] as $prefix) {
            foreach (['.mp4', '.ass'] as $ext) {
                $f = $dir . '/' . $prefix . $clipId . $ext;
                if (file_exists($f)) @unlink($f);
            }
        }
    }
}
