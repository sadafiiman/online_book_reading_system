<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\User;
use App\Models\UserBook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LibraryApiTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): array
    {
        return ['X-User-Id' => $user->id];
    }

    #[Test]
    public function user_can_add_a_book_to_their_library(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->withHeaders($this->asUser($user))
            ->postJson('/api/library/books', ['book_id' => $book->id])
            ->assertCreated()
            ->assertJson(['success' => true, 'data' => ['book_id' => $book->id, 'title' => $book->title]]);

        $this->assertDatabaseHas('user_books', ['user_id' => $user->id, 'book_id' => $book->id]);
    }

    #[Test]
    public function adding_a_nonexistent_book_returns_404(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->asUser($user))
            ->postJson('/api/library/books', ['book_id' => 999999])
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function adding_a_book_twice_returns_409(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->withHeaders($this->asUser($user))->postJson('/api/library/books', ['book_id' => $book->id])->assertCreated();

        $this->withHeaders($this->asUser($user))
            ->postJson('/api/library/books', ['book_id' => $book->id])
            ->assertStatus(409)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function adding_a_book_requires_a_book_id(): void
    {
        $user = User::factory()->create();

        $this->withHeaders($this->asUser($user))
            ->postJson('/api/library/books', [])
            ->assertStatus(422);
    }

    #[Test]
    public function user_can_open_a_book_in_their_library(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['total_chars' => 20000]);

        UserBook::factory()->create([
            'user_id' => $user->id, 'book_id' => $book->id,
            'last_read_char_position' => 4000, 'is_active' => false,
        ]);

        $this->withHeaders($this->asUser($user))
            ->postJson("/api/library/books/{$book->id}/open", ['font_size' => 16])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['book_id' => $book->id, 'last_page' => 3, 'total_pages' => 10, 'font_size' => 16],
            ]);

        $this->assertDatabaseHas('user_books', ['user_id' => $user->id, 'book_id' => $book->id, 'is_active' => true]);
    }

    #[Test]
    public function opening_a_book_not_in_the_library_returns_404(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->withHeaders($this->asUser($user))
            ->postJson("/api/library/books/{$book->id}/open", ['font_size' => 16])
            ->assertStatus(404);
    }

    #[Test]
    public function opening_a_book_deactivates_the_previously_active_book(): void
    {
        $user  = User::factory()->create();
        $bookA = Book::factory()->create();
        $bookB = Book::factory()->create();

        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $bookA->id, 'is_active' => true]);
        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $bookB->id, 'is_active' => false]);

        $this->withHeaders($this->asUser($user))
            ->postJson("/api/library/books/{$bookB->id}/open", ['font_size' => 16])
            ->assertOk();

        $this->assertDatabaseHas('user_books', ['user_id' => $user->id, 'book_id' => $bookA->id, 'is_active' => false]);
        $this->assertDatabaseHas('user_books', ['user_id' => $user->id, 'book_id' => $bookB->id, 'is_active' => true]);
    }

    #[Test]
    public function rejects_an_out_of_range_font_size(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id]);

        $this->withHeaders($this->asUser($user))
            ->postJson("/api/library/books/{$book->id}/open", ['font_size' => 200])
            ->assertStatus(422);
    }

    #[Test]
    public function user_can_turn_the_page_of_the_active_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['total_chars' => 20000]);

        UserBook::factory()->create([
            'user_id' => $user->id, 'book_id' => $book->id,
            'is_active' => true, 'last_read_char_position' => 0, 'font_size' => 16,
        ]);

        $this->withHeaders($this->asUser($user))
            ->postJson("/api/library/books/{$book->id}/turn-page", ['font_size' => 16])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['book_id' => $book->id, 'current_page' => 2, 'total_pages' => 10, 'is_last_page' => false],
            ]);

        $this->assertDatabaseHas('user_books', ['user_id' => $user->id, 'book_id' => $book->id, 'last_read_char_position' => 2000]);
    }

    #[Test]
    public function turning_the_page_of_an_inactive_book_returns_422(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['total_chars' => 20000]);

        UserBook::factory()->create(['user_id' => $user->id, 'book_id' => $book->id, 'is_active' => false]);

        $this->withHeaders($this->asUser($user))
            ->postJson("/api/library/books/{$book->id}/turn-page", ['font_size' => 16])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function turning_the_page_past_the_end_returns_422(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create(['total_chars' => 20000]);

        UserBook::factory()->create([
            'user_id' => $user->id, 'book_id' => $book->id,
            'is_active' => true, 'last_read_char_position' => 19999, 'font_size' => 16,
        ]);

        $this->withHeaders($this->asUser($user))
            ->postJson("/api/library/books/{$book->id}/turn-page", ['font_size' => 16])
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    #[Test]
    public function turning_the_page_of_a_book_not_in_the_library_returns_404(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();

        $this->withHeaders($this->asUser($user))
            ->postJson("/api/library/books/{$book->id}/turn-page", ['font_size' => 16])
            ->assertStatus(404);
    }

    /**
     * End-to-end: add -> open -> turn pages -> reopen with a different
     * font size, and confirm the underlying position survived the change.
     *
     * @test
     */
    public function full_reading_flow_preserves_position_across_font_size_changes(): void
    {
        $user    = User::factory()->create();
        $book    = Book::factory()->create(['total_chars' => 20000]);
        $headers = $this->asUser($user);

        $this->withHeaders($headers)->postJson('/api/library/books', ['book_id' => $book->id])->assertCreated();
        $this->withHeaders($headers)->postJson("/api/library/books/{$book->id}/open", ['font_size' => 16])->assertOk();

        for ($i = 0; $i < 3; $i++) {
            $this->withHeaders($headers)
                ->postJson("/api/library/books/{$book->id}/turn-page", ['font_size' => 16])
                ->assertOk();
        }

        $this->assertDatabaseHas('user_books', [
            'user_id' => $user->id, 'book_id' => $book->id, 'last_read_char_position' => 6000, // 3 * 2000
        ]);

        $reopen = $this->withHeaders($headers)
            ->postJson("/api/library/books/{$book->id}/open", ['font_size' => 24])
            ->assertOk();

        $reopen->assertJsonPath('data.font_size', 24);

        // Position is unchanged by the font-size switch.
        $this->assertDatabaseHas('user_books', [
            'user_id' => $user->id, 'book_id' => $book->id, 'last_read_char_position' => 6000,
        ]);
    }
}
