<?php

namespace App\DTOs;

final readonly class OpenBookData
{
    public function __construct(
        public int $userId,
        public int $bookId,
        public int $fontSize,
    ) {}
}
