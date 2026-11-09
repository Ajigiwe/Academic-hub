<?php

namespace App\Console\Commands;

use App\Services\Deployment\DeployEngine;
use Illuminate\Console\Command;

class DeployRunCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'deploy:run 
                            {--branch= : Target branch to deploy (default from config)}
                            {--quick : Skip composer and migrations}
                            {--skip-migrate : Skip database migrations}
                            {--no-composer : Skip composer install}
                            {--maintenance : Put site in maintenance mode during deployment}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute the deployment pipeline to pull from Git and refresh production';

    /**
     * Execute the console command.
     */
    public function handle(DeployEngine $engine): int
    {
        $this->info('Starting Academic Hub Deployment...');

        $options = [
            'branch' => $this->option('branch') ?: config('deploy.branch', 'main'),
            'composer' => !$this->option('quick') && !$this->option('no-composer'),
            'migrate' => !$this->option('quick') && !$this->option('skip-migrate'),
            'maintenance' => (bool) $this->option('maintenance'),
        ];

        try {
            $deployment = $engine->run(
                options: $options,
                trigger: 'cli',
                initiatedBy: 'CLI Console'
            );

            $this->line($deployment->output);

            if ($deployment->isSuccess()) {
                $this->info("✓ Deployment #{$deployment->id} completed successfully in {$deployment->duration_seconds}s.");
                return Command::SUCCESS;
            } else {
                $this->error("✗ Deployment #{$deployment->id} failed in {$deployment->duration_seconds}s.");
                return Command::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('Deployment error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

