<?php

namespace App\Http\Controllers;

use App\Exceptions\BookExceptions\BookAlreadyInLibraryException;
use App\Exceptions\BookExceptions\BookNotActiveException;
use App\Exceptions\BookExceptions\BookNotFoundException;
use App\Exceptions\BookExceptions\LastPageReachedException;
use App\Http\Requests\AddBookRequest;
use App\Http\Requests\OpenBookRequest;
use App\Http\Requests\TurnPageRequest;
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
            $result = $this->bookService->addToLibrary(
                userId: $request->getUserId(),
                bookId: $request->integer('book_id'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Book added to your library.',
                'data'    => $result,
            ], 201);
        } catch (BookNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (BookAlreadyInLibraryException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        }
    }

    /**
     * POST /api/library/books/{bookId}/open
     * Mark book as active, return last read page.
     */
    public function openBook(OpenBookRequest $request, int $bookId): JsonResponse
    {
        try {
            $result = $this->bookService->openBook(
                userId:   $request->getUserId(),
                bookId:   $bookId,
                fontSize: $request->integer('font_size', config('books.default_font_size', 16)),
            );

            return response()->json([
                'success' => true,
                'message' => 'Book opened successfully.',
                'data'    => $result,
            ]);
        } catch (BookNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }
    }

    /**
     * POST /api/library/books/{bookId}/turn-page
     * Turn to the next page of the active book.
     */
    public function turnPage(TurnPageRequest $request, int $bookId): JsonResponse
    {
        try {
            $result = $this->bookService->turnPage(
                userId:   $request->getUserId(),
                bookId:   $bookId,
                fontSize: $request->integer('font_size', config('books.default_font_size', 16)),
            );

            return response()->json([
                'success' => true,
                'message' => 'Page turned successfully.',
                'data'    => $result,
            ]);
        } catch (BookNotFoundException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        } catch (BookNotActiveException|LastPageReachedException $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    private function errorResponse(string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data'    => null,
        ], $status);
    }
}
