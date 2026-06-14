<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBook extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'book_id',
        'is_active',
        'last_read_char_position',
        'font_size',
    ];

    protected $casts = [
        'is_active'              => 'boolean',
        'last_read_char_position' => 'integer',
        'font_size'              => 'integer',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /**
     * Get the current page number for the stored char position at given font size.
     * This is the key insight: we persist character position (font-size-agnostic),
     * and compute the page number dynamically based on current font size.
     */
    public function currentPage(?int $fontSize = null): int
    {
        $fontSize        = $fontSize ?? $this->font_size ?? config('books.default_font_size', 16);
        $charsPerPage    = $this->charsPerPage($fontSize);
        $charPosition    = max(0, $this->last_read_char_position);

        return (int) floor($charPosition / $charsPerPage) + 1;
    }

    /**
     * Get the next page number.
     */
    public function nextPage(?int $fontSize = null): int
    {
        $fontSize      = $fontSize ?? $this->font_size ?? config('books.default_font_size', 16);
        $totalPages    = $this->book->totalPagesForFontSize($fontSize);
        $currentPage   = $this->currentPage($fontSize);

        return min($currentPage + 1, $totalPages);
    }

    /**
     * Advance to next page: move char position forward by one page worth of chars.
     */
    public function advanceToNextPage(?int $fontSize = null): void
    {
        $fontSize     = $fontSize ?? $this->font_size ?? config('books.default_font_size', 16);
        $charsPerPage = $this->charsPerPage($fontSize);
        $totalChars   = $this->book->total_chars;

        $newPosition = min(
            $this->last_read_char_position + $charsPerPage,
            $totalChars - 1
        );

        $this->last_read_char_position = $newPosition;
        $this->font_size               = $fontSize;
    }

    private function charsPerPage(int $fontSize): int
    {
        $baseCharsPerPage = (int) config('books.base_chars_per_page', 2000);
        $baseFontSize     = (int) config('books.default_font_size', 16);

        return max(100, (int) ($baseCharsPerPage * ($baseFontSize / $fontSize)));
    }
}
