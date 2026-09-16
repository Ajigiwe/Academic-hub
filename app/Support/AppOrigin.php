<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The origin (scheme + host) a student must RETURN to after paying.
 *
 * It has to be the host they started on — not blindly APP_URL. The
 * session cookie is host-scoped (SESSION_DOMAIN is null), so bouncing a
 * student to a different hostname after checkout loses their session:
 * /payments/verify then redirects to the login page and a completed
 * payment looks lost until they sign in again.
 *
 * The request's own origin is used when it is trusted:
 *
 *  - the configured APP_URL host (the canonical public origin),
 *  - any host listed in PAYMENT_TRUSTED_HOSTS (a tunnel, an apex domain),
 *  - outside production, the loopback names — one dev box is reachable
 *    as both `localhost` and `127.0.0.1`, and those are different cookie
 *    hosts even though they are the same server.
 *
 * Anything else falls back to APP_URL, so a spoofed Host header can never
 * send a student (or a payment reference) to a host we do not control.
 *
 * Webhooks are NOT built from this: they are server-to-server, so they
 * keep the canonical APP_URL (see PaymentManager::status()).
 */
class AppOrigin
{
    public static function for(Request $request): string
    {
        return self::isTrusted($request->getHost())
            ? $request->getSchemeAndHttpHost()
            : self::configured();
    }

    /** The canonical origin from configuration, with no trailing slash. */
    public static function configured(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    public static function isTrusted(string $host): bool
    {
        $host = strtolower(trim($host));
        if ($host === '') {
            return false;
        }

        $trusted = array_map(
            fn ($value) => strtolower(trim((string) $value)),
            config('payments.trusted_hosts', []),
        );

        $configuredHost = parse_url(self::configured(), PHP_URL_HOST);
        if (is_string($configuredHost) && $configuredHost !== '') {
            $trusted[] = strtolower($configuredHost);
        }

        if (! app()->isProduction()) {
            $trusted = array_merge($trusted, ['localhost', '127.0.0.1', '::1', '[::1]']);
        }

        return in_array($host, $trusted, true);
    }
}
