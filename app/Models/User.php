<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Meaning;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function words()
    {
        return $this->hasMany(Word::class);
    }

    public function meanings()
    {
        return $this->hasMany(Meaning::class);
    }

    public function updateReputation(int $points, String $reason, $related)
    {
        $this->reputation += $points;
        $this->save();

        // Determine the class or type of $related
        if (is_object($related)) {
            $relatedClass = get_class($related);
            $relatedId = $related->id; // Extract ID if it's an object
        } elseif (is_int($related)) {
            $relatedClass = null; // No class for plain integers
            $relatedId = $related; // Use the integer as the ID
        } else {
            throw new InvalidArgumentException('Invalid related argument type.');
        }

        // Log the reputation change
        $this->reputationLogs()->create([
            'change' => $points,
            'reason' => $reason,
            'related_id' => is_object($related) ? $related->id : $related, // Handle both cases
            'related_type' => is_object($related) ? get_class($related) : null,
        ]);

    }

    public function reputationLogs()
    {
        return $this->hasMany(ReputationLog::class);
    }

    public function referredBy()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    public function referrals()
    {
        return $this->hasMany(User::class, 'referred_by');
    }

    // Generate a unique referral code
    public function generateReferralCode()
    {
        do {
            $code = strtoupper(uniqid($this->id . '_'));
        } while (self::where('referral_code', $code)->exists());
    
        $this->referral_code = $code;
        $this->save();
    }

}
