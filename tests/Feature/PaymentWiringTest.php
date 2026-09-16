<?php

namespace Tests\Feature;

use App\Models\{Bundle, Order, Payment, User, WebhookEvent};
use App\Services\Payments\MockProvider;
use App\Services\Payments\PaymentManager;
use App\Services\Payments\PaystackProvider;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Wiring the real gateway: which provider a checkout actually uses, what
 * happens when the selected one is not ready, and the production guard
 * that stops the sandbox from giving content away on a public secret.
 */
class PaymentWiringTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $admin;

    private Bundle $bundle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PastqSeeder::class);
        $this->student = User::where('email', 'student@pastq.test')->first();
        $this->admin = User::where('email', 'admin@pastq.test')->first();
        $this->bundle = Bundle::where('slug', 'acc-102-introduction-to-financial-accounting-2024-2025')->first();
    }

    protected function tearDown(): void
    {
        // Environment switches are process-wide; put it back so the rest
        // of the suite keeps seeing "testing".
        $this->app->detectEnvironment(fn () => 'testing');
        parent::tearDown();
    }

    private function inProduction(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        // Laravel only skips CSRF while the environment is "testing", so
        // a production-flavoured test must opt out explicitly (the app
        // itself would be behind a real form token).
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function selectPaystack(?string $key = null): void
    {
        config([
            'payments.provider' => 'paystack',
            'payments.paystack.secret_key' => $key,
        ]);
    }

    // ── Selection ─────────────────────────────────────────────────────

    public function test_paystack_selected_without_a_key_keeps_development_on_the_sandbox(): void
    {
        $this->selectPaystack(null);

        $response = $this->actingAs($this->student)->post('/checkout', ['bundle_id' => $this->bundle->id]);

        $response->assertRedirect();
        $this->assertStringContainsString('/payment/mock-checkout', $response->headers->get('Location'));
        $this->assertSame('mock', Payment::sole()->provider);
        $this->assertSame('mock', app(PaymentManager::class)->status()['effective']);
        $this->assertTrue(app(PaymentManager::class)->status()['falling_back']);
    }

    public function test_paystack_selected_without_a_key_refuses_checkout_in_production(): void
    {
        $this->inProduction();
        $this->selectPaystack(null);

        $response = $this->actingAs($this->student)->post('/checkout', ['bundle_id' => $this->bundle->id]);

        // A clear, user-facing failure — never a 500, never a free unlock.
        $response->assertSessionHasErrors('bundle');
        $this->assertSame('CANCELLED', Order::sole()->status);
        $this->assertSame(0, Payment::count());
    }

    public function test_the_configured_key_makes_paystack_the_effective_provider(): void
    {
        $this->selectPaystack('sk_test_abcdef');

        $status = app(PaymentManager::class)->status();

        $this->assertSame('paystack', $status['selected']);
        $this->assertSame('paystack', $status['effective']);
        $this->assertSame('test', $status['effective_mode']);
        $this->assertTrue($status['selected_ready']);
        $this->assertFalse($status['falling_back']);
        $this->assertNull($status['note']);
    }

    public function test_mode_reflects_the_paystack_key_prefix(): void
    {
        $paystack = app(PaystackProvider::class);

        config(['payments.paystack.secret_key' => null]);
        $this->assertFalse($paystack->isConfigured());
        $this->assertSame('unconfigured', $paystack->mode());

        config(['payments.paystack.secret_key' => 'sk_test_123']);
        $this->assertTrue($paystack->isConfigured());
        $this->assertSame('test', $paystack->mode());

        config(['payments.paystack.secret_key' => 'sk_live_123']);
        $this->assertSame('live', $paystack->mode());
    }

    // ── The sandbox in a live environment ─────────────────────────────

    public function test_sandbox_is_shut_down_in_production_on_the_public_dev_secret(): void
    {
        $this->inProduction();
        config(['payments.provider' => 'mock', 'payments.webhook_secret' => MockProvider::DEV_SECRET]);

        // The pages do not exist…
        $this->get('/payment/mock-checkout?ref=PQ-X')->assertNotFound();

        // …and the endpoint is gone, so nothing can be approved for free.
        $this->post('/payment/mock-gateway', ['ref' => 'PQ-X', 'outcome' => 'success'])->assertNotFound();

        // A forged delivery signed with the repo's public default 401s.
        $body = json_encode(['event' => 'payment.success', 'eventId' => 'evt_forged', 'reference' => 'PQ-X']);
        $this->call('POST', '/payments/webhook', [], [], [], $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'x-pastq-signature' => hash_hmac('sha256', $body, MockProvider::DEV_SECRET),
        ]), $body)->assertStatus(401);

        $this->assertSame(0, WebhookEvent::count());
    }

    public function test_sandbox_still_runs_in_production_when_explicitly_selected_with_a_real_secret(): void
    {
        $this->inProduction();
        config(['payments.provider' => 'mock', 'payments.webhook_secret' => 'a-real-deployment-secret']);

        $this->get('/payment/mock-checkout?ref=PQ-X')->assertOk();

        // …and the signed chain still fulfils: a delivery signed with the
        // deployment's own secret is accepted.
        $order = $this->pendingOrder('PQ-LIVES1');
        $body = json_encode([
            'event' => 'payment.success',
            'eventId' => 'evt_real_deploy',
            'reference' => $order->reference,
        ]);
        $this->call('POST', '/payments/webhook', [], [], [], $this->transformHeadersToServerVars([
            'Content-Type: application/json',
            'x-pastq-signature' => hash_hmac('sha256', $body, 'a-real-deployment-secret'),
        ]), $body)->assertOk();

        $this->assertSame('PAID', $order->fresh()->status);
    }

    public function test_production_never_falls_back_to_the_sandbox_when_paystack_is_selected(): void
    {
        $this->inProduction();
        $this->selectPaystack(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('PAYSTACK_SECRET_KEY');

        app(PaymentManager::class)->provider();
    }

    // ── Callback shapes ───────────────────────────────────────────────

    public function test_verify_accepts_paystacks_own_callback_parameters(): void
    {
        $order = $this->pendingOrder('PQ-CALLB0');
        $this->acknowledge($order->reference);

        // Paystack appends ?trxref=…&reference=… to the callback URL.
        $this->actingAs($this->student)
            ->get('/payments/verify?trxref='.$order->reference.'&reference='.$order->reference)
            ->assertRedirect();

        $this->assertSame('PAID', $order->fresh()->status);
    }

    public function test_verify_accepts_the_reference_in_the_path(): void
    {
        $order = $this->pendingOrder('PQ-PATHR1');
        $this->acknowledge($order->reference);

        $this->actingAs($this->student)
            ->get('/payments/verify/'.$order->reference)
            ->assertRedirect();

        $this->assertSame('PAID', $order->fresh()->status);
    }

    // ── Return origin (never log a paying student out) ─────────────────

    public function test_the_checkout_return_url_uses_the_host_the_student_is_on(): void
    {
        config(['app.url' => 'http://localhost:8080']);

        // The student browses 127.0.0.1 while APP_URL says localhost — two
        // different cookie hosts. The payment must come back to the one
        // holding their session.
        $response = $this->actingAs($this->student)
            ->post('http://127.0.0.1:8080/checkout', ['bundle_id' => $this->bundle->id]);

        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('http://127.0.0.1:8080/payment/mock-checkout', $location);
        $this->assertStringContainsString('callback='.urlencode('http://127.0.0.1:8080/payments/verify/'), $location);

        // Webhooks stay on the canonical public origin — they are
        // server-to-server and have nothing to do with the student's host.
        $this->assertSame('http://localhost:8080/payments/webhook', app(PaymentManager::class)->status()['webhook_url']);
    }

    public function test_the_verify_callback_returns_the_student_to_their_own_host(): void
    {
        config(['app.url' => 'http://localhost:8080']);
        $order = $this->orderForCheckout('PQ-ORIGIN');
        $this->acknowledge($order->reference);

        $response = $this->actingAs($this->student)
            ->get('http://127.0.0.1:8080/payments/verify/'.$order->reference);

        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('http://127.0.0.1:8080/payment/result?status=success', $location);
        $this->assertSame('PAID', $order->fresh()->status);
    }

    public function test_a_tunnel_host_is_honoured_when_it_is_trusted(): void
    {
        config([
            'app.url' => 'https://hub.example.com',
            'payments.trusted_hosts' => ['pastq.ngrok.app'],
        ]);

        $response = $this->actingAs($this->student)
            ->post('https://pastq.ngrok.app/checkout', ['bundle_id' => $this->bundle->id]);

        $this->assertStringStartsWith(
            'https://pastq.ngrok.app/payment/mock-checkout',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_an_untrusted_host_falls_back_to_the_configured_origin(): void
    {
        $this->inProduction();
        config([
            'app.url' => 'https://hub.example.com',
            'payments.provider' => 'mock',
            'payments.webhook_secret' => 'a-real-deployment-secret',
        ]);

        // A spoofed Host header must never send a student (or a payment
        // reference) somewhere we do not control.
        $response = $this->actingAs($this->student)
            ->post('http://evil.example.com/checkout', ['bundle_id' => $this->bundle->id]);

        $this->assertStringStartsWith(
            'https://hub.example.com/payment/mock-checkout',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_the_login_page_explains_a_payment_interruption(): void
    {
        $order = $this->pendingOrder('PQ-LOGIN1');

        // The student's session is gone (different host, expired cookie, a
        // completed payment in another browser) — the login page must say
        // what they are logging in FOR.
        $this->get('http://127.0.0.1:8080/payments/verify/'.$order->reference)
            ->assertRedirect('/login');

        $this->get('http://127.0.0.1:8080/login')
            ->assertOk()
            ->assertSee('Sign in to finish confirming your payment');
    }

    public function test_the_login_page_stays_quiet_for_ordinary_sign_ins(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('finish confirming your payment');
    }

    // ── Admin visibility ──────────────────────────────────────────────

    public function test_settings_shows_the_gateway_wiring_and_the_webhook_url(): void
    {
        $this->selectPaystack('sk_test_abcdef');
        config(['app.url' => 'https://hub.example.com']);

        $response = $this->actingAs($this->admin)->get('/admin/settings');

        $response->assertOk();
        $response->assertSee('Paystack · test keys');
        $response->assertSee('https://hub.example.com/payments/webhook');
        $response->assertSee('https://hub.example.com/payments/verify');
        $response->assertDontSee('sk_test_abcdef'); // never echo the secret
    }

    public function test_settings_flags_an_unconfigured_paystack(): void
    {
        $this->selectPaystack(null);

        $response = $this->actingAs($this->admin)->get('/admin/settings');

        $response->assertOk();
        $response->assertSee('falling back to sandbox');
        $response->assertSee('Paystack is selected but not configured');
        $response->assertSee('To switch to Paystack set');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /** A pending order + payment row, ready to be acknowledged. */
    private function orderForCheckout(string $reference): Order
    {
        return $this->pendingOrder($reference);
    }

    private function pendingOrder(string $reference): Order
    {
        $order = Order::create([
            'reference' => $reference,
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
            'provider_ref' => 'mock_'.$reference,
            'reference' => $reference,
            'channel' => 'MOCK',
            'amount_pesewas' => $order->amount_pesewas,
            'currency' => 'GHS',
        ]);

        return $order;
    }

    /** The sandbox's signed acknowledgment for a reference. */
    private function acknowledge(string $reference): void
    {
        $body = json_encode([
            'event' => 'payment.success',
            'eventId' => 'evt_wire_'.$reference,
            'reference' => $reference,
        ]);
        $secret = (string) config('payments.webhook_secret');

        $this->call('POST', '/payments/webhook', [], [], [], $this->transformHeadersToServerVars([
            'Content-Type' => 'application/json',
            'x-pastq-signature' => hash_hmac('sha256', $body, $secret),
        ]), $body)->assertOk();
    }
}
