<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'reference', 'user_id', 'status', 'amount_pesewas', 'currency', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /** Money the gateway has actually returned for this order. */
    public function refundedPesewas(): int
    {
        return (int) $this->refunds()->processed()->sum('amount_pesewas');
    }

    /**
     * A refund counts as full once the returned money covers the order.
     * Anything less keeps the student's access — they still paid for part
     * of it, and the papers are not divisible.
     */
    public function isFullyRefunded(): bool
    {
        return $this->refundedPesewas() >= $this->amount_pesewas;
    }
}
