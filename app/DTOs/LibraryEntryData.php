<?php

namespace App\DTOs;

use Carbon\CarbonInterface;

final readonly class LibraryEntryData
{
    public function __construct(
        public int $bookId,
        public string $title,
        public string $author,
        public CarbonInterface $addedAt,
    ) {}

    public function toArray(): array
    {
        return [
            'book_id'  => $this->bookId,
            'title'    => $this->title,
            'author'   => $this->author,
            'added_at' => $this->addedAt->toISOString(),
        ];
    }
}
