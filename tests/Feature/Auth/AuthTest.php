<?php

namespace tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ----------------------------------------------------------------
    // REGISTER TESTS
    // ----------------------------------------------------------------

    /** @test */
    public function user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Bassel Adel',
            'email'                 => 'bassel@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'message',
                     'user' => ['id', 'name', 'email'],
                     'token',
                     'token_type',
                 ]);

        $this->assertDatabaseHas('users', ['email' => 'bassel@example.com']);
    }

    /** @test */
    public function registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'bassel@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Bassel Adel',
            'email'                 => 'bassel@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function registration_fails_when_password_confirmation_does_not_match(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Bassel Adel',
            'email'                 => 'bassel@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['password']);
    }

    /** @test */
    public function registration_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Bassel Adel',
            'email'                 => 'bassel@example.com',
            'password'              => '123',
            'password_confirmation' => '123',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['password']);
    }

    /** @test */
    public function registration_fails_with_missing_fields(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    // ----------------------------------------------------------------
    // LOGIN TESTS
    // ----------------------------------------------------------------

    /** @test */
    public function user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email'    => 'bassel@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'bassel@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'message',
                     'user' => ['id', 'name', 'email'],
                     'token',
                     'token_type',
                 ]);
    }

    /** @test */
    public function login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email'    => 'bassel@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => 'bassel@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
                 ->assertJson(['message' => 'Invalid credentials. Please check your email and password.']);
    }

    /** @test */
    public function login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'nobody@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function login_fails_with_missing_fields(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['email', 'password']);
    }

    // ----------------------------------------------------------------
    // PROFILE TESTS
    // ----------------------------------------------------------------

    /** @test */
    public function authenticated_user_can_get_their_profile(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/profile');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'user' => ['id', 'name', 'email', 'created_at'],
                 ])
                 ->assertJsonPath('user.email', $user->email);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/auth/profile');

        $response->assertStatus(401);
    }

    // ----------------------------------------------------------------
    // LOGOUT TESTS
    // ----------------------------------------------------------------

    /** @test */
    public function authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Logged out successfully.']);
    }

    /** @test */
    public function user_can_logout_from_all_devices(): void
    {
        $user = User::factory()->create();

        // Create multiple tokens to simulate multiple devices
        $user->createToken('device_1');
        $user->createToken('device_2');

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout-all');

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Logged out from all devices successfully.']);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    /** @test */
    public function unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }
}