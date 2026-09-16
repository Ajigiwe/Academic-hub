<?php

namespace Tests\Feature;

use App\Models\{Bundle, Notification, Order, User};
use App\Services\Payments\OrderFulfiller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The bell is the student's record of what happened to their money. A
 * confirmation must name what was bought (not just an order reference),
 * each row must lead somewhere useful, and opening one must clear it
 * without ever exposing another student's notifications.
 */
class NotificationTest extends TestCase
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

    // ── Purchase copy ─────────────────────────────────────────────────

    public function test_a_purchase_notification_names_the_papers_and_the_amount(): void
    {
        $order = $this->pendingOrder('PQ-NOTIF1');

        (new OrderFulfiller)->fulfill($order->reference);

        $notification = Notification::sole();

        $this->assertSame('PURCHASE', $notification->type);
        $this->assertSame('Purchase confirmed', $notification->title);

        // What was bought, what it cost, and which order to quote.
        $this->assertStringContainsString('Introduction to Financial Accounting', $notification->body);
        $this->assertStringContainsString('GH₵15.00', $notification->body);
        $this->assertStringContainsString('PQ-NOTIF1', $notification->body);

        // It is not dead text: it points at the receipt for that order.
        $this->assertSame('/payment/result?status=success&ref=PQ-NOTIF1', $notification->url);
    }

    public function test_a_multi_bundle_order_lists_what_was_bought(): void
    {
        $other = Bundle::where('id', '!=', $this->bundle->id)->first();
        $order = $this->pendingOrder('PQ-NOTIF2', [$this->bundle, $other]);

        (new OrderFulfiller)->fulfill($order->reference);

        $body = Notification::sole()->body;

        $this->assertStringContainsString($this->bundle->title, $body);
        $this->assertStringContainsString($other->title, $body);
    }

    // ── Opening a row ─────────────────────────────────────────────────

    public function test_opening_a_notification_clears_it_and_sends_the_student_on(): void
    {
        $order = $this->pendingOrder('PQ-NOTIF3');
        (new OrderFulfiller)->fulfill($order->reference);
        $notification = Notification::sole();

        $this->assertNull($notification->read_at);

        $this->actingAs($this->student)
            ->get(route('notifications.open', $notification))
            ->assertRedirect('/payment/result?status=success&ref=PQ-NOTIF3');

        $this->assertNotNull($notification->fresh()->read_at);

        // Opening it again is harmless and still lands on the receipt.
        $this->actingAs($this->student)
            ->get(route('notifications.open', $notification))
            ->assertRedirect('/payment/result?status=success&ref=PQ-NOTIF3');
    }

    public function test_a_notification_with_nowhere_to_go_returns_to_the_list(): void
    {
        $notification = $this->note('System notice');

        $this->actingAs($this->student)
            ->get(route('notifications.open', $notification))
            ->assertRedirect(route('notifications.index'));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_an_offsite_destination_is_never_followed(): void
    {
        // Rows are ours, but a stored value must still never bounce a
        // student (or a session) to another site.
        foreach (['https://evil.test/steal', '//evil.test/steal'] as $url) {
            $notification = $this->note('Suspicious notice', $url);

            $this->actingAs($this->student)
                ->get(route('notifications.open', $notification))
                ->assertRedirect(route('notifications.index'));
        }
    }

    public function test_a_student_cannot_open_someone_elses_notification(): void
    {
        $other = User::create([
            'name' => 'Someone Else',
            'first_name' => 'Someone',
            'last_name' => 'Else',
            'email' => 'someone-else@example.com',
            'password' => bcrypt('Password@123'),
            'role' => 'STUDENT',
            'status' => 'ACTIVE',
        ]);
        $notification = Notification::create([
            'user_id' => $other->id,
            'type' => 'PURCHASE',
            'title' => 'Purchase confirmed',
            'body' => 'Someone else bought something.',
            'url' => '/library',
        ]);

        $this->actingAs($this->student)->get(route('notifications.open', $notification))->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);

        $this->actingAs($this->student)->get('/notifications')->assertDontSee('Someone else bought something.');
    }

    // ── The list ──────────────────────────────────────────────────────

    public function test_the_list_marks_unread_rows_and_groups_them_by_day(): void
    {
        $this->note('From today');
        $this->note('From yesterday', createdAt: now()->subDay());

        $response = $this->actingAs($this->student)->get('/notifications');

        $response->assertOk();
        $response->assertSee('From today');
        $response->assertSee('From yesterday');
        $response->assertSee('Today');
        $response->assertSee('Yesterday');
        $response->assertSee('2 unread');
        $response->assertSee('Unread');
    }

    public function test_an_empty_list_invites_a_browse(): void
    {
        $response = $this->actingAs($this->student)->get('/notifications');

        $response->assertOk();
        $response->assertSee('Nothing here yet');
        $response->assertSee('caught up');
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /** A system notification, optionally backdated. */
    private function note(string $title, ?string $url = null, ?\DateTimeInterface $createdAt = null): Notification
    {
        $notification = Notification::create([
            'user_id' => $this->student->id,
            'type' => 'SYSTEM',
            'title' => $title,
            'body' => $title.' body.',
            'url' => $url,
        ]);

        if ($createdAt) {
            $notification->forceFill(['created_at' => $createdAt])->save();
        }

        return $notification;
    }

    /**
     * A pending order, priced as the storefront would price it.
     *
     * @param  array<int, Bundle>  $bundles
     */
    private function pendingOrder(string $reference, ?array $bundles = null): Order
    {
        $bundles ??= [$this->bundle];
        $amount = collect($bundles)->sum('price_pesewas');

        $order = Order::create([
            'reference' => $reference,
            'user_id' => $this->student->id,
            'status' => 'PENDING',
            'amount_pesewas' => $amount,
            'currency' => 'GHS',
        ]);

        foreach ($bundles as $bundle) {
            $order->items()->create([
                'bundle_id' => $bundle->id,
                'unit_price_pesewas' => $bundle->price_pesewas,
                'quantity' => 1,
            ]);
        }

        return $order;
    }
}
