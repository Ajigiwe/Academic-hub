<?php

namespace Tests\Feature;

use App\Models\Deployment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class DeployTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PastqSeeder::class);

        $this->admin = User::where('email', 'admin@pastq.test')->first();
        $this->student = User::where('email', 'student@pastq.test')->first();

        config([
            'deploy.webhook_secret' => 'test-deploy-secret-12345',
            'deploy.branch' => 'main',
            'deploy.remote' => 'origin',
        ]);

        Process::fake([
            'git *' => Process::result('git command output'),
            'php *' => Process::result('php command output'),
            '*' => Process::result(''),
        ]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('framework/down'));
        @unlink(storage_path('app/deployment.lock'));
        parent::tearDown();
    }

    // ── Access Control ────────────────────────────────────────────────

    public function test_guest_cannot_access_deploy_engine(): void
    {
        $this->get('/admin/deploy')->assertRedirect('/login');
    }

    public function test_student_gets_404_on_deploy_engine(): void
    {
        $this->actingAs($this->student)->get('/admin/deploy')->assertNotFound();
    }

    public function test_admin_can_access_deploy_engine(): void
    {
        $response = $this->actingAs($this->admin)->get('/admin/deploy');
        $response->assertOk();
        $response->assertSee('Deploy Engine');
        $response->assertSee('Trigger Manual Deployment');
        $response->assertSee('GitHub Auto-Deploy');
    }

    // ── Admin Actions ─────────────────────────────────────────────────

    public function test_admin_can_clear_caches(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/deploy/cache');
        $response->assertRedirect();
        $response->assertSessionHas('status');
    }

    public function test_admin_can_toggle_maintenance(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/deploy/maintenance', [
            'enable' => '1',
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('status');

        $responseBack = $this->actingAs($this->admin)->post('/admin/deploy/maintenance', [
            'enable' => '0',
        ]);
        $responseBack->assertRedirect();
        $responseBack->assertSessionHas('status');
    }

    public function test_admin_can_run_deploy_and_records_history(): void
    {
        $response = $this->actingAs($this->admin)->post('/admin/deploy/run', [
            'branch' => 'main',
            'composer' => false,
            'migrate' => true,
            'cache_config' => false,
            'cache_routes' => false,
            'cache_views' => false,
            'maintenance' => false,
            'opcache' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('deployments', [
            'trigger' => 'manual',
            'status' => 'success',
        ]);

        $deployment = Deployment::latest()->first();
        $this->assertNotNull($deployment);
        $this->assertStringContainsString('Platform Admin', $deployment->initiated_by);
        $this->assertNotNull($deployment->output);
    }

    public function test_admin_can_view_deployment_log_json(): void
    {
        $deployment = Deployment::create([
            'status' => 'success',
            'trigger' => 'manual',
            'initiated_by' => 'Admin User',
            'commit_before' => 'abcdef1',
            'commit_after' => 'abcdef2',
            'commit_message' => 'Test commit message',
            'branch' => 'main',
            'duration_seconds' => 3.14,
            'output' => "Line 1\nLine 2",
        ]);

        $response = $this->actingAs($this->admin)->getJson("/admin/deploy/log/{$deployment->id}");
        $response->assertOk();
        $response->assertJson([
            'id' => $deployment->id,
            'status' => 'success',
            'trigger' => 'manual',
            'output' => "Line 1\nLine 2",
        ]);
    }

    // ── Webhook Authentication & Dispatch ─────────────────────────────

    public function test_webhook_rejects_requests_when_secret_not_configured(): void
    {
        config(['deploy.webhook_secret' => '']);

        $response = $this->postJson('/deploy/webhook', []);
        $response->assertStatus(403);
        $response->assertJson(['status' => 'error']);
    }

    public function test_webhook_rejects_unauthorized_requests(): void
    {
        $response = $this->postJson('/deploy/webhook', ['ref' => 'refs/heads/main']);
        $response->assertStatus(401);
        $response->assertJson(['status' => 'error']);
    }

    public function test_webhook_handles_github_ping_event(): void
    {
        $secret = 'test-deploy-secret-12345';
        $payload = json_encode(['zen' => 'Keep it logically awesome.']);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        $response = $this->call(
            'POST',
            '/deploy/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
                'HTTP_X_GITHUB_EVENT' => 'ping',
            ],
            $payload
        );

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);
    }

    public function test_webhook_ignores_push_to_other_branches(): void
    {
        $secret = 'test-deploy-secret-12345';
        $payload = json_encode([
            'ref' => 'refs/heads/feature-branch',
            'pusher' => ['name' => 'developer'],
        ]);
        $signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);

        $response = $this->call(
            'POST',
            '/deploy/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
                'HTTP_X_GITHUB_EVENT' => 'push',
            ],
            $payload
        );

        $response->assertOk();
        $response->assertJson(['status' => 'ignored']);
    }

    public function test_webhook_authorizes_via_token_header_and_triggers_deploy(): void
    {
        $secret = 'test-deploy-secret-12345';
        $payload = [
            'ref' => 'refs/heads/main',
            'pusher' => ['name' => 'Ajigiwe'],
            'head_commit' => [
                'message' => 'Update catalog views',
            ],
        ];

        // Disable composer in test environment to keep test fast
        config(['deploy.run_composer' => false]);

        $response = $this->postJson('/deploy/webhook', $payload, [
            'X-Deploy-Token' => $secret,
            'X-GitHub-Event' => 'push',
        ]);

        $response->assertOk();
        $response->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('deployments', [
            'trigger' => 'webhook',
            'status' => 'success',
        ]);
    }

    public function test_cli_deploy_command(): void
    {
        $this->artisan('deploy:run --quick')
            ->assertSuccessful();

        $this->assertDatabaseHas('deployments', [
            'trigger' => 'cli',
            'status' => 'success',
        ]);
    }
}
