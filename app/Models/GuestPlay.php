<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GuestPlay extends Model
{
    protected $fillable = [
        'user_id',
        'mode',
        'puzzle_type',
        'puzzle_id',
    ];
}