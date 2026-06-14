<?php

namespace App\Http\Requests;

class TurnPageRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'font_size' => ['sometimes', 'integer', 'between:8,40'],
        ];
    }
}
