<?php

namespace App\Http\Requests\Joke;

use Illuminate\Foundation\Http\FormRequest;

class StoreJokeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'exists:riddle_categories,id'],
            'setup' => ['required', 'string', 'max:1000'],
            'punchline' => ['required', 'string', 'max:500'],
            'distractors' => ['nullable', 'array', 'min:0'],
            'distractors.*' => ['string', 'max:500'],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }
}