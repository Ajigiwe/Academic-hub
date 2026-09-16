<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Port of Prisma `Favourite` — a student's bookmark on a resource.
 * Composite (user_id, resource_id) primary key; no incrementing id, so
 * Eloquent must never try to fetch one (the Postgres transaction-poisoning
 * lesson from the viewer slice applies here too).
 */
class Favourite extends Model
{
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['user_id', 'resource_id'];

    protected static function booted(): void
    {
        static::creating(function (self $f) {
            $f->created_at = $f->created_at ?? now();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }
}
