<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TurnPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'font_size' => ['sometimes', 'integer', 'min:8', 'max:72'],
        ];
    }

    public function getUserId(): int
    {
        return (int) $this->input('_resolved_user_id');
    }
}
