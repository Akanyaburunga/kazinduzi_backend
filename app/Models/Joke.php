<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Joke extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'setup',
        'punchline',
        'distractors',
        'source',
        'is_suspended',
        'suspended_reason',
        'created_by',
    ];

    protected $casts = [
        'distractors' => 'array',
        'is_suspended' => 'boolean',
    ];

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
        return $this->hasMany(JokeAttempt::class);
    }
}