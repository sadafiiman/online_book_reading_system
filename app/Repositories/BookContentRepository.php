<?php

namespace App\Repositories;

use App\Exceptions\BookExceptions\BookContentUnavailableException;
use App\Models\Book;
use App\Repositories\Interfaces\BookContentRepositoryInterface;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToReadFile;

class BookContentRepository implements BookContentRepositoryInterface
{
    public function getContent(Book $book): string
    {
        try {
            return Storage::disk('book')->get($book->file_path);
        } catch (UnableToReadFile $e) {
            throw new BookContentUnavailableException("Content file missing for book {$book->id}.", previous: $e);
        }
    }
}
