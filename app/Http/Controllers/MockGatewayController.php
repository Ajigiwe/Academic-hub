<?php

namespace App\Http\Controllers;

use App\Services\Payments\WebhookSigner;
use App\Support\AppOrigin;
use Illuminate\Http\Request;

/**
 * Mock gateway "server". Simulates what a real provider does: receives
 * the student's pay/cancel action, sends a SIGNED webhook, then hands
 * back the redirect for the student's browser.
 *
 * Delivery detail: the webhook is dispatched IN-PROCESS as a synthesized
 * request straight into the real webhook controller — signature
 * verification, event dedupe, and fulfillment all run identically. A
 * loopback HTTP call would deadlock on the single-worker Windows dev
 * server (php -S), and running the full kernel for the sub-request would
 * corrupt the caller's session (StartSession re-keys the shared store).
 * A real provider delivers over the network; the mock is replaced
 * entirely when one is configured — no product code depends on this.
 */
class MockGatewayController extends Controller
{
    public function __invoke(Request $request, WebhookSigner $signer)
    {
        $data = $request->validate([
            'ref' => ['required', 'string'],
            'outcome' => ['required', 'in:success,failure,cancel'],
        ]);

        $ref = $data['ref'];
        $outcome = $data['outcome'];
        // Back to the host the student's browser is on (their session
        // cookie lives there), not the canonical APP_URL.
        $appUrl = AppOrigin::for($request);

        if ($outcome === 'cancel') {
            return response()->json([
                'redirect_url' => $appUrl.'/payment/result?status=cancelled&ref='.urlencode($ref),
            ]);
        }

        $success = $outcome === 'success';
        $eventId = 'evt_mock_'.$ref.'_'.$outcome.'_'.now()->getTimestampMs();

        $payload = json_encode([
            'event' => $success ? 'payment.success' : 'payment.failed',
            'eventId' => $eventId,
            'reference' => $ref,
        ]);

        // Signed webhook — the webhook controller verifies the signature
        // over these exact raw bytes, dedupes on eventId, and fulfills.
        $subRequest = Request::create('/payments/webhook', 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_PASTQ_SIGNATURE' => $signer->sign($payload),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $subResponse = app()->call(app(PaymentWebhookController::class), ['request' => $subRequest]);
        if (! $subResponse->isSuccessful()) {
            logger()->error('Mock webhook delivery failed', ['status' => $subResponse->getStatusCode()]);
        }

        // Same callback shape as a real gateway: reference in the path.
        return response()->json([
            'redirect_url' => $success
                ? $appUrl.'/payments/verify/'.rawurlencode($ref)
                : $appUrl.'/payment/result?status=failed&ref='.urlencode($ref),
        ]);
    }
}
