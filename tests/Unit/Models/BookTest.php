<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BookTest extends TestCase
{
    #[Test]
    public function it_computes_pages_at_default_font_size(): void
    {
        $book = Book::factory()->make(['total_chars' => 20000]);

        $this->assertSame(10, $book->totalPagesForFontSize(16));
    }

    #[Test]
    public function larger_font_size_means_more_pages(): void
    {
        $book = Book::factory()->make(['total_chars' => 20000]);

        $this->assertGreaterThan(
            $book->totalPagesForFontSize(16),
            $book->totalPagesForFontSize(24)
        );
    }

    #[Test]
    public function smaller_font_size_means_fewer_pages(): void
    {
        $book = Book::factory()->make(['total_chars' => 20000]);

        $this->assertLessThan(
            $book->totalPagesForFontSize(16),
            $book->totalPagesForFontSize(12)
        );
    }

    #[Test]
    public function it_rounds_up_partial_pages(): void
    {
        // 20001 / 2000 = 10.0005 -> 11 pages
        $book = Book::factory()->make(['total_chars' => 20001]);

        $this->assertSame(11, $book->totalPagesForFontSize(16));
    }

    #[Test]
    public function a_book_with_no_content_has_zero_pages(): void
    {
        $book = Book::factory()->make(['total_chars' => 0]);

        $this->assertSame(0, $book->totalPagesForFontSize(16));
    }
}
