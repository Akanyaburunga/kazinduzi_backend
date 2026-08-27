<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Riddle extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'question',
        'answer',
        'difficulty',
        'hint',
        'hint2',
        'source',
        'is_suspended',
        'created_by',
    ];

    protected $casts = [
        'is_suspended' => 'boolean',
    ];

    public const DIFFICULTIES = ['easy', 'medium', 'hard'];

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
}
