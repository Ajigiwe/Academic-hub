<?php

namespace Tests\Feature;

use App\Models\{Bundle, Entitlement, Order, Payment, Refund, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The post-payment page is the receipt. It must state the truth for every
 * outcome (paid, still confirming, refunded, cancelled, refused), and it
 * must never expose an order to anyone but its owner — the link carries
 * only a reference.
 */
class PaymentResultTest extends TestCase
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

    // ── The receipt ───────────────────────────────────────────────────

    public function test_a_paid_order_shows_a_receipt_with_what_was_bought(): void
    {
        $order = $this->paidOrder('PQ-RCPT01');

        $response = $this->actingAs($this->student)
            ->get('/payment/result?status=success&ref='.$order->reference
                .'&bundle=/bundles/'.$this->bundle->slug);

        $response->assertOk();
        $response->assertSee('Payment confirmed');
        $response->assertSee('Order summary');
        $response->assertSee('PQ-RCPT01');

        // What was bought, from our own records.
        $response->assertSee('ACC 102');
        $response->assertSee('Introduction to Financial Accounting');
        $response->assertSee('Level 100');

        // Money, method, access.
        $response->assertSee('GH₵15.00');
        $response->assertSee('Mobile money');
        $response->assertSee('2 papers unlocked');
        $response->assertSee('Paid');

        // Actions and the support line, with the reference to quote.
        $response->assertSee('Start reading');
        $response->assertSee('/bundles/'.$this->bundle->slug, false);
        $response->assertSee('Keep order reference');

        // The owner has the receipt, so there is nothing to explain away.
        $response->assertDontSee('Sign in with the account you paid with');
    }

    public function test_a_guests_reference_leaks_no_order_details(): void
    {
        $order = $this->paidOrder('PQ-RCPT02');

        // A guest never sees the receipt…
        $guest = $this->get('/payment/result?status=success&ref='.$order->reference);
        $guest->assertOk()->assertSee('Payment confirmed');
        $guest->assertDontSee('Order summary');
        $guest->assertDontSee('Introduction to Financial Accounting');
        $guest->assertDontSee('GH₵15.00');

        // …but is told why, and offered the fix. A student returned to the
        // wrong host has no session, which reads as "signed out" mid-payment.
        $guest->assertSee('Sign in with the account you paid with');
        $guest->assertSee('href="'.route('login').'"', false);

        // …and neither does a signed-in student holding someone else's
        // reference.
        $other = User::create([
            'name' => 'Someone Else',
            'first_name' => 'Someone',
            'last_name' => 'Else',
            'email' => 'someone-else@example.com',
            'password' => bcrypt('Password@123'),
            'role' => 'STUDENT',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($other)
            ->get('/payment/result?status=success&ref='.$order->reference)
            ->assertOk()
            ->assertDontSee('Order summary')
            ->assertDontSee('Introduction to Financial Accounting')
            // Already signed in, so a sign-in prompt would be noise.
            ->assertDontSee('Sign in with the account you paid with')
            ->assertSee('This order is not on your account');
    }

    public function test_a_reference_with_no_order_still_offers_sign_in(): void
    {
        // Nothing stored for this reference at all (typed URL, expired link).
        $this->get('/payment/result?status=success&ref=PQ-NOSUCH')
            ->assertOk()
            ->assertSee('PQ-NOSUCH')
            ->assertSee('Sign in with the account you paid with');

        // And a page with no reference says nothing about signing in.
        $this->get('/payment/result?status=cancelled')
            ->assertOk()
            ->assertDontSee('Sign in with the account you paid with');
    }

    // ── States ────────────────────────────────────────────────────────

    public function test_a_pending_order_says_the_papers_unlock_when_it_clears(): void
    {
        $order = $this->paidOrder('PQ-RCPT03', paid: false);

        $response = $this->actingAs($this->student)
            ->get('/payment/result?status=pending&ref='.$order->reference);

        $response->assertOk();
        $response->assertSee('Payment being confirmed');
        $response->assertSee('Awaiting confirmation');
        $response->assertSee('Unlocks automatically once the payment clears');
        $response->assertDontSee('2 papers unlocked');
    }

    public function test_a_refunded_order_states_that_access_is_gone(): void
    {
        $order = $this->paidOrder('PQ-RCPT04');
        $order->update(['status' => 'REFUNDED']);
        Entitlement::where('order_id', $order->id)->update(['status' => 'revoked', 'revoked_at' => now()]);
        Refund::create([
            'order_id' => $order->id,
            'payment_id' => $order->payments->first()->id,
            'provider' => 'paystack',
            'provider_ref' => '3019000',
            'status' => 'PROCESSED',
            'amount_pesewas' => 1500,
            'currency' => 'GHS',
            'processed_at' => now(),
        ]);

        $response = $this->actingAs($this->student)
            ->get('/payment/result?status=refunded&ref='.$order->reference);

        $response->assertOk();
        $response->assertSee('This order was refunded');
        $response->assertSee('GH₵15.00');
        $response->assertSee('Access removed by the refund');
    }

    public function test_a_partial_refund_says_access_is_kept(): void
    {
        $order = $this->paidOrder('PQ-RCPT05');
        $order->update(['status' => 'PARTIALLY_REFUNDED']);
        Refund::create([
            'order_id' => $order->id,
            'payment_id' => $order->payments->first()->id,
            'provider' => 'paystack',
            'provider_ref' => '3019001',
            'status' => 'PROCESSED',
            'amount_pesewas' => 500,
            'currency' => 'GHS',
            'processed_at' => now(),
        ]);

        $response = $this->actingAs($this->student)
            ->get('/payment/result?status=refunded&ref='.$order->reference);

        $response->assertOk();
        $response->assertSee('GH₵5.00');
        $response->assertSee('of GH₵15.00 paid');
        $response->assertSee('Access kept');
    }

    public function test_a_failed_attempt_says_nothing_was_charged(): void
    {
        $order = $this->paidOrder('PQ-RCPT06', paid: false);

        $response = $this->actingAs($this->student)
            ->get('/payment/result?status=failed&ref='.$order->reference.'&reason=Gateway+timeout');

        $response->assertOk();
        $response->assertSee('Payment not confirmed');
        $response->assertSee('Gateway timeout');
        $response->assertSee('no charge was made');
        $response->assertSee('Check My Library');
    }

    public function test_a_cancelled_attempt_offers_a_retry_on_the_bundle(): void
    {
        $order = $this->paidOrder('PQ-RCPT07', paid: false);

        $response = $this->actingAs($this->student)
            ->get('/payment/result?status=cancelled&ref='.$order->reference
                .'&bundle=/bundles/'.$this->bundle->slug);

        $response->assertOk();
        $response->assertSee('Payment cancelled');
        $response->assertSee('no charge was made');
        $response->assertSee('Try the payment again');
    }

    public function test_an_unknown_status_falls_back_to_the_refusal_page(): void
    {
        $this->get('/payment/result?status=somethingelse')
            ->assertOk()
            ->assertSee('Payment not confirmed');
    }

    public function test_a_stale_reason_is_not_shown_on_a_successful_page(): void
    {
        $order = $this->paidOrder('PQ-RCPT08');

        $this->actingAs($this->student)
            ->get('/payment/result?status=success&ref='.$order->reference.'&reason=Some+stale+error')
            ->assertOk()
            ->assertDontSee('Some stale error');
    }

    // ── Helper ────────────────────────────────────────────────────────

    /** An order with a payment (and entitlements), paid or still pending. */
    private function paidOrder(string $reference, bool $paid = true): Order
    {
        $order = Order::create([
            'reference' => $reference,
            'user_id' => $this->student->id,
            'status' => $paid ? 'PAID' : 'PENDING',
            'amount_pesewas' => 1500,
            'currency' => 'GHS',
            'paid_at' => $paid ? now() : null,
        ]);
        $order->items()->create(['bundle_id' => $this->bundle->id, 'unit_price_pesewas' => 1500]);
        Payment::create([
            'order_id' => $order->id,
            'provider' => 'paystack',
            'provider_ref' => $reference,
            'provider_txn_id' => '6562614663',
            'reference' => $reference,
            'channel' => 'MOBILE_MONEY',
            'status' => $paid ? 'SUCCESSFUL' : 'PENDING',
            'amount_pesewas' => 1500,
            'currency' => 'GHS',
            'verified_at' => $paid ? now() : null,
        ]);

        if ($paid) {
            foreach ($this->bundle->resources()->published()->pluck('id') as $resourceId) {
                Entitlement::create([
                    'user_id' => $this->student->id,
                    'resource_id' => $resourceId,
                    'order_id' => $order->id,
                    'status' => 'active',
                ]);
            }
        }

        return $order;
    }
}
