<?php

namespace App\Http\Requests\Riddle;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRiddleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'exists:riddle_categories,id'],
            'question' => ['sometimes', 'string'],
            'answer' => ['sometimes', 'string', 'max:255'],
            'hint' => ['nullable', 'string'],
        ];
    }
}
