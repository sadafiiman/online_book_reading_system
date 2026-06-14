<?php

namespace App\Http\Resources;

use App\DTOs\LibraryEntryData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LibraryEntryData
 */
class LibraryEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'book_id'  => $this->resource->bookId,
            'title'    => $this->resource->title,
            'author'   => $this->resource->author,
            'added_at' => $this->resource->addedAt->toISOString(),
        ];
    }
}
