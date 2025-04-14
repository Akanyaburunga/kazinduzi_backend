<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModerationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'action_by',
        'target_user',
        'word_id',
        'meaning_id',
        'action',
        'reason',
    ];
}
