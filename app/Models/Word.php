<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Word extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($word) {
            $word->slug = Str::slug($word->word);
        });
    }

    public function getRouteKeyName()
    {
        return 'slug';
    }

}
