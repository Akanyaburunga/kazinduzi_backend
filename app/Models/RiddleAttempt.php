<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiddleAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'riddle_id',
        'submitted_answer',
        'is_correct',
        'rewarded',
    ];

    protected $casts = [
        'is_correct' => 'boolean',
        'rewarded'   => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function riddle()
    {
        return $this->belongsTo(Riddle::class);
    }
}
