<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'meaning_id', 'vote'];

    public function meaning()
    {
        return $this->belongsTo(Meaning::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
