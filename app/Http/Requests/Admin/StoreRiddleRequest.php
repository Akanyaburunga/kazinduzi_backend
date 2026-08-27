<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreRiddleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'exists:riddle_categories,id'],
            'question' => ['required', 'string', 'max:1000'],
            'answer' => ['required', 'string', 'max:255'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'hint' => ['nullable', 'string', 'max:500'],
            'hint2' => ['nullable', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }
}
