<?php

namespace App\Models;

use App\Services\BundlePricing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bundle extends Model
{
    protected $fillable = [
        'slug', 'title', 'description', 'solved', 'status', 'level',
        'academic_year', 'price_pesewas', 'published_at',
        'programme_id', 'course_id',
    ];

    protected function casts(): array
    {
        return [
            'solved' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Display price. Every bundle sells for the same platform amount
     * (Admin → Settings), so this reads BundlePricing rather than the
     * row — `price_pesewas` only mirrors it, and a mirror can lag.
     */
    public function priceCedis(): string
    {
        return BundlePricing::formatted();
    }

    /**
     * Authoritative price for money paths — what checkout charges and
     * what order_items snapshots. Never read the raw column to charge.
     */
    public function pricePesewas(): int
    {
        return BundlePricing::pesewas();
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'PUBLISHED')->whereNotNull('published_at');
    }
}
