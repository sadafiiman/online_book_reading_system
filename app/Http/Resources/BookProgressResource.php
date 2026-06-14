<?php

namespace App\Http\Resources;

use App\DTOs\BookProgressData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookProgressData
 */
class BookProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'book_id'     => $this->resource->bookId,
            'title'       => $this->resource->title,
            'last_page'   => $this->resource->lastPage,
            'total_pages' => $this->resource->totalPages,
            'font_size'   => $this->resource->fontSize,
        ];
    }
}
