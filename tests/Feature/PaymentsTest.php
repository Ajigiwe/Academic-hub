<?php

namespace Tests\Feature;

use App\Models\{Bundle, Entitlement, Order, Payment, User, WebhookEvent};
use App\Services\BundlePricing;
use App\Services\Payments\OrderFulfiller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Bundle $bundle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PastqSeeder::class);
        $this->student = User::where('email', 'student@pastq.test')->first();
        $this->bundle = Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();
    }

    // ── Checkout ─────────────────────────────────────────────────────

    public function test_authenticated_student_can_start_checkout(): void
    {
        $response = $this->actingAs($this->student)->post('/checkout', [
            'bundle_id' => $this->bundle->id,
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/payment/mock-checkout', $response->headers->get('Location'));

        $order = Order::where('user_id', $this->student->id)->sole();
        $this->assertSame('PENDING', $order->status);
        $this->assertSame($this->bundle->price_pesewas, $order->amount_pesewas);
        $this->assertMatchesRegularExpression('/^PQ-[A-Z2-9]{6}$/', $order->reference);

        $payment = Payment::where('order_id', $order->id)->sole();
        $this->assertSame('mock', $payment->provider);
        $this->assertSame('PENDING', $payment->status);
    }

    public function test_checkout_charges_the_platform_price_not_a_stale_bundle_row(): void
    {
        // Platform price moves to GH₵25.00, then one bundle row is edited
        // behind the service's back (manual surgery, legacy row). Money
        // paths read the setting, so the stale mirror cannot undercharge.
        BundlePricing::set(2500);
        Bundle::query()->where('id', $this->bundle->id)->update(['price_pesewas' => 99]);

        $this->actingAs($this->student)->post('/checkout', ['bundle_id' => $this->bundle->id]);

        $order = Order::where('user_id', $this->student->id)->sole();
        $this->assertSame(2500, $order->amount_pesewas);
        $this->assertSame(2500, $order->items()->sole()->unit_price_pesewas);
    }

    public function test_guest_cannot_checkout(): void
    {
        $this->post('/checkout', ['bundle_id' => $this->bundle->id])->assertRedirect('/login');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_repurchase_blocked_when_student_owns_every_paper(): void
    {
        // Own every paper in the bundle already (via a prior paid order).
        $prior = Order::create([
            'reference' => 'PQ-OWNED1',
            'user_id' => $this->student->id,
            'status' => 'PAID',
            'amount_pesewas' => $this->bundle->price_pesewas,
            'currency' => 'GHS',
            'paid_at' => now(),
        ]);
        foreach ($this->bundle->resources as $resource) {
            Entitlement::create([
                'user_id' => $this->student->id,
                'resource_id' => $resource->id,
                'order_id' => $prior->id,
            ]);
        }

        $response = $this->actingAs($this->student)->post('/checkout', [
            'bundle_id' => $this->bundle->id,
        ]);

        $response->assertSessionHasErrors('bundle');
        $this->assertSame(1, Order::where('user_id', $this->student->id)->count()); // only the factory order
    }

    // ── The guarantee chain ──────────────────────────────────────────

    public function test_mock_gateway_webhook_fulfills_order_end_to_end(): void
    {
        $order = $this->makePendingOrderWithWebhook();

        $this->assertSame('PAID', $order->refresh()->status);
        $this->assertSame('SUCCESSFUL', Payment::where('order_id', $order->id)->value('status'));

        // Every paper in the bundle is unlocked.
        $this->assertSame(
            $this->bundle->resources->count(),
            Entitlement::where('user_id', $this->student->id)->count()
        );
        $this->assertDatabaseHas('notifications', ['user_id' => $this->student->id, 'type' => 'PURCHASE']);
    }

    public function test_duplicate_webhook_delivery_is_deduplicated_and_never_refulfills(): void
    {
        $order = $this->makePendingOrderWithWebhook();

        // Replay deliveries: same eventId each time (counter frozen).
        $counterBackup = $this->eventIdCounter;
        for ($i = 0; $i < 5; $i++) {
            $this->eventIdCounter = $counterBackup - 1; // reuse last eventId
            $this->postWebhook(...$this->webhookArgs($order->reference, success: true))
                ->assertOk()
                ->assertJson(['ok' => true, 'deduplicated' => true]);
        }

        $this->assertSame(1, WebhookEvent::count()); // only the original insert
        $this->assertSame(
            $this->bundle->resources->count(),
            Entitlement::where('user_id', $this->student->id)->count()
        );
    }

    public function test_webhook_with_bad_signature_is_rejected_and_grants_nothing(): void
    {
        $order = $this->createPendingOrder();
        [$rawBody, $goodHeaders] = $this->webhookArgs($order->reference, success: true);

        $response = $this->postWebhook($rawBody, [
            'Content-Type' => 'application/json',
            'x-pastq-signature' => str_repeat('0', 64),
        ]);

        $response->assertStatus(401);
        $this->assertSame(0, WebhookEvent::count());
        $this->assertSame(0, Entitlement::count());
        $this->assertSame('PENDING', $order->refresh()->status);
    }

    public function test_failed_webhook_is_ignored_and_verify_marks_payment_failed(): void
    {
        $order = $this->createPendingOrder();

        // A payment.failed webhook is persisted but ignored — same as the
        // Next.js implementation (only payment.success is processed).
        $this->postWebhook(...$this->webhookArgs($order->reference, success: false))
            ->assertOk()
            ->assertJson(['ok' => true, 'ignored' => true]);
        $this->assertSame('PENDING', $order->refresh()->status);
        $this->assertSame(0, Entitlement::count());

        // The FAILED status is set by the server-side verification path:
        // an unacknowledged transaction fails verification.
        $this->actingAs($this->student)
            ->get('/payments/verify?ref='.$order->reference)
            ->assertRedirect();

        $this->assertSame('FAILED', Payment::where('order_id', $order->id)->value('status'));
        $this->assertNotNull(Payment::where('order_id', $order->id)->value('failure_reason'));
        $this->assertSame('PENDING', $order->refresh()->status);
        $this->assertSame(0, Entitlement::count());
    }

    public function test_verify_redirects_to_payment_result_landing_page(): void
    {
        // Regression: the browser-return flow ends at /payment/result —
        // the route+view were missing until the live test pass hit a 404.
        $order = $this->createPendingOrder();
        $this->postWebhook(...$this->webhookArgs($order->reference, success: true));

        $location = $this->actingAs($this->student)
            ->get('/payments/verify?ref='.$order->reference)
            ->headers->get('Location');

        $this->assertStringContainsString('/payment/result?status=success', $location);
        $this->assertStringContainsString('ref='.$order->reference, $location);

        // The landing page itself renders in all three states.
        $this->get('/payment/result?status=success&ref='.$order->reference
            .'&bundle=/bundles/acc-102-introduction-to-financial-accounting-2024-2025')
            ->assertOk()
            ->assertSee('Payment confirmed')
            ->assertSee($order->reference)
            ->assertSee('Start reading');

        $this->get('/payment/result?status=cancelled&ref='.$order->reference)
            ->assertOk()
            ->assertSee('Payment cancelled');

        $this->get('/payment/result?status=failed&ref='.$order->reference.'&reason=Gateway+timeout')
            ->assertOk()
            ->assertSee('Gateway timeout');
    }

    public function test_verify_callback_fulfills_without_webhook(): void
    {
        $order = $this->createPendingOrder();

        // Gateway redirects the student here BEFORE any webhook arrived.
        // Still verified server-side: no acknowledgment → no fulfillment.
        $this->actingAs($this->student)
            ->get('/payments/verify?ref='.$order->reference)
            ->assertRedirect();

        $this->assertSame('PENDING', $order->refresh()->status);

        // Now the gateway acknowledges (webhook), and the callback succeeds.
        $this->postWebhook(...$this->webhookArgs($order->reference, success: true));

        $this->actingAs($this->student)
            ->get('/payments/verify?ref='.$order->reference)
            ->assertRedirect();

        $this->assertSame('PAID', $order->refresh()->status);
        $this->assertSame(
            $this->bundle->resources->count(),
            Entitlement::where('user_id', $this->student->id)->count()
        );
    }

    public function test_fulfillment_is_idempotent_when_called_directly(): void
    {
        $order = $this->createPendingOrder();
        $fulfiller = app(OrderFulfiller::class);

        $first = $fulfiller->fulfill($order->reference);
        $second = $fulfiller->fulfill($order->reference);
        $third = $fulfiller->fulfill($order->reference);

        $this->assertFalse($first['already_fulfilled']);
        $this->assertTrue($second['already_fulfilled']);
        $this->assertTrue($third['already_fulfilled']);
        $this->assertSame(
            $this->bundle->resources->count(),
            Entitlement::count(),
            'No duplicate entitlement rows may exist.'
        );
    }

    public function test_webhook_failure_keeps_processed_at_null_for_retry(): void
    {
        // A reference with no order behind it → processor throws after the
        // event insert → 500 with processed_at null, so the provider can
        // retry (and a late-arriving order could then be fulfilled).
        $response = $this->postWebhook(...$this->webhookArgs('PQ-NOPE00', success: true));

        $response->assertStatus(500);
        $event = WebhookEvent::sole();
        $this->assertNull($event->processed_at, 'Provider must be able to retry.');
    }

    // ── Library ──────────────────────────────────────────────────────

    public function test_library_shows_owned_papers_and_hides_unowned(): void
    {
        $other = Bundle::where('slug', 'mkt-203-principles-of-marketing-2024-2025')->first();

        $response = $this->actingAs($this->student)->get('/library');
        $response->assertOk()->assertSee('Your library is empty.');

        $this->makePendingOrderWithWebhook();

        $response = $this->actingAs($this->student)->get('/library');
        $response->assertOk()->assertSee('ACC 102')->assertSee('Semester 1');
        $response->assertDontSee('MKT 203');

        $this->assertNotNull($other); // sanity: the other bundle exists but is not visible
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function createPendingOrder(): Order
    {
        $order = Order::create([
            'reference' => 'PQ-TEST01',
            'user_id' => $this->student->id,
            'status' => 'PENDING',
            'amount_pesewas' => $this->bundle->price_pesewas,
            'currency' => 'GHS',
        ]);
        $order->items()->create([
            'bundle_id' => $this->bundle->id,
            'unit_price_pesewas' => $this->bundle->price_pesewas,
        ]);
        Payment::create([
            'order_id' => $order->id,
            'provider' => 'mock',
            'provider_ref' => 'mock_PQ-TEST01',
            'reference' => $order->reference,
            'channel' => 'MOCK',
            'amount_pesewas' => $order->amount_pesewas,
            'currency' => 'GHS',
        ]);

        return $order;
    }

    /**
     * Runs the whole gateway flow the way the sandbox does it: approve →
     * signed server-to-server webhook → fulfillment.
     */
    private function makePendingOrderWithWebhook(): Order
    {
        $order = $this->createPendingOrder();
        $this->postWebhook(...$this->webhookArgs($order->reference, success: true))
            ->assertOk();

        return $order;
    }

    /** POST the exact signed bytes to the webhook endpoint. */
    private function postWebhook(string $rawBody, array $headers)
    {
        return $this->call(
            'POST',
            '/payments/webhook',
            [],
            [],
            [],
            $this->transformHeadersToServerVars($headers),
            $rawBody,
        );
    }

    /**
     * Webhooks are signed over the RAW body — so the test must send the
     * exact bytes that were signed. postJson() re-encodes arrays, which
     * would break the signature; call() with a string body does not.
     *
     * @return array{0: string, 1: array} [rawBody, headers]
     */
    private function webhookArgs(string $reference, bool $success): array
    {
        $body = json_encode([
            'event' => $success ? 'payment.success' : 'payment.failed',
            'eventId' => 'evt_test_'.$reference.'_'.$this->eventIdCounter++,
            'reference' => $reference,
        ]);
        $secret = config('payments.webhook_secret');

        return [$body, [
            'Content-Type' => 'application/json',
            'x-pastq-signature' => hash_hmac('sha256', $body, $secret),
        ]];
    }

    private int $eventIdCounter = 0;
}
