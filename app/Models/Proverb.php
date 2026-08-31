<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proverb extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'question',
        'answer',
        'answer_aliases',
        'difficulty',
        'source',
        'is_suspended',
        'suspended_reason',
        'created_by',
    ];

    protected $casts = [
        'is_suspended' => 'boolean',
    ];

    public const DIFFICULTIES = ['easy', 'medium', 'hard'];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($proverb) {
            if (empty($proverb->answer)) {
                return;
            }
            $proverb->answer = \App\Support\RiddleHelper::normalize($proverb->answer);
            if (! empty($proverb->answer_aliases)) {
                $proverb->answer_aliases = \App\Support\RiddleHelper::normalize($proverb->answer_aliases);
            }
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
        return $this->hasMany(ProverbAttempt::class);
    }
}
