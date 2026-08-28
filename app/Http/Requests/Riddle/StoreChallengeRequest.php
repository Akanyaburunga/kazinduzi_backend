<?php

namespace App\Http\Requests\Riddle;

use Illuminate\Foundation\Http\FormRequest;

class StoreChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'opponent_id' => ['required', 'exists:users,id'],
            'riddle_id' => ['required', 'exists:riddles,id'],
            'wager' => ['nullable', 'integer', 'min:0', 'max:' . (int) config('riddles.duel_max_wager')],
        ];
    }
}
