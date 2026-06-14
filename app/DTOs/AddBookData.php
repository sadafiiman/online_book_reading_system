<?php

namespace App\DTOs;

final readonly class AddBookData
{
    public function __construct(
        public int $userId,
        public int $bookId,
    ) {}
}
