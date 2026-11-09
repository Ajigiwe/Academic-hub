<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deployment;
use App\Services\Deployment\DeployEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DeployAdminController extends Controller
{
    /**
     * Display the Deploy Engine control dashboard.
     */
    public function index(Request $request, DeployEngine $engine): View
    {
        $gitStatus = $engine->getGitStatus();
        $diagnostics = $engine->getDiagnostics();
        $deployments = Deployment::latest()->paginate(15);
        $latestDeployment = Deployment::latest()->first();

        $webhookUrl = url('/deploy/webhook');
        $webhookSecret = config('deploy.webhook_secret');
        $webhookConfigured = !empty($webhookSecret);

        $updatesInfo = session('updates_info');

        return view('admin.deploy', [
            'gitStatus' => $gitStatus,
            'diagnostics' => $diagnostics,
            'deployments' => $deployments,
            'latestDeployment' => $latestDeployment,
            'webhookUrl' => $webhookUrl,
            'webhookSecret' => $webhookSecret,
            'webhookConfigured' => $webhookConfigured,
            'updatesInfo' => $updatesInfo,
        ]);
    }

    /**
     * Check for updates on remote Git repository.
     */
    public function checkUpdates(DeployEngine $engine): RedirectResponse
    {
        try {
            $updates = $engine->checkForUpdates();
            return back()->with('updates_info', $updates)->with(
                $updates['success'] ? 'status' : 'error',
                $updates['message']
            );
        } catch (\Throwable $e) {
            return back()->with('error', 'Error checking for updates: ' . $e->getMessage());
        }
    }

    /**
     * Manually trigger deployment from admin panel.
     */
    public function runDeploy(Request $request, DeployEngine $engine): RedirectResponse
    {
        $options = [
            'branch' => $request->input('branch', config('deploy.branch', 'main')),
            'composer' => $request->boolean('composer', true),
            'migrate' => $request->boolean('migrate', true),
            'cache_config' => $request->boolean('cache_config', true),
            'cache_routes' => $request->boolean('cache_routes', true),
            'cache_views' => $request->boolean('cache_views', true),
            'maintenance' => $request->boolean('maintenance', false),
            'opcache' => $request->boolean('opcache', true),
        ];

        $user = $request->user();
        $initiatedBy = $user ? "{$user->name} ({$user->email})" : 'Admin User';

        try {
            $deployment = $engine->run(
                options: $options,
                trigger: 'manual',
                initiatedBy: $initiatedBy
            );

            if ($deployment->isSuccess()) {
                return back()->with('status', "Deployment #{$deployment->id} completed successfully in {$deployment->duration_seconds}s.");
            } else {
                return back()->with('error', "Deployment #{$deployment->id} encountered errors. Check the terminal log below.");
            }
        } catch (\Throwable $e) {
            return back()->with('error', 'Deployment failed: ' . $e->getMessage());
        }
    }

    /**
     * Clear application caches.
     */
    public function clearCache(DeployEngine $engine): RedirectResponse
    {
        try {
            $result = $engine->clearCaches();
            return back()->with('status', 'Application config, route, and view caches have been cleared.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to clear caches: ' . $e->getMessage());
        }
    }

    /**
     * Toggle maintenance mode on or off.
     */
    public function toggleMaintenance(Request $request, DeployEngine $engine): RedirectResponse
    {
        $enable = $request->boolean('enable');

        try {
            $result = $engine->toggleMaintenance($enable);
            $msg = $enable ? 'Site is now in Maintenance Mode.' : 'Site is now Live and accessible to all users.';
            return back()->with('status', $msg);
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to toggle maintenance mode: ' . $e->getMessage());
        }
    }

    /**
     * Return deployment log data as JSON for live inspection.
     */
    public function showLog(Deployment $deployment): JsonResponse
    {
        return response()->json([
            'id' => $deployment->id,
            'status' => $deployment->status,
            'trigger' => $deployment->trigger,
            'initiated_by' => $deployment->initiated_by,
            'commit_before' => $deployment->short_commit_before,
            'commit_after' => $deployment->short_commit_after,
            'duration' => $deployment->duration_seconds,
            'created_at' => $deployment->created_at->toIso8601String(),
            'output' => $deployment->output,
            'error_output' => $deployment->error_output,
        ]);
    }
}

