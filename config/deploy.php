<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Git Branch & Remote
    |--------------------------------------------------------------------------
    | Default Git branch and remote to monitor and pull from.
    */
    'branch' => env('DEPLOY_BRANCH', 'main'),
    'remote' => env('DEPLOY_REMOTE', 'origin'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    | Shared secret used to verify incoming push webhooks from GitHub
    | (via X-Hub-Signature-256 HMAC-SHA256) or manual curl calls
    | with header X-Deploy-Token or query parameter ?token=.
    */
    'webhook_secret' => env('DEPLOY_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Target Public Path (for Option B shared hosting layout)
    |--------------------------------------------------------------------------
    | If your repository is outside public_html (e.g. /home/cpuser/repositories/Academic-hub)
    | and your site's webroot is /home/cpuser/public_html, set this to the absolute
    | path of public_html. The deploy engine will copy public assets there and
    | rewire index.php to load from the app repository.
    */
    'public_path' => env('DEPLOY_PUBLIC_PATH', null),

    /*
    |--------------------------------------------------------------------------
    | PHP & Composer Binaries
    |--------------------------------------------------------------------------
    | Paths to binaries. If left null, DeployEngine will auto-detect them.
    */
    'php_binary' => env('DEPLOY_PHP_BINARY', null),
    'composer_binary' => env('DEPLOY_COMPOSER_BINARY', null),

    /*
    |--------------------------------------------------------------------------
    | Deployment Defaults
    |--------------------------------------------------------------------------
    */
    'maintenance' => (bool) env('DEPLOY_MAINTENANCE', false),
    'run_migrations' => (bool) env('DEPLOY_RUN_MIGRATIONS', true),
    'run_composer' => (bool) env('DEPLOY_RUN_COMPOSER', true),
    'cache_config' => (bool) env('DEPLOY_CACHE_CONFIG', true),
    'cache_routes' => (bool) env('DEPLOY_CACHE_ROUTES', true),
    'cache_views' => (bool) env('DEPLOY_CACHE_VIEWS', true),
    'timeout' => (int) env('DEPLOY_TIMEOUT', 300), // in seconds
];

