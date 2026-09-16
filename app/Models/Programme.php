<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Programme extends Model
{
    /** Valid student levels — 100 to 400 in 100 steps (port of LEVELS). */
    public const LEVELS = [100, 200, 300, 400];

    /**
     * Compact labels shown in pills/badges — port of the `short` field in
     * src/lib/programmes.ts. Falls back to a de-slugged name for any
     * programme added later without a mapping.
     */
    public static function shortName(string $slug): string
    {
        $shorts = [
            'procurement-and-supply' => 'Procurement',
            'marketing' => 'Marketing',
            'accounting' => 'Accounting',
            'secretaryship-and-management' => 'Secretaryship',
        ];

        return $shorts[$slug] ?? ucfirst(str_replace('-', ' ', $slug));
    }

    /**
     * Port of slugify (resource-admin.ts): lowercase, strip apostrophes,
     * non-alphanumerics to dashes, trim, cap at 80 chars.
     */
    public static function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = str_replace(["'", '’'], '', $text);
        $text = trim(preg_replace('/[^a-z0-9]+/', '-', $text) ?? '', '-');

        return substr($text, 0, 80);
    }

    protected $fillable = ['name', 'slug'];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function bundles(): HasMany
    {
        return $this->hasMany(Bundle::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }
}
