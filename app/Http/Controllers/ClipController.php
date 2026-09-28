<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresQueueWorker;
use App\Helpers\YoutubeHelper;
use App\Http\Requests\StoreClipRequest;
use App\Jobs\ClipVideoJob;
use App\Jobs\GeminiAnalysisJob;
use App\Models\ClipBatch;
use App\Models\ClipJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClipController extends Controller
{
    use EnsuresQueueWorker;

    public function store(StoreClipRequest $request): JsonResponse
    {
        $data = $request->validated();

        $batch = ClipBatch::create([
            'youtube_url' => $data['youtube_url'],
            'video_id'    => YoutubeHelper::extractVideoId($data['youtube_url']),
            'layout_mode' => $data['layout_mode'],
            'clip_count'  => $data['clip_count'],
        ]);

        GeminiAnalysisJob::dispatch($batch->id);
        $this->ensureQueueWorkerRunning();

        return response()->json(['batch_id' => $batch->id, 'status' => $batch->status], 201);
    }

    public function batchStatus(string $batchId): JsonResponse
    {
        $batch = ClipBatch::with('clips')->findOrFail($batchId);

        $clips = $batch->clips->map(fn($c) => [
            'id'              => $c->id,
            'status'          => $c->status,
            'start_time'      => $c->start_time,
            'end_time'        => $c->end_time,
            'gemini_hook'     => $c->gemini_hook,
            'gemini_caption'  => $c->gemini_caption,
            'gemini_hashtags' => $c->gemini_hashtags ?? [],
            'error_msg'       => $c->error_msg,
            'expires_at'      => $c->expires_at?->toISOString(),
        ]);

        return response()->json([
            'batch_id'    => $batch->id,
            'status'      => $batch->status,
            'error_msg'   => $batch->error_msg,
            'layout_mode' => $batch->layout_mode,
            'clips'       => $clips,
        ]);
    }

    public function generate(Request $request, string $clipId): JsonResponse
    {
        $clip = ClipJob::findOrFail($clipId);

        abort_if(!in_array($clip->status, ['ready', 'failed']), 422, 'Clip not in ready state.');

        $data = $request->validate([
            'start_time'       => ['sometimes', 'integer', 'min:0'],
            'end_time'         => ['sometimes', 'integer', 'min:1'],
            'auto_caption'     => ['sometimes', 'boolean'],
            'caption_language' => ['sometimes', 'string', 'max:10'],
        ]);

        $updates = ['status' => 'pending', 'error_msg' => null];

        if (isset($data['start_time'], $data['end_time'])) {
            abort_if($data['end_time'] - $data['start_time'] > 600, 422, 'Max clip duration is 10 minutes.');
            $updates['start_time'] = $data['start_time'];
            $updates['end_time']   = $data['end_time'];
        }

        if (array_key_exists('auto_caption', $data)) {
            $updates['auto_caption']     = $data['auto_caption'];
            $updates['caption_language'] = $data['caption_language'] ?? null;
        }

        $clip->update($updates);

        ClipVideoJob::dispatch($clip->id);
        $this->ensureQueueWorkerRunning();

        return response()->json(['id' => $clip->id, 'status' => 'pending']);
    }

    public function status(string $id): JsonResponse
    {
        $clip = ClipJob::findOrFail($id);

        return response()->json([
            'id'         => $clip->id,
            'status'     => $clip->status,
            'error_msg'  => $clip->error_msg,
            'expires_at' => $clip->expires_at?->toISOString(),
        ]);
    }

    public function download(string $id): StreamedResponse
    {
        $clip = ClipJob::findOrFail($id);

        abort_if($clip->status !== 'done', 404, 'File not ready.');
        abort_if(!$clip->file_path || !Storage::exists($clip->file_path), 404, 'File not found.');

        return Storage::download($clip->file_path, 'clip_' . $id . '.mp4');
    }

    public function testSubtitle(): JsonResponse
    {
        $clip = ClipJob::create([
            'youtube_url'      => 'https://www.youtube.com/watch?v=test',
            'start_time'       => 0,
            'end_time'         => 60,
            'status'           => 'pending',
            'auto_caption'     => true,
            'caption_language' => 'id',
            'layout_mode'      => 'gaussian_blur',
        ]);

        ClipVideoJob::dispatch($clip->id);
        $this->ensureQueueWorkerRunning();

        return response()->json([
            'id'          => $clip->id,
            'status_url'  => url("/api/clip/{$clip->id}/status"),
            'download_url' => url("/api/clip/{$clip->id}/download"),
        ]);
    }

    public function preview(string $clipId): \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
    {
        $clip = ClipJob::find($clipId);
        if (!$clip) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $videoId = YoutubeHelper::extractVideoId($clip->youtube_url);
        if (!$videoId) {
            return response()->json(['error' => 'Invalid YouTube URL'], 422);
        }

        $tmpThumb = '/tmp/' . $clipId . '_thumb.jpg';
        $tmpOut   = '/tmp/' . $clipId . '_preview.jpg';

        foreach (['maxresdefault', 'hqdefault'] as $qual) {
            $content = @file_get_contents("https://img.youtube.com/vi/{$videoId}/{$qual}.jpg");
            if ($content && strlen($content) > 5000) {
                file_put_contents($tmpThumb, $content);
                break;
            }
        }

        if (!file_exists($tmpThumb)) {
            return response()->json(['error' => 'Thumbnail unavailable'], 502);
        }

        $layout     = new \App\Services\VideoLayoutService;
        $ffmpeg     = $layout->findBinary('ffmpeg');
        $input      = escapeshellarg($tmpThumb);
        $output     = escapeshellarg($tmpOut);
        $layoutMode = $clip->layout_mode ?? 'gaussian_blur';

        $hasText    = (bool) $clip->gemini_hook;
        $layoutOut  = $hasText ? '[pre_text]' : '[out]';
        $filterGraph = $layout->layoutFilter($layoutMode, $layoutOut);
        $finalLabel  = '[out]';

        if ($hasText) {
            try {
                $filterGraph .= ';' . $layout->drawtextFilter('[pre_text]', $clip->gemini_hook);
            } catch (\RuntimeException) {
                $finalLabel = '[pre_text]'; // no font — use layout output directly
            }
        }

        exec("$ffmpeg -i $input -filter_complex \"{$filterGraph}\" -map '$finalLabel' -vframes 1 -q:v 2 $output -y 2>&1", $ffOut, $code);
        @unlink($tmpThumb);

        if ($code !== 0 || !file_exists($tmpOut)) {
            return response()->json([
                'error'   => 'Preview generation failed',
                'details' => implode("\n", $ffOut),
            ], 500);
        }

        $image = file_get_contents($tmpOut);
        @unlink($tmpOut);

        return response($image, 200)->header('Content-Type', 'image/jpeg');
    }
}
