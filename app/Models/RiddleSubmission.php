<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\RiddleHelper;

class RiddleSubmission extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'user_id',
        'category_id',
        'riddle_id',
        'question',
        'answer',
        'difficulty',
        'riddle_type',
        'hint',
        'hint2',
        'source',
        'status',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($submission) {
            if (empty($submission->answer)) {
                return;
            }
            $submission->answer = RiddleHelper::normalize($submission->answer);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category()
    {
        return $this->belongsTo(RiddleCategory::class);
    }

    /**
     * The published Riddle created when this submission was approved.
     */
    public function riddle()
    {
        return $this->belongsTo(Riddle::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}
