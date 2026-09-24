<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreJokeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'exists:riddle_categories,id'],
            'setup' => ['required', 'string', 'max:1000'],
            'punchline' => ['required', 'string', 'max:255'],
            'distractors' => ['nullable', 'array'],
            'distractors.*' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }
}