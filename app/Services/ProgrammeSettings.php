<?php

namespace App\Services;

use App\Models\Programme;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * Port of src/lib/settings.ts — platform settings stored as key/value
 * rows. Programme visibility is the primary consumer: students only see
 * the programme tracks the admins have switched on (Admin → Settings).
 *
 * Convention: key = `programme.<slug>.enabled`, value "true" | "false".
 * A MISSING row counts as enabled (safe default) — the seed writes rows
 * so fresh installs start with the canonical programmes enabled.
 */
class ProgrammeSettings
{
    public static function settingKey(string $slug): string
    {
        return "programme.{$slug}.enabled";
    }

    /** All programme tracks in the catalogue, with student visibility. */
    public static function allWithVisibility(): Collection
    {
        $rows = Programme::orderBy('name')->get(['id', 'slug', 'name']);
        $settings = Setting::where('key', 'like', 'programme.%.enabled')->pluck('value', 'key');

        return $rows->map(function (Programme $p) use ($settings) {
            $p->enabled = ($settings[static::settingKey($p->slug)] ?? 'true') === 'true';

            return $p;
        });
    }

    /**
     * Slugs of programme tracks students may see. A programme with NO
     * settings row counts as enabled (the historical default).
     */
    public static function enabledSlugs(): array
    {
        return static::allWithVisibility()
            ->filter(fn ($p) => $p->enabled)
            ->pluck('slug')
            ->all();
    }

    /**
     * True when a programme's slug is visible to students. Any slug in
     * the Programme table follows the Admin → Settings toggle; a slug
     * with no row is not managed and stays visible.
     */
    public static function isEnabled(string $slug): bool
    {
        $row = static::allWithVisibility()->firstWhere('slug', $slug);

        return $row ? $row->enabled : true;
    }

    /** Persist one visibility toggle. Returns the effective value. */
    public static function setEnabled(string $slug, bool $enabled): bool
    {
        Setting::updateOrCreate(
            ['key' => static::settingKey($slug)],
            ['value' => $enabled ? 'true' : 'false'],
        );

        return $enabled;
    }
}
