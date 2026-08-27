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
            'hint' => ['nullable', 'string', 'max:500'],
        ];
    }
}
