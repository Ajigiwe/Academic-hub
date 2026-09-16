<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\Programme;
use App\Models\Resource;

/**
 * Content counts for the guided browse flow's level and semester cards,
 * so a card can say "3 bundles · 1 free material" before the visitor
 * commits to it.
 *
 * Only content the visitor can actually open is counted:
 *
 *  - a bundle counts when it has at least one PUBLISHED paper on the
 *    level/semester asked about — the same gate the catalogue listing
 *    applies, so a card never promises more than the next page shows;
 *  - a "material" is a PUBLISHED resource outside every bundle, i.e. the
 *    free library served by /materials.
 */
class ContentCounts
{
    /**
     * Level cards for a programme: [100 => ['bundles' => n, 'materials' => n], ...].
     * Pass withMaterials: false on materials-only pages (and vice versa).
     */
    public static function levelCards(?int $programmeId, bool $withBundles = true, bool $withMaterials = true): array
    {
        if (! $programmeId) {
            return [];
        }

        $bundles = $withBundles
            ? self::groupedBy(self::publishedBundles($programmeId), 'level')
            : [];
        $materials = $withMaterials
            ? self::groupedBy(self::freeMaterials($programmeId), 'level')
            : [];

        $cards = [];
        foreach (Programme::LEVELS as $level) {
            $cards[$level] = [
                'bundles' => $bundles[$level] ?? 0,
                'materials' => $materials[$level] ?? 0,
            ];
        }

        return $cards;
    }

    /** Semester cards for a programme + level: [1 => [...], 2 => [...]]. */
    public static function semesterCards(?int $programmeId, ?int $level, bool $withBundles = true, bool $withMaterials = true): array
    {
        if (! $programmeId || ! $level) {
            return [];
        }

        // A bundle carries no semester of its own — its papers do — so
        // each semester is counted with the same whereHas the catalogue
        // listing uses, rather than grouped by a column that isn't there.
        $bundles = [];
        if ($withBundles) {
            foreach ([1, 2] as $semester) {
                $bundles[$semester] = self::publishedBundles($programmeId)
                    ->where('level', $level)
                    ->whereHas('resources', fn ($r) => $r->published()->where('semester', $semester))
                    ->count();
            }
        }

        $materials = [];
        if ($withMaterials) {
            $materials = self::groupedBy(self::freeMaterials($programmeId)->where('level', $level), 'semester');
        }

        $cards = [];
        foreach ([1, 2] as $semester) {
            $cards[$semester] = [
                'bundles' => $bundles[$semester] ?? 0,
                'materials' => $materials[$semester] ?? 0,
            ];
        }

        return $cards;
    }

    /** Published bundles of a programme that have at least one published paper. */
    private static function publishedBundles(int $programmeId)
    {
        return Bundle::published()
            ->where('programme_id', $programmeId)
            ->whereHas('resources', fn ($r) => $r->published());
    }

    /** Published resources that belong to no bundle — the free library. */
    private static function freeMaterials(int $programmeId)
    {
        return Resource::published()
            ->whereNull('bundle_id')
            ->where('programme_id', $programmeId);
    }

    /**
     * Count a grouped column in one query.
     *
     * @return array<int, int> column value => count
     */
    private static function groupedBy($query, string $column): array
    {
        return $query
            ->selectRaw("{$column} as card_value, count(*) as card_total")
            ->groupBy($column)
            ->pluck('card_total', 'card_value')
            ->mapWithKeys(fn ($count, $value) => [(int) $value => (int) $count])
            ->all();
    }
}
