<?php

namespace App\Repositories;

use App\Exceptions\BookExceptions\BookNotActiveException;
use App\Exceptions\BookExceptions\BookNotFoundException;
use App\Exceptions\BookExceptions\LastPageReachedException;
use App\Models\Book;
use App\Models\UserBook;
use App\Repositories\Interfaces\BookRepositoryInterface;
use Illuminate\Support\Facades\DB;

class BookRepository implements BookRepositoryInterface
{
    public function findById(int $id): ?Book
    {
        return Book::find($id);
    }

    public function findLibrary(int $userId, int $bookId): ?UserBook
    {
        return UserBook::where('user_id', $userId)
            ->where('book_id', $bookId)
            ->first();
    }

    public function findActiveLibrary(int $userId): ?UserBook
    {
        return UserBook::where('user_id', $userId)
            ->where('is_active', true)
            ->with('book')
            ->first();
    }

    public function addBookToLibrary(int $userId, int $bookId): UserBook
    {
        return UserBook::firstOrCreate(
            ['user_id' => $userId, 'book_id' => $bookId],
            [
                'is_active'               => false,
                'last_read_char_position' => 0,
                'font_size'               => config('books.default_font_size', 16),
            ]
        );
    }

    public function deactivateAllLibraries(int $userId): void
    {
        UserBook::where('user_id', $userId)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    public function activateLibrary(UserBook $Library): UserBook
    {
        $Library->update(['is_active' => true]);

        return $Library->fresh(['book']);
    }

    /**
     * Turn page with pessimistic locking to prevent race conditions.
     *
     * Uses DB::transaction + lockForUpdate() so that concurrent requests
     * for the same user/book are serialized at the database level.
     * Only one request advances the page; others wait for the lock.
     *
     * This is critical: without locking, two simultaneous "turn page"
     * requests could both read page 5, both write page 6 — losing a turn.
     */
    public function turnPage(int $userId, int $bookId, int $fontSize): UserBook
    {
        return DB::transaction(function () use ($userId, $bookId, $fontSize) {
            // lockForUpdate() issues SELECT ... FOR UPDATE
            // Blocks any other transaction from reading/writing this row
            // until this transaction commits or rolls back.
            $Library = UserBook::where('user_id', $userId)
                ->where('book_id', $bookId)
                ->lockForUpdate()
                ->first();

            if (! $Library) {
                throw new BookNotFoundException('Book not found in user library.');
            }

            if (! $Library->is_active) {
                throw new BookNotActiveException('Book is not currently active. Open the book first.');
            }

            $Library->load('book');

            $totalPages  = $Library->book->totalPagesForFontSize($fontSize);
            $currentPage = $Library->currentPage($fontSize);

            if ($currentPage >= $totalPages) {
                throw new LastPageReachedException('You have reached the last page of this book.');
            }

            $Library->advanceToNextPage($fontSize);
            $Library->save();

            return $Library;
        });
    }

    public function findUserBook(int $userId, int $bookId): ?UserBook
    {
        // TODO: Implement findUserBook() method.
    }

    public function findActiveUserBook(int $userId): ?UserBook
    {
        // TODO: Implement findActiveUserBook() method.
    }

    public function deactivateAllUserBooks(int $userId): void
    {
        // TODO: Implement deactivateAllUserBooks() method.
    }

    public function activateUserBook(UserBook $userBook): UserBook
    {
        // TODO: Implement activateUserBook() method.
    }
}
