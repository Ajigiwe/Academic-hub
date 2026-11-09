<?php

namespace App\Http\Controllers;

use App\Services\Deployment\DeployEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeployWebhookController extends Controller
{
    /**
     * Handle incoming git push webhooks (GitHub, GitLab, or curl).
     */
    public function handle(Request $request, DeployEngine $engine): JsonResponse
    {
        $secret = config('deploy.webhook_secret');

        if (empty($secret)) {
            Log::warning('Deployment webhook rejected: DEPLOY_WEBHOOK_SECRET is not configured.');
            return response()->json([
                'status' => 'error',
                'message' => 'DEPLOY_WEBHOOK_SECRET is not configured on this server.',
            ], 403);
        }

        // 1. Authenticate Request
        if (!$this->isAuthorized($request, $secret)) {
            Log::warning('Deployment webhook rejected: invalid signature or token.');
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid signature or token.',
            ], 401);
        }

        // 2. Handle GitHub ping event
        if ($request->header('X-GitHub-Event') === 'ping') {
            return response()->json([
                'status' => 'ok',
                'message' => 'Webhook ping received successfully.',
            ]);
        }

        // 3. Verify Branch (for push events)
        $targetBranch = config('deploy.branch', 'main');
        $ref = $request->input('ref'); // e.g. refs/heads/main

        if ($ref && $ref !== "refs/heads/{$targetBranch}") {
            return response()->json([
                'status' => 'ignored',
                'message' => "Push event was for '{$ref}', ignoring (target branch is '{$targetBranch}').",
            ]);
        }

        // 4. Extract initiator details
        $pusher = $request->input('pusher.name')
            ?? $request->input('head_commit.author.username')
            ?? $request->input('head_commit.author.name')
            ?? 'GitHub Webhook';

        $commitMsg = $request->input('head_commit.message');

        try {
            $deployment = $engine->run(
                options: [
                    'branch' => $targetBranch,
                    'remote' => config('deploy.remote', 'origin'),
                ],
                trigger: 'webhook',
                initiatedBy: "GitHub ({$pusher})"
            );

            if ($deployment->isSuccess()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Deployment finished successfully.',
                    'deployment_id' => $deployment->id,
                    'commit' => $deployment->short_commit_after,
                    'duration' => $deployment->duration_seconds,
                ]);
            } else {
                return response()->json([
                    'status' => 'failed',
                    'message' => 'Deployment completed with errors.',
                    'deployment_id' => $deployment->id,
                    'duration' => $deployment->duration_seconds,
                ], 500);
            }
        } catch (\Throwable $e) {
            Log::error('Deployment webhook exception: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'status' => 'error',
                'message' => 'Deployment exception: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify whether the incoming request is authorized via GitHub HMAC or token.
     */
    protected function isAuthorized(Request $request, string $secret): bool
    {
        // Check GitHub HMAC SHA-256 header
        $hubSignature = $request->header('X-Hub-Signature-256');
        if ($hubSignature && str_starts_with($hubSignature, 'sha256=')) {
            $expectedSignature = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
            return hash_equals($expectedSignature, $hubSignature);
        }

        // Check header token
        $token = $request->header('X-Deploy-Token') ?: $request->query('token');
        if ($token && hash_equals($secret, (string) $token)) {
            return true;
        }

        return false;
    }
}

