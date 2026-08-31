<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Round extends Model
{
    use HasFactory;

    public const MODE_SOKWE = 'sokwe';
    public const MODE_HERA = 'hera';
    public const MODE_TUJA = 'tuja';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'user_id',
        'mode',
        'level',
        'item_count',
        'score',
        'current_streak',
        'best_streak',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'level' => 'integer',
        'item_count' => 'integer',
        'score' => 'integer',
        'current_streak' => 'integer',
        'best_streak' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(RoundItem::class)->orderBy('position');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
