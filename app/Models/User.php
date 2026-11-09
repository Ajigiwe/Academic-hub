<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'avatar',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    public function viewingSessions(): HasMany
    {
        return $this->hasMany(ViewingSession::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'ADMIN';
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function getInitialsAttribute(): string
    {
        $first = mb_substr($this->first_name ?? $this->name ?? 'U', 0, 1);
        $last = mb_substr($this->last_name ?? '', 0, 1);
        return mb_strtoupper($first . ($last ?: ''));
    }

    public function getAvatarUrlAttribute(): string
    {
        return route('avatar', ['user' => $this->id, 'v' => $this->updated_at?->timestamp ?? 0]);
    }

    public function hasCustomAvatar(): bool
    {
        return !empty($this->avatar);
    }

    /**
     * Send the password reset notification.
     *
     * @param string $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }
}
