@extends('admin.layout')

@section('title', 'Deploy Engine — Admin · Academic Hub')

@section('admin-content')
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-bold text-neutral-900 dark:text-white">Deploy Engine</h1>
            <p class="mt-1 text-sm text-neutral-600 dark:text-neutral-400">
                Continuous deployment pipeline from Git to live server with zero manual FTP file uploads.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            {{-- Check for remote updates --}}
            <form method="POST" action="{{ route('admin.deploy.check') }}">
                @csrf
                <button type="submit" class="btn-secondary btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21h5v-5"/></svg>
                    Check for Updates
                </button>
            </form>

            {{-- Quick clear caches --}}
            <form method="POST" action="{{ route('admin.deploy.cache') }}">
                @csrf
                <button type="submit" class="btn-secondary btn-sm" title="Clear config, route and view caches">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                    Clear Caches
                </button>
            </form>

            {{-- Toggle maintenance mode --}}
            <form method="POST" action="{{ route('admin.deploy.maintenance') }}">
                @csrf
                <input type="hidden" name="enable" value="{{ $diagnostics['in_maintenance'] ? '0' : '1' }}">
                <button type="submit" class="{{ $diagnostics['in_maintenance'] ? 'btn-primary' : 'btn-secondary' }} btn-sm" onclick="return confirm('{{ $diagnostics['in_maintenance'] ? 'Bring site back LIVE to all visitors?' : 'Put site in MAINTENANCE MODE? Non-admins will see maintenance page.' }}');">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    {{ $diagnostics['in_maintenance'] ? 'Site Maintenance: ON (Click to make Live)' : 'Site: Live' }}
                </button>
            </form>
        </div>
    </div>

    {{-- ── 1. Status Overview Grid ──────────────────────────────────────── --}}
    <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Current Commit --}}
        <div class="card-padded min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Current Commit</p>
            <div class="mt-2 flex items-center gap-2">
                <span class="font-mono text-sm font-bold text-brand-700 dark:text-brand-300">{{ $gitStatus['short_commit'] ?: 'N/A' }}</span>
                @if ($gitStatus['is_dirty'])
                    <span class="badge-gold text-[10px]" title="Working directory has uncommitted edits">modified</span>
                @else
                    <span class="badge-success text-[10px]">clean</span>
                @endif
            </div>
            <p class="mt-1 truncate text-xs text-neutral-600 dark:text-neutral-400" title="{{ $gitStatus['message'] }}">
                {{ $gitStatus['message'] ?: 'No git history detected' }}
            </p>
            <p class="mt-0.5 text-[11px] text-neutral-400">
                {{ $gitStatus['author'] }} · {{ $gitStatus['date'] }}
            </p>
        </div>

        {{-- Active Branch & Remote --}}
        <div class="card-padded min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Branch & Remote</p>
            <p class="mt-2 text-base font-bold text-neutral-900 dark:text-white">
                <span class="inline-flex items-center gap-1 font-mono text-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="3" x2="6" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/></svg>
                    {{ $gitStatus['branch'] }}
                </span>
            </p>
            <p class="mt-1 truncate text-xs text-neutral-500 dark:text-neutral-400" title="{{ $gitStatus['remote_url'] }}">
                {{ $gitStatus['remote_url'] ?: 'No remote configured' }}
            </p>
        </div>

        {{-- Environment & Runtime --}}
        <div class="card-padded min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Environment & PHP</p>
            <p class="mt-2 text-base font-bold text-neutral-900 dark:text-white">
                <span class="{{ $diagnostics['environment'] === 'production' ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
                    {{ ucfirst($diagnostics['environment']) }}
                </span>
                <span class="text-xs font-normal text-neutral-500">· PHP {{ $diagnostics['php_version'] }}</span>
            </p>
            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                Composer: {{ $diagnostics['composer_type'] }}
            </p>
        </div>

        {{-- Remote Sync Status --}}
        <div class="card-padded min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-500 dark:text-neutral-400">Remote Sync</p>
            <div class="mt-2">
                @if (isset($updatesInfo['behind']) && $updatesInfo['behind'] > 0)
                    <span class="badge-gold font-semibold">{{ $updatesInfo['behind'] }} commit(s) behind remote</span>
                @else
                    <span class="badge-success font-semibold">Synchronized with remote</span>
                @endif
            </div>
            <p class="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                @if (isset($updatesInfo['behind']) && $updatesInfo['behind'] > 0)
                    Ready to pull into production.
                @else
                    Server is running latest commit.
                @endif
            </p>
        </div>
    </div>

    {{-- Incoming Commits Banner (when updates are available) --}}
    @if (isset($updatesInfo['behind']) && $updatesInfo['behind'] > 0)
        <div class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/40">
            <div class="flex items-start gap-3">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 text-amber-700 dark:text-amber-400"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-bold text-amber-900 dark:text-amber-200">
                        {{ $updatesInfo['behind'] }} new commit{{ $updatesInfo['behind'] === 1 ? '' : 's' }} ready on {{ config('deploy.branch', 'main') }}
                    </h3>
                    <p class="mt-0.5 text-xs text-amber-800 dark:text-amber-300">
                        Click "Deploy to Production Now" below to pull these changes, run migrations, and update production caches.
                    </p>

                    <div class="mt-3 divide-y divide-amber-200/60 rounded-lg border border-amber-200 bg-white/80 dark:divide-neutral-800 dark:border-neutral-800 dark:bg-neutral-900/80">
                        @foreach ($updatesInfo['commits'] as $c)
                            <div class="flex flex-wrap items-center justify-between gap-2 p-2.5 text-xs">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-brand-700 dark:text-brand-300">{{ $c['hash'] }}</span>
                                    <span class="font-medium text-neutral-900 dark:text-neutral-100">{{ $c['message'] }}</span>
                                </div>
                                <span class="text-neutral-500 dark:text-neutral-400">{{ $c['author'] }} · {{ $c['date'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ── 2. Deploy Actions & Webhook Guide ───────────────────────────── --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Deploy Form --}}
        <div class="card p-5 lg:col-span-2">
            <h2 class="text-base font-bold text-neutral-900 dark:text-white">Trigger Manual Deployment</h2>
            <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
                Pulls latest changes directly on the server, updates composer packages, executes any pending schema migrations, and optimizes Laravel caches.
            </p>

            <form method="POST" action="{{ route('admin.deploy.run') }}" class="mt-5" onsubmit="return confirm('Start deployment now? This will pull Git updates and refresh production.');">
                @csrf
                <input type="hidden" name="branch" value="{{ $gitStatus['branch'] ?: config('deploy.branch', 'main') }}">

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="flex items-start gap-2.5 rounded-lg border border-neutral-200 p-3 text-xs dark:border-neutral-800">
                        <input type="checkbox" name="composer" value="1" checked class="mt-0.5 rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                        <div>
                            <span class="font-semibold text-neutral-900 dark:text-white">Install Dependencies</span>
                            <p class="text-neutral-500 dark:text-neutral-400">Runs Composer install --no-dev --optimize-autoloader</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-2.5 rounded-lg border border-neutral-200 p-3 text-xs dark:border-neutral-800">
                        <input type="checkbox" name="migrate" value="1" checked class="mt-0.5 rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                        <div>
                            <span class="font-semibold text-neutral-900 dark:text-white">Run Database Migrations</span>
                            <p class="text-neutral-500 dark:text-neutral-400">Runs artisan migrate --force</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-2.5 rounded-lg border border-neutral-200 p-3 text-xs dark:border-neutral-800">
                        <input type="checkbox" name="cache_config" value="1" checked class="mt-0.5 rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                        <div>
                            <span class="font-semibold text-neutral-900 dark:text-white">Optimize Framework Caches</span>
                            <p class="text-neutral-500 dark:text-neutral-400">Caches config, routes, and views</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-2.5 rounded-lg border border-neutral-200 p-3 text-xs dark:border-neutral-800">
                        <input type="checkbox" name="opcache" value="1" checked class="mt-0.5 rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                        <div>
                            <span class="font-semibold text-neutral-900 dark:text-white">Reset OPcache</span>
                            <p class="text-neutral-500 dark:text-neutral-400">Clears compiled PHP bytecode</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-2.5 rounded-lg border border-neutral-200 p-3 text-xs dark:border-neutral-800 sm:col-span-2">
                        <input type="checkbox" name="maintenance" value="1" class="mt-0.5 rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                        <div>
                            <span class="font-semibold text-neutral-900 dark:text-white">Maintenance mode during deploy</span>
                            <p class="text-neutral-500 dark:text-neutral-400">Puts site in maintenance while tasks execute, then brings it live when done</p>
                        </div>
                    </label>
                </div>

                <div class="mt-5 flex items-center justify-between border-t border-neutral-200 pt-4 dark:border-neutral-800">
                    <p class="text-xs text-neutral-500">
                        Target: <span class="font-mono font-semibold">{{ config('deploy.remote', 'origin') }}/{{ $gitStatus['branch'] ?: 'main' }}</span>
                    </p>
                    <button type="submit" class="btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mr-1.5"><path d="M4.5 16.5c-1.5 1.26-2 5-2 5s3.74-.5 5-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="m12 15-3-3a22 22 0 0 1 2-3.95A12.88 12.88 0 0 1 22 2c0 2.72-.78 7.5-6 11a22.35 22.35 0 0 1-4 2z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/></svg>
                        Deploy to Production Now
                    </button>
                </div>
            </form>
        </div>

        {{-- Automated GitHub Webhook Guide --}}
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-bold text-neutral-900 dark:text-white">GitHub Auto-Deploy</h2>
                @if ($webhookConfigured)
                    <span class="badge-success text-[10px]">Secret Active</span>
                @else
                    <span class="badge-gold text-[10px]">Secret Missing</span>
                @endif
            </div>

            <p class="mt-1 text-xs text-neutral-600 dark:text-neutral-400">
                Push code to GitHub and your server will pull and update automatically.
            </p>

            <div class="mt-4 space-y-3">
                <div>
                    <label class="text-[10px] font-bold uppercase tracking-wide text-neutral-500">Webhook URL</label>
                    <div class="mt-1 flex items-center gap-1.5">
                        <input type="text" readonly value="{{ $webhookUrl }}" id="webhook-url-input" class="input font-mono !py-1.5 !text-xs">
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('webhook-url-input').value); alert('Webhook URL copied!');" class="btn-secondary !px-2.5 !py-1.5 text-xs shrink-0" title="Copy to clipboard">
                            Copy
                        </button>
                    </div>
                </div>

                <div>
                    <label class="text-[10px] font-bold uppercase tracking-wide text-neutral-500">Webhook Secret</label>
                    @if ($webhookConfigured)
                        <p class="mt-0.5 font-mono text-xs text-neutral-700 dark:text-neutral-300">•••••••••••••••• (configured in .env)</p>
                    @else
                        <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-400">
                            Set <span class="font-mono font-semibold">DEPLOY_WEBHOOK_SECRET=your_secret</span> in <span class="font-mono">.env</span> to enable auto-deploy.
                        </p>
                    @endif
                </div>

                <div class="rounded-lg bg-neutral-50 p-3 text-xs text-neutral-600 dark:bg-neutral-900 dark:text-neutral-400">
                    <p class="font-semibold text-neutral-800 dark:text-neutral-200">GitHub 1-Minute Setup:</p>
                    <ol class="mt-1.5 list-decimal pl-4 space-y-1 text-[11px]">
                        <li>In GitHub, open <b>Settings → Webhooks → Add webhook</b>.</li>
                        <li>Paste the <b>Webhook URL</b> above.</li>
                        <li>Set <b>Content type</b> to <span class="font-mono">application/json</span>.</li>
                        <li>Paste your <b>Secret</b> (<span class="font-mono">DEPLOY_WEBHOOK_SECRET</span>).</li>
                        <li>Select <b>Just the push event</b> and save.</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    {{-- ── 3. Latest Deployment Terminal Console ───────────────────────── --}}
    @if ($latestDeployment)
        <div class="card mt-6 p-5">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-neutral-200 pb-3 dark:border-neutral-800">
                <div>
                    <h2 class="text-base font-bold text-neutral-900 dark:text-white">
                        Latest Deployment Log #{{ $latestDeployment->id }}
                    </h2>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                        Triggered via {{ ucfirst($latestDeployment->trigger) }} by {{ $latestDeployment->initiated_by }} · {{ $latestDeployment->created_at->diffForHumans() }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="{{ $latestDeployment->status_badge }} font-bold text-xs">
                        {{ strtoupper($latestDeployment->status) }}
                    </span>
                    @if ($latestDeployment->duration_seconds)
                        <span class="badge-neutral text-xs tabular-nums">{{ $latestDeployment->duration_seconds }}s</span>
                    @endif
                </div>
            </div>

            <div class="mt-4 rounded-xl bg-neutral-950 p-4 text-xs font-mono text-neutral-200 overflow-x-auto max-h-96 whitespace-pre-wrap select-all leading-relaxed border border-neutral-800">
{{ $latestDeployment->output }}
@if ($latestDeployment->error_output)

[ERROR DETAILS]
{{ $latestDeployment->error_output }}
@endif
            </div>
        </div>
    @endif

    {{-- ── 4. System Diagnostics Checklist ────────────────────────────── --}}
    <div class="card mt-6 p-5">
        <h2 class="text-base font-bold text-neutral-900 dark:text-white">Server Diagnostics & Permissions</h2>
        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 text-xs">
            <div class="flex items-center justify-between rounded-lg border border-neutral-200 p-2.5 dark:border-neutral-800">
                <span class="text-neutral-600 dark:text-neutral-400">Git Binary</span>
                <span class="{{ $diagnostics['git_available'] ? 'text-green-600 dark:text-green-400 font-medium' : 'text-red-600 font-medium' }}">
                    {{ $diagnostics['git_available'] ? 'Available' : 'Missing' }}
                </span>
            </div>

            <div class="flex items-center justify-between rounded-lg border border-neutral-200 p-2.5 dark:border-neutral-800">
                <span class="text-neutral-600 dark:text-neutral-400">PHP CLI Binary</span>
                <span class="truncate font-mono text-neutral-900 dark:text-neutral-200 max-w-[180px]" title="{{ $diagnostics['php_binary'] }}">
                    {{ basename($diagnostics['php_binary']) }}
                </span>
            </div>

            <div class="flex items-center justify-between rounded-lg border border-neutral-200 p-2.5 dark:border-neutral-800">
                <span class="text-neutral-600 dark:text-neutral-400">Composer</span>
                <span class="{{ $diagnostics['composer_available'] ? 'text-green-600 dark:text-green-400 font-medium' : 'text-red-600 font-medium' }}">
                    {{ $diagnostics['composer_type'] }}
                </span>
            </div>

            <div class="flex items-center justify-between rounded-lg border border-neutral-200 p-2.5 dark:border-neutral-800">
                <span class="text-neutral-600 dark:text-neutral-400">storage/ writable</span>
                <span class="{{ $diagnostics['storage_writable'] ? 'text-green-600 dark:text-green-400 font-medium' : 'text-red-600 font-medium' }}">
                    {{ $diagnostics['storage_writable'] ? 'Yes' : 'No' }}
                </span>
            </div>

            <div class="flex items-center justify-between rounded-lg border border-neutral-200 p-2.5 dark:border-neutral-800">
                <span class="text-neutral-600 dark:text-neutral-400">bootstrap/cache writable</span>
                <span class="{{ $diagnostics['bootstrap_cache_writable'] ? 'text-green-600 dark:text-green-400 font-medium' : 'text-red-600 font-medium' }}">
                    {{ $diagnostics['bootstrap_cache_writable'] ? 'Yes' : 'No' }}
                </span>
            </div>

            <div class="flex items-center justify-between rounded-lg border border-neutral-200 p-2.5 dark:border-neutral-800">
                <span class="text-neutral-600 dark:text-neutral-400">Public Root Sync (Option B)</span>
                <span class="font-mono text-neutral-700 dark:text-neutral-300">
                    {{ $diagnostics['public_target'] ? basename($diagnostics['public_target']) : 'Standard /public' }}
                </span>
            </div>
        </div>
    </div>

    {{-- ── 5. Deployment History Table ─────────────────────────────────── --}}
    <div class="mt-6">
        <h2 class="text-base font-bold text-neutral-900 dark:text-white">Deployment History</h2>
        <div class="card mt-3 overflow-x-auto">
            <table class="table-responsive w-full text-sm">
                <thead>
                    <tr class="border-b border-neutral-200 text-left text-xs uppercase tracking-wide text-neutral-500 dark:border-neutral-800">
                        <th class="px-4 py-3 font-medium">ID</th>
                        <th class="px-4 py-3 font-medium">Trigger</th>
                        <th class="px-4 py-3 font-medium">Initiated By</th>
                        <th class="px-4 py-3 font-medium">Commit</th>
                        <th class="px-4 py-3 font-medium">Duration</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium text-right">Log</th>
                    </tr>
                </thead>
                <tbody class="md:table-row-group">
                    @forelse ($deployments as $d)
                        <tr class="md:table-row md:border-b md:border-neutral-100 dark:md:border-neutral-800">
                            <td data-label="ID" class="px-4 py-3 font-mono text-xs font-semibold">#{{ $d->id }}</td>
                            <td data-label="Trigger" class="px-4 py-3 font-medium">
                                <span class="badge-neutral text-xs">{{ ucfirst($d->trigger) }}</span>
                            </td>
                            <td data-label="Initiated By" class="px-4 py-3 text-xs text-neutral-700 dark:text-neutral-300">
                                {{ $d->initiated_by }}
                            </td>
                            <td data-label="Commit" class="px-4 py-3">
                                <span class="font-mono text-xs text-brand-700 dark:text-brand-300">
                                    {{ $d->short_commit_after ?: $d->short_commit_before }}
                                </span>
                                @if ($d->commit_message)
                                    <p class="truncate text-[11px] text-neutral-500 max-w-[200px]" title="{{ $d->commit_message }}">
                                        {{ $d->commit_message }}
                                    </p>
                                @endif
                            </td>
                            <td data-label="Duration" class="whitespace-nowrap px-4 py-3 text-xs tabular-nums text-neutral-600 dark:text-neutral-400">
                                {{ $d->duration_seconds ? $d->duration_seconds . 's' : '—' }}
                            </td>
                            <td data-label="Status" class="px-4 py-3">
                                <span class="{{ $d->status_badge }} text-xs">{{ strtoupper($d->status) }}</span>
                            </td>
                            <td data-label="Date" class="whitespace-nowrap px-4 py-3 text-xs text-neutral-600 dark:text-neutral-400">
                                {{ $d->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td data-label="" class="px-4 py-3 text-right">
                                <button type="button" onclick="showDeploymentModal({{ $d->id }})" class="btn-secondary !py-1 !px-2.5 text-xs">
                                    View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-neutral-500" data-label="">
                                No deployments recorded yet. Trigger your first deployment above or push to GitHub.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($deployments->hasPages())
            <div class="mt-4">
                {{ $deployments->links() }}
            </div>
        @endif
    </div>

    {{-- Deployment Modal --}}
    <div id="deploy-modal" class="fixed inset-0 z-50 hidden place-items-center bg-black/60 p-4" onclick="if(event.target === this) closeDeploymentModal();">
        <div class="w-full max-w-3xl rounded-xl border border-neutral-200 bg-white p-5 shadow-2xl dark:border-neutral-800 dark:bg-neutral-900">
            <div class="flex items-center justify-between border-b border-neutral-200 pb-3 dark:border-neutral-800">
                <div>
                    <h3 id="modal-title" class="text-base font-bold text-neutral-900 dark:text-white">Deployment Details</h3>
                    <p id="modal-subtitle" class="text-xs text-neutral-500"></p>
                </div>
                <button type="button" onclick="closeDeploymentModal()" class="grid h-8 w-8 place-items-center rounded-lg text-neutral-400 hover:bg-neutral-100 hover:text-neutral-900 dark:hover:bg-neutral-800">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                </button>
            </div>
            <pre id="modal-output" class="mt-4 max-h-[60vh] overflow-y-auto rounded-lg bg-neutral-950 p-4 text-xs font-mono text-neutral-200 leading-relaxed whitespace-pre-wrap select-all"></pre>
        </div>
    </div>

    <script>
        function showDeploymentModal(id) {
            fetch('{{ url("/admin/deploy/log") }}/' + id)
                .then(r => r.json())
                .then(data => {
                    document.getElementById('modal-title').innerText = 'Deployment #' + data.id + ' (' + data.status.toUpperCase() + ')';
                    document.getElementById('modal-subtitle').innerText = 'Triggered via ' + data.trigger + ' by ' + data.initiated_by + ' · ' + (data.duration ? data.duration + 's' : '');
                    document.getElementById('modal-output').innerText = data.output + (data.error_output ? '\n\n[ERROR DETAILS]\n' + data.error_output : '');
                    var m = document.getElementById('deploy-modal');
                    m.classList.remove('hidden');
                    m.classList.add('grid');
                });
        }
        function closeDeploymentModal() {
            var m = document.getElementById('deploy-modal');
            m.classList.add('hidden');
            m.classList.remove('grid');
        }
    </script>
@endsection

