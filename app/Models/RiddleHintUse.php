<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiddleHintUse extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'riddle_id',
        'count',
    ];

    protected $casts = [
        'count' => 'integer',
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
