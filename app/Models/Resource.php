<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Resource extends Model
{
    protected $table = 'resources';

    protected $fillable = [
        'slug', 'title', 'description', 'type', 'status', 'level', 'semester',
        'academic_year', 'page_count', 'preview_pages', 'download_count',
        'published_at', 'bundle_id', 'programme_id', 'course_id',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ResourceFile::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'PUBLISHED')->whereNotNull('published_at');
    }

    /** Human label for the type enum (port of FreeMaterialCard's TYPE_LABEL). */
    public function typeLabel(): string
    {
        return match ($this->type) {
            'PAST_QUESTION' => 'Past question',
            'LECTURE_NOTES' => 'Lecture notes',
            'SLIDES' => 'Slides',
            'REVISION' => 'Revision',
            'PRACTICE' => 'Practice',
            default => strtolower(str_replace('_', ' ', (string) $this->type)),
        };
    }
}
