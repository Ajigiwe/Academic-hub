<?php

return [
    // Signed document URLs live this long. The reader re-mints a fresh
    // URL on each page load via a reload of /view/{slug}.
    'url_ttl_seconds' => (int) env('VIEWER_URL_TTL_SECONDS', 600),
];
