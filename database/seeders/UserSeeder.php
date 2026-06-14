<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Testing\WithFaker;

class UserSeeder extends Seeder
{
    use WithFaker;
    public function run(): void
    {
        User::factory()
            ->count(5)
            ->sequence(fn () => [
                'email' => fake()->unique()->safeEmail(),
            ])
            ->create();
    }
}
