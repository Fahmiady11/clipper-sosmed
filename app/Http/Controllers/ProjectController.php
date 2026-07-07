<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Jobs\AnalyzeVideoJob;
use App\Models\ClipProject;
use App\Models\HookSetting;
use App\Models\SubtitleSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProjectController extends Controller
{
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $data = $request->validated();

        $project = ClipProject::create([
            'user_id'       => Auth::id(),
            'youtube_url'   => $data['youtube_url'],
            'layout_type'   => $data['layout_type'],
            'clip_count'    => $data['clip_count'],
            'duration_mode' => $data['duration_mode'],
            'min_duration'  => $data['min_duration'] ?? null,
            'max_duration'  => $data['max_duration'] ?? null,
            'status'        => 'processing',
        ]);

        // Create subtitle settings
        $sub = $data['subtitle'] ?? [];
        SubtitleSetting::create([
            'clip_project_id'  => $project->id,
            'enabled'          => $sub['enabled'] ?? true,
            'font_family'      => $sub['font_family'] ?? 'Montserrat',
            'font_size'        => $sub['font_size'] ?? 42,
            'text_color'       => $sub['text_color'] ?? '#ffffff',
            'highlight_color'  => $sub['highlight_color'] ?? '#facc15',
            'position'         => $sub['position'] ?? 'bottom',
            'background_style' => $sub['background_style'] ?? 'semi',
        ]);

        // Create hook settings
        $hook = $data['hook'] ?? [];
        HookSetting::create([
            'clip_project_id'  => $project->id,
            'enabled'          => $hook['enabled'] ?? true,
            'hook_text'        => $hook['hook_text'] ?? null,
            'is_ai_generated'  => $hook['is_ai_generated'] ?? false,
            'duration_seconds' => $hook['duration_seconds'] ?? 3.0,
            'position'         => $hook['position'] ?? 'center',
            'text_color'       => $hook['text_color'] ?? '#ffffff',
            'background_style' => $hook['background_style'] ?? 'semi',
        ]);

        AnalyzeVideoJob::dispatch($project->id);
        $this->ensureQueueWorkerRunning();

        Log::channel('clipper_api')->info('Project created, AnalyzeVideoJob dispatched', [
            'project_id' => $project->id,
            'user_id'    => Auth::id(),
            'url'        => $project->youtube_url,
            'layout'     => $project->layout_type,
            'clip_count' => $project->clip_count,
        ]);

        return response()->json([
            'project_id' => $project->id,
            'status'     => $project->status,
        ], 201);
    }

    public function index(): JsonResponse
    {
        $projects = ClipProject::where('user_id', Auth::id())
            ->with(['generatedClips' => fn($q) => $q->where('status', 'done')->orderBy('ranking')])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json($projects->map(fn($p) => [
            'project_id'       => $p->id,
            'title'            => $p->video_title,
            'thumbnail_url'    => $p->thumbnail_url,
            'layout_type'      => $p->layout_type,
            'status'           => $p->status,
            'created_at'       => $p->created_at->toISOString(),
            'rendered_clip_id' => $p->generatedClips->first()?->id,
        ]));
    }

    public function destroy(string $projectId): JsonResponse
    {
        $project = ClipProject::where('id', $projectId)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $project->subtitleSetting()->delete();
        $project->hookSetting()->delete();
        $project->transcript()->delete();
        $project->generatedClips()->delete();
        $project->delete();

        return response()->json(['success' => true]);
    }

    public function status(string $projectId): JsonResponse
    {
        $project = ClipProject::with('generatedClips')
            ->where('user_id', Auth::id())
            ->findOrFail($projectId);

        $clips = $project->generatedClips->map(fn($c) => [
            'id'             => $c->id,
            'ranking'        => $c->ranking,
            'start_seconds'  => $c->start_seconds,
            'end_seconds'    => $c->end_seconds,
            'topic'          => $c->topic,
            'reason'         => $c->reason,
            'viral_potential' => $c->viral_potential,
            'hook_text'      => $c->hook_text,
            'status'         => $c->status,
        ]);

        return response()->json([
            'project_id'       => $project->id,
            'status'           => $project->status,
            'progress_stage'   => $project->progress_stage,
            'progress_message' => $project->progress_message,
            'error_msg'        => $project->error_msg,
            'clips'      => $clips,
        ]);
    }

    private function ensureQueueWorkerRunning(): void
    {
        // Match only OUR persistent worker (--timeout=600), not artisan serve's
        // internal --once runners which have default 60s timeout and will kill
        // long-running jobs like AnalyzeVideoJob (yt-dlp downloads take 2-15 min).
        exec("pgrep -f 'queue:work.*--timeout=600' 2>/dev/null", $pids);
        if (!empty($pids)) {
            return;
        }

        $php     = PHP_BINARY;
        $artisan = base_path('artisan');
        $log     = storage_path('logs/queue-worker.log');

        $cmd = "{$php} {$artisan} queue:work"
             . ' --timeout=600'
             . ' --memory=512'
             . ' --sleep=1'
             . ' --tries=1'
             . " >> {$log} 2>&1";

        exec("nohup {$cmd} &");
    }
}
