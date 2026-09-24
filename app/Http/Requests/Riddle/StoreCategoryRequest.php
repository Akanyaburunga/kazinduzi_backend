<?php

namespace App\Http\Requests\Riddle;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:riddle_categories,slug'],
            'description' => ['nullable', 'string'],
        ];
    }
}
