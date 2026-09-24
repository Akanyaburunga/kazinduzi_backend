<?php

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

class StartRoundRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'level' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('riddles.round_levels', 5)],
        ];
    }
}
