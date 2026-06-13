<?php

namespace Tests\Unit\BookTests;

use App\Models\Book;
use App\Models\UserBook;
use Tests\TestCase;

class PageCalculationTest extends TestCase
{
    private function makeBook(int $totalChars): Book
    {
        $book              = new Book();
        $book->total_chars = $totalChars;

        return $book;
    }

    private function makeUserBook(Book $book, int $charPosition, int $fontSize = 16): UserBook
    {
        $userBook                         = new UserBook();
        $userBook->book                   = $book;
        $userBook->last_read_char_position = $charPosition;
        $userBook->font_size              = $fontSize;

        return $userBook;
    }

    // ---------- Book::totalPagesForFontSize ----------

    public function test_total_pages_at_default_font_size(): void
    {
        // 20000 chars / 2000 chars_per_page = 10 pages
        $book = $this->makeBook(20000);
        $this->assertEquals(10, $book->totalPagesForFontSize(16));
    }

    public function test_more_pages_at_larger_font_size(): void
    {
        // font 32 = 2x default => chars_per_page = 1000 => 20000 / 1000 = 20 pages
        $book = $this->makeBook(20000);
        $this->assertEquals(20, $book->totalPagesForFontSize(32));
    }

    public function test_fewer_pages_at_smaller_font_size(): void
    {
        // font 8 = 0.5x default => chars_per_page = 4000 => 20000 / 4000 = 5 pages
        $book = $this->makeBook(20000);
        $this->assertEquals(5, $book->totalPagesForFontSize(8));
    }

    public function test_partial_pages_ceil_correctly(): void
    {
        // 2100 chars at font 16 => 2100/2000 = 1.05 => ceil = 2 pages
        $book = $this->makeBook(2100);
        $this->assertEquals(2, $book->totalPagesForFontSize(16));
    }

    // ---------- UserBook::currentPage ----------

    public function test_starts_at_page_1_when_at_char_0(): void
    {
        $book     = $this->makeBook(20000);
        $userBook = $this->makeUserBook($book, 0);
        $this->assertEquals(1, $userBook->currentPage(16));
    }

    public function test_page_2_starts_at_chars_per_page(): void
    {
        $book     = $this->makeBook(20000);
        $userBook = $this->makeUserBook($book, 2000); // exactly at char 2000
        $this->assertEquals(2, $userBook->currentPage(16));
    }

    public function test_same_char_position_yields_different_pages_for_different_font_sizes(): void
    {
        $book     = $this->makeBook(20000);
        $userBook = $this->makeUserBook($book, 4000);

        // font 16: chars_per_page=2000, page = floor(4000/2000)+1 = 3
        $this->assertEquals(3, $userBook->currentPage(16));

        // font 32: chars_per_page=1000, page = floor(4000/1000)+1 = 5
        $this->assertEquals(5, $userBook->currentPage(32));
    }

    // ---------- UserBook::advanceToNextPage ----------

    public function test_advance_moves_by_one_page_of_chars(): void
    {
        $book     = $this->makeBook(20000);
        $userBook = $this->makeUserBook($book, 0);

        $userBook->advanceToNextPage(16);

        // Should move forward by 2000 chars (one page at font 16)
        $this->assertEquals(2000, $userBook->last_read_char_position);
    }

    public function test_advance_does_not_exceed_total_chars(): void
    {
        $book     = $this->makeBook(2500);
        // Position it near the end
        $userBook = $this->makeUserBook($book, 2000);

        $userBook->advanceToNextPage(16);

        // Max is total_chars - 1 = 2499
        $this->assertLessThanOrEqual(2499, $userBook->last_read_char_position);
    }

    public function test_advance_at_larger_font_moves_fewer_chars(): void
    {
        $book         = $this->makeBook(20000);
        $userBook     = $this->makeUserBook($book, 0);
        $userBook32   = $this->makeUserBook($book, 0);

        $userBook->advanceToNextPage(16);   // moves 2000 chars
        $userBook32->advanceToNextPage(32); // moves 1000 chars

        $this->assertGreaterThan(
            $userBook32->last_read_char_position,
            $userBook->last_read_char_position
        );
    }

    // ---------- Race condition safety (conceptual) ----------

    public function test_char_position_is_idempotent_to_font_size_changes(): void
    {
        // If user reads 3 pages at font 16, then changes to font 32 and reads 1 more page,
        // the char position should remain consistent regardless of font size.
        $book     = $this->makeBook(20000);
        $userBook = $this->makeUserBook($book, 0);

        // Simulate reading 3 pages at font 16
        $userBook->advanceToNextPage(16); // char 2000, page 2
        $userBook->advanceToNextPage(16); // char 4000, page 3
        $userBook->advanceToNextPage(16); // char 6000, page 4

        // Now switch to font 32: char 6000 / 1000 = page 7
        $this->assertEquals(7, $userBook->currentPage(32));

        // Turn one more page at font 32 → char 7000 → page 8
        $userBook->advanceToNextPage(32);
        $this->assertEquals(8, $userBook->currentPage(32));

        // Switch back to font 16: char 7000 / 2000 = page 4 (floor) + 1 = page 4.5 → 4
        $this->assertEquals(4, $userBook->currentPage(16));
    }
}
