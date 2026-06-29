<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'monthly_quota', 'used_this_month', 'quota_reset_at', 'daftra_domain', 'daftra_api_key'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'quota_reset_at' => 'datetime',
            'daftra_api_key' => 'encrypted',
        ];
    }

    public function webhookEndpoints()
    {
        return $this->hasMany(WebhookEndpoint::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function usageLogs()
    {
        return $this->hasMany(UsageLog::class);
    }

    public function subscription()
    {
        return $this->hasOne(UserSubscription::class)->latestOfMany();
    }
}
