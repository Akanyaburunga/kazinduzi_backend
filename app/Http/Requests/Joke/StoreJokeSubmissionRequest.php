<?php

namespace App\Http\Requests\Joke;

use Illuminate\Foundation\Http\FormRequest;

class StoreJokeSubmissionRequest extends FormRequest
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
            'punchline' => ['required', 'string', 'max:500'],
            'source' => ['required', 'string', 'max:255'],
        ];
    }
}