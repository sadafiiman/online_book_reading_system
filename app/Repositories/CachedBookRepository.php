<?php

namespace App\Repositories;

use App\Logging\BookActivityLoggerInterface;
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
        private readonly BookActivityLoggerInterface $activityLogger,
    ) {}

    public function findById(int $bookId): ?Book
    {
        $key = $this->bookKey($bookId);

        if (Cache::has($key)) {
            $this->activityLogger->cacheHit($key);

            $book = new Book();
            $book->setRawAttributes(Cache::get($key), true);

            return $book;
        }

        $this->activityLogger->cacheMiss($key);

        $book = $this->repository->findById($bookId);

        if ($book) {
            Cache::put($key, $book->toArray(), self::BOOK_TTL);
        }

        return $book;
    }

    public function findUserBook(int $userId, int $bookId): ?UserBook
    {
        $key = $this->userBookKey($userId, $bookId);

        if (Cache::has($key)) {
            $this->activityLogger->cacheHit($key);

            return $this->hydrateUserBook(Cache::get($key));
        }

        $this->activityLogger->cacheMiss($key);

        $userBook = $this->repository->findUserBook($userId, $bookId);

        if ($userBook) {
            Cache::put($key, $userBook->toArray(), self::USER_BOOK_TTL);
        }

        return $userBook;
    }

    public function findActiveUserBook(int $userId): ?UserBook
    {
        $key = $this->activeBookKey($userId);

        if (Cache::has($key)) {
            $this->activityLogger->cacheHit($key);

            return $this->hydrateUserBook(Cache::get($key));
        }

        $this->activityLogger->cacheMiss($key);

        $userBook = $this->repository->findActiveUserBook($userId);

        if ($userBook) {
            Cache::put($key, $userBook->toArray(), self::USER_BOOK_TTL);
        }

        return $userBook;
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

        $this->activityLogger->cacheRefresh([
            $this->userBookKey($userId, $bookId),
            $this->activeBookKey($userId),
        ]);

        return $userBook;
    }

    public function turnPage(int $userId, int $bookId, int $fontSize): UserBook
    {
        $userBook = $this->repository->turnPage($userId, $bookId, $fontSize);

        Cache::put($this->userBookKey($userId, $bookId), $userBook->toArray(), self::USER_BOOK_TTL);
        Cache::put($this->activeBookKey($userId), $userBook->toArray(), self::USER_BOOK_TTL);

        $this->activityLogger->cacheRefresh([
            $this->userBookKey($userId, $bookId),
            $this->activeBookKey($userId),
        ]);

        return $userBook;
    }

    /**
     * Rebuild a UserBook (and its book relation, if present) from a
     * plain attribute array stored in cache. newFromBuilder() marks the
     * model as existing and applies casts correctly — unlike
     * `UserBook::hydrate([$array])`, which would set a nested 'book'
     * array as a raw attribute, shadowing the book() relation and
     * breaking any later `$userBook->book->...` call.
     */
    private function hydrateUserBook(array $attributes): UserBook
    {
        $bookAttributes = $attributes['book'] ?? null;
        unset($attributes['book']);

        $userBook = new UserBook();
        $userBook->setRawAttributes($attributes, true);

        if ($bookAttributes) {
            $book = new Book();
            $book->setRawAttributes($bookAttributes, true);
            $userBook->setRelation('book', $book);
        }

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
