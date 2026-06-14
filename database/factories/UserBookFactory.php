<?php

namespace Database\Factories;

use App\Models\UserBook;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserBookFactory extends Factory
{
    protected $model = UserBook::class;

    public function definition(): array
    {
        return [
            'last_read_char_position' => 0,
            'font_size'               => 16,
            'is_active'               => false,
        ];
    }
}
