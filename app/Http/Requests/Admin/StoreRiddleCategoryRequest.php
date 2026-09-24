<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreRiddleCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:riddle_categories,name'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:riddle_categories,slug'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
