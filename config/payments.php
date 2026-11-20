<?php

use App\Services\Payments\MockProvider;

return [
    // "mock" = sandbox gateway, completes instantly, no real money.
    // "moolre" = Moolre gateway (Ghana mobile money & card).
    // "paystack" = Paystack gateway (Ghana card & mobile money).
    //
    // Selecting a driver that is not configured falls back to the sandbox OUTSIDE production;
    // in production checkout fails rather than taking orders it cannot
    // charge for. See PaymentManager::provider().
    'provider' => env('PAYMENT_PROVIDER', 'moolre'),

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

    'moolre' => [
        'public_key' => env('MOOLRE_PUBLIC_KEY', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJ1c2VyaWQiOjExMTUxNywid2FsbGV0aWQiOiI3ODU5NiIsImV4cCI6MTk1NjUyNzk5OX0.XoebPda86h4yplp426-G_xDThIDT74d8ruNGDCJHzlM'),
        'private_key' => env('MOOLRE_PRIVATE_KEY', 'nx5mGtSLJ4iXRlRqoW3rTqG7C4sRqSUq3nJU9O5ppCiHo5b1rD8WDEOtawlqTGmt'),
        'username' => env('MOOLRE_USERNAME', 'amgacademichub'),
        'account_number' => env('MOOLRE_ACCOUNT_NUMBER', '78596'),
        'base_url' => env('MOOLRE_BASE_URL', 'https://api.moolre.com'),
    ],

    'paystack' => [
        // Secret key from the Paystack dashboard (sk_test_… / sk_live_…).
        // Doubles as the HMAC-SHA512 key for Paystack webhook signatures.
        'secret_key' => env('PAYSTACK_SECRET_KEY'),

        // Overridable so tests can point at a stub API.
        'base_url' => env('PAYSTACK_BASE_URL', 'https://api.paystack.co'),
    ],
];
