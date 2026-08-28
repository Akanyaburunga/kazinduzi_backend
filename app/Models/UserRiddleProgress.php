<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRiddleProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'riddle_id',
        'hints_revealed',
        'last_hinted_at',
    ];

    protected $casts = [
        'hints_revealed' => 'integer',
        'last_hinted_at' => 'datetime',
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
