<?php

namespace App\Models;

use App\Notifications\VerifyEmailNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'country',
        'timezone',
        'is_active',
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * Set the password attribute - encrypt it before saving
     */
    public function setPasswordAttribute($value)
    {
        if ($value !== null && $value !== '') {
            $this->attributes['password'] = Crypt::encryptString($value);
        } else {
            // Don't update password if value is null or empty
            unset($this->attributes['password']);
        }
    }

    /**
     * Get the decrypted password
     */
    public function getDecryptedPasswordAttribute()
    {
        try {
            return Crypt::decryptString($this->attributes['password'] ?? '');
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get the user's profile.
     */
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Get the user's orders.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the user's companies through orders.
     * Since companies no longer have user_id, we query through orders.
     */
    public function companies()
    {
        return $this->hasManyThrough(
            Company::class,
            Order::class,
            'user_id', // Foreign key on orders table
            'id', // Foreign key on companies table
            'id', // Local key on users table
            'company_id' // Local key on orders table
        );
    }

    /**
     * Get the user's payments.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the user's affiliate account.
     */
    public function affiliate()
    {
        return $this->hasOne(Affiliate::class);
    }

    /**
     * Get the user's support tickets.
     */
    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * Get the user's notifications.
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get the user's activity logs.
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Send the email verification notification.
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailNotification());
    }

    /**
     * Get the email address that should be used for verification.
     */
    public function getEmailForVerification()
    {
        return $this->email;
    }
}
