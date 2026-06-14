<?php

namespace Tests\Feature\Repositories;

use App\Exceptions\BookExceptions\BookNotActiveException;
use App\Exceptions\BookExceptions\BookNotFoundException;
use App\Exceptions\BookExceptions\LastPageReachedException;
use App\Models\Book;
use App\Models\User;
use App\Models\UserBook;
use App\Repositories\BookRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private BookRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new BookRepository();
    }

    #[Test]
    public function adding_a_book_twice_returns_the_same_row(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $first  = $this->repository->addBookToLibrary($user->id, $book->id);
        $second = $this->repository->addBookToLibrary($user->id, $book->id);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('user_books', 1);
    }

    #[Test]
    public function switching_active_book_deactivates_the_previous_one(): void
    {
        $user  = User::factory()->create();
        $bookA = Book::factory()->create();
        $bookB = Book::factory()->create();

        $entryA = UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $bookA->id, 'is_active' => true]);
        $entryB = UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $bookB->id, 'is_active' => false]);

        $this->repository->switchActiveBook($user->id, $bookB->id);

        $this->assertFalse($entryA->fresh()->is_active);
        $this->assertTrue($entryB->fresh()->is_active);
    }

    #[Test]
    public function switching_to_the_already_active_book_is_a_no_op(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $entry = UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id, 'is_active' => true]);

        $result = $this->repository->switchActiveBook($user->id, $book->id);

        $this->assertTrue($result->is_active);
        $this->assertDatabaseHas('user_books', ['id' => $entry->id, 'is_active' => true]);
    }

    #[Test]
    public function switching_active_book_throws_if_not_in_library(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->expectException(BookNotFoundException::class);

        $this->repository->switchActiveBook($user->id, $book->id);
    }

    #[Test]
    public function a_user_never_ends_up_with_more_than_one_active_book(): void
    {
        $user  = User::factory()->create();
        $books = Book::factory()->count(3)->create();

        foreach ($books as $book) {
            UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id, 'is_active' => false]);
        }

        $this->repository->switchActiveBook($user->id, $books[0]->id);
        $this->repository->switchActiveBook($user->id, $books[1]->id);
        $this->repository->switchActiveBook($user->id, $books[2]->id);

        $activeCount = UserBook::where('user_id', $user->id)->where('is_active', true)->count();

        $this->assertSame(1, $activeCount);
    }

    #[Test]
    public function turn_page_advances_position_by_one_page(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['total_chars' => 20000]);

        UserBook::factory()->create([
            'user_id' => $user->id, 'book_id' => $book->id,
            'is_active' => true, 'last_read_char_position' => 0, 'font_size' => 16,
        ]);

        $result = $this->repository->turnPage($user->id, $book->id, 16);

        $this->assertSame(2000, $result->last_read_char_position);
    }

    #[Test]
    public function turn_page_throws_if_book_is_not_active(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['total_chars' => 20000]);

        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id, 'is_active' => false]);

        $this->expectException(BookNotActiveException::class);

        $this->repository->turnPage($user->id, $book->id, 16);
    }

    #[Test]
    public function turn_page_throws_when_book_not_in_library(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->expectException(BookNotFoundException::class);

        $this->repository->turnPage($user->id, $book->id, 16);
    }

    #[Test]
    public function turn_page_throws_on_the_last_page(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['total_chars' => 20000]);

        UserBook::factory()->create([
            'user_id' => $user->id, 'book_id' => $book->id,
            'is_active' => true, 'last_read_char_position' => 19999, 'font_size' => 16,
        ]);

        $this->expectException(LastPageReachedException::class);

        $this->repository->turnPage($user->id, $book->id, 16);
    }

    /**
     * The property that lockForUpdate protects under concurrency: each
     * call advances by exactly one page, never duplicating or skipping.
     *
     * @test
     */
    public function repeated_turn_page_calls_advance_monotonically(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['total_chars' => 20000]); // 10 pages

        UserBook::factory()->create([
            'user_id' => $user->id, 'book_id' => $book->id,
            'is_active' => true, 'last_read_char_position' => 0, 'font_size' => 16,
        ]);

        $positions = [];
        for ($i = 0; $i < 9; $i++) {
            $positions[] = $this->repository->turnPage($user->id, $book->id, 16)->last_read_char_position;
        }

        $this->assertSame([2000, 4000, 6000, 8000, 10000, 12000, 14000, 16000, 18000], $positions);

        $this->expectException(LastPageReachedException::class);
        $this->repository->turnPage($user->id, $book->id, 16);
    }
}
