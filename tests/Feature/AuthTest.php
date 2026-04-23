<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AuthTest extends TestCase
{
    /**
     * A basic feature test example.
     */
  public function test_user_can_register(): void
{
    $response = $this->postJson('/api/auth/register', [
        'name'                  => 'Bassel Adel',
        'email'                 => 'test@test.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
             ->assertJsonStructure([
                 'message',
                 'user' => ['id', 'name', 'email'],
                 'token',
             ]);
}
}