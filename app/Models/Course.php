<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = ['code', 'title', 'slug'];

    public function bundles(): HasMany
    {
        return $this->hasMany(Bundle::class);
    }
}
