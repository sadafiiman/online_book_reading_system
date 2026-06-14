<?php

namespace App\Repositories\Interfaces;

use App\Models\Book;
use App\Models\UserBook;

interface BookRepositoryInterface
{
    public function findById(int $bookId): ?Book;

    public function findUserBook(int $userId, int $bookId): ?UserBook;

    public function findActiveUserBook(int $userId): ?UserBook;

    public function addBookToLibrary(int $userId, int $bookId): UserBook;

    /**
     * Atomically deactivate any currently-active book for this user
     * and activate the given one.
     */
    public function switchActiveBook(int $userId, int $bookId): UserBook;

    public function turnPage(int $userId, int $bookId, int $fontSize): UserBook;
}
