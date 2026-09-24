<?php

namespace App\Http\Requests\Proverb;

use App\Models\Proverb;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProverbRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'exists:riddle_categories,id'],
            'question' => ['required', 'string', 'max:1000'],
            'answer' => ['required', 'string', 'max:500'],
            'answer_aliases' => ['nullable', 'string'],
            'difficulty' => ['nullable', Rule::in(Proverb::DIFFICULTIES)],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }
}
