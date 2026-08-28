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
            'riddle_type' => ['nullable', 'in:what_am_i,what_is_it,who_am_i,riddle,brain_teaser,math'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['required', 'distinct', 'present'],
            'hint' => ['nullable', 'string', 'max:500'],
            'hint2' => ['nullable', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }
}
