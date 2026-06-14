<?php

namespace App\Services;

use App\DTOs\AddBookData;
use App\DTOs\BookProgressData;
use App\DTOs\LibraryEntryData;
use App\DTOs\OpenBookData;
use App\DTOs\TurnPageData;
use App\DTOs\TurnPageResultData;
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

    public function addToLibrary(AddBookData $data): LibraryEntryData
    {
        $book = $this->bookRepository->findById($data->bookId);

        if (! $book) {
            throw new BookNotFoundException("Book with ID {$data->bookId} does not exist.");
        }

        $existing = $this->bookRepository->findUserBook($data->userId, $data->bookId);

        if ($existing) {
            throw new BookAlreadyInLibraryException('This book is already in your library.');
        }

        $userBook = $this->bookRepository->addBookToLibrary($data->userId, $data->bookId);

        return new LibraryEntryData(
            bookId: $book->id,
            title: $book->title,
            author: $book->author,
            addedAt: $userBook->created_at ?? now(),
        );
    }

    public function openBook(OpenBookData $data): BookProgressData
    {
        $book = $this->bookRepository->findById($data->bookId);

        if (! $book) {
            throw new BookNotFoundException("Book with ID {$data->bookId} does not exist.");
        }

        if (! $this->bookRepository->findUserBook($data->userId, $data->bookId)) {
            throw new BookNotFoundException('Book not found in your library. Add it first.');
        }

        $userBook = $this->bookRepository->switchActiveBook($data->userId, $data->bookId);

        return new BookProgressData(
            bookId: $book->id,
            title: $book->title,
            lastPage: $userBook->currentPage($data->fontSize),
            totalPages: $book->totalPagesForFontSize($data->fontSize),
            fontSize: $data->fontSize,
        );
    }

    public function turnPage(TurnPageData $data): TurnPageResultData
    {
        $userBook = $this->bookRepository->turnPage($data->userId, $data->bookId, $data->fontSize);

        $currentPage = $userBook->currentPage($data->fontSize);
        $totalPages  = $userBook->book->totalPagesForFontSize($data->fontSize);

        return new TurnPageResultData(
            bookId: $userBook->book_id,
            currentPage: $currentPage,
            totalPages: $totalPages,
            fontSize: $data->fontSize,
            isLastPage: $currentPage >= $totalPages,
        );
    }
}
