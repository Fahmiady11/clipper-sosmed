<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\EnsuresQueueWorker;
use App\Http\Requests\StoreProjectRequest;
use App\Models\ClipProject;
use App\Services\ProjectCreator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ProjectController extends Controller
{
    use EnsuresQueueWorker;

    public function store(StoreProjectRequest $request, ProjectCreator $creator): JsonResponse
    {
        $data    = $request->validated();
        $project = $creator->create(Auth::id(), $data['youtube_url'], $data);
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
}
