<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_are_paginated_and_private_fields_are_hidden(): void
    {
        $user = User::factory()->create();
        User::factory()->count(3)->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonMissingPath('data.0.password');
    }

    public function test_a_user_can_view_another_users_public_profile(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/users/'.$otherUser->id)
            ->assertOk()
            ->assertJsonPath('data.name', $otherUser->name)
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.password');
    }

    public function test_a_user_can_view_their_own_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/users/'.$user->id)
            ->assertOk()
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_a_missing_user_returns_json_not_found(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/users/99999')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_user_pagination_is_validated(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/users?per_page=101&page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'page']);
    }
}
