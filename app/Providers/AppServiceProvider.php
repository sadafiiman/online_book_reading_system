<?php

namespace App\Providers;

use App\Logging\BookActivityLogger;
use App\Logging\BookActivityLoggerInterface;
use App\Repositories\BookContentRepository;
use App\Repositories\BookRepository;
use App\Repositories\CachedBookContentRepository;
use App\Repositories\CachedBookRepository;
use App\Repositories\Interfaces\BookContentRepositoryInterface;
use App\Repositories\Interfaces\BookRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BookActivityLoggerInterface::class, BookActivityLogger::class);

        $this->app->bind(BookRepositoryInterface::class, function ($app) {
            $logger = $app->make(BookActivityLoggerInterface::class);

            return new CachedBookRepository(
                new BookRepository($logger),
                $logger,
            );
        });

        $this->app->bind(BookContentRepositoryInterface::class, function ($app) {
            return new CachedBookContentRepository(
                new BookContentRepository(),
                $app->make(BookActivityLoggerInterface::class),
            );
        });
    }

    public function boot(): void {}
}
