<?php

namespace App\Repositories;

use App\Models\Book;
use App\Models\UserBook;
use App\Repositories\Interfaces\BookRepositoryInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Decorator around BookRepository adding a Redis cache-aside layer.
 *
 * Reads: cache-aside (check cache, fall back to DB, populate cache).
 * Writes that need correctness under concurrency (turnPage, switchActiveBook)
 * always hit the DB with row locking first; the cache is refreshed
 * afterward from the committed result — never the source of truth.
 */
class CachedBookRepository implements BookRepositoryInterface
{
    private const BOOK_TTL      = 86400; // 24h — static reference data
    private const USER_BOOK_TTL = 300;   // 5m — reading progress

    public function __construct(
        private readonly BookRepository $repository,
    ) {}

    public function findById(int $bookId): ?Book
    {
        $data = Cache::remember(
            $this->bookKey($bookId),
            self::BOOK_TTL,
            fn () => $this->repository->findById($bookId)?->toArray()
        );

        return $data ? Book::hydrate([$data])->first() : null;
    }

    public function findUserBook(int $userId, int $bookId): ?UserBook
    {
        $data = Cache::remember(
            $this->userBookKey($userId, $bookId),
            self::USER_BOOK_TTL,
            fn () => $this->repository->findUserBook($userId, $bookId)?->toArray()
        );

        return $data ? UserBook::hydrate([$data])->first() : null;
    }

    public function findActiveUserBook(int $userId): ?UserBook
    {
        $data = Cache::remember(
            $this->activeBookKey($userId),
            self::USER_BOOK_TTL,
            fn () => $this->repository->findActiveUserBook($userId)?->toArray()
        );

        return $data ? UserBook::hydrate([$data])->first() : null;
    }

    public function addBookToLibrary(int $userId, int $bookId): UserBook
    {
        $userBook = $this->repository->addBookToLibrary($userId, $bookId);

        Cache::put($this->userBookKey($userId, $bookId), $userBook->toArray(), self::USER_BOOK_TTL);

        return $userBook;
    }

    public function switchActiveBook(int $userId, int $bookId): UserBook
    {
        $previouslyActive = $this->findActiveUserBook($userId);

        $userBook = $this->repository->switchActiveBook($userId, $bookId);

        if ($previouslyActive && $previouslyActive->book_id !== $bookId) {
            Cache::forget($this->userBookKey($userId, $previouslyActive->book_id));
        }

        Cache::put($this->userBookKey($userId, $bookId), $userBook->toArray(), self::USER_BOOK_TTL);
        Cache::put($this->activeBookKey($userId), $userBook->toArray(), self::USER_BOOK_TTL);

        return $userBook;
    }

    public function turnPage(int $userId, int $bookId, int $fontSize): UserBook
    {
        $userBook = $this->repository->turnPage($userId, $bookId, $fontSize);

        Cache::put($this->userBookKey($userId, $bookId), $userBook->toArray(), self::USER_BOOK_TTL);
        Cache::put($this->activeBookKey($userId), $userBook->toArray(), self::USER_BOOK_TTL);

        return $userBook;
    }

    private function bookKey(int $bookId): string
    {
        return "book:{$bookId}";
    }

    private function userBookKey(int $userId, int $bookId): string
    {
        return "user:{$userId}:book:{$bookId}";
    }

    private function activeBookKey(int $userId): string
    {
        return "user:{$userId}:active_book";
    }
}
