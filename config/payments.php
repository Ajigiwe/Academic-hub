<?php

use App\Services\Payments\MockProvider;

return [
    // "mock" = sandbox gateway, completes instantly, no real money.
    // "paystack" = real gateway (Ghana card + mobile money).
    //
    // Selecting a driver that is not configured (paystack without
    // PAYSTACK_SECRET_KEY) falls back to the sandbox OUTSIDE production;
    // in production checkout fails rather than taking orders it cannot
    // charge for. See PaymentManager::provider().
    'provider' => env('PAYMENT_PROVIDER', 'mock'),

    // HMAC secret for the mock gateway's webhooks (HMAC-SHA256). The dev
    // default keeps the sandbox usable outside production; a live
    // environment must set a real secret, otherwise the mock refuses
    // every delivery (and its routes 404) — that default is public, so a
    // forged payment.success could otherwise unlock content for free.
    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET', MockProvider::DEV_SECRET),

    // Extra hosts a student may return to after paying, on top of the
    // APP_URL host (comma-separated env list). Needed when the site is
    // reachable through more than one origin — an apex domain, a www
    // alias, a tunnel in front of a local dev server — because the return
    // URL must be the host that holds their session cookie.
    'trusted_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PAYMENT_TRUSTED_HOSTS', '')),
    ))),

    'paystack' => [
        // Secret key from the Paystack dashboard (sk_test_… / sk_live_…).
        // Doubles as the HMAC-SHA512 key for Paystack webhook signatures.
        'secret_key' => env('PAYSTACK_SECRET_KEY'),

        // Overridable so tests can point at a stub API.
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],
];
