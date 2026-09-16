<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecentlyViewed extends Model
{
    // Laravel would pluralize to "recently_vieweds" — the actual table
    // (mirroring the Prisma model) is "recently_viewed".
    protected $table = 'recently_viewed';

    // Composite (user_id, resource_id) primary key — no auto-increment id
    // column exists, so Eloquent must never try to fetch one after insert.
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = ['user_id', 'resource_id'];

    protected $fillable = ['user_id', 'resource_id', 'viewed_at'];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
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
