<?php

namespace App\Http\Requests\Riddle;

use Illuminate\Foundation\Http\FormRequest;

class AnswerRiddleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'answer' => ['required', 'string', 'max:255'],
        ];
    }
}
