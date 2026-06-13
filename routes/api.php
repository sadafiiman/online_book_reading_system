<?php

use App\Http\Controllers\BookController;
use App\Http\Middleware\ResolveUserIdMiddleware;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| All routes require the X-User-ID header (handled by ResolveUserId middleware).
| No authentication is implemented per task requirements.
*/

Route::middleware([ResolveUserIdMiddleware::class])->prefix('library')->group(function () {

    // Add a book to the user's library
    // POST /api/library/books
    // Body: { "book_id": 1 }
    Route::post('/books', [BookController::class, 'addToLibrary']);

    // Open a book (mark as active, return last page)
    // POST /api/library/books/{bookId}/open
    // Body (optional): { "font_size": 18 }
    Route::post('/books/{bookId}/open', [BookController::class, 'openBook'])
        ->where('bookId', '[0-9]+');

    // Turn to the next page
    // POST /api/library/books/{bookId}/turn-page
    // Body (optional): { "font_size": 18 }
    Route::post('/books/{bookId}/turn-page', [BookController::class, 'turnPage'])
        ->where('bookId', '[0-9]+');
});
