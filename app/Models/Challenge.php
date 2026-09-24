<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Challenge extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'initiator_id',
        'opponent_id',
        'riddle_id',
        'wager',
        'status',
        'winner_id',
        'accepted_at',
        'resolved_at',
    ];

    protected $casts = [
        'wager' => 'integer',
        'accepted_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function opponent()
    {
        return $this->belongsTo(User::class, 'opponent_id');
    }

    public function riddle()
    {
        return $this->belongsTo(Riddle::class);
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function attempts()
    {
        return $this->hasMany(ChallengeAttempt::class);
    }

    /**
     * Whether the challenge is still open to new attempts (pending accepted duels
     * that have not yet been resolved).
     */
    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    /**
     * Pending duels whose acceptance window has expired.
     */
    public function scopeStale($query, int $staleHours)
    {
        return $query
            ->where('status', self::STATUS_PENDING)
            ->where('created_at', '<', now()->subHours($staleHours));
    }
}
