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
            return new CachedBookRepository(new BookRepository());
        });
    }

    public function boot(): void {}
}
