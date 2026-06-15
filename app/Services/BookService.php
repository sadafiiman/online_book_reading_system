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
use App\Logging\BookActivityLoggerInterface;
use App\Repositories\Interfaces\BookRepositoryInterface;

class BookService
{
    public function __construct(
        private readonly BookRepositoryInterface $bookRepository,
        private readonly BookActivityLoggerInterface $activityLogger,
    ) {}

    public function addToLibrary(AddBookData $data): LibraryEntryData
    {
        $book = $this->bookRepository->findById($data->bookId);

        if (! $book) {
            $this->activityLogger->actionRejected('book.add_to_library_rejected', [
                'user_id' => $data->userId,
                'book_id' => $data->bookId,
                'reason'  => 'book_not_found',
            ]);

            throw new BookNotFoundException("Book with ID {$data->bookId} does not exist.");
        }

        $existing = $this->bookRepository->findUserBook($data->userId, $data->bookId);

        if ($existing) {
            $this->activityLogger->actionRejected('book.add_to_library_rejected', [
                'user_id' => $data->userId,
                'book_id' => $data->bookId,
                'reason'  => 'already_in_library',
            ]);

            throw new BookAlreadyInLibraryException('This book is already in your library.');
        }

        $userBook = $this->bookRepository->addBookToLibrary($data->userId, $data->bookId);

        $this->activityLogger->bookAddedToLibrary($data->userId, $data->bookId);

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
            $this->activityLogger->actionRejected('book.open_rejected', [
                'user_id' => $data->userId,
                'book_id' => $data->bookId,
                'reason'  => 'book_not_found',
            ]);

            throw new BookNotFoundException("Book with ID {$data->bookId} does not exist.");
        }

        if (! $this->bookRepository->findUserBook($data->userId, $data->bookId)) {
            $this->activityLogger->actionRejected('book.open_rejected', [
                'user_id' => $data->userId,
                'book_id' => $data->bookId,
                'reason'  => 'not_in_library',
            ]);

            throw new BookNotFoundException('Book not found in your library. Add it first.');
        }

        $userBook = $this->bookRepository->switchActiveBook($data->userId, $data->bookId);

        $progress = new BookProgressData(
            bookId: $book->id,
            title: $book->title,
            lastPage: $userBook->currentPage($data->fontSize),
            totalPages: $book->totalPagesForFontSize($data->fontSize),
            fontSize: $data->fontSize,
        );

        $this->activityLogger->bookOpened($data->userId, $book->id, $progress->lastPage, $data->fontSize);

        return $progress;
    }

    public function turnPage(TurnPageData $data): TurnPageResultData
    {
        $userBook = $this->bookRepository->turnPage($data->userId, $data->bookId, $data->fontSize);

        $currentPage = $userBook->currentPage($data->fontSize);
        $totalPages  = $userBook->book->totalPagesForFontSize($data->fontSize);

        $this->activityLogger->pageTurned(
            $data->userId,
            $userBook->book_id,
            $currentPage,
            $currentPage,
            $data->fontSize,
        );

        return new TurnPageResultData(
            bookId: $userBook->book_id,
            currentPage: $currentPage,
            totalPages: $totalPages,
            fontSize: $data->fontSize,
            isLastPage: $currentPage >= $totalPages,
        );
    }
}
