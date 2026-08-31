<?php

namespace App\Http\Requests\Joke;

use Illuminate\Foundation\Http\FormRequest;

class AnswerJokeRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'option' => ['required', 'string', 'max:500'],
        ];
    }
}