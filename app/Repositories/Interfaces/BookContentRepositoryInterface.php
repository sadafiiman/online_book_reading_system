<?php

namespace App\Repositories\Interfaces;

use App\Models\Book;

interface BookContentRepositoryInterface
{
    /**
     * Returns the full raw text content of the book.
     */
    public function getContent(Book $book): string;
}
