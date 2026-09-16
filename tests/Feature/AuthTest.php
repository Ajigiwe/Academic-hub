<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function seedUsers(): void
    {
        $this->seed(\Database\Seeders\PastqSeeder::class);
    }

    public function test_login_page_loads_for_guests(): void
    {
        $this->get('/login')->assertOk()->assertSee('Log in');
    }

    public function test_student_can_log_in_with_valid_credentials(): void
    {
        $this->seedUsers();

        $response = $this->post('/login', [
            'email' => 'student@pastq.test',
            'password' => 'Student@123',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs(User::where('email', 'student@pastq.test')->first());
    }

    public function test_login_rejects_bad_password(): void
    {
        $this->seedUsers();

        $this->post('/login', [
            'email' => 'student@pastq.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_registration_creates_a_student(): void
    {
        $this->seedUsers();

        $response = $this->post('/register', [
            'first_name' => 'Kofi',
            'last_name' => 'Owusu',
            'email' => 'kofi@pastq.test',
            'password' => 'SuperSecret1',
            'password_confirmation' => 'SuperSecret1',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();

        $user = User::where('email', 'kofi@pastq.test')->first();
        $this->assertNotNull($user);
        $this->assertSame('STUDENT', $user->role);
        $this->assertSame('ACTIVE', $user->status);
    }

    public function test_password_fields_render_masked_with_a_show_toggle(): void
    {
        // The eye is progressive enhancement: the field ships masked (the
        // toggle script flips it client-side), and every password field
        // carries a button naming its own input.
        $this->get('/login')
            ->assertOk()
            ->assertSee('id="password" type="password"', false)
            ->assertSee('data-password-toggle="password"', false)
            ->assertSee('aria-label="Show password"', false);

        $this->get('/register')
            ->assertOk()
            ->assertSee('id="password" type="password"', false)
            ->assertSee('id="password_confirmation" type="password"', false)
            ->assertSee('data-password-toggle="password_confirmation"', false)
            ->assertSee('At least 8 characters.');
    }

    public function test_admin_area_is_hidden_from_students(): void
    {
        $this->seedUsers();
        $student = User::where('email', 'student@pastq.test')->first();

        $this->actingAs($student)->get('/admin')->assertNotFound();
    }

    public function test_admin_area_is_accessible_to_admins(): void
    {
        $this->seedUsers();
        $admin = User::where('email', 'admin@pastq.test')->first();

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Dashboard');
    }
}
