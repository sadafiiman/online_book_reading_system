<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'author',
        'isbn',
        'total_chars',
        'file_path',
        'description',
    ];

    protected $casts = [
        'total_chars' => 'integer',
    ];

    public function userBooks(): HasMany
    {
        return $this->hasMany(UserBook::class);
    }

    /**
     * Calculate total pages based on font size.
     * Font size affects how many characters fit per page.
     * Base: 2000 chars/page at font size 16.
     * Larger font = fewer chars per page = more pages.
     */
    public function totalPagesForFontSize(int $fontSize): int
    {
        $baseCharsPerPage = (int) config('books.base_chars_per_page', 2000);
        $baseFontSize     = (int) config('books.default_font_size', 16);

        $charsPerPage = (int) ($baseCharsPerPage * ($baseFontSize / $fontSize));
        $charsPerPage = max(100, $charsPerPage); // safety floor

        return (int) ceil($this->total_chars / $charsPerPage);
    }
}
