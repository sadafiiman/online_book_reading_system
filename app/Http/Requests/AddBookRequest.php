<?php

namespace App\Http\Requests;

class AddBookRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'book_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
