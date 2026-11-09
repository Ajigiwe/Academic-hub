<?php

namespace App\Services\Deployment;

use App\Models\Deployment;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Throwable;

class DeployEngine
{
    protected string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath ?: base_path();
    }

    /**
     * Get the PHP CLI binary to use for Artisan / composer invocations.
     */
    public function getPhpBinary(): string
    {
        if ($configured = config('deploy.php_binary')) {
            if (file_exists($configured) && is_executable($configured)) {
                return $configured;
            }
        }

        // Check if PHP_BINARY is CLI (not cgi / fpm)
        if (defined('PHP_BINARY') && PHP_BINARY) {
            $bin = PHP_BINARY;
            if (!str_contains($bin, 'fpm') && !str_contains($bin, 'cgi') && file_exists($bin)) {
                return $bin;
            }
        }

        $candidates = [
            '/opt/alt/php84/bin/php',
            '/opt/alt/php83/bin/php',
            '/opt/alt/php82/bin/php',
            '/usr/local/bin/php',
            '/usr/bin/php',
            'C:\\xampp\\php\\php.exe',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return 'php';
    }

    /**
     * Get the Composer command / phar to use.
     */
    public function getComposerBinary(): ?string
    {
        if ($configured = config('deploy.composer_binary')) {
            return $configured;
        }

        $pharCandidates = [
            $this->basePath . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'composer.phar',
            $this->basePath . DIRECTORY_SEPARATOR . 'composer.phar',
        ];

        foreach ($pharCandidates as $phar) {
            if (file_exists($phar)) {
                return $this->getPhpBinary() . ' ' . escapeshellarg($phar);
            }
        }

        return 'composer';
    }

    /**
     * Check if current working tree is a git repository.
     */
    public function isGitRepository(): bool
    {
        if (!is_dir($this->basePath . DIRECTORY_SEPARATOR . '.git')) {
            return false;
        }

        $res = Process::path($this->basePath)->run(['git', 'rev-parse', '--is-inside-work-tree']);
        return $res->successful() && trim($res->output()) === 'true';
    }

    /**
     * Get current Git status and commit information.
     */
    public function getGitStatus(): array
    {
        if (!$this->isGitRepository()) {
            return [
                'is_git' => false,
                'branch' => 'unknown',
                'commit' => null,
                'short_commit' => null,
                'author' => null,
                'date' => null,
                'message' => null,
                'remote_url' => null,
                'is_dirty' => false,
            ];
        }

        $branch = trim(Process::path($this->basePath)->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD'])->output()) ?: config('deploy.branch', 'main');
        $commit = trim(Process::path($this->basePath)->run(['git', 'rev-parse', 'HEAD'])->output());
        $shortCommit = trim(Process::path($this->basePath)->run(['git', 'rev-parse', '--short', 'HEAD'])->output());
        $logLine = trim(Process::path($this->basePath)->run(['git', 'log', '-1', '--format=%h|%an|%ar|%s'])->output());

        $author = null;
        $date = null;
        $message = null;
        if ($logLine && str_contains($logLine, '|')) {
            $parts = explode('|', $logLine, 4);
            $author = $parts[1] ?? null;
            $date = $parts[2] ?? null;
            $message = $parts[3] ?? null;
        }

        $remote = config('deploy.remote', 'origin');
        $remoteUrl = trim(Process::path($this->basePath)->run(['git', 'remote', 'get-url', $remote])->output());
        if (!$remoteUrl) {
            $remoteUrl = trim(Process::path($this->basePath)->run(['git', 'config', '--get', "remote.{$remote}.url"])->output());
        }

        $statusPorcelain = trim(Process::path($this->basePath)->run(['git', 'status', '--porcelain'])->output());

        return [
            'is_git' => true,
            'branch' => $branch,
            'commit' => $commit,
            'short_commit' => $shortCommit,
            'author' => $author,
            'date' => $date,
            'message' => $message,
            'remote_url' => $remoteUrl,
            'is_dirty' => !empty($statusPorcelain),
        ];
    }

    /**
     * Fetch from remote and check for incoming commits.
     */
    public function checkForUpdates(): array
    {
        if (!$this->isGitRepository()) {
            return [
                'success' => false,
                'message' => 'Not a valid Git repository.',
                'behind' => 0,
                'ahead' => 0,
                'commits' => [],
            ];
        }

        $remote = config('deploy.remote', 'origin');
        $branch = config('deploy.branch', 'main');

        // Fetch remote updates
        $fetchRes = Process::path($this->basePath)
            ->timeout(60)
            ->run(['git', 'fetch', $remote, $branch]);

        if (!$fetchRes->successful()) {
            return [
                'success' => false,
                'message' => 'git fetch failed: ' . ($fetchRes->errorOutput() ?: $fetchRes->output()),
                'behind' => 0,
                'ahead' => 0,
                'commits' => [],
            ];
        }

        // Count behind / ahead
        $behindRes = Process::path($this->basePath)->run(['git', 'rev-list', '--count', "HEAD..{$remote}/{$branch}"]);
        $aheadRes = Process::path($this->basePath)->run(['git', 'rev-list', '--count', "{$remote}/{$branch}..HEAD"]);

        $behind = (int) trim($behindRes->output());
        $ahead = (int) trim($aheadRes->output());

        $commits = [];
        if ($behind > 0) {
            $logRes = Process::path($this->basePath)->run([
                'git', 'log', "HEAD..{$remote}/{$branch}", '--pretty=format:%h|%an|%ar|%s', '-n', '15',
            ]);
            $lines = array_filter(explode("\n", trim($logRes->output())));
            foreach ($lines as $line) {
                $p = explode('|', trim($line), 4);
                $commits[] = [
                    'hash' => $p[0] ?? '',
                    'author' => $p[1] ?? '',
                    'date' => $p[2] ?? '',
                    'message' => $p[3] ?? '',
                ];
            }
        }

        return [
            'success' => true,
            'behind' => $behind,
            'ahead' => $ahead,
            'commits' => $commits,
            'message' => $behind > 0
                ? "{$behind} new commit(s) available on {$remote}/{$branch}."
                : "Up to date with {$remote}/{$branch}.",
        ];
    }

    /**
     * Diagnostic checklist for the server environment.
     */
    public function getDiagnostics(): array
    {
        $phpBin = $this->getPhpBinary();
        $phpVer = PHP_VERSION;

        $gitOk = false;
        $gitVer = 'Not found';
        try {
            $gitRes = Process::path($this->basePath)->run(['git', '--version']);
            if ($gitRes->successful()) {
                $gitOk = true;
                $gitVer = trim($gitRes->output());
            }
        } catch (Throwable) {}

        $storageWritable = is_writable(storage_path());
        $bootstrapCacheWritable = is_writable(base_path('bootstrap/cache'));

        $composerOk = false;
        $composerStr = 'Not found';
        if ($phar = $this->getComposerBinary()) {
            $composerOk = true;
            $composerStr = str_contains($phar, 'composer.phar') ? 'tools/composer.phar' : 'System Composer';
        }

        $opcacheEnabled = function_exists('opcache_get_status') && !empty(opcache_get_status(false)['opcache_enabled']);

        $publicTarget = config('deploy.public_path');
        $publicTargetExists = $publicTarget && is_dir($publicTarget);

        return [
            'php_binary' => $phpBin,
            'php_version' => $phpVer,
            'git_available' => $gitOk,
            'git_version' => $gitVer,
            'composer_available' => $composerOk,
            'composer_type' => $composerStr,
            'storage_writable' => $storageWritable,
            'bootstrap_cache_writable' => $bootstrapCacheWritable,
            'opcache_enabled' => $opcacheEnabled,
            'in_maintenance' => app()->isDownForMaintenance(),
            'environment' => app()->environment(),
            'public_target' => $publicTarget,
            'public_target_exists' => $publicTargetExists,
        ];
    }

    /**
     * Run the complete deployment process.
     */
    public function run(array $options = [], string $trigger = 'manual', ?string $initiatedBy = null): Deployment
    {
        $lockFile = storage_path('app/deployment.lock');

        // Check if deployment is already running (10-minute expiry)
        if (file_exists($lockFile)) {
            $lockAge = time() - filemtime($lockFile);
            if ($lockAge < 600) {
                $activeDep = Deployment::where('status', 'running')->latest()->first();
                $depId = $activeDep ? "#{$activeDep->id}" : '';
                throw new \RuntimeException("Another deployment {$depId} is currently running. Please wait for it to complete.");
            }
            @unlink($lockFile);
        }

        @file_put_contents($lockFile, (string) time());

        $remote = $options['remote'] ?? config('deploy.remote', 'origin');
        $branch = $options['branch'] ?? config('deploy.branch', 'main');
        $runMigrations = $options['migrate'] ?? config('deploy.run_migrations', true);
        $runComposer = $options['composer'] ?? config('deploy.run_composer', true);
        $cacheConfig = $options['cache_config'] ?? config('deploy.cache_config', true);
        $cacheRoutes = $options['cache_routes'] ?? config('deploy.cache_routes', true);
        $cacheViews = $options['cache_views'] ?? config('deploy.cache_views', true);
        $maintenance = $options['maintenance'] ?? config('deploy.maintenance', false);
        $resetOpcache = $options['opcache'] ?? true;
        $syncPublic = $options['sync_public'] ?? true;
        $timeout = (int) ($options['timeout'] ?? config('deploy.timeout', 300));

        $gitStatusBefore = $this->getGitStatus();
        $commitBefore = $gitStatusBefore['commit'];

        $deployment = Deployment::create([
            'status' => 'running',
            'trigger' => $trigger,
            'initiated_by' => $initiatedBy ?: 'System',
            'commit_before' => $commitBefore,
            'branch' => $branch,
            'output' => "Deployment started at " . now()->toIso8601String() . " by {$initiatedBy}\n",
        ]);

        $startTime = microtime(true);
        $logs = [];
        $failed = false;
        $phpBin = $this->getPhpBinary();
        $maintenanceActive = false;

        $log = function (string $type, string $message) use (&$logs, $deployment) {
            $time = date('H:i:s');
            $line = "[{$time}] [{$type}] {$message}";
            $logs[] = $line;
            $deployment->update(['output' => implode("\n", $logs)]);
        };

        try {
            $log('INFO', "Target branch: {$branch} (Remote: {$remote})");
            $log('INFO', "PHP CLI Binary: {$phpBin}");

            // Step 1: Optional Maintenance Mode
            if ($maintenance) {
                $log('STEP', "Entering maintenance mode...");
                $downRes = Process::path($this->basePath)->run([$phpBin, 'artisan', 'down', '--retry=60']);
                $maintenanceActive = true;
                $log('INFO', trim($downRes->output()) ?: 'Application is now in maintenance mode.');
            }

            // Step 2: Git Pull
            if ($this->isGitRepository()) {
                $log('STEP', "Pulling updates from {$remote}/{$branch}...");

                // Fetch remote branch first
                $fetch = Process::path($this->basePath)->timeout($timeout)->run(['git', 'fetch', $remote, $branch]);
                if (!$fetch->successful()) {
                    $log('WARN', "git fetch: " . ($fetch->errorOutput() ?: $fetch->output()));
                }

                // Execute pull
                $pull = Process::path($this->basePath)->timeout($timeout)->run(['git', 'pull', $remote, $branch]);
                $pullOutput = trim($pull->output() . "\n" . $pull->errorOutput());
                $log('INFO', $pullOutput ?: 'git pull completed.');

                if (!$pull->successful()) {
                    throw new \RuntimeException("Git pull failed:\n" . $pullOutput);
                }
            } else {
                $log('WARN', "Not a git repository — skipping git pull.");
            }

            // Step 3: Public assets sync (cPanel Option B layout)
            if ($syncPublic) {
                $syncRes = $this->syncPublicAssets();
                if (!empty($syncRes['skipped'])) {
                    $log('INFO', "Public sync: " . $syncRes['message']);
                } else {
                    $log('STEP', "Public sync: {$syncRes['copied_files']} files copied to {$syncRes['target']}");
                    if ($syncRes['index_rewritten']) {
                        $log('INFO', "Rewrote index.php bootstrap paths for external document root.");
                    }
                }
            }

            // Step 4: Composer Install
            if ($runComposer) {
                $composerBin = $this->getComposerBinary();
                $log('STEP', "Running Composer install (--no-dev --optimize-autoloader)...");

                // Execute composer
                $compCmd = str_contains($composerBin, ' ')
                    ? "{$composerBin} install --no-dev --optimize-autoloader --no-interaction"
                    : "{$phpBin} {$composerBin} install --no-dev --optimize-autoloader --no-interaction";

                $compProc = Process::path($this->basePath)->timeout($timeout)->run($compCmd);
                $compOut = trim($compProc->output() . "\n" . $compProc->errorOutput());
                if (!empty($compOut)) {
                    $log('INFO', substr($compOut, -500)); // append tail of composer output
                }

                if (!$compProc->successful()) {
                    $log('WARN', "Composer warning / non-zero exit: " . $compProc->exitCode());
                } else {
                    $log('SUCCESS', "Composer dependencies optimized.");
                }
            }

            // Step 5: Database Migrations
            if ($runMigrations) {
                $log('STEP', "Running database migrations (artisan migrate --force)...");
                $migProc = Process::path($this->basePath)->timeout(120)->run([$phpBin, 'artisan', 'migrate', '--force']);
                $migOut = trim($migProc->output() . "\n" . $migProc->errorOutput());
                $log('INFO', $migOut ?: 'No new migrations.');

                if (!$migProc->successful()) {
                    throw new \RuntimeException("Artisan migrate failed:\n" . $migOut);
                }
            }

            // Step 6: Optimizing Caches
            $log('STEP', "Rebuilding framework caches...");

            if ($cacheConfig) {
                $proc = Process::path($this->basePath)->run([$phpBin, 'artisan', 'config:cache']);
                $log('INFO', trim($proc->output()) ?: 'Config cached.');
            }

            if ($cacheRoutes) {
                $proc = Process::path($this->basePath)->run([$phpBin, 'artisan', 'route:cache']);
                $log('INFO', trim($proc->output()) ?: 'Routes cached.');
            }

            if ($cacheViews) {
                $proc = Process::path($this->basePath)->run([$phpBin, 'artisan', 'view:cache']);
                $log('INFO', trim($proc->output()) ?: 'Views compiled.');
            }

            // Ensure storage symlink exists
            if (!file_exists(public_path('storage'))) {
                Process::path($this->basePath)->run([$phpBin, 'artisan', 'storage:link']);
                $log('INFO', "Artisan storage:link invoked.");
            }

            // Step 7: OPcache Reset
            if ($resetOpcache) {
                if (function_exists('opcache_reset')) {
                    @opcache_reset();
                    $log('INFO', "OPcache reset executed via opcache_reset().");
                }
                // Touching index.php causes PHP OPcache to invalidate old bytecode
                @touch(public_path('index.php'));
            }

            $log('SUCCESS', "All deployment tasks completed successfully.");

        } catch (Throwable $e) {
            $failed = true;
            $log('ERROR', "Deployment failed: " . $e->getMessage());
            $deployment->update(['error_output' => $e->getTraceAsString()]);
        } finally {
            // Restore application from maintenance mode if we turned it on
            if ($maintenanceActive) {
                $upRes = Process::path($this->basePath)->run([$phpBin, 'artisan', 'up']);
                $log('INFO', trim($upRes->output()) ?: 'Application is live again.');
            }

            @unlink($lockFile);

            $duration = round(microtime(true) - $startTime, 2);
            $gitStatusAfter = $this->getGitStatus();

            $deployment->update([
                'status' => $failed ? 'failed' : 'success',
                'commit_after' => $gitStatusAfter['commit'] ?: $commitBefore,
                'commit_message' => $gitStatusAfter['message'],
                'duration_seconds' => $duration,
                'output' => implode("\n", $logs),
            ]);
        }

        return $deployment->fresh();
    }

    /**
     * Copy public/ assets into cPanel public_html document root (Option B).
     */
    public function syncPublicAssets(?string $target = null): array
    {
        $target = $target ?: config('deploy.public_path');

        if (!$target) {
            // Auto-detect common cPanel layout: if app is in ~/repositories/Academic-hub,
            // check if ~/public_html exists
            $possiblePath = dirname($this->basePath, 2) . DIRECTORY_SEPARATOR . 'public_html';
            if (is_dir($possiblePath) && $possiblePath !== public_path()) {
                $target = $possiblePath;
            }
        }

        if (!$target || !is_dir($target)) {
            return [
                'skipped' => true,
                'message' => 'No target public_path configured or directory does not exist.',
            ];
        }

        $source = public_path();
        if (realpath($source) === realpath($target)) {
            return [
                'skipped' => true,
                'message' => 'Public source and target are identical.',
            ];
        }

        $count = 0;
        File::copyDirectory($source, $target);

        // Adjust index.php in target directory to point to app directory
        $targetIndex = $target . DIRECTORY_SEPARATOR . 'index.php';
        $indexRewritten = false;

        if (file_exists($targetIndex)) {
            $indexContent = file_get_contents($targetIndex);
            $relApp = rtrim(str_replace('\\', '/', $this->basePath), '/');

            // Replace standard Laravel index.php relative path with reference to app directory
            if (str_contains($indexContent, "__DIR__.'/../")) {
                $updatedContent = str_replace(
                    "__DIR__.'/../",
                    "'{$relApp}/",
                    $indexContent
                );
                file_put_contents($targetIndex, $updatedContent);
                $indexRewritten = true;
            }
        }

        return [
            'skipped' => false,
            'target' => $target,
            'copied_files' => count(File::allFiles($target)),
            'index_rewritten' => $indexRewritten,
        ];
    }

    /**
     * Clear config, route, and view caches.
     */
    public function clearCaches(): array
    {
        $phpBin = $this->getPhpBinary();

        $c1 = Process::path($this->basePath)->run([$phpBin, 'artisan', 'config:clear']);
        $c2 = Process::path($this->basePath)->run([$phpBin, 'artisan', 'route:clear']);
        $c3 = Process::path($this->basePath)->run([$phpBin, 'artisan', 'view:clear']);

        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }

        return [
            'success' => true,
            'output' => trim($c1->output() . "\n" . $c2->output() . "\n" . $c3->output()),
        ];
    }

    /**
     * Put application into or out of maintenance mode.
     */
    public function toggleMaintenance(bool $enable): array
    {
        $phpBin = $this->getPhpBinary();
        $cmd = $enable
            ? [$phpBin, 'artisan', 'down', '--retry=60']
            : [$phpBin, 'artisan', 'up'];

        $res = Process::path($this->basePath)->run($cmd);

        return [
            'success' => $res->successful(),
            'message' => trim($res->output()),
            'in_maintenance' => $enable,
        ];
    }
}

