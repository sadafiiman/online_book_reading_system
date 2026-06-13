<?php

namespace Tests\Feature\BookTests;

use App\Models\Book;
use App\Models\UserBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookApiTest extends TestCase
{
    use RefreshDatabase;

    private Book $book;
    private int $userId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->book = Book::create([
            'title'       => 'Test Book',
            'author'      => 'Test Author',
            'isbn'        => '978-0000000001',
            'total_chars' => 20000, // 10 pages at font 16 (2000 chars/page)
            'description' => 'A test book',
        ]);
    }

    // ---------- Add Book to Library ----------

    public function test_can_add_book_to_library(): void
    {
        $response = $this->postJson('/api/library/books', [
            'book_id' => $this->book->id,
        ], ['X-User-ID' => $this->userId]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'book_id' => $this->book->id,
                    'title'   => 'Test Book',
                    'author'  => 'Test Author',
                ],
            ]);
    }

    public function test_cannot_add_nonexistent_book(): void
    {
        $response = $this->postJson('/api/library/books', [
            'book_id' => 99999,
        ], ['X-User-ID' => $this->userId]);

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    public function test_cannot_add_same_book_twice(): void
    {
        UserBook::create([
            'user_id' => $this->userId,
            'book_id' => $this->book->id,
            'is_active' => false,
            'last_read_char_position' => 0,
            'font_size' => 16,
        ]);

        $response = $this->postJson('/api/library/books', [
            'book_id' => $this->book->id,
        ], ['X-User-ID' => $this->userId]);

        $response->assertStatus(409)
            ->assertJson(['success' => false]);
    }

    public function test_returns_400_without_user_id_header(): void
    {
        $response = $this->postJson('/api/library/books', ['book_id' => $this->book->id]);

        $response->assertStatus(400);
    }

    public function test_returns_400_with_invalid_user_id_header(): void
    {
        $response = $this->postJson('/api/library/books', [
            'book_id' => $this->book->id,
        ], ['X-User-ID' => 'not-an-integer']);

        $response->assertStatus(400);
    }

    // ---------- Open Book ----------

    public function test_can_open_book_in_library(): void
    {
        UserBook::create([
            'user_id'                => $this->userId,
            'book_id'                => $this->book->id,
            'is_active'              => false,
            'last_read_char_position' => 4000,
            'font_size'              => 16,
        ]);

        $response = $this->postJson(
            "/api/library/books/{$this->book->id}/open",
            ['font_size' => 16],
            ['X-User-ID' => $this->userId]
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'book_id'   => $this->book->id,
                    'last_page' => 3, // 4000 chars / 2000 chars_per_page = page 3
                ],
            ]);
    }

    public function test_open_book_deactivates_previous_active_book(): void
    {
        $book2 = Book::create([
            'title'       => 'Book Two',
            'author'      => 'Author Two',
            'isbn'        => '978-0000000002',
            'total_chars' => 10000,
        ]);

        $userBook1 = UserBook::create([
            'user_id' => $this->userId, 'book_id' => $this->book->id,
            'is_active' => true, 'last_read_char_position' => 0, 'font_size' => 16,
        ]);
        $userBook2 = UserBook::create([
            'user_id' => $this->userId, 'book_id' => $book2->id,
            'is_active' => false, 'last_read_char_position' => 0, 'font_size' => 16,
        ]);

        $this->postJson(
            "/api/library/books/{$book2->id}/open",
            [],
            ['X-User-ID' => $this->userId]
        )->assertStatus(200);

        $this->assertDatabaseHas('user_books', ['id' => $userBook1->id, 'is_active' => false]);
        $this->assertDatabaseHas('user_books', ['id' => $userBook2->id, 'is_active' => true]);
    }

    public function test_cannot_open_book_not_in_library(): void
    {
        $response = $this->postJson(
            "/api/library/books/{$this->book->id}/open",
            [],
            ['X-User-ID' => $this->userId]
        );

        $response->assertStatus(404);
    }

    // ---------- Turn Page ----------

    public function test_can_turn_page_on_active_book(): void
    {
        UserBook::create([
            'user_id'                => $this->userId,
            'book_id'                => $this->book->id,
            'is_active'              => true,
            'last_read_char_position' => 0,
            'font_size'              => 16,
        ]);

        $response = $this->postJson(
            "/api/library/books/{$this->book->id}/turn-page",
            ['font_size' => 16],
            ['X-User-ID' => $this->userId]
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'current_page' => 2,
                    'is_last_page' => false,
                ],
            ]);
    }

    public function test_cannot_turn_page_on_inactive_book(): void
    {
        UserBook::create([
            'user_id'  => $this->userId,
            'book_id'  => $this->book->id,
            'is_active' => false,
            'last_read_char_position' => 0,
            'font_size' => 16,
        ]);

        $response = $this->postJson(
            "/api/library/books/{$this->book->id}/turn-page",
            [],
            ['X-User-ID' => $this->userId]
        );

        $response->assertStatus(422);
    }

    public function test_cannot_turn_page_past_last_page(): void
    {
        UserBook::create([
            'user_id'                => $this->userId,
            'book_id'                => $this->book->id,
            'is_active'              => true,
            'last_read_char_position' => 19999, // at last char
            'font_size'              => 16,
        ]);

        $response = $this->postJson(
            "/api/library/books/{$this->book->id}/turn-page",
            ['font_size' => 16],
            ['X-User-ID' => $this->userId]
        );

        $response->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }

    public function test_page_changes_with_font_size_but_position_is_preserved(): void
    {
        // Store position at char 4000 (page 3 at font 16)
        UserBook::create([
            'user_id'                => $this->userId,
            'book_id'                => $this->book->id,
            'is_active'              => true,
            'last_read_char_position' => 4000,
            'font_size'              => 16,
        ]);

        // At font 32 (double size), chars_per_page = 1000, so page = 4000/1000 + 1 = 5
        $response = $this->postJson(
            "/api/library/books/{$this->book->id}/turn-page",
            ['font_size' => 32],
            ['X-User-ID' => $this->userId]
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => ['current_page' => 6], // turned from page 5 to 6
            ]);
    }

    public function test_validates_font_size_range(): void
    {
        UserBook::create([
            'user_id' => $this->userId, 'book_id' => $this->book->id,
            'is_active' => true, 'last_read_char_position' => 0, 'font_size' => 16,
        ]);

        $this->postJson(
            "/api/library/books/{$this->book->id}/turn-page",
            ['font_size' => 200], // out of range
            ['X-User-ID' => $this->userId]
        )->assertStatus(422);
    }
}
