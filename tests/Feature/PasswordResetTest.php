<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PastqSeeder::class);
        $this->student = User::where('email', 'student@pastq.test')->first();
    }

    public function test_forgot_password_page_loads_for_guests(): void
    {
        $response = $this->get('/forgot-password');

        $response->assertOk();
        $response->assertSee('Forgot password?');
        $response->assertSee('Send password reset link');
    }

    public function test_login_page_contains_forgot_password_link(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee(route('password.request'));
        $response->assertSee('Forgot password?');
    }

    public function test_authenticated_user_is_redirected_away_from_forgot_password(): void
    {
        $response = $this->actingAs($this->student)->get('/forgot-password');

        $response->assertRedirect(route('home'));
    }

    public function test_reset_link_is_dispatched_for_valid_email(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', [
            'email' => 'student@pastq.test',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        Notification::assertSentTo($this->student, ResetPasswordNotification::class, function ($notification) {
            $this->assertNotEmpty($notification->token);
            $mail = $notification->toMail($this->student);
            $this->assertStringContainsString('/reset-password/', $mail->actionUrl);
            $this->assertStringContainsString('student%40pastq.test', $mail->actionUrl);
            return true;
        });

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'student@pastq.test',
        ]);
    }

    public function test_reset_link_fails_for_unknown_email(): void
    {
        Notification::fake();

        $response = $this->post('/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_reset_link_fails_for_suspended_account(): void
    {
        Notification::fake();

        $this->student->update(['status' => 'SUSPENDED']);

        $response = $this->post('/forgot-password', [
            'email' => 'student@pastq.test',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_reset_password_page_loads_with_token(): void
    {
        $token = Password::broker()->createToken($this->student);

        $response = $this->get("/reset-password/{$token}?email=student%40pastq.test");

        $response->assertOk();
        $response->assertSee('Set new password');
        $response->assertSee('student@pastq.test');
        $response->assertSee('Reset password');
    }

    public function test_user_can_reset_password_and_login_with_new_credentials(): void
    {
        $token = Password::broker()->createToken($this->student);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'student@pastq.test',
            'password' => 'BrandNewPass123',
            'password_confirmation' => 'BrandNewPass123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status', 'Your password has been reset successfully! You can now log in.');

        // Verify password was updated in DB
        $this->student->refresh();
        $this->assertTrue(Hash::check('BrandNewPass123', $this->student->password));

        // Verify token was cleared
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'student@pastq.test',
        ]);

        // Verify student can log in with new password
        $loginResponse = $this->post('/login', [
            'email' => 'student@pastq.test',
            'password' => 'BrandNewPass123',
        ]);
        $loginResponse->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($this->student);

        // Verify old password fails
        auth()->logout();
        $oldLogin = $this->post('/login', [
            'email' => 'student@pastq.test',
            'password' => 'Student@123',
        ]);
        $oldLogin->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_reset_password_rejects_mismatched_confirmation(): void
    {
        $token = Password::broker()->createToken($this->student);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => 'student@pastq.test',
            'password' => 'BrandNewPass123',
            'password_confirmation' => 'DifferentPass456',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $response = $this->post('/reset-password', [
            'token' => 'invalid-expired-token',
            'email' => 'student@pastq.test',
            'password' => 'BrandNewPass123',
            'password_confirmation' => 'BrandNewPass123',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
