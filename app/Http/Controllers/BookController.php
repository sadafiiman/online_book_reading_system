<?php

namespace App\Http\Controllers;

use App\DTOs\AddBookData;
use App\DTOs\OpenBookData;
use App\DTOs\TurnPageData;
use App\Exceptions\BookExceptions\BookAlreadyInLibraryException;
use App\Exceptions\BookExceptions\BookNotActiveException;
use App\Exceptions\BookExceptions\BookNotFoundException;
use App\Exceptions\BookExceptions\LastPageReachedException;
use App\Http\Requests\AddBookRequest;
use App\Http\Requests\OpenBookRequest;
use App\Http\Requests\TurnPageRequest;
use App\Http\Resources\BookProgressResource;
use App\Http\Resources\LibraryEntryResource;
use App\Http\Resources\TurnPageResultResource;
use App\Http\Responses\ApiResponse;
use App\Services\BookService;
use Illuminate\Http\JsonResponse;

class BookController extends Controller
{
    public function __construct(
        private readonly BookService $bookService,
    ) {}

    /**
     * POST /api/library/books
     * Add a book to the user's library.
     */
    public function addToLibrary(AddBookRequest $request): JsonResponse
    {
        try {
            $result = $this->bookService->addToLibrary(new AddBookData(
                userId: $request->getUserId(),
                bookId: $request->integer('book_id'),
            ));

            return ApiResponse::success(
                message: 'Book added to your library.',
                data: new LibraryEntryResource($result),
                status: 201,
            );
        } catch (BookNotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        } catch (BookAlreadyInLibraryException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }
    }

    /**
     * POST /api/library/books/{bookId}/open
     * Mark book as active, return last read page.
     */
    public function openBook(OpenBookRequest $request, int $bookId): JsonResponse
    {
        try {
            $result = $this->bookService->openBook(new OpenBookData(
                userId: $request->getUserId(),
                bookId: $bookId,
                fontSize: $request->integer('font_size', config('books.default_font_size', 16)),
            ));

            return ApiResponse::success(
                message: 'Book opened successfully.',
                data: new BookProgressResource($result),
            );
        } catch (BookNotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }
    }

    /**
     * POST /api/library/books/{bookId}/turn-page
     * Turn to the next page of the active book.
     */
    public function turnPage(TurnPageRequest $request, int $bookId): JsonResponse
    {
        try {
            $result = $this->bookService->turnPage(new TurnPageData(
                userId: $request->getUserId(),
                bookId: $bookId,
                fontSize: $request->integer('font_size', config('books.default_font_size', 16)),
            ));

            return ApiResponse::success(
                message: 'Page turned successfully.',
                data: new TurnPageResultResource($result),
            );
        } catch (BookNotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        } catch (BookNotActiveException|LastPageReachedException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
    }
}
