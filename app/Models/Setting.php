<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Port of Prisma `Setting` — platform key/value settings. Programme
 * visibility is the primary consumer; see ProgrammeSettings service.
 * Composite-free single-string primary key, no auto-increment id.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['key', 'value'];

    protected static function booted(): void
    {
        static::creating(fn (self $s) => $s->updated_at ??= now());
    }
}
