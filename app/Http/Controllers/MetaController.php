<?php

namespace App\Http\Controllers;

use App\Services\YtDlpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MetaController extends Controller
{
    public function show(Request $request, YtDlpService $ytdlp): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'string', 'regex:/(?:youtube\.com\/(?:watch\?v=|shorts\/|embed\/)|youtu\.be\/)[A-Za-z0-9_-]{6,}/'],
        ]);

        $url = $request->string('url')->toString();
        $log = Log::channel('clipper_api');

        try {
            $meta = $ytdlp->getMetadata($url);

            $log->info('Meta fetched', [
                'user_id'  => $request->user()?->id,
                'url'      => $url,
                'title'    => $meta['title'],
                'duration' => $meta['duration_seconds'],
            ]);

            return response()->json($meta);
        } catch (\RuntimeException $e) {
            $log->warning('Meta fetch failed', [
                'user_id' => $request->user()?->id,
                'url'     => $url,
                'error'   => $e->getMessage(),
            ]);

            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
