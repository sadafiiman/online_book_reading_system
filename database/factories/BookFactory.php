<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        return [
            'title'       => fake()->sentence(3),
            'author'      => fake()->name(),
            'isbn'        => fake()->unique()->isbn13(),
            'description' => fake()->paragraph(),
            // 20,000 chars = exactly 10 pages @ default font size (2000 chars/page)
            'total_chars' => 20000,
            'file_path'   => 'books/test-book.txt',
        ];
    }
}
