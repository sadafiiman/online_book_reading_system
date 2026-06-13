<?php

namespace App\Repositories;

use App\Models\Book;
use App\Models\UserBook;
use App\Repositories\Interfaces\BookRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cache Decorator for BookRepository.
 *
 * Implements a "best-effort" caching strategy:
 * - If Redis is available  → serve from cache, fall through to DB on miss.
 * - If Redis is down       → silently fall through to DB, log a warning.
 *   The application keeps working at full DB cost but never crashes.
 *
 * Pattern: Decorator (GoF) + Cache-Aside with graceful degradation.
 */
class CachedBookRepository implements BookRepositoryInterface
{
    private const BOOK_TTL       = 60 * 60 * 24; // 24 h
    private const LIBRARY_TTL    = 60 * 60;       // 1 h
    private const BOOK_STATE_TTL = 60 * 5;        // 5 min

    public function __construct(
        private readonly BookRepositoryInterface $repository,
    ) {}

    // -------------------------------------------------------------------------
    // Cache key definitions
    // -------------------------------------------------------------------------

    private function bookKey(int $bookId): string
    {
        return "book:{$bookId}";
    }

    private function userLibraryKey(int $userId): string
    {
        return "user:{$userId}:library";
    }

    private function userBookStateKey(int $userId, int $bookId): string
    {
        return "user:{$userId}:book:{$bookId}:state";
    }

    private function userActiveBookKey(int $userId): string
    {
        return "user:{$userId}:active_book";
    }

    // -------------------------------------------------------------------------
    // Safe cache helpers — never throw, always fall back to $fallback callable
    // -------------------------------------------------------------------------

    /**
     * Safe read-through cache.
     * If Redis is unreachable, logs a warning and calls $fallback directly.
     */
    private function remember(string $key, int $ttl, callable $fallback): mixed
    {
        try {
            return Cache::remember($key, $ttl, $fallback);
        } catch (\Throwable $e) {
            Log::warning('Cache read failed, falling back to DB.', [
                'key'   => $key,
                'error' => $e->getMessage(),
            ]);

            return $fallback();
        }
    }

    /**
     * Safe cache write.
     * If Redis is unreachable, silently skip — DB is the source of truth.
     */
    private function put(string $key, mixed $value, int $ttl): void
    {
        try {
            Cache::put($key, $value, $ttl);
        } catch (\Throwable $e) {
            Log::warning('Cache write failed, skipping cache.', [
                'key'   => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Safe cache get.
     * Returns null if Redis is unreachable — caller falls through to DB.
     */
    private function get(string $key): mixed
    {
        try {
            return Cache::get($key);
        } catch (\Throwable $e) {
            Log::warning('Cache get failed, falling back to DB.', [
                'key'   => $key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Safe cache forget.
     * If Redis is unreachable, silently skip — stale key will expire on its own TTL.
     */
    private function forget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (\Throwable $e) {
            Log::warning('Cache invalidation failed, key will expire naturally.', [
                'key'   => $key,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Read methods
    // -------------------------------------------------------------------------

    public function findById(int $id): ?Book
    {
        return $this->remember(
            $this->bookKey($id),
            self::BOOK_TTL,
            fn () => $this->repository->findById($id)
        );
    }

    public function findUserBook(int $userId, int $bookId): ?UserBook
    {
        return $this->remember(
            $this->userBookStateKey($userId, $bookId),
            self::BOOK_STATE_TTL,
            fn () => $this->repository->findUserBook($userId, $bookId)
        );
    }

    public function findActiveUserBook(int $userId): ?UserBook
    {
        // get() returns null on Redis failure — falls through to DB cleanly
        $activeBookId = $this->get($this->userActiveBookKey($userId));

        if ($activeBookId) {
            return $this->findUserBook($userId, $activeBookId);
        }

        $userBook = $this->repository->findActiveUserBook($userId);

        if ($userBook) {
            $this->put($this->userActiveBookKey($userId), $userBook->book_id, self::BOOK_STATE_TTL);
        }

        return $userBook;
    }

    public function getUserLibrary(int $userId): array
    {
        return $this->remember(
            $this->userLibraryKey($userId),
            self::LIBRARY_TTL,
            fn () => $this->repository->getUserLibrary($userId)
        );
    }

    // -------------------------------------------------------------------------
    // Write methods — delegate then invalidate
    // -------------------------------------------------------------------------

    public function addBookToLibrary(int $userId, int $bookId): UserBook
    {
        $userBook = $this->repository->addBookToLibrary($userId, $bookId);

        $this->forget($this->userLibraryKey($userId));

        return $userBook;
    }

    public function deactivateAllUserBooks(int $userId): void
    {
        $activeBookId = $this->get($this->userActiveBookKey($userId));

        if ($activeBookId) {
            $this->forget($this->userBookStateKey($userId, $activeBookId));
        }

        $this->forget($this->userActiveBookKey($userId));

        $this->repository->deactivateAllUserBooks($userId);
    }

    public function activateUserBook(UserBook $userBook): UserBook
    {
        $result = $this->repository->activateUserBook($userBook);

        $this->put(
            $this->userBookStateKey($userBook->user_id, $userBook->book_id),
            $result,
            self::BOOK_STATE_TTL
        );

        $this->put(
            $this->userActiveBookKey($userBook->user_id),
            $userBook->book_id,
            self::BOOK_STATE_TTL
        );

        return $result;
    }

    /**
     * Page turn: DB lock always runs — cache is never the authority here.
     * After commit, stale cache is invalidated and re-warmed with fresh data.
     * If Redis is down, the DB result is simply returned without caching.
     */
    public function turnPage(int $userId, int $bookId, int $fontSize): UserBook
    {
        $userBook = $this->repository->turnPage($userId, $bookId, $fontSize);

        $this->forget($this->userBookStateKey($userId, $bookId));
        $this->put(
            $this->userBookStateKey($userId, $bookId),
            $userBook,
            self::BOOK_STATE_TTL
        );

        return $userBook;
    }
}
