<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class AddBookRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'book_id' => ['required', 'integer', 'min:1', Rule::exists('books', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'book_id.required' => 'A book_id is required.',
            'book_id.integer'  => 'book_id must be an integer.',
            'book_id.min'      => 'book_id must be a positive integer.',
        ];
    }
}
