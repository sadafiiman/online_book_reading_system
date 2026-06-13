<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddBookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'book_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function getUserId(): int
    {
        return (int) $this->input('_resolved_user_id');
    }
}
