<?php

namespace App\Http\Controllers;

use App\Jobs\UploadToTikTokJob;
use App\Models\GeneratedClip;
use App\Models\TiktokAccount;
use App\Services\TikTokService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TikTokController extends Controller
{
    public function __construct(private TikTokService $tiktok) {}

    public function connect(Request $request): RedirectResponse
    {
        if (!$this->tiktok->isConfigured()) {
            return redirect('/?tiktok=not_configured');
        }

        $state = Str::random(40);
        $request->session()->put('tiktok_oauth_state', $state);

        return redirect()->away($this->tiktok->authorizeUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expected = $request->session()->pull('tiktok_oauth_state');
        if (!$expected || !hash_equals($expected, (string) $request->query('state'))) {
            return redirect('/?tiktok=invalid_state');
        }
        if ($request->query('error') || !$request->query('code')) {
            return redirect('/?tiktok=denied');
        }

        try {
            $this->tiktok->connect(Auth::id(), (string) $request->query('code'));
        } catch (Throwable $e) {
            Log::channel('clipper_api')->error('TikTok connect failed', ['error' => $e->getMessage()]);
            return redirect('/?tiktok=error');
        }

        return redirect('/?tiktok=connected');
    }

    public function accounts(): JsonResponse
    {
        return response()->json([
            'configured' => $this->tiktok->isConfigured(),
            'accounts'   => TiktokAccount::where('user_id', Auth::id())
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'avatar_url']),
        ]);
    }

    public function disconnect(int $accountId): JsonResponse
    {
        TiktokAccount::where('user_id', Auth::id())->findOrFail($accountId)->delete();

        return response()->json(['success' => true]);
    }

    public function upload(Request $request, string $clipId): JsonResponse
    {
        $data    = $request->validate(['account_id' => ['required', 'integer']]);
        $clip    = $this->ownedClip($clipId);
        $account = TiktokAccount::where('user_id', Auth::id())->findOrFail($data['account_id']);

        abort_if($clip->status !== 'done' || !$clip->output_path, 422, 'Clip belum selesai dirender.');

        if (in_array($clip->tiktok_status, ['queued', 'uploading', 'processing'], true)) {
            return $this->statusResponse($clip);
        }

        $clip->update([
            'tiktok_account_id' => $account->id,
            'tiktok_publish_id' => null,
            'tiktok_status'     => 'queued',
            'tiktok_error'      => null,
        ]);
        UploadToTikTokJob::dispatch($clip->id, $account->id);

        return $this->statusResponse($clip);
    }

    public function status(string $clipId): JsonResponse
    {
        $clip = $this->ownedClip($clipId);

        // The job stops polling after ~1 minute; pick up the final state here
        if ($clip->tiktok_status === 'processing' && $clip->tiktok_publish_id && $clip->tiktok_account_id) {
            try {
                $account = TiktokAccount::findOrFail($clip->tiktok_account_id);
                $status  = $this->tiktok->publishStatus($account, $clip->tiktok_publish_id);
                $clip->update([
                    'tiktok_status' => UploadToTikTokJob::mapStatus($status['status']),
                    'tiktok_error'  => $status['fail_reason'],
                ]);
            } catch (Throwable $e) {
                Log::channel('clipper_api')->warning('TikTok status refresh failed', ['error' => $e->getMessage()]);
            }
        }

        return $this->statusResponse($clip);
    }

    private function ownedClip(string $clipId): GeneratedClip
    {
        $clip = GeneratedClip::with('clipProject')->findOrFail($clipId);
        abort_if($clip->clipProject->user_id !== Auth::id(), 403);

        return $clip;
    }

    private function statusResponse(GeneratedClip $clip): JsonResponse
    {
        return response()->json([
            'clip_id' => $clip->id,
            'status'  => $clip->tiktok_status,
            'error'   => $clip->tiktok_error,
        ]);
    }
}
