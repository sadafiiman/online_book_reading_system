<?php

namespace App\DTOs;

final readonly class BookProgressData
{
    public function __construct(
        public int $bookId,
        public string $title,
        public int $lastPage,
        public int $totalPages,
        public int $fontSize,
        public string $pageText,
    ) {}

    public function toArray(): array
    {
        return [
            'book_id'     => $this->bookId,
            'title'       => $this->title,
            'last_page'   => $this->lastPage,
            'total_pages' => $this->totalPages,
            'font_size'   => $this->fontSize,
            'page_text'   => $this->pageText,
        ];
    }
}
