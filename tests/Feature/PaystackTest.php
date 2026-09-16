<?php

namespace Tests\Feature;

use App\Models\{Entitlement, Notification, Order, Payment, WebhookEvent};
use App\Services\Payments\PaystackProvider;
use App\Services\Payments\PaystackRequestException;
use App\Services\Payments\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Slice 6: the Paystack provider. The HTTP API is stubbed (an anonymous
 * subclass overriding the protected request() method, container-bound so
 * PaymentManager receives it), but webhook signature verification is
 * fully real: HMAC-SHA512 over the raw body with the configured secret,
 * timing-safe compare — the exact security boundary a real gateway
 * exercises. Dedupe and fulfillment are the SHARED production chain.
 */
class PaystackTest extends TestCase
{
    use RefreshDatabase;

    private object $paystack; // anonymous PaystackProvider subclass

    private string $secret = 'sk_test_webhook_secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PastqSeeder::class);

        config(['payments.paystack.secret_key' => $this->secret]);

        $this->paystack = new class extends PaystackProvider
        {
            /** @var array<string, array<string, mixed>> keyed by HTTP method */
            public array $responses = [];

            /** @var array<string, array{0: int, 1: string}> HTTP failures keyed by method */
            public array $errorResponses = [];

            /** @var list<array{method: string, path: string, payload: array}> */
            public array $calls = [];

            protected function request(string $method, string $path, array $payload = []): array
            {
                $this->calls[] = ['method' => $method, 'path' => $path, 'payload' => $payload];

                if (array_key_exists($method, $this->errorResponses)) {
                    [$status, $message] = $this->errorResponses[$method];

                    // Exactly what the real provider throws for a non-2xx.
                    throw new PaystackRequestException($status, $path, "Paystack {$path} failed: {$message}");
                }

                if (array_key_exists($method, $this->responses)) {
                    return $this->responses[$method];
                }

                throw new \RuntimeException("No stubbed Paystack response for {$method} {$path}");
            }
        };
        $this->app->instance(PaystackProvider::class, $this->paystack);
    }

    // ── Checkout: initialize ──────────────────────────────────────────

    public function test_initialize_posts_the_expected_payload(): void
    {
        $bundle = \App\Models\Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();
        $student = \App\Models\User::where('email', 'student@pastq.test')->first();

        $this->paystack->responses['POST'] = [
            'status' => true,
            'message' => 'Authorization URL created',
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/abc123',
                'access_code' => 'abc123',
                'reference' => 'PQ-INIT01',
            ],
        ];

        $order = $this->pendingOrder('PQ-INIT01', $student, $bundle);
        config(['app.url' => 'http://127.0.0.1:8080']);

        $provider = app(PaymentManager::class)->provider('paystack');
        $result = $provider->initiatePayment(
            $order->reference,
            1500,
            'GHS',
            $student->email,
            'http://127.0.0.1:8080/payments/verify?ref=PQ-INIT01',
            'http://127.0.0.1:8080',
        );

        $this->assertSame('https://checkout.paystack.com/abc123', $result['redirect_url']);
        $this->assertSame('PQ-INIT01', $result['provider_ref']);

        [$call] = $this->paystack->calls;
        $this->assertSame('POST', $call['method']);
        $this->assertSame('/transaction/initialize', $call['path']);
        $this->assertSame($student->email, $call['payload']['email']);
        $this->assertSame(1500, $call['payload']['amount']);
        $this->assertSame('GHS', $call['payload']['currency']);
        $this->assertSame($order->reference, $call['payload']['reference']);
        $this->assertSame(['mobile_money', 'card'], $call['payload']['channels']);
        $this->assertStringContainsString('/payments/verify?ref=', $call['payload']['callback_url']);
    }

    public function test_initialize_rejects_a_rejected_payment(): void
    {
        $this->paystack->responses['POST'] = [
            'status' => false,
            'message' => 'Invalid reference',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid reference');

        app(PaymentManager::class)->provider('paystack')->initiatePayment(
            'PQ-INIT02', 1500, 'GHS', 's@t.test', 'http://x/payments/verify', 'http://x',
        );
    }

    // ── Verify: the server-to-server check ────────────────────────────

    public function test_verify_maps_a_successful_payment(): void
    {
        $this->paystack->responses['GET'] = [
            'status' => true,
            'data' => ['status' => 'success', 'reference' => 'PQ-VER01', 'amount' => 2500, 'currency' => 'GHS', 'channel' => 'card'],
        ];

        $verified = app(PaymentManager::class)->provider('paystack')->verifyPayment('PQ-VER01');

        $this->assertTrue($verified['successful']);
        $this->assertSame(2500, $verified['amount_pesewas']);
        $this->assertSame('CARD', $verified['channel']);
        $this->assertSame('PQ-VER01', $verified['provider_ref']);

        // The verify call hit the real verify endpoint path.
        $this->assertSame('/transaction/verify/PQ-VER01', $this->paystack->calls[0]['path']);
    }

    public function test_verify_maps_a_failed_payment(): void
    {
        $this->paystack->responses['GET'] = [
            'status' => true,
            'data' => ['status' => 'failed', 'reference' => 'PQ-VER02', 'amount' => 0, 'currency' => 'GHS', 'channel' => 'mobile_money'],
        ];

        $verified = app(PaymentManager::class)->provider('paystack')->verifyPayment('PQ-VER02');

        $this->assertFalse($verified['successful']);
        $this->assertSame('Paystack did not report a successful payment.', $verified['failure_reason']);
    }

    public function test_verify_treats_an_unknown_reference_as_a_clean_failure(): void
    {
        // Paystack answers `400 Transaction reference not found` for a
        // reference it has never seen — the exact status and message the
        // live API returned when probed. That is an answer, not an error,
        // and it must not keep a webhook retrying for days. (404 handled
        // too, in case they ever normalise it.)
        foreach ([400, 404] as $status) {
            $this->paystack->errorResponses['GET'] = [$status, 'Transaction reference not found'];

            $verified = app(PaymentManager::class)->provider('paystack')->verifyPayment('PQ-GHOST1');

            $this->assertFalse($verified['successful']);
            $this->assertFalse($verified['pending']);
            $this->assertSame('Paystack has no record of that payment reference.', $verified['failure_reason']);
        }
    }

    public function test_verify_reports_mobile_money_still_in_flight_as_pending(): void
    {
        // Mobile money is asynchronous: the student authorises, the
        // operator confirms later. "ongoing" is neither success nor
        // failure and must never be treated as a failed payment.
        $this->paystack->responses['GET'] = [
            'status' => true,
            'data' => ['status' => 'ongoing', 'reference' => 'PQ-ONGOIN', 'amount' => 1500, 'currency' => 'GHS', 'channel' => 'mobile_money'],
        ];

        $verified = app(PaymentManager::class)->provider('paystack')->verifyPayment('PQ-ONGOIN');

        $this->assertFalse($verified['successful']);
        $this->assertTrue($verified['pending']);
        $this->assertSame('Paystack is still confirming this payment.', $verified['failure_reason']);
    }

    public function test_verify_keeps_server_errors_as_errors_so_deliveries_retry(): void
    {
        $this->paystack->errorResponses['GET'] = [503, 'Service unavailable'];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Service unavailable');

        app(PaymentManager::class)->provider('paystack')->verifyPayment('PQ-FLAKY1');
    }

    // ── Webhook: HMAC-SHA512 over the raw body ────────────────────────

    public function test_charge_success_webhook_fulfills_the_order(): void
    {
        $bundle = \App\Models\Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();
        $student = \App\Models\User::where('email', 'student@pastq.test')->first();
        $order = $this->pendingOrder('PQ-WEB001', $student, $bundle);

        // What the gateway's verify API will answer when the processor
        // re-checks server-side (must match the order, or fulfillment
        // aborts via the tamper check).
        $this->paystack->responses['GET'] = [
            'status' => true,
            'data' => ['status' => 'success', 'reference' => $order->reference, 'amount' => 1500, 'currency' => 'GHS', 'channel' => 'mobile_money'],
        ];

        $body = json_encode([
            'event' => 'charge.success',
            'data' => ['id' => 3029612841, 'reference' => $order->reference, 'status' => 'success', 'amount' => 1500, 'currency' => 'GHS', 'channel' => 'mobile_money'],
        ]);

        $response = $this->postWebhook($body, ['x-paystack-signature' => $this->sign($body)]);

        $response->assertOk()->assertJson(['ok' => true]);

        // Event persisted with the PAYSTACK event-id format, processed.
        $event = WebhookEvent::where('event_id', 'paystack:charge.success:3029612841')->sole();
        $this->assertSame('paystack', $event->provider);
        $this->assertNotNull($event->processed_at);

        // Order fulfilled, entitlements granted, notification created —
        // all via the SHARED production chain.
        $this->assertSame('PAID', $order->fresh()->status);
        $this->assertSame(2, Entitlement::where('user_id', $student->id)->count());
        $this->assertSame(1, Notification::where('user_id', $student->id)->count());
    }

    public function test_invalid_signature_is_rejected_before_parsing(): void
    {
        $body = json_encode(['event' => 'charge.success', 'data' => ['id' => 1, 'reference' => 'PQ-BADSIG']]);

        // Right secret, wrong algorithm (mock's SHA-256) — must NOT pass.
        $this->postWebhook($body, ['x-paystack-signature' => hash_hmac('sha256', $body, $this->secret)])
            ->assertStatus(401);

        // Right algorithm, wrong secret.
        $this->postWebhook($body, ['x-paystack-signature' => hash_hmac('sha512', $body, 'wrong-secret')])
            ->assertStatus(401);

        $this->assertSame(0, WebhookEvent::count());
    }

    public function test_missing_signature_header_is_rejected(): void
    {
        $body = json_encode(['event' => 'charge.success', 'data' => ['id' => 2, 'reference' => 'PQ-NOSIG']]);

        $this->postWebhook($body, [])->assertStatus(401);
        $this->assertSame(0, WebhookEvent::count());
    }

    public function test_unconfigured_secret_rejects_everything(): void
    {
        config(['payments.paystack.secret_key' => null]);
        $body = json_encode(['event' => 'charge.success', 'data' => ['id' => 3, 'reference' => 'PQ-NOSEC']]);

        $this->postWebhook($body, ['x-paystack-signature' => hash_hmac('sha512', $body, $this->secret)])
            ->assertStatus(401);
    }

    public function test_missing_reference_is_rejected(): void
    {
        $body = json_encode(['event' => 'charge.success', 'data' => ['id' => 4]]);

        $this->postWebhook($body, ['x-paystack-signature' => $this->sign($body)])
            ->assertStatus(400);
        $this->assertSame(0, WebhookEvent::count());
    }

    public function test_duplicate_delivery_is_deduplicated(): void
    {
        $bundle = \App\Models\Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();
        $student = \App\Models\User::where('email', 'student@pastq.test')->first();
        $order = $this->pendingOrder('PQ-DEDUP1', $student, $bundle);

        $this->paystack->responses['GET'] = [
            'status' => true,
            'data' => ['status' => 'success', 'reference' => $order->reference, 'amount' => 1500, 'currency' => 'GHS', 'channel' => 'card'],
        ];

        $body = json_encode([
            'event' => 'charge.success',
            'data' => ['id' => 3029612999, 'reference' => $order->reference, 'amount' => 1500, 'currency' => 'GHS', 'channel' => 'card'],
        ]);
        $headers = ['x-paystack-signature' => $this->sign($body)];

        $this->postWebhook($body, $headers)->assertOk()->assertJson(['ok' => true]);
        // Same event id, delivered again (Paystack retries) — ACKed
        // deduplicated, NOT reprocessed.
        $this->postWebhook($body, $headers)->assertOk()->assertJson(['ok' => true, 'deduplicated' => true]);

        $this->assertSame(1, WebhookEvent::count());
        $this->assertSame('PAID', $order->fresh()->status);
        // Fulfillment ran exactly once: 2 papers → 2 entitlements, no doubles.
        $this->assertSame(2, Entitlement::where('user_id', $student->id)->count());
        $this->assertSame(1, Notification::where('user_id', $student->id)->count());
    }

    public function test_non_success_events_are_ignored(): void
    {
        $body = json_encode(['event' => 'charge.failed', 'data' => ['id' => 3029612777, 'reference' => 'PQ-FAILED']]);

        $this->postWebhook($body, ['x-paystack-signature' => $this->sign($body)])
            ->assertOk()
            ->assertJson(['ok' => true, 'ignored' => true]);

        $event = WebhookEvent::sole();
        $this->assertSame('charge.failed', $event->type);
        $this->assertNotNull($event->processed_at); // marked, not left for retry
        $this->assertSame('PENDING', Order::where('reference', 'PQ-FAILED')->value('status') ?? 'PENDING');
    }

    public function test_tampered_amount_leaves_the_event_for_retry(): void
    {
        $bundle = \App\Models\Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();
        $student = \App\Models\User::where('email', 'student@pastq.test')->first();
        $order = $this->pendingOrder('PQ-TAMPR1', $student, $bundle);

        // The gateway's verify API reports a DIFFERENT amount than the
        // order — the tamper check must abort fulfillment.
        $this->paystack->responses['GET'] = [
            'status' => true,
            'data' => ['status' => 'success', 'reference' => $order->reference, 'amount' => 1, 'currency' => 'GHS', 'channel' => 'card'],
        ];

        $body = json_encode([
            'event' => 'charge.success',
            'data' => ['id' => 3029612555, 'reference' => $order->reference, 'amount' => 1, 'currency' => 'GHS', 'channel' => 'card'],
        ]);

        $this->postWebhook($body, ['x-paystack-signature' => $this->sign($body)])
            ->assertStatus(500)
            ->assertJson(['error' => 'Processing failed; will retry.']);

        // processed_at stays null so Paystack can retry after a fix.
        $event = WebhookEvent::sole();
        $this->assertNull($event->processed_at);

        // Order untouched, payment marked FAILED.
        $this->assertSame('PENDING', $order->fresh()->status);
        $this->assertSame('FAILED', Payment::where('order_id', $order->id)->value('status'));
        $this->assertSame(0, Entitlement::where('user_id', $student->id)->count());
    }

    public function test_processing_failure_leaves_the_event_for_retry(): void
    {
        $bundle = \App\Models\Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();
        $student = \App\Models\User::where('email', 'student@pastq.test')->first();
        $order = $this->pendingOrder('PQ-RETRY1', $student, $bundle);

        // Verify API answers failure → processor throws → 500, retry later.
        $this->paystack->responses['GET'] = [
            'status' => true,
            'data' => ['status' => 'failed', 'reference' => $order->reference],
        ];

        $body = json_encode([
            'event' => 'charge.success',
            'data' => ['id' => 3029612333, 'reference' => $order->reference, 'amount' => 1500, 'currency' => 'GHS'],
        ]);

        $this->postWebhook($body, ['x-paystack-signature' => $this->sign($body)])
            ->assertStatus(500);

        $this->assertNull(WebhookEvent::sole()->processed_at);
        $this->assertSame('PENDING', $order->fresh()->status);
    }

    public function test_checkout_explains_a_rejected_account_email(): void
    {
        // Paystack refuses reserved/fake domains (`…@…test`) in live mode.
        // "Could not start the payment" would have the student retrying
        // forever — say what they can fix.
        $student = \App\Models\User::where('email', 'student@pastq.test')->first();
        $bundle = \App\Models\Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();
        config(['payments.provider' => 'paystack']);
        $this->paystack->errorResponses['POST'] = [400, 'Invalid Email Address Passed'];

        $response = $this->actingAs($student)->post('/checkout', ['bundle_id' => $bundle->id]);

        $response->assertSessionHasErrors(['bundle' => 'The payment provider rejected your account email address. Update it in your profile to a real, working inbox and try again.']);
        $this->assertSame('CANCELLED', Order::where('user_id', $student->id)->sole()->status);
    }

    // ── Refunds ───────────────────────────────────────────────────────

    public function test_a_refund_delivery_is_normalised_from_the_event_name(): void
    {
        // Paystack's refund payload carries the sale under `transaction`
        // and often reports no `status` field — the event name decides, and
        // a bare transaction id is kept for later resolution.
        $delivery = app(PaymentManager::class)->provider('paystack')->refundFrom([
            'event' => 'refund.processed',
            'data' => ['id' => 3018284, 'amount' => 1500, 'currency' => 'GHS', 'transaction' => 1004723697],
        ]);

        $this->assertSame('3018284', $delivery['refund_id']);
        $this->assertSame('PROCESSED', $delivery['status']);
        $this->assertSame(1500, $delivery['amount_pesewas']);
        $this->assertSame('1004723697', $delivery['transaction_id']);
        $this->assertNull($delivery['reference']);

        // The sale reference is picked out when the delivery nests it.
        $nested = app(PaymentManager::class)->provider('paystack')->refundFrom([
            'event' => 'refund.pending',
            'data' => ['id' => 7, 'transaction' => ['id' => 1, 'reference' => 'PQ-NESTED']],
        ]);

        $this->assertSame('PQ-NESTED', $nested['reference']);
        $this->assertSame('PENDING', $nested['status']);

        // Nothing to act on without a refund id.
        $this->assertNull(app(PaymentManager::class)->provider('paystack')->refundFrom([
            'event' => 'refund.processed',
            'data' => ['amount' => 1500],
        ]));
    }

    public function test_verify_refund_reads_the_gateway_and_chases_a_bare_transaction_id(): void
    {
        $this->paystack->responses['GET'] = [
            'status' => true,
            'data' => ['id' => 3018284, 'amount' => 1500, 'currency' => 'GHS', 'status' => 'processed', 'transaction' => 1004723697],
        ];

        $snapshot = app(PaymentManager::class)->provider('paystack')->verifyRefund('3018284');

        // The refund endpoint answers first — verification never trusts the
        // webhook body…
        $this->assertSame('/refund/3018284', $this->paystack->calls[0]['path']);
        $this->assertSame('PROCESSED', $snapshot['status']);
        $this->assertSame(1500, $snapshot['amount_pesewas']);
        $this->assertSame('1004723697', $snapshot['transaction_id']);

        // …and a bare transaction id is chased down for a reference
        // (Paystack's /transaction/:id accepts an id or a reference).
        $this->assertSame('/transaction/1004723697', $this->paystack->calls[1]['path']);
    }

    // ── Money that is still in flight (mobile money) ──────────────────

    public function test_pending_callback_reassures_instead_of_failing_the_payment(): void
    {
        $bundle = \App\Models\Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();
        $student = \App\Models\User::where('email', 'student@pastq.test')->first();
        $order = $this->pendingOrder('PQ-WAIT01', $student, $bundle);
        config(['payments.provider' => 'paystack']); // the callback verifies with the configured driver

        // The student's mobile-money authorisation has not settled yet.
        $this->paystack->responses['GET'] = [
            'status' => true,
            'data' => ['status' => 'ongoing', 'reference' => $order->reference, 'amount' => 1500, 'currency' => 'GHS', 'channel' => 'mobile_money'],
        ];

        $response = $this->actingAs($student)->get('/payments/verify/'.$order->reference);

        $response->assertRedirect();
        $this->assertStringContainsString('status=pending', $response->headers->get('Location'));

        // Nothing failed, nothing was released, the payment stays open for
        // the webhook to settle.
        $this->assertSame('PENDING', $order->fresh()->status);
        $this->assertSame('PENDING', Payment::where('order_id', $order->id)->value('status'));
        $this->assertSame(0, Entitlement::where('user_id', $student->id)->count());
    }

    public function test_pending_webhook_delivery_is_left_for_retry(): void
    {
        $bundle = \App\Models\Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();
        $student = \App\Models\User::where('email', 'student@pastq.test')->first();
        $order = $this->pendingOrder('PQ-WAIT02', $student, $bundle);

        $this->paystack->responses['GET'] = [
            'status' => true,
            'data' => ['status' => 'processing', 'reference' => $order->reference, 'amount' => 1500, 'currency' => 'GHS', 'channel' => 'mobile_money'],
        ];

        $body = json_encode([
            'event' => 'charge.success',
            'data' => ['id' => 3029612111, 'reference' => $order->reference, 'amount' => 1500, 'currency' => 'GHS'],
        ]);

        $this->postWebhook($body, ['x-paystack-signature' => $this->sign($body)])
            ->assertStatus(500)
            ->assertJson(['error' => 'Awaiting gateway confirmation; will retry.']);

        $this->assertNull(WebhookEvent::sole()->processed_at);
        $this->assertSame('PENDING', $order->fresh()->status);
    }

    public function test_a_late_success_recovers_a_payment_an_earlier_callback_marked_failed(): void
    {
        $bundle = \App\Models\Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();
        $student = \App\Models\User::where('email', 'student@pastq.test')->first();
        $order = $this->pendingOrder('PQ-LATE01', $student, $bundle);
        config(['payments.provider' => 'paystack']); // the callback verifies with the configured driver

        // An earlier browser return gave up on it…
        Payment::where('order_id', $order->id)->update([
            'status' => 'FAILED',
            'failure_reason' => 'Paystack did not report a successful payment.',
        ]);

        // …and the money landed afterwards.
        $this->paystack->responses['GET'] = [
            'status' => true,
            'data' => ['status' => 'success', 'reference' => $order->reference, 'amount' => 1500, 'currency' => 'GHS', 'channel' => 'mobile_money'],
        ];

        $this->actingAs($student)->get('/payments/verify/'.$order->reference)->assertRedirect();

        $this->assertSame('PAID', $order->fresh()->status);
        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame('SUCCESSFUL', $payment->status);
        $this->assertNull($payment->failure_reason);
        $this->assertNotNull($payment->verified_at);
        $this->assertSame($bundle->resources()->published()->count(), Entitlement::where('user_id', $student->id)->count());
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /** HMAC-SHA512 with the configured secret — what Paystack sends. */
    private function sign(string $rawBody): string
    {
        return hash_hmac('sha512', $rawBody, $this->secret);
    }

    /** POST the exact signed bytes (postJson would re-encode the body). */
    private function postWebhook(string $rawBody, array $headers)
    {
        return $this->call(
            'POST',
            '/payments/webhook',
            [],
            [],
            [],
            $this->transformHeadersToServerVars(array_merge([
                'Content-Type' => 'application/json',
            ], $headers)),
            $rawBody,
        );
    }

    private function pendingOrder(string $reference, $user, $bundle): Order
    {
        $order = Order::create([
            'reference' => $reference,
            'user_id' => $user->id,
            'status' => 'PENDING',
            'amount_pesewas' => $bundle->price_pesewas,
            'currency' => 'GHS',
        ]);
        $order->items()->create([
            'bundle_id' => $bundle->id,
            'unit_price_pesewas' => $bundle->price_pesewas,
            'quantity' => 1,
        ]);
        Payment::create([
            'order_id' => $order->id,
            'provider' => 'paystack',
            'reference' => $reference,
            'provider_ref' => $reference,
            'channel' => 'MOBILE_MONEY',
            'amount_pesewas' => $bundle->price_pesewas,
            'currency' => 'GHS',
        ]);

        return $order;
    }
}
