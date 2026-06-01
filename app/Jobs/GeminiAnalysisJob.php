<?php

namespace App\Jobs;

use App\Models\ClipBatch;
use App\Models\ClipJob;
use App\Services\GeminiService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GeminiAnalysisJob implements ShouldQueue
{
    use Queueable;

    public int $tries   = 2;
    public int $timeout = 180;

    public function __construct(public string $batchId) {}

    public function handle(GeminiService $gemini): void
    {
        $batch = ClipBatch::findOrFail($this->batchId);
        $batch->update(['status' => 'analyzing']);

        $segments = $gemini->analyzeVideo(
            $batch->youtube_url,
            $batch->clip_count,
            $batch->layout_mode,
        );

        foreach ($segments as $seg) {
            $start = (int) ($seg['start_seconds'] ?? 0);
            $end   = (int) ($seg['end_seconds']   ?? $start + 60);

            ClipJob::create([
                'batch_id'        => $batch->id,
                'youtube_url'     => $batch->youtube_url,
                'layout_mode'     => $batch->layout_mode,
                'start_time'      => $start,
                'end_time'        => $end,
                'status'          => 'ready',
                'gemini_hook'     => $seg['hook']     ?? null,
                'gemini_caption'  => $seg['caption']  ?? null,
                'gemini_hashtags' => $seg['hashtags'] ?? [],
            ]);
        }

        $batch->update(['status' => 'analyzed']);
    }

    public function failed(Throwable $e): void
    {
        ClipBatch::where('id', $this->batchId)->update([
            'status'    => 'failed',
            'error_msg' => $e->getMessage(),
        ]);
    }
}
