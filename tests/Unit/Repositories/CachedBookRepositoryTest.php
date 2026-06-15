<?php

namespace Tests\Unit\Repositories;

use App\Logging\BookActivityLoggerInterface;
use App\Models\Book;
use App\Models\UserBook;
use App\Repositories\BookRepository;
use App\Repositories\CachedBookRepository;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CachedBookRepositoryTest extends TestCase
{
    private BookRepository|MockInterface $inner;
    private CachedBookRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::store('array')->flush();

        $this->inner       = Mockery::mock(BookRepository::class);
        $this->repository = new CachedBookRepository($this->inner, Mockery::spy(BookActivityLoggerInterface::class));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function find_by_id_is_cached_after_the_first_call(): void
    {
        $book = Book::factory()->make(['id' => 1]);

        $this->inner->shouldReceive('findById')->with(1)->once()->andReturn($book);

        $first  = $this->repository->findById(1);
        $second = $this->repository->findById(1); // served from cache

        $this->assertSame($book->title, $first->title);
        $this->assertSame($book->title, $second->title);
    }

    #[Test]
    public function find_user_book_is_cached_per_user_and_book(): void
    {
        $userBook = UserBook::factory()->make(['id' => 1, 'user_id' => 10, 'book_id' => 1]);

        $this->inner->shouldReceive('findUserBook')->with(10, 1)->once()->andReturn($userBook);

        $first  = $this->repository->findUserBook(10, 1);
        $second = $this->repository->findUserBook(10, 1); // mock expectation fails if called twice

        $this->assertSame($userBook->id, $first->id);
        $this->assertSame($userBook->user_id, $first->user_id);
        $this->assertSame($userBook->book_id, $first->book_id);

        $this->assertSame($userBook->id, $second->id);
        $this->assertSame($userBook->user_id, $second->user_id);
        $this->assertSame($userBook->book_id, $second->book_id);
    }

    #[Test]
    public function add_book_to_library_warms_the_user_book_cache(): void
    {
        $userBook = UserBook::factory()->make(['id' => 1, 'user_id' => 10, 'book_id' => 1]);

        $this->inner->shouldReceive('addBookToLibrary')->with(10, 1)->once()->andReturn($userBook);
        $this->inner->shouldNotReceive('findUserBook');

        $this->repository->addBookToLibrary(10, 1);
        $cached = $this->repository->findUserBook(10, 1);

        $this->assertSame(1, $cached->book_id);
    }

    #[Test]
    public function switching_active_book_invalidates_the_previously_active_books_cache(): void
    {
        $book1 = UserBook::factory()->make(['id' => 1, 'user_id' => 10, 'book_id' => 1, 'is_active' => true]);
        $book2 = UserBook::factory()->make(['id' => 2, 'user_id' => 10, 'book_id' => 2, 'is_active' => true]);

        $this->inner->shouldReceive('findActiveUserBook')->with(10)->once()->andReturn($book1);
        $this->inner->shouldReceive('switchActiveBook')->with(10, 2)->once()->andReturn($book2);

        // Simulate book1's entry already cached from an earlier read.
        Cache::put('user:10:book:1', $book1, 300);

        $this->repository->switchActiveBook(10, 2);

        $this->assertFalse(Cache::has('user:10:book:1')); // stale is_active=true purged
        $this->assertTrue(Cache::has('user:10:book:2'));
        $this->assertTrue(Cache::has('user:10:active_book'));
    }

    #[Test]
    public function turn_page_refreshes_user_book_and_active_book_cache(): void
    {
        $userBook = UserBook::factory()->make([
            'id' => 1, 'user_id' => 10, 'book_id' => 1,
            'is_active' => true, 'last_read_char_position' => 2000,
        ]);

        $this->inner->shouldReceive('turnPage')->with(10, 1, 16)->once()->andReturn($userBook);

        $result = $this->repository->turnPage(10, 1, 16);

        $this->assertSame(2000, $result->last_read_char_position);
        $this->assertTrue(Cache::has('user:10:book:1'));
        $this->assertTrue(Cache::has('user:10:active_book'));
    }

    #[Test]
    public function turn_page_always_calls_the_underlying_repository_never_only_cache(): void
    {
        $first  = UserBook::factory()->make(['id' => 1, 'user_id' => 10, 'book_id' => 1, 'is_active' => true, 'last_read_char_position' => 0]);
        $second = UserBook::factory()->make(['id' => 1, 'user_id' => 10, 'book_id' => 1, 'is_active' => true, 'last_read_char_position' => 2000]);

        $this->inner->shouldReceive('turnPage')->with(10, 1, 16)->twice()->andReturn($first, $second);

        $this->assertSame(0, $this->repository->turnPage(10, 1, 16)->last_read_char_position);
        $this->assertSame(2000, $this->repository->turnPage(10, 1, 16)->last_read_char_position);
    }
}
