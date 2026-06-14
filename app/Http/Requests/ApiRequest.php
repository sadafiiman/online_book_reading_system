<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Stand-in for authentication per the take-home guidelines.
     * In production this would be auth()->id().
     */
    public function getUserId(): int
    {
        return (int) $this->header('X-User-Id');
    }
}
