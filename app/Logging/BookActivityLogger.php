<?php

namespace App\Logging;

use Illuminate\Support\Facades\Log;

class BookActivityLogger implements BookActivityLoggerInterface
{
    private const string CHANNEL = 'book_activity';

    public function cacheHit(string $key): void
    {
        Log::channel(self::CHANNEL)->debug('cache.hit', ['key' => $key]);
    }

    public function cacheMiss(string $key): void
    {
        Log::channel(self::CHANNEL)->debug('cache.miss', ['key' => $key]);
    }

    public function cacheRefresh(array $keys): void
    {
        Log::channel(self::CHANNEL)->info('cache.refresh', ['keys' => $keys]);
    }

    public function bookAddedToLibrary(int $userId, int $bookId): void
    {
        Log::channel(self::CHANNEL)->info('book.added_to_library', [
            'user_id' => $userId,
            'book_id' => $bookId,
        ]);
    }

    public function bookOpened(int $userId, int $bookId, int $page, int $fontSize): void
    {
        Log::channel(self::CHANNEL)->info('book.opened', [
            'user_id'   => $userId,
            'book_id'   => $bookId,
            'page'      => $page,
            'font_size' => $fontSize,
        ]);
    }

    public function pageTurned(int $userId, int $bookId, int $page, int $position, int $fontSize): void
    {
        Log::channel(self::CHANNEL)->info('book.page_turned', [
            'user_id'   => $userId,
            'book_id'   => $bookId,
            'page'      => $page,
            'position'  => $position,
            'font_size' => $fontSize,
        ]);
    }

    public function actionRejected(string $event, array $context): void
    {
        Log::channel(self::CHANNEL)->warning($event, $context);
    }

    public function lockContention(string $table, float $waitMs): void
    {
        Log::channel(self::CHANNEL)->warning('db.lock_contention', [
            'table'   => $table,
            'wait_ms' => $waitMs,
        ]);
    }
}
