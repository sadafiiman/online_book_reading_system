<?php

namespace App\Providers;

use App\Repositories\BookRepository;
use App\Repositories\CachedBookRepository;
use App\Repositories\Interfaces\BookRepositoryInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BookRepositoryInterface::class, function () {
            // Decorator pattern wired here — the only place in the entire
            // codebase that knows CachedBookRepository exists.
            //
            // BookService  →  CachedBookRepository  →  BookRepository  →  MySQL
            //                        ↕
            //                      Redis
            //
            // To disable caching entirely (e.g. in tests): swap to BookRepository::class directly.
            return new CachedBookRepository(
                new BookRepository()
            );
        });
    }

    public function boot(): void {}
}
