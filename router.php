<?php

/**
 * Router for PHP's built-in dev server (php -S host:port -t public router.php).
 * Serves real files under public/ (icon.svg, future images) directly and
 * hands everything else to Laravel's front controller. Without this the
 * built-in server routes every path through Laravel, 404ing static assets.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$file = __DIR__.'\public'.$path;

if ($path !== '/' && is_file($file)) {
    return false; // let the built-in server stream the real file
}

require __DIR__.'\public\index.php';
