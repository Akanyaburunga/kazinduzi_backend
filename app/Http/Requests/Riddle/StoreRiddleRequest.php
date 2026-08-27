<?php

namespace App\Http\Requests\Riddle;

use Illuminate\Foundation\Http\FormRequest;

class StoreRiddleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'exists:riddle_categories,id'],
            'question' => ['required', 'string'],
            'answer' => ['required', 'string', 'max:255'],
            'hint' => ['nullable', 'string'],
        ];
    }
}
