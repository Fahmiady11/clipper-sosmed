<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresQueueWorker;
use App\Jobs\RenderClipJob;
use App\Models\GeneratedClip;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RenderController extends Controller
{
    use EnsuresQueueWorker;

    public function render(string $clipId): JsonResponse
    {
        $clip = GeneratedClip::with('clipProject')->findOrFail($clipId);

        abort_if($clip->clipProject->user_id !== Auth::id(), 403);

        // Already done or in-flight — return current status, frontend will poll
        if (in_array($clip->status, ['done', 'processing'])) {
            return response()->json(['clip_id' => $clip->id, 'status' => $clip->status]);
        }

        $clip->update(['status' => 'processing', 'error_msg' => null]);

        RenderClipJob::dispatch($clip->id);
        $this->ensureQueueWorkerRunning();

        return response()->json(['clip_id' => $clip->id, 'status' => 'processing']);
    }

    public function renderStatus(string $clipId): JsonResponse
    {
        $clip = GeneratedClip::with('clipProject')->findOrFail($clipId);

        abort_if($clip->clipProject->user_id !== Auth::id(), 403);

        return response()->json([
            'clip_id'     => $clip->id,
            'status'      => $clip->status,
            'error_msg'   => $clip->error_msg,
            'output_path' => $clip->output_path,
            'download_url' => $clip->status === 'done'
                ? url("/api/clips/{$clip->id}/download")
                : null,
        ]);
    }

    public function caption(string $clipId, GeminiService $gemini): JsonResponse
    {
        $clip = GeneratedClip::with('clipProject')->findOrFail($clipId);

        abort_if($clip->clipProject->user_id !== Auth::id(), 403);

        // Return cached caption if already generated
        if ($clip->caption !== null) {
            return response()->json([
                'caption'  => $clip->caption,
                'hashtags' => $clip->hashtags_json ?? [],
            ]);
        }

        $lang     = $clip->clipProject->transcript?->language ?? 'id';
        $segments = $clip->subtitle_json ?? [];

        $result = $gemini->generateCaption(
            $clip->topic,
            $clip->hook_text ?? '',
            $segments,
            $lang,
        );

        $clip->update([
            'caption'       => $result['caption'],
            'hashtags_json' => $result['hashtags'],
        ]);

        return response()->json([
            'caption'  => $result['caption'],
            'hashtags' => $result['hashtags'],
        ]);
    }
}
