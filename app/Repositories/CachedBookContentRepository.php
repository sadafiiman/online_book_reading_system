<?php

namespace App\Repositories;

use App\Logging\BookActivityLoggerInterface;
use App\Models\Book;
use App\Repositories\Interfaces\BookContentRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class CachedBookContentRepository implements BookContentRepositoryInterface
{
    private const CONTENT_TTL = 86400; // 24h — book content is static

    public function __construct(
        private readonly BookContentRepository $repository,
        private readonly BookActivityLoggerInterface $activityLogger,
    ) {}

    public function getContent(Book $book): string
    {
        $key = $this->contentKey($book->id);

        if (Cache::has($key)) {
            $this->activityLogger->cacheHit($key);

            return Cache::get($key);
        }

        $this->activityLogger->cacheMiss($key);

        $content = $this->repository->getContent($book);

        Cache::put($key, $content, self::CONTENT_TTL);

        return $content;
    }

    private function contentKey(int $bookId): string
    {
        return "book_content:{$bookId}";
    }
}
