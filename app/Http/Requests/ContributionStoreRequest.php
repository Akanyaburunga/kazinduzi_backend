<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContributionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:sokwe,hera,tuja,other'],
            'body' => ['required', 'string', 'max:1000'],
            'answer' => ['nullable', 'string', 'max:500'],
            'who' => ['nullable', 'string', 'max:255'],
        ];
    }
}
