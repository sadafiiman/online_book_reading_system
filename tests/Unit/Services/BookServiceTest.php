<?php

namespace Tests\Unit\Services;

use App\Exceptions\BookExceptions\BookAlreadyInLibraryException;
use App\Exceptions\BookExceptions\BookNotActiveException;
use App\Exceptions\BookExceptions\BookNotFoundException;
use App\Exceptions\BookExceptions\LastPageReachedException;
use App\Models\Book;
use App\Models\UserBook;
use App\Repositories\Interfaces\BookRepositoryInterface;
use App\Services\BookService;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookServiceTest extends TestCase
{
    private BookRepositoryInterface|MockInterface $repository;
    private BookService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = Mockery::mock(BookRepositoryInterface::class);
        $this->service    = new BookService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_adds_a_book_to_the_library(): void
    {
        $book     = Book::factory()->make(['id' => 1, 'title' => 'Clean Code', 'author' => 'Bob']);
        $userBook = UserBook::factory()->make(['id' => 1, 'user_id' => 10, 'book_id' => 1]);

        $this->repository->shouldReceive('findById')->with(1)->once()->andReturn($book);
        $this->repository->shouldReceive('findUserBook')->with(10, 1)->once()->andReturnNull();
        $this->repository->shouldReceive('addBookToLibrary')->with(10, 1)->once()->andReturn($userBook);

        $result = $this->service->addToLibrary(10, 1);

        $this->assertSame(1, $result['book_id']);
        $this->assertSame('Clean Code', $result['title']);
        $this->assertArrayHasKey('added_at', $result);
    }

    #[Test]
    public function it_throws_when_adding_a_nonexistent_book(): void
    {
        $this->repository->shouldReceive('findById')->with(999)->once()->andReturnNull();

        $this->expectException(BookNotFoundException::class);

        $this->service->addToLibrary(10, 999);
    }

    #[Test]
    public function it_throws_when_book_already_in_library(): void
    {
        $book     = Book::factory()->make(['id' => 1]);
        $existing = UserBook::factory()->make(['id' => 1, 'user_id' => 10, 'book_id' => 1]);

        $this->repository->shouldReceive('findById')->with(1)->once()->andReturn($book);
        $this->repository->shouldReceive('findUserBook')->with(10, 1)->once()->andReturn($existing);

        $this->expectException(BookAlreadyInLibraryException::class);

        $this->service->addToLibrary(10, 1);
    }

    #[Test]
    public function it_opens_a_book_and_returns_progress(): void
    {
        $book     = Book::factory()->make(['id' => 1, 'title' => 'Clean Code', 'total_chars' => 20000]);
        $existing = UserBook::factory()->make(['id' => 1, 'user_id' => 10, 'book_id' => 1, 'last_read_char_position' => 4000]);

        $activated = UserBook::factory()->make([
            'id' => 1, 'user_id' => 10, 'book_id' => 1,
            'last_read_char_position' => 4000, 'is_active' => true,
        ]);
        $activated->setRelation('book', $book);

        $this->repository->shouldReceive('findById')->with(1)->once()->andReturn($book);
        $this->repository->shouldReceive('findUserBook')->with(10, 1)->once()->andReturn($existing);
        $this->repository->shouldReceive('switchActiveBook')->with(10, 1)->once()->andReturn($activated);

        $result = $this->service->openBook(10, 1, 16);

        $this->assertSame(1, $result['book_id']);
        $this->assertSame(3, $result['last_page']);    // floor(4000/2000)+1
        $this->assertSame(10, $result['total_pages']); // 20000/2000
        $this->assertSame(16, $result['font_size']);
    }

    #[Test]
    public function it_throws_when_opening_a_nonexistent_book(): void
    {
        $this->repository->shouldReceive('findById')->with(999)->once()->andReturnNull();

        $this->expectException(BookNotFoundException::class);

        $this->service->openBook(10, 999, 16);
    }

    #[Test]
    public function it_throws_when_opening_a_book_not_in_the_library(): void
    {
        $book = Book::factory()->make(['id' => 1]);

        $this->repository->shouldReceive('findById')->with(1)->once()->andReturn($book);
        $this->repository->shouldReceive('findUserBook')->with(10, 1)->once()->andReturnNull();

        $this->expectException(BookNotFoundException::class);

        $this->service->openBook(10, 1, 16);
    }

    #[Test]
    public function it_turns_the_page_and_returns_progress(): void
    {
        $book     = Book::factory()->make(['id' => 1, 'total_chars' => 20000]);
        $userBook = UserBook::factory()->make([
            'id' => 1, 'user_id' => 10, 'book_id' => 1,
            'last_read_char_position' => 2000, 'is_active' => true,
        ]);
        $userBook->setRelation('book', $book);

        $this->repository->shouldReceive('turnPage')->with(10, 1, 16)->once()->andReturn($userBook);

        $result = $this->service->turnPage(10, 1, 16);

        $this->assertSame(1, $result['book_id']);
        $this->assertSame(2, $result['current_page']);
        $this->assertSame(10, $result['total_pages']);
        $this->assertFalse($result['is_last_page']);
    }

    #[Test]
    public function it_propagates_last_page_reached(): void
    {
        $this->repository->shouldReceive('turnPage')
            ->with(10, 1, 16)
            ->once()
            ->andThrow(new LastPageReachedException('You have reached the last page of this book.'));

        $this->expectException(LastPageReachedException::class);

        $this->service->turnPage(10, 1, 16);
    }

    #[Test]
    public function it_propagates_book_not_active(): void
    {
        $this->repository->shouldReceive('turnPage')
            ->with(10, 1, 16)
            ->once()
            ->andThrow(new BookNotActiveException('Book is not currently active. Open the book first.'));

        $this->expectException(BookNotActiveException::class);

        $this->service->turnPage(10, 1, 16);
    }
}
