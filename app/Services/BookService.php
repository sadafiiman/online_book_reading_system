<?php

namespace App\Services;

use App\Exceptions\BookExceptions\BookAlreadyInLibraryException;
use App\Exceptions\BookExceptions\BookNotFoundException;
use App\Repositories\Interfaces\BookRepositoryInterface;

/**
 * Pure business logic — zero infrastructure concerns.
 * No Cache facade, no DB facade, no Redis knowledge here.
 * Caching is transparently handled by CachedBookRepository.
 */
class BookService
{
    public function __construct(
        private readonly BookRepositoryInterface $bookRepository,
    ) {}

    public function addToLibrary(int $userId, int $bookId): array
    {
        $book = $this->bookRepository->findById($bookId);

        if (! $book) {
            throw new BookNotFoundException("Book with ID {$bookId} does not exist.");
        }

        $existing = $this->bookRepository->findUserBook($userId, $bookId);

        if ($existing) {
            throw new BookAlreadyInLibraryException('This book is already in your library.');
        }

        $userBook = $this->bookRepository->addBookToLibrary($userId, $bookId);

        return [
            'book_id'  => $book->id,
            'title'    => $book->title,
            'author'   => $book->author,
            'added_at' => $userBook->created_at,
        ];
    }

    public function openBook(int $userId, int $bookId, int $fontSize): array
    {
        $book = $this->bookRepository->findById($bookId);

        if (! $book) {
            throw new BookNotFoundException("Book with ID {$bookId} does not exist.");
        }

        if (! $this->bookRepository->findUserBook($userId, $bookId)) {
            throw new BookNotFoundException('Book not found in your library. Add it first.');
        }

        $userBook = $this->bookRepository->switchActiveBook($userId, $bookId);

        return [
            'book_id'     => $book->id,
            'title'       => $book->title,
            'last_page'   => $userBook->currentPage($fontSize),
            'total_pages' => $book->totalPagesForFontSize($fontSize),
            'font_size'   => $fontSize,
        ];
    }

    public function turnPage(int $userId, int $bookId, int $fontSize): array
    {
        $userBook = $this->bookRepository->turnPage($userId, $bookId, $fontSize);

        $currentPage = $userBook->currentPage($fontSize);
        $totalPages  = $userBook->book->totalPagesForFontSize($fontSize);

        return [
            'book_id'      => $userBook->book_id,
            'current_page' => $currentPage,
            'total_pages'  => $totalPages,
            'font_size'    => $fontSize,
            'is_last_page' => $currentPage >= $totalPages,
        ];
    }
}
