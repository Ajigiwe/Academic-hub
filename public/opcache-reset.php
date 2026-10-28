<?php

/**
 * OPcache reset for shared cPanel hosts without SSH.
 *
 * Usage:  https://yourdomain.com/opcache-reset.php?token=CHANGE_ME
 *
 * 1. Change TOKEN below to a long random string.
 * 2. Hit the URL once after every deployment.
 * 3. Delete this file (or leave it — it is useless without the token).
 *
 * Requires opcache.enable (the common default on shared hosts).
 */

const TOKEN = 'CHANGE_ME';

if (!hash_equals(TOKEN, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden');
}

$reset = function_exists('opcache_reset');
if ($reset) {
    opcache_reset();
    if (function_exists('opcache_invalidate')) {
        // Also invalidate the front controller copy that lives outside
        // this directory on Option-B layouts (public_html/index.php).
        opcache_invalidate(__DIR__ . '/index.php', true);
    }
}

header('Content-Type: text/plain');
echo $reset
    ? "OPcache reset (" . date('c') . ")\n"
    : "opcache_reset() unavailable on this host — ask the host to restart PHP.\n";
