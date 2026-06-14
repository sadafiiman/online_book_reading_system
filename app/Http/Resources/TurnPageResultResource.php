<?php

namespace App\Http\Resources;

use App\DTOs\TurnPageResultData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TurnPageResultData
 */
class TurnPageResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'book_id'      => $this->resource->bookId,
            'current_page' => $this->resource->currentPage,
            'total_pages'  => $this->resource->totalPages,
            'font_size'    => $this->resource->fontSize,
            'is_last_page' => $this->resource->isLastPage,
        ];
    }
}
