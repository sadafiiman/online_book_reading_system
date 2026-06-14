<?php

namespace App\DTOs;

final readonly class TurnPageData
{
    public function __construct(
        public int $userId,
        public int $bookId,
        public int $fontSize,
    ) {}
}
