<?php

namespace App\Repositories\Interfaces;

use App\Models\Book;
use App\Models\UserBook;

interface BookRepositoryInterface
{
    public function findById(int $id): ?Book;

    public function findUserBook(int $userId, int $bookId): ?UserBook;

    public function findActiveUserBook(int $userId): ?UserBook;

    public function addBookToLibrary(int $userId, int $bookId): UserBook;

    public function deactivateAllUserBooks(int $userId): void;

    public function activateUserBook(UserBook $userBook): UserBook;

    /**
     * Atomically advance the page for a user's active book.
     * Must handle race conditions with DB-level locking.
     *
     * @throws \App\Exceptions\BookExceptions\BookNotActiveException
     * @throws \App\Exceptions\BookExceptions\LastPageReachedException
     */
    public function turnPage(int $userId, int $bookId, int $fontSize): UserBook;
}
