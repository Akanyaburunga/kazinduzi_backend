<?php

namespace App\Http\Requests\Riddle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'slug' => ['sometimes', 'string', 'max:100', Rule::unique('riddle_categories', 'slug')->ignore($this->category)],
            'description' => ['nullable', 'string'],
        ];
    }
}
