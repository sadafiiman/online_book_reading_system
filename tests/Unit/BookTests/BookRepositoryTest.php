<?php

namespace Tests\Unit\BookTests;

use App\Exceptions\BookExceptions\BookNotActiveException;
use App\Exceptions\BookExceptions\LastPageReachedException;
use App\Models\Book;
use App\Models\UserBook;
use App\Repositories\BookRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private BookRepository $repository;
    private Book $book;
    private int $userId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new BookRepository();

        $this->book = Book::create([
            'title'       => 'Race Condition Test Book',
            'author'      => 'Concurrency Author',
            'isbn'        => '978-0000099999',
            'total_chars' => 10000,
        ]);
    }

    public function test_turn_page_throws_if_book_not_active(): void
    {
        UserBook::create([
            'user_id'                => $this->userId,
            'book_id'                => $this->book->id,
            'is_active'              => false,
            'last_read_char_position' => 0,
            'font_size'              => 16,
        ]);

        $this->expectException(BookNotActiveException::class);
        $this->repository->turnPage($this->userId, $this->book->id, 16);
    }

    public function test_turn_page_throws_on_last_page(): void
    {
        UserBook::create([
            'user_id'                => $this->userId,
            'book_id'                => $this->book->id,
            'is_active'              => true,
            'last_read_char_position' => 9999, // last char
            'font_size'              => 16,
        ]);

        $this->expectException(LastPageReachedException::class);
        $this->repository->turnPage($this->userId, $this->book->id, 16);
    }

    public function test_turn_page_advances_char_position(): void
    {
        UserBook::create([
            'user_id'                => $this->userId,
            'book_id'                => $this->book->id,
            'is_active'              => true,
            'last_read_char_position' => 0,
            'font_size'              => 16,
        ]);

        $userBook = $this->repository->turnPage($this->userId, $this->book->id, 16);

        $this->assertGreaterThan(0, $userBook->last_read_char_position);
    }

    public function test_sequential_page_turns_advance_linearly(): void
    {
        UserBook::create([
            'user_id'                => $this->userId,
            'book_id'                => $this->book->id,
            'is_active'              => true,
            'last_read_char_position' => 0,
            'font_size'              => 16,
        ]);

        $ub1 = $this->repository->turnPage($this->userId, $this->book->id, 16);
        $ub2 = $this->repository->turnPage($this->userId, $this->book->id, 16);
        $ub3 = $this->repository->turnPage($this->userId, $this->book->id, 16);

        $this->assertEquals(2, $ub1->currentPage(16));
        $this->assertEquals(3, $ub2->currentPage(16));
        $this->assertEquals(4, $ub3->currentPage(16));
    }

    public function test_add_book_to_library_is_idempotent_on_first_create(): void
    {
        $userBook = $this->repository->addBookToLibrary($this->userId, $this->book->id);
        $this->assertEquals($this->userId, $userBook->user_id);
        $this->assertEquals($this->book->id, $userBook->book_id);
        $this->assertFalse((bool) $userBook->is_active);
    }

    public function test_deactivate_all_sets_is_active_false(): void
    {
        UserBook::create([
            'user_id'  => $this->userId, 'book_id' => $this->book->id,
            'is_active' => true, 'last_read_char_position' => 0, 'font_size' => 16,
        ]);

        $this->repository->deactivateAllUserBooks($this->userId);

        $this->assertDatabaseHas('user_books', [
            'user_id'  => $this->userId,
            'book_id'  => $this->book->id,
            'is_active' => false,
        ]);
    }
}
