<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class AnswerRoundItemRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'answer' => ['nullable', 'string', 'max:255'],
            'option' => ['nullable', 'string', 'max:255'],
        ];
    }
}
