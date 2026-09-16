<?php

namespace App\Http\Middleware;

use App\Services\Payments\PaymentManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the sandbox gateway's browser routes (`/payment/mock-checkout`,
 * the approve/fail/cancel endpoint). The mock can mark an order PAID with
 * no money behind it, so when it is not available — no webhook secret, or
 * a live environment that did not explicitly select it — these URLs must
 * not exist at all rather than merely being unadvertised.
 */
class SandboxGatewayOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(PaymentManager::class)->sandboxAvailable()) {
            abort(404);
        }

        return $next($request);
    }
}
