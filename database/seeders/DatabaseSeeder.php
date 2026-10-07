<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(['email' => 'demo@example.com'], [
            'name' => 'Demo User',
            'password' => 'password123',
        ]);

        if (! $user->posts()->exists()) {
            Post::factory()->for($user)->count(5)->create();
        }
    }
}
