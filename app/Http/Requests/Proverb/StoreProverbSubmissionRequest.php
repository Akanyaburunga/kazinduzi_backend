<?php

namespace App\Http\Requests\Proverb;

use Illuminate\Foundation\Http\FormRequest;

class StoreProverbSubmissionRequest extends FormRequest
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
            'answer' => ['required', 'string', 'max:500'],
            'difficulty' => ['nullable', 'in:easy,medium,hard'],
            'source' => ['required', 'string', 'max:255'],
        ];
    }
}