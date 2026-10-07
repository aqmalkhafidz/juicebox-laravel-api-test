<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_a_post(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson('/api/posts', [
            'title' => 'First post',
            'content' => 'Hello from Perth.',
        ])->assertCreated()
            ->assertJsonPath('data.title', 'First post')
            ->assertJsonPath('data.user.id', $user->id);

        $this->assertDatabaseHas('posts', [
            'user_id' => $user->id,
            'title' => 'First post',
            'content' => 'Hello from Perth.',
        ]);
    }

    public function test_posts_are_paginated_with_their_authors(): void
    {
        $user = User::factory()->create();
        Post::factory()->for($user)->count(3)->create();
        $otherPost = Post::factory()->create();

        $this->actingAs($user, 'sanctum')->getJson('/api/posts?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('data.0.id', $otherPost->id)
            ->assertJsonPath('data.0.user.id', $otherPost->user_id)
            ->assertJsonMissingPath('data.0.user.email');
    }

    public function test_a_post_can_store_multibyte_content_within_the_character_limit(): void
    {
        $content = str_repeat('🌏', 20000);

        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/posts', [
            'title' => 'Unicode content',
            'content' => $content,
        ])->assertCreated()->assertJsonPath('data.content', $content);
    }

    public function test_a_user_can_view_a_specific_post(): void
    {
        $post = Post::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/posts/'.$post->id)
            ->assertOk()
            ->assertJsonPath('data.id', $post->id)
            ->assertJsonPath('data.content', $post->content);
    }

    public function test_an_owner_can_partially_update_their_post(): void
    {
        $post = Post::factory()->create(['content' => 'Original content']);

        $this->actingAs($post->user, 'sanctum')->patchJson('/api/posts/'.$post->id, [
            'title' => 'Updated title',
        ])->assertOk()
            ->assertJsonPath('data.title', 'Updated title')
            ->assertJsonPath('data.content', 'Original content');

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => 'Updated title']);
    }

    public function test_an_owner_can_delete_their_post(): void
    {
        $post = Post::factory()->create();

        $this->actingAs($post->user, 'sanctum')->deleteJson('/api/posts/'.$post->id)->assertNoContent();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    public function test_another_user_cannot_update_or_delete_a_post(): void
    {
        $post = Post::factory()->create(['title' => 'Original title']);

        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->patchJson('/api/posts/'.$post->id, ['title' => 'Changed'])->assertForbidden();
        $this->deleteJson('/api/posts/'.$post->id)->assertForbidden();

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'title' => 'Original title']);
    }

    public function test_post_creation_is_validated(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/posts', [
            'title' => str_repeat('x', 256),
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'content']);

        $this->assertDatabaseCount('posts', 0);
    }

    public function test_a_client_cannot_choose_a_posts_owner(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')->postJson('/api/posts', [
            'title' => 'First post',
            'content' => 'Hello.',
            'user_id' => User::factory()->create()->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['user_id']);
    }

    public function test_a_post_cannot_be_transferred_to_another_user(): void
    {
        $post = Post::factory()->create();

        $this->actingAs($post->user, 'sanctum')->patchJson('/api/posts/'.$post->id, [
            'user_id' => User::factory()->create()->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['user_id']);

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'user_id' => $post->user_id]);
    }

    public function test_updates_cannot_clear_required_fields(): void
    {
        $post = Post::factory()->create();

        $this->actingAs($post->user, 'sanctum')->patchJson('/api/posts/'.$post->id, [
            'title' => '',
            'content' => null,
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'content']);
    }

    public function test_a_missing_post_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/posts/99999')->assertNotFound();
    }

    public function test_post_pagination_is_validated(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')->getJson('/api/posts?per_page=0&page=invalid')
            ->assertUnprocessable()->assertJsonValidationErrors(['per_page', 'page']);
    }
}
