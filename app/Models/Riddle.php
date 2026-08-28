<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Riddle extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'question',
        'answer',
        'difficulty',
        'hint',
        'hint2',
        'source',
        'riddle_type',
        'popularity_score',
        'is_suspended',
        'suspended_reason',
        'created_by',
    ];

    protected $casts = [
        'is_suspended' => 'boolean',
        'popularity_score' => 'integer',
    ];

    public const DIFFICULTIES = ['easy', 'medium', 'hard'];

    public const RIDDLE_TYPES = [
        'what_am_i',
        'what_is_it',
        'who_am_i',
        'riddle',
        'brain_teaser',
        'math',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($riddle) {
            if (empty($riddle->answer)) {
                return;
            }
            $riddle->answer = \App\Support\RiddleHelper::normalize($riddle->answer);
        });
    }

    public function category()
    {
        return $this->belongsTo(RiddleCategory::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attempts()
    {
        return $this->hasMany(RiddleAttempt::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
}
