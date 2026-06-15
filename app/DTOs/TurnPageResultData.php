<?php

namespace App\DTOs;

final readonly class TurnPageResultData
{
    public function __construct(
        public int $bookId,
        public int $currentPage,
        public int $totalPages,
        public int $fontSize,
        public bool $isLastPage,
        public string $pageText,
    ) {}

    public function toArray(): array
    {
        return [
            'book_id'      => $this->bookId,
            'current_page' => $this->currentPage,
            'total_pages'  => $this->totalPages,
            'font_size'    => $this->fontSize,
            'is_last_page' => $this->isLastPage,
            'page_text'    => $this->pageText,
        ];
    }
}
