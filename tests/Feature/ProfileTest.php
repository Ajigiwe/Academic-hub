<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PastqSeeder::class);

        $this->admin = User::where('email', 'admin@pastq.test')->first();
        $this->student = User::where('email', 'student@pastq.test')->first();
    }

    public function test_guest_cannot_access_profile(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }

    public function test_user_can_view_profile_page(): void
    {
        $response = $this->actingAs($this->student)->get('/profile');
        $response->assertOk();
        $response->assertSee($this->student->name);
        $response->assertSee($this->student->email);
        $response->assertSee('Account Information');
        $response->assertSee('Purchase History');
    }

    public function test_user_can_update_account_info(): void
    {
        $response = $this->actingAs($this->student)->post('/profile', [
            'first_name' => 'Kofi',
            'last_name' => 'Annan',
            'email' => 'kofi.annan@test.com',
            'phone' => '+233240001122',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->student->refresh();
        $this->assertSame('Kofi Annan', $this->student->name);
        $this->assertSame('Kofi', $this->student->first_name);
        $this->assertSame('Annan', $this->student->last_name);
        $this->assertSame('kofi.annan@test.com', $this->student->email);
        $this->assertSame('+233240001122', $this->student->phone);
    }

    public function test_user_cannot_update_to_existing_email(): void
    {
        $response = $this->actingAs($this->student)->post('/profile', [
            'first_name' => 'Ama',
            'last_name' => 'Mensah',
            'email' => 'admin@pastq.test', // already taken
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_user_can_upload_and_destroy_avatar(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('my-photo.jpg', 200, 200);

        $uploadResponse = $this->actingAs($this->student)->post('/profile/avatar', [
            'avatar' => $file,
        ]);

        $uploadResponse->assertRedirect();
        $uploadResponse->assertSessionHas('status');

        $this->student->refresh();
        $this->assertNotNull($this->student->avatar);
        Storage::disk('public')->assertExists($this->student->avatar);

        // Remove avatar
        $deleteResponse = $this->actingAs($this->student)->delete('/profile/avatar');
        $deleteResponse->assertRedirect();
        $deleteResponse->assertSessionHas('status');

        $this->student->refresh();
        $this->assertNull($this->student->avatar);
    }

    public function test_user_can_change_password(): void
    {
        $response = $this->actingAs($this->student)->post('/profile/password', [
            'current_password' => 'Student@123',
            'password' => 'NewPassword@2026',
            'password_confirmation' => 'NewPassword@2026',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->student->refresh();
        $this->assertTrue(Hash::check('NewPassword@2026', $this->student->password));
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $response = $this->actingAs($this->student)->post('/profile/password', [
            'current_password' => 'IncorrectPassword',
            'password' => 'NewPassword@2026',
            'password_confirmation' => 'NewPassword@2026',
        ]);

        $response->assertSessionHasErrors('current_password');
    }

    public function test_avatar_endpoint_returns_svg_when_no_avatar_uploaded(): void
    {
        $response = $this->get("/avatar/{$this->student->id}");
        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $response->assertSee($this->student->initials);
    }

    public function test_purchase_history_shows_orders_and_receipt_links(): void
    {
        $bundle = Bundle::first();

        $order = Order::create([
            'reference' => 'ord_test_profile_123',
            'user_id' => $this->student->id,
            'status' => 'PAID',
            'amount_pesewas' => 2500,
            'currency' => 'GHS',
            'paid_at' => now(),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'bundle_id' => $bundle->id,
            'unit_price_pesewas' => 2500,
            'quantity' => 1,
        ]);

        $response = $this->actingAs($this->student)->get('/profile');
        $response->assertOk();
        $response->assertSee('ord_test_profile_123');
        $response->assertSee('GHS 25.00');
        $response->assertSee('PAID');
        $response->assertSee(route('payment.result', ['ref' => 'ord_test_profile_123']));
    }
}
