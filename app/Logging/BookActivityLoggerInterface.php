<?php

namespace App\Logging;

interface BookActivityLoggerInterface
{
    public function cacheHit(string $key): void;

    public function cacheMiss(string $key): void;

    public function cacheRefresh(array $keys): void;

    public function bookAddedToLibrary(int $userId, int $bookId): void;

    public function bookOpened(int $userId, int $bookId, int $page, int $fontSize): void;

    public function pageTurned(int $userId, int $bookId, int $page, int $position, int $fontSize): void;

    public function actionRejected(string $event, array $context): void;

    public function lockContention(string $table, float $waitMs): void;
}
