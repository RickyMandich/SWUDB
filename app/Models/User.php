<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'admin',
        'email_verification_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Generate a random email verification token
     */
    public function generateEmailVerificationToken()
    {
        $this->email_verification_token = \Illuminate\Support\Str::random(60);
        $this->save();
        return $this->email_verification_token;
    }

    /**
     * Check if email is verified
     */
    public function isEmailVerified()
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Mark email as verified
     */
    public function markEmailAsVerified()
    {
        $this->email_verified_at = now();
        $this->email_verification_token = null;
        $this->save();
    }

    /**
     * Get all admin users for notifications
     * Ottiene tutti gli utenti admin per le notifiche
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    public static function getAdmins()
    {
        return static::where('admin', 1)->get();
    }
}
