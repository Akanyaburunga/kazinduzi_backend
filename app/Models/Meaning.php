<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meaning extends Model
{
    use HasFactory;

    protected $fillable = ['meaning', 'word_id', 'user_id'];

    public function word()
    {
        return $this->belongsTo(Word::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function votes()
    {
        return $this->hasMany(Vote::class);
    }

    public function voteCount()
    {
        return $this->votes()
            ->selectRaw('meaning_id, SUM(CASE WHEN vote = "up" THEN 1 WHEN vote = "down" THEN -1 ELSE 0 END) as total_votes')
            ->groupBy('meaning_id');
    }
}
