<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRiddleCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', 'unique:riddle_categories,name,' . $this->route('category')?->id],
            'slug' => ['nullable', 'string', 'max:255', 'unique:riddle_categories,slug,' . $this->route('category')?->id],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
