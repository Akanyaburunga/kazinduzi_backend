<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoundItem extends Model
{
    use HasFactory;

    public const PUZZLE_RIDDLE = 'riddle';
    public const PUZZLE_PROVERB = 'proverb';
    public const PUZZLE_JOKE = 'joke';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SOLVED = 'solved';
    public const STATUS_CONCEDED = 'conceded';

    protected $fillable = [
        'round_id',
        'puzzle_type',
        'puzzle_id',
        'position',
        'status',
        'is_correct',
        'attempts',
        'answered_at',
    ];

    protected $casts = [
        'puzzle_id' => 'integer',
        'position' => 'integer',
        'is_correct' => 'boolean',
        'attempts' => 'integer',
        'answered_at' => 'datetime',
    ];

    public function round()
    {
        return $this->belongsTo(Round::class);
    }

    public function isAnswered(): bool
    {
        return $this->status !== self::STATUS_PENDING;
    }

    /**
     * Resolve the concrete puzzle instance referenced by this round item.
     */
    public function puzzleModel()
    {
        return match ($this->puzzle_type) {
            self::PUZZLE_RIDDLE => Riddle::withTrashed()->find($this->puzzle_id),
            self::PUZZLE_PROVERB => Proverb::withTrashed()->find($this->puzzle_id),
            self::PUZZLE_JOKE => Joke::withTrashed()->find($this->puzzle_id),
            default => null,
        };
    }
}
