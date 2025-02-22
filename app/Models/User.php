<?php

namespace App\Models;

use App\Models\Community;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Cashier\Billable;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, Billable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'profile_image',
        'first_name',
        'last_name',
        'email',
        'password',
        'country',
        'city',
        'zip_code',
        'address',
        'status',
        'last_login_at',
        'is_admin',
        'payment_methods',
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
        'is_admin' => 'boolean',
    ];

    public function sendPasswordResetNotification($token)
    {
        // API-based reset URL
        $url = config('app.frontend_url') . '/reset-password?token=' . $token;

        $this->notify(new \App\Notifications\ResetPasswordNotification($url));
    }


    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
    public function votes()
    {
        return $this->hasMany(Vote::class);
    }

    public function community()
    {
        return $this->hasMany(Community::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    

}
