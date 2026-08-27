<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRiddleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'exists:riddle_categories,id'],
            'question' => ['sometimes', 'required', 'string', 'max:1000'],
            'answer' => ['sometimes', 'required', 'string', 'max:255'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'hint' => ['nullable', 'string', 'max:500'],
            'hint2' => ['nullable', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }
}
