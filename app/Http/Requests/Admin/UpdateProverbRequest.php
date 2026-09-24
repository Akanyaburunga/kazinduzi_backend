<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProverbRequest extends FormRequest
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
            'answer_aliases' => ['nullable', 'string', 'max:255'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }
}