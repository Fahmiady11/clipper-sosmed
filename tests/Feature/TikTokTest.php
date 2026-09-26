<?php

namespace Tests\Feature;

use App\Jobs\UploadToTikTokJob;
use App\Models\ClipProject;
use App\Models\GeneratedClip;
use App\Models\TiktokAccount;
use App\Models\User;
use App\Services\TikTokService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TikTokTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key'                      => 'base64:' . base64_encode(str_repeat('k', 32)),
            'services.tiktok.client_key'    => 'ck',
            'services.tiktok.client_secret' => 'cs',
            'services.tiktok.redirect_uri'  => 'https://example.test/tiktok/callback',
        ]);
    }

    private function account(User $user, array $attrs = []): TiktokAccount
    {
        return TiktokAccount::create($attrs + [
            'user_id'            => $user->id,
            'open_id'            => 'open-1',
            'display_name'       => 'Creator',
            'access_token'       => 'at-old',
            'refresh_token'      => 'rt-old',
            'access_expires_at'  => now()->addDay(),
            'refresh_expires_at' => now()->addDays(300),
        ]);
    }

    private function doneClip(User $user): GeneratedClip
    {
        $project = ClipProject::create([
            'user_id' => $user->id, 'youtube_url' => 'https://youtu.be/abcdefg',
            'layout_type' => 'reframe', 'clip_count' => 1, 'duration_mode' => 'auto', 'status' => 'done',
        ]);
        Storage::put('clips/test.mp4', str_repeat('v', 2048));

        return GeneratedClip::create([
            'clip_project_id' => $project->id, 'ranking' => 1, 'start_seconds' => 0, 'end_seconds' => 10,
            'topic' => 't', 'reason' => 'r', 'status' => 'done', 'output_path' => 'clips/test.mp4',
        ]);
    }

    public function test_chunk_plan(): void
    {
        $mb = 1024 * 1024;
        $this->assertSame([3 * $mb, 1], TikTokService::chunkPlan(3 * $mb));
        $this->assertSame([64 * $mb, 1], TikTokService::chunkPlan(64 * $mb));
        // 95 MB → 9 chunks of 10 MB, the last one carries the extra 5 MB
        $this->assertSame([10 * $mb, 9], TikTokService::chunkPlan(95 * $mb));
    }

    public function test_connect_redirects_with_state_and_callback_stores_account(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->get('/tiktok/connect');
        $res->assertRedirect();
        parse_str(parse_url($res->headers->get('Location'), PHP_URL_QUERY), $q);
        $this->assertSame('ck', $q['client_key']);
        $this->assertSame('https://example.test/tiktok/callback', $q['redirect_uri']);

        Http::fake([
            'open.tiktokapis.com/v2/oauth/token/' => Http::response([
                'access_token' => 'at-1', 'refresh_token' => 'rt-1', 'open_id' => 'open-1',
                'expires_in' => 86400, 'refresh_expires_in' => 31536000, 'scope' => 'user.info.basic,video.upload',
            ]),
            'open.tiktokapis.com/v2/user/info/*' => Http::response([
                'data' => ['user' => ['open_id' => 'open-1', 'display_name' => 'Creator']],
                'error' => ['code' => 'ok'],
            ]),
        ]);

        $this->actingAs($user)
            ->get('/tiktok/callback?code=abc&state=' . $q['state'])
            ->assertRedirect('/?tiktok=connected');

        $account = TiktokAccount::firstOrFail();
        $this->assertSame('Creator', $account->display_name);
        $this->assertSame('at-1', $account->access_token);
        $this->assertNotSame('at-1', $account->getRawOriginal('access_token')); // encrypted at rest
    }

    public function test_callback_rejects_wrong_state(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/tiktok/connect');

        $this->actingAs($user)
            ->get('/tiktok/callback?code=abc&state=forged')
            ->assertRedirect('/?tiktok=invalid_state');
        $this->assertSame(0, TiktokAccount::count());
    }

    public function test_expired_access_token_is_refreshed(): void
    {
        $account = $this->account(User::factory()->create(), ['access_expires_at' => now()->subMinute()]);
        Http::fake(['open.tiktokapis.com/v2/oauth/token/' => Http::response([
            'access_token' => 'at-new', 'refresh_token' => 'rt-new', 'open_id' => 'open-1', 'expires_in' => 86400,
        ])]);

        $this->assertSame('at-new', app(TikTokService::class)->freshToken($account));
        Http::assertSent(fn(Request $r) => $r['grant_type'] === 'refresh_token' && $r['refresh_token'] === 'rt-old');
        $this->assertSame('rt-new', $account->fresh()->refresh_token);
    }

    public function test_upload_to_inbox_inits_and_puts_file(): void
    {
        $account = $this->account(User::factory()->create());
        $path    = tempnam(sys_get_temp_dir(), 'vid');
        file_put_contents($path, str_repeat('x', 1000));

        Http::fake([
            'open.tiktokapis.com/v2/post/publish/inbox/video/init/' => Http::response([
                'data' => ['publish_id' => 'pub-1', 'upload_url' => 'https://upload.example/u1'],
                'error' => ['code' => 'ok'],
            ]),
            'upload.example/*' => Http::response('', 201),
        ]);

        $this->assertSame('pub-1', app(TikTokService::class)->uploadToInbox($account, $path));

        Http::assertSent(fn(Request $r) => str_contains($r->url(), 'inbox/video/init')
            && $r->hasHeader('Authorization', 'Bearer at-old')
            && $r['source_info'] === ['source' => 'FILE_UPLOAD', 'video_size' => 1000, 'chunk_size' => 1000, 'total_chunk_count' => 1]);
        Http::assertSent(fn(Request $r) => $r->method() === 'PUT'
            && $r->hasHeader('Content-Range', 'bytes 0-999/1000')
            && strlen($r->body()) === 1000);
        @unlink($path);
    }

    public function test_api_error_code_is_raised(): void
    {
        $account = $this->account(User::factory()->create());
        Http::fake(['open.tiktokapis.com/*' => Http::response([
            'data' => [], 'error' => ['code' => 'spam_risk_too_many_posts', 'message' => 'too many'],
        ])]);

        $this->expectExceptionMessage('spam_risk_too_many_posts');
        app(TikTokService::class)->publishStatus($account, 'pub-1');
    }

    public function test_upload_endpoint_queues_job_for_own_account_only(): void
    {
        Queue::fake();
        $user  = User::factory()->create();
        $clip  = $this->doneClip($user);
        $mine  = $this->account($user);
        $other = $this->account(User::factory()->create(), ['open_id' => 'open-2']);

        $this->actingAs($user)->postJson("/api/clips/{$clip->id}/tiktok", ['account_id' => $other->id])
            ->assertNotFound();

        $this->actingAs($user)->postJson("/api/clips/{$clip->id}/tiktok", ['account_id' => $mine->id])
            ->assertOk()->assertJson(['status' => 'queued']);
        Queue::assertPushed(UploadToTikTokJob::class, fn($job) => $job->clipId === $clip->id && $job->accountId === $mine->id);
    }

    public function test_job_uploads_and_records_inbox_status(): void
    {
        $user    = User::factory()->create();
        $clip    = $this->doneClip($user);
        $account = $this->account($user);

        $svc = $this->mock(TikTokService::class);
        $svc->shouldReceive('uploadToInbox')->once()->andReturn('pub-9');
        $svc->shouldReceive('publishStatus')->once()->andReturn(['status' => 'SEND_TO_USER_INBOX', 'fail_reason' => null]);

        $job = new UploadToTikTokJob($clip->id, $account->id);
        $job->pollSeconds = 0;
        $this->app->call([$job, 'handle']);

        $clip->refresh();
        $this->assertSame('pub-9', $clip->tiktok_publish_id);
        $this->assertSame('inbox', $clip->tiktok_status);
    }
}
