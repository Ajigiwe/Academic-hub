<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Deployment extends Model
{
    protected $fillable = [
        'status',
        'trigger',
        'initiated_by',
        'commit_before',
        'commit_after',
        'commit_message',
        'branch',
        'duration_seconds',
        'output',
        'error_output',
    ];

    protected $casts = [
        'duration_seconds' => 'float',
    ];

    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    public function getShortCommitBeforeAttribute(): ?string
    {
        return $this->commit_before ? substr($this->commit_before, 0, 7) : null;
    }

    public function getShortCommitAfterAttribute(): ?string
    {
        return $this->commit_after ? substr($this->commit_after, 0, 7) : null;
    }

    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'success' => 'badge-success',
            'running' => 'badge-gold',
            'failed' => 'badge-danger',
            default => 'badge-neutral',
        };
    }
}

