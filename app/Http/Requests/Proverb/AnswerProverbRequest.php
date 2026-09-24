<?php

namespace App\Http\Requests\Proverb;

use Illuminate\Foundation\Http\FormRequest;

class AnswerProverbRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'answer' => ['required', 'string', 'max:255'],
        ];
    }
}
