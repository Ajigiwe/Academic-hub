<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = ['user_id', 'type', 'title', 'body', 'url', 'read_at'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Unread = never opened. The bell counts these. */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * The page this notification points at, if it is safe to follow. Only
     * local paths are honoured — a stored value can never bounce a student
     * to another site.
     */
    public function destination(): ?string
    {
        $url = trim((string) $this->url);

        return str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : null;
    }

    /** Short label for the type chip. */
    public function typeLabel(): string
    {
        return match ($this->type) {
            'PURCHASE' => 'Purchase',
            'PAYMENT' => 'Payment',
            'PROMO' => 'Offer',
            default => 'Notice',
        };
    }
}
