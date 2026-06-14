<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\UserBook;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UserBookTest extends TestCase
{
    private function makeUserBook(int $position, int $totalChars = 20000, int $fontSize = 16): UserBook
    {
        $book = Book::factory()->make(['id' => 1, 'total_chars' => $totalChars]);

        $userBook = UserBook::factory()->make([
            'last_read_char_position' => $position,
            'font_size'                => $fontSize,
            'is_active'                => true,
        ]);
        $userBook->setRelation('book', $book);

        return $userBook;
    }

    #[Test]
    public function current_page_is_derived_from_char_position(): void
    {
        $this->assertSame(1, $this->makeUserBook(0)->currentPage(16));
        $this->assertSame(2, $this->makeUserBook(2000)->currentPage(16));
        $this->assertSame(3, $this->makeUserBook(4500)->currentPage(16));
    }

    #[Test]
    public function advancing_moves_position_by_one_page_worth_of_chars(): void
    {
        $userBook = $this->makeUserBook(0);

        $userBook->advanceToNextPage(16);

        $this->assertSame(2000, $userBook->last_read_char_position);
        $this->assertSame(2, $userBook->currentPage(16));
    }

    #[Test]
    public function advancing_does_not_overshoot_the_end_of_the_book(): void
    {
        $userBook = $this->makeUserBook(position: 18500, totalChars: 20000);

        $userBook->advanceToNextPage(16);

        $this->assertSame(19999, $userBook->last_read_char_position);
    }

    #[Test]
    public function next_page_is_capped_at_total_pages(): void
    {
        $userBook = $this->makeUserBook(position: 19999, totalChars: 20000);

        $this->assertSame(10, $userBook->currentPage(16));
        $this->assertSame(10, $userBook->nextPage(16)); // not 11
    }

    /**
     * Core requirement: a font size change must not move the underlying
     * reading position, even though the displayed page number changes.
     *
     * @test
     */
    public function reading_position_survives_a_font_size_change(): void
    {
        $userBook = $this->makeUserBook(position: 4500, fontSize: 16);
        $this->assertSame(3, $userBook->currentPage(16));

        $pageAtLargerFont       = $userBook->currentPage(24);
        $totalPagesAtLargerFont = $userBook->book->totalPagesForFontSize(24);

        // The stored char position is untouched by the font size change.
        $this->assertSame(4500, $userBook->last_read_char_position);

        // The displayed page number changes (more pages at larger font),
        // but stays within the valid range for the new font size.
        $this->assertGreaterThan(3, $pageAtLargerFont);
        $this->assertLessThanOrEqual($totalPagesAtLargerFont, $pageAtLargerFont);
    }

    #[Test]
    public function turning_the_page_persists_the_font_size_used_for_that_turn(): void
    {
        $userBook = $this->makeUserBook(position: 0, fontSize: 16);

        $userBook->advanceToNextPage(24);

        $this->assertSame(24, $userBook->font_size);
    }
}
