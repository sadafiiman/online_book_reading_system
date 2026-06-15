<?php

namespace App\Repositories;

use App\Exceptions\BookExceptions\BookNotActiveException;
use App\Exceptions\BookExceptions\BookNotFoundException;
use App\Exceptions\BookExceptions\LastPageReachedException;
use App\Logging\BookActivityLoggerInterface;
use App\Models\Book;
use App\Models\UserBook;
use App\Repositories\Interfaces\BookRepositoryInterface;
use Illuminate\Support\Facades\DB;

class BookRepository implements BookRepositoryInterface
{
    /**
     * Log a warning if acquiring a row lock takes longer than this —
     * a signal of real contention, not just normal lock overhead.
     */
    private const LOCK_WAIT_WARNING_THRESHOLD_MS = 50;

    public function __construct(
        private readonly BookActivityLoggerInterface $activityLogger,
    ) {}

    public function findById(int $bookId): ?Book
    {
        return Book::find($bookId);
    }

    public function findUserBook(int $userId, int $bookId): ?UserBook
    {
        return UserBook::where('user_id', $userId)
            ->where('book_id', $bookId)
            ->first();
    }

    public function findActiveUserBook(int $userId): ?UserBook
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

    /**
     * Deactivate any other active book and activate the target one,
     * inside a single transaction with row locks. This guarantees a
     * user always ends up with exactly one active book even under
     * concurrent "open book" calls (same book or different books).
     */
    public function switchActiveBook(int $userId, int $bookId): UserBook
    {
        return DB::transaction(function () use ($userId, $bookId) {
            $waitStart = microtime(true);

            $target = UserBook::where('user_id', $userId)
                ->where('book_id', $bookId)
                ->lockForUpdate()
                ->first();

            $this->logLockWait('user_books', $waitStart);

            if (! $target) {
                throw new BookNotFoundException('Book not found in your library. Add it first.');
            }

            $waitStart = microtime(true);

            UserBook::where('user_id', $userId)
                ->where('is_active', true)
                ->where('id', '!=', $target->id)
                ->lockForUpdate()
                ->update(['is_active' => false]);

            $this->logLockWait('user_books', $waitStart);

            if (! $target->is_active) {
                $target->update(['is_active' => true]);
            }

            return $target->fresh(['book']);
        });
    }

    /**
     * Pessimistic locking prevents two concurrent "turn page" requests
     * for the same user/book from both reading position N and both
     * writing N+1 — the second waits for the first transaction to commit.
     */
    public function turnPage(int $userId, int $bookId, int $fontSize): UserBook
    {
        return DB::transaction(function () use ($userId, $bookId, $fontSize) {
            $waitStart = microtime(true);

            $userBook = UserBook::where('user_id', $userId)
                ->where('book_id', $bookId)
                ->lockForUpdate()
                ->first();

            $this->logLockWait('user_books', $waitStart);

            if (! $userBook) {
                throw new BookNotFoundException('Book not found in user library.');
            }

            if (! $userBook->is_active) {
                throw new BookNotActiveException('Book is not currently active. Open the book first.');
            }

            $userBook->load('book');

            $totalPages  = $userBook->book->totalPagesForFontSize($fontSize);
            $currentPage = $userBook->currentPage($fontSize);

            if ($currentPage >= $totalPages) {
                throw new LastPageReachedException('You have reached the last page of this book.');
            }

            $userBook->advanceToNextPage($fontSize);
            $userBook->save();

            return $userBook;
        });
    }

    private function logLockWait(string $table, float $waitStart): void
    {
        $waitMs = round((microtime(true) - $waitStart) * 1000, 2);

        if ($waitMs > self::LOCK_WAIT_WARNING_THRESHOLD_MS) {
            $this->activityLogger->lockContention($table, $waitMs);
        }
    }
}
