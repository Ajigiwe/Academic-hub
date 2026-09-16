<?php

namespace Tests\Feature;

use App\Models\{Bundle, Entitlement, Notification, Order, Payment, Refund, Resource, User, WebhookEvent};
use App\Services\Documents\DocumentStore;
use App\Services\Entitlements;
use App\Services\Payments\PaystackProvider;
use App\Services\Payments\PaystackRequestException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Refunds arrive as signed Paystack webhooks (refund.pending → processing
 * → processed → failed). The refund is re-fetched from the gateway's API
 * before anything changes; only PROCESSED money state moves, a full
 * refund revokes the papers that order unlocked, and a replay never
 * refunds twice.
 */
class RefundTest extends TestCase
{
    use RefreshDatabase;

    private object $paystack; // anonymous PaystackProvider subclass

    private string $secret = 'sk_test_webhook_secret';

    private User $student;

    private Bundle $bundle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PastqSeeder::class);

        $this->student = User::where('email', 'student@pastq.test')->first();
        $this->bundle = Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();

        config(['payments.paystack.secret_key' => $this->secret]);

        $this->paystack = new class extends PaystackProvider
        {
            /** @var array<string, array<string, mixed>> stubbed by path fragment */
            public array $responses = [];

            /** @var array<string, array{0: int, 1: string}> failures by path fragment */
            public array $errors = [];

            /** @var list<array{method: string, path: string}> */
            public array $calls = [];

            protected function request(string $method, string $path, array $payload = []): array
            {
                $this->calls[] = ['method' => $method, 'path' => $path];

                foreach ($this->errors as $fragment => [$status, $message]) {
                    if (str_contains($path, $fragment)) {
                        throw new PaystackRequestException($status, $path, "Paystack {$path} failed: {$message}");
                    }
                }

                foreach ($this->responses as $fragment => $response) {
                    if (str_contains($path, $fragment)) {
                        return $response;
                    }
                }

                throw new \RuntimeException("No stubbed Paystack response for {$method} {$path}");
            }
        };
        $this->app->instance(PaystackProvider::class, $this->paystack);
        config(['payments.provider' => 'paystack']);
    }

    // ── Full refund ───────────────────────────────────────────────────

    public function test_a_processed_full_refund_revokes_access_and_flips_the_order(): void
    {
        $order = $this->paidOrder('PQ-REF001', '1004723697');
        $this->stubRefund('3018284', 'processed', 1500, ['id' => '1004723697', 'reference' => $order->reference]);

        $response = $this->postRefund('refund.processed', '3018284', ['amount' => 1500]);

        $response->assertOk()->assertJson(['ok' => true, 'refund' => 'PROCESSED', 'revoked' => 2]);

        // Recorded, with the gateway's own numbers.
        $refund = Refund::sole();
        $this->assertSame($order->id, $refund->order_id);
        $this->assertSame('paystack', $refund->provider);
        $this->assertSame('3018284', $refund->provider_ref);
        $this->assertSame(1500, $refund->amount_pesewas);
        $this->assertSame('PROCESSED', $refund->status);
        $this->assertNotNull($refund->processed_at);

        // Order + payment flipped.
        $this->assertSame('REFUNDED', $order->fresh()->status);
        $this->assertSame('REFUNDED', Payment::where('order_id', $order->id)->value('status'));

        // Access revoked, with an audit trail — and the entitlement engine
        // (including its bundle-ownership fallback) agrees.
        $this->assertSame(0, Entitlement::where('order_id', $order->id)->where('status', 'active')->count());
        $this->assertNotNull(Entitlement::where('order_id', $order->id)->first()->revoked_at);
        $this->assertFalse(app(Entitlements::class)->has($this->student, $this->firstPaperId()));

        // The student is told, and the papers are gone from the library.
        $notification = Notification::where('user_id', $this->student->id)->sole();
        $this->assertSame('PAYMENT', $notification->type);
        $this->assertSame('Refund processed', $notification->title);
        $this->assertStringContainsString('GH₵15.00', $notification->body);

        $this->assertStringContainsString(
            'Your library is empty',
            $this->actingAs($this->student)->get('/library')->getContent(),
        );
    }

    public function test_the_viewer_stops_serving_a_refunded_paper(): void
    {
        $order = $this->paidOrder('PQ-REF002', '1004723698');
        $paper = Resource::find($this->firstPaperId());
        $this->attachPdf($paper);
        $slug = $paper->slug;

        $this->actingAs($this->student)->get('/view/'.$slug)->assertOk();

        $this->stubRefund('3018285', 'processed', 1500, ['id' => '1004723698', 'reference' => $order->reference]);
        $this->postRefund('refund.processed', '3018285', ['amount' => 1500])->assertOk();

        $this->actingAs($this->student)->get('/view/'.$slug)->assertStatus(403);
    }

    // ── Partial refund ────────────────────────────────────────────────

    public function test_a_partial_refund_is_recorded_and_keeps_access(): void
    {
        $order = $this->paidOrder('PQ-REF003', '1004723699');
        $this->stubRefund('3018286', 'processed', 500, ['id' => '1004723699', 'reference' => $order->reference]);

        $this->postRefund('refund.processed', '3018286', ['amount' => 500])
            ->assertOk()
            ->assertJson(['ok' => true, 'refund' => 'PROCESSED', 'revoked' => 0]);

        $this->assertSame(500, Refund::sole()->amount_pesewas);
        $this->assertSame('PARTIALLY_REFUNDED', $order->fresh()->status);
        $this->assertSame('REFUNDED_PARTIALLY', Payment::where('order_id', $order->id)->value('status'));

        // Papers are not divisible: they still have what they paid for.
        $this->assertSame(2, Entitlement::where('order_id', $order->id)->where('status', 'active')->count());
        $this->assertTrue(app(Entitlements::class)->has($this->student, $this->firstPaperId()));

        $notification = Notification::where('user_id', $this->student->id)->sole();
        $this->assertSame('Partial refund processed', $notification->title);
        $this->assertStringContainsString('Your papers stay available', $notification->body);
    }

    public function test_a_second_partial_refund_that_completes_the_order_revokes_access(): void
    {
        $order = $this->paidOrder('PQ-REF004', '1004723700');

        $this->stubRefund('3018287', 'processed', 1000, ['id' => '1004723700', 'reference' => $order->reference]);
        $this->postRefund('refund.processed', '3018287', ['amount' => 1000])->assertOk();
        $this->assertSame('PARTIALLY_REFUNDED', $order->fresh()->status);

        $this->stubRefund('3018288', 'processed', 500, ['id' => '1004723700', 'reference' => $order->reference]);
        $this->postRefund('refund.processed', '3018288', ['amount' => 500])->assertOk()->assertJson(['revoked' => 2]);

        $this->assertSame(2, Refund::count());
        $this->assertSame('REFUNDED', $order->fresh()->status);
        $this->assertSame(0, Entitlement::where('order_id', $order->id)->where('status', 'active')->count());
    }

    // ── In-flight and failed refunds ──────────────────────────────────

    public function test_a_pending_refund_is_recorded_without_touching_access(): void
    {
        $order = $this->paidOrder('PQ-REF005', '1004723701');
        $this->stubRefund('3018289', 'pending', 1500, ['id' => '1004723701', 'reference' => $order->reference]);

        $this->postRefund('refund.pending', '3018289', ['amount' => 1500])
            ->assertOk()
            ->assertJson(['ok' => true, 'refund' => 'PENDING']);

        $this->assertSame('PENDING', Refund::sole()->status);
        $this->assertNull(Refund::sole()->processed_at);
        $this->assertSame('PAID', $order->fresh()->status);
        $this->assertSame(2, Entitlement::where('order_id', $order->id)->where('status', 'active')->count());
        $this->assertSame(0, Notification::count());
    }

    public function test_a_failed_refund_is_recorded_and_keeps_access(): void
    {
        $order = $this->paidOrder('PQ-REF006', '1004723702');
        $this->stubRefund('3018290', 'failed', 1500, ['id' => '1004723702', 'reference' => $order->reference]);

        $this->postRefund('refund.failed', '3018290', ['amount' => 1500])->assertOk()->assertJson(['refund' => 'FAILED']);

        $this->assertSame('FAILED', Refund::sole()->status);
        $this->assertSame('PAID', $order->fresh()->status);
        $this->assertTrue(app(Entitlements::class)->has($this->student, $this->firstPaperId()));
    }

    public function test_a_pending_refund_that_later_processes_applies_once(): void
    {
        $order = $this->paidOrder('PQ-REF007', '1004723703');

        $this->stubRefund('3018291', 'pending', 1500, ['id' => '1004723703', 'reference' => $order->reference]);
        $this->postRefund('refund.pending', '3018291', ['amount' => 1500])->assertOk();

        $this->stubRefund('3018291', 'processed', 1500, ['id' => '1004723703', 'reference' => $order->reference]);
        $this->postRefund('refund.processed', '3018291', ['amount' => 1500])->assertOk()->assertJson(['revoked' => 2]);

        $this->assertSame(1, Refund::count());
        $this->assertSame('PROCESSED', Refund::sole()->status);
        $this->assertSame('REFUNDED', $order->fresh()->status);
        $this->assertSame(1, Notification::count());
    }

    // ── Idempotency & lookup ──────────────────────────────────────────

    public function test_a_redelivered_processed_refund_never_refunds_twice(): void
    {
        $order = $this->paidOrder('PQ-REF008', '1004723704');
        $this->stubRefund('3018292', 'processed', 1500, ['id' => '1004723704', 'reference' => $order->reference]);

        $this->postRefund('refund.processed', '3018292', ['amount' => 1500])->assertOk()->assertJson(['revoked' => 2]);

        // Paystack redelivers the same event: the event-id dedupe ACKs it
        // without reprocessing.
        $this->postRefund('refund.processed', '3018292', ['amount' => 1500])
            ->assertOk()
            ->assertJson(['ok' => true, 'deduplicated' => true]);

        $this->assertSame(1, Refund::count());
        $this->assertSame(1, Notification::count());
        $this->assertSame('REFUNDED', $order->fresh()->status);
    }

    public function test_a_late_processing_event_cannot_demote_a_processed_refund(): void
    {
        $order = $this->paidOrder('PQ-REF013', '1004723709');

        $this->stubRefund('3018298', 'processed', 1500, ['id' => '1004723709', 'reference' => $order->reference]);
        $this->postRefund('refund.processed', '3018298', ['amount' => 1500])->assertOk();

        // refund.processing is a different event id, so it reaches the
        // processor — but refunds only move forward.
        $this->paystack->responses['/refund/3018298']['data']['status'] = 'processing';
        $this->postRefund('refund.processing', '3018298', ['amount' => 1500])
            ->assertOk()
            ->assertJson(['refund' => 'PROCESSED', 'revoked' => 0]);

        $this->assertSame('PROCESSED', Refund::sole()->status);
        $this->assertNotNull(Refund::sole()->processed_at);
        $this->assertSame('REFUNDED', $order->fresh()->status);
        $this->assertSame(1, Notification::count());
    }

    public function test_the_order_is_found_from_the_gateway_transaction_id_alone(): void
    {
        $order = $this->paidOrder('PQ-REF009', '1004723705');

        // This delivery only knows the gateway's transaction id — no
        // reference anywhere — and the refund object reports a bare id too.
        $this->paystack->responses['/refund/'] = [
            'status' => true,
            'data' => ['id' => 3018293, 'amount' => 1500, 'currency' => 'GHS', 'status' => 'processed', 'transaction' => 1004723705],
        ];
        $this->paystack->responses['/transaction/1004723705'] = [
            'status' => true,
            'data' => ['id' => 1004723705, 'reference' => $order->reference, 'status' => 'success'],
        ];

        $this->postRefund('refund.processed', '3018293', [], referenceless: true)
            ->assertOk()
            ->assertJson(['order' => $order->reference, 'refund' => 'PROCESSED']);

        $this->assertSame('REFUNDED', $order->fresh()->status);
        $this->assertSame(0, Entitlement::where('order_id', $order->id)->where('status', 'active')->count());
    }

    public function test_a_refund_for_an_unknown_order_is_acknowledged_without_action(): void
    {
        $this->stubRefund('3018294', 'processed', 1500, ['id' => '9999999999', 'reference' => 'PQ-UNKNOWNX']);

        $this->postRefund('refund.processed', '3018294', ['amount' => 1500])
            ->assertOk()
            ->assertJson(['ok' => true, 'unmatched' => true]);

        $this->assertSame(0, Refund::count());
        $this->assertSame(0, Entitlement::where('status', 'revoked')->count());
    }

    public function test_an_unverifiable_refund_leaves_the_event_for_retry(): void
    {
        $order = $this->paidOrder('PQ-REF010', '1004723706');
        $this->paystack->errors['/refund/'] = [503, 'Service unavailable'];

        $this->postRefund('refund.processed', '3018295', ['amount' => 1500])
            ->assertStatus(500)
            ->assertJson(['error' => 'Refund processing failed; will retry.']);

        $this->assertNull(WebhookEvent::sole()->processed_at);
        $this->assertSame(0, Refund::count());
        $this->assertSame('PAID', $order->fresh()->status);
        $this->assertSame(2, Entitlement::where('order_id', $order->id)->where('status', 'active')->count());
    }

    public function test_a_forged_refund_delivery_is_rejected(): void
    {
        $order = $this->paidOrder('PQ-REF011', '1004723707');
        $this->stubRefund('3018296', 'processed', 1500, ['id' => '1004723707', 'reference' => $order->reference]);

        $body = json_encode(['event' => 'refund.processed', 'data' => ['id' => 3018296, 'amount' => 1500]]);

        $this->postWebhook($body, hash_hmac('sha512', $body, 'wrong-secret'))->assertStatus(401);

        $this->assertSame(0, Refund::count());
        $this->assertSame('PAID', $order->fresh()->status);
    }

    public function test_the_sandbox_gateway_ignores_refund_events(): void
    {
        $order = $this->paidOrder('PQ-REF012', '1004723708');
        config(['payments.provider' => 'mock', 'payments.webhook_secret' => 'sandbox-secret']);

        // The sandbox's own payload shape (reference at the root) with a
        // refund-shaped event name it would never actually send.
        $body = json_encode([
            'event' => 'refund.processed',
            'eventId' => 'evt_mock_refund',
            'reference' => $order->reference,
        ]);

        $this->postWebhook($body, hash_hmac('sha256', $body, 'sandbox-secret'), 'x-pastq-signature')
            ->assertOk()
            ->assertJson(['ok' => true, 'ignored' => true]);

        $this->assertSame(0, Refund::count());
        $this->assertSame('PAID', $order->fresh()->status);
    }

    // ── A refunded sale stays closed ──────────────────────────────────

    public function test_a_stale_browser_return_cannot_undo_a_refund(): void
    {
        $order = $this->paidOrder('PQ-REF015', '1004723711');

        // The charge itself still verifies as successful — that is exactly
        // what makes this dangerous: the money succeeded once.
        $this->stubRefund('3018300', 'processed', 1500, ['id' => '1004723711', 'reference' => $order->reference]);
        $this->postRefund('refund.processed', '3018300', ['amount' => 1500])->assertOk();

        $response = $this->actingAs($this->student)->get('/payments/verify/'.$order->reference);

        $response->assertRedirect();
        $this->assertStringContainsString('status=refunded', $response->headers->get('Location'));

        $this->assertSame('REFUNDED', $order->fresh()->status);
        $this->assertSame('REFUNDED', Payment::where('order_id', $order->id)->value('status'));
        $this->assertSame(0, Entitlement::where('order_id', $order->id)->where('status', 'active')->count());
    }

    public function test_fulfillment_refuses_to_reopen_a_refunded_order(): void
    {
        $order = $this->paidOrder('PQ-REF016', '1004723712');
        $this->stubRefund('3018301', 'processed', 1500, ['id' => '1004723712', 'reference' => $order->reference]);
        $this->postRefund('refund.processed', '3018301', ['amount' => 1500])->assertOk();

        $notifications = Notification::count();
        $result = app(\App\Services\Payments\OrderFulfiller::class)->fulfill($order->reference);

        $this->assertTrue($result['already_fulfilled']);
        $this->assertSame('REFUNDED', $order->fresh()->status);
        $this->assertSame(0, Entitlement::where('order_id', $order->id)->where('status', 'active')->count());
        $this->assertSame($notifications, Notification::count());
    }

    // ── Admin visibility ──────────────────────────────────────────────

    public function test_the_orders_page_shows_the_refund(): void
    {
        $admin = User::where('email', 'admin@pastq.test')->first();
        $order = $this->paidOrder('PQ-REF014', '1004723710');
        $this->stubRefund('3018299', 'processed', 500, ['id' => '1004723710', 'reference' => $order->reference]);

        $this->postRefund('refund.processed', '3018299', ['amount' => 500])->assertOk();

        $response = $this->actingAs($admin)->get('/admin/orders');

        $response->assertOk();
        $response->assertSee('PARTIALLY_REFUNDED');
        $response->assertSee('−GH₵5.00 refunded');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    private function firstPaperId(): int
    {
        return (int) $this->bundle->resources()->published()->orderBy('id')->value('id');
    }

    /** A paid order with a successful payment and active entitlements. */
    private function paidOrder(string $reference, string $transactionId, int $amountPesewas = 1500): Order
    {
        $order = Order::create([
            'reference' => $reference,
            'user_id' => $this->student->id,
            'status' => 'PAID',
            'amount_pesewas' => $amountPesewas,
            'currency' => 'GHS',
            'paid_at' => now(),
        ]);
        $order->items()->create([
            'bundle_id' => $this->bundle->id,
            'unit_price_pesewas' => $amountPesewas,
        ]);
        Payment::create([
            'order_id' => $order->id,
            'provider' => 'paystack',
            'provider_ref' => $reference,
            'provider_txn_id' => $transactionId,
            'reference' => $reference,
            'channel' => 'MOBILE_MONEY',
            'status' => 'SUCCESSFUL',
            'amount_pesewas' => $amountPesewas,
            'currency' => 'GHS',
            'verified_at' => now(),
        ]);

        foreach ($this->bundle->resources()->published()->pluck('id') as $resourceId) {
            Entitlement::create([
                'user_id' => $this->student->id,
                'resource_id' => $resourceId,
                'order_id' => $order->id,
                'status' => 'active',
            ]);
        }

        return $order;
    }

    /** The viewer needs a real PDF behind the paper to serve it at all. */
    private function attachPdf(Resource $paper): void
    {
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'pt', 'A4', true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', '', 12);
        $pdf->Text(50, 50, 'Refund test page');
        $bytes = (string) $pdf->Output('', 'S');

        $store = app(DocumentStore::class);
        $key = $store->buildStorageKey();
        $store->put($key, $bytes);

        $paper->files()->create([
            'storage_key' => $key,
            'mime_type' => 'application/pdf',
            'size_bytes' => strlen($bytes),
            'checksum' => hash('sha256', $bytes),
            'original_name' => 'test.pdf',
            'is_current' => true,
        ]);
    }

    /**
     * What GET /refund/:id answers — the authoritative snapshot.
     *
     * @param array<string, mixed> $transaction
     */
    private function stubRefund(string $refundId, string $status, int $amount, array $transaction): void
    {
        $this->paystack->responses['/refund/'.$refundId] = [
            'status' => true,
            'data' => [
                'id' => (int) $refundId,
                'amount' => $amount,
                'currency' => 'GHS',
                'status' => $status,
                'merchant_note' => 'Refund requested from the dashboard',
                'transaction' => $transaction,
            ],
        ];
    }

    /**
     * Signed refund delivery, exactly as Paystack sends it.
     *
     * @param array<string, mixed> $data
     */
    private function postRefund(string $event, string $refundId, array $data, bool $referenceless = false)
    {
        $payload = [
            'event' => $event,
            'data' => array_merge(['id' => (int) $refundId], $data),
        ];
        if (! $referenceless) {
            $payload['data']['transaction'] = ['id' => '1004723697'];
        }

        $body = json_encode($payload);

        return $this->postWebhook($body, hash_hmac('sha512', $body, $this->secret));
    }

    /** POST the exact signed bytes (postJson would re-encode the body). */
    private function postWebhook(string $rawBody, string $signature, string $header = 'x-paystack-signature')
    {
        return $this->call(
            'POST',
            '/payments/webhook',
            [],
            [],
            [],
            $this->transformHeadersToServerVars([
                'Content-Type' => 'application/json',
                $header => $signature,
            ]),
            $rawBody,
        );
    }
}
