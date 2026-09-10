import { cache } from "react";
import { prisma } from "@/lib/db";
import { PROGRAMMES, type ProgrammeSlug } from "@/lib/programmes";

/**
 * Platform settings, stored as key/value rows (model `Setting`).
 * Programme visibility is the primary consumer: students only see the
 * programme tracks the admins have switched on (Admin → Settings).
 *
 * Convention: key = `programme.<slug>.enabled`, value "true" | "false".
 * A MISSING row counts as enabled (safe default) — the seed writes rows
 * so fresh installs start with the canonical programmes enabled.
 *
 * Programmes live in the DB (model `Programme`) and are admin-editable;
 * PROGRAMMES in src/lib/programmes.ts is only the seed default. All
 * visibility helpers below therefore read from the DB, never from the
 * hardcoded list.
 */

export function programmeSettingKey(slug: string): string {
  return `programme.${slug}.enabled`;
}

export interface EnabledProgramme {
  id: string;
  slug: string;
  name: string;
}

/** All programme tracks in the catalogue, with student visibility. */
export const getAllProgrammesWithVisibility = cache(
  async (): Promise<Array<EnabledProgramme & { enabled: boolean }>> => {
    const [rows, settings] = await Promise.all([
      prisma.programme.findMany({
        orderBy: { name: "asc" },
        select: { id: true, slug: true, name: true },
      }),
      prisma.setting.findMany({
        where: { key: { startsWith: "programme." } },
      }),
    ]);
    const byKey = new Map(settings.map((r) => [r.key, r.value === "true"]));
    // Missing row counts as enabled (matches the historical default).
    return rows.map((p) => ({
      ...p,
      enabled: byKey.get(programmeSettingKey(p.slug)) ?? true,
    }));
  },
);

/** Programme tracks students may see (Admin → Settings toggles). Cached per request. */
export const getEnabledProgrammes = cache(
  async (): Promise<EnabledProgramme[]> => {
    const all = await getAllProgrammesWithVisibility();
    return all.filter((p) => p.enabled).map(({ id, slug, name }) => ({ id, slug, name }));
  },
);

export async function getEnabledProgrammeSlugs(): Promise<string[]> {
  return (await getEnabledProgrammes()).map((p) => p.slug);
}

/**
 * True when a programme row's slug is visible to students. Any slug in
 * the Programme table follows the Admin → Settings toggle; a slug with
 * no row is not managed by the settings system and stays visible.
 */
export async function isProgrammeEnabled(slug: string): Promise<boolean> {
  const all = await getAllProgrammesWithVisibility();
  const row = all.find((p) => p.slug === slug);
  return row ? row.enabled : true;
}

/** Keep legacy typed call sites compiling — resolves via the DB rows. */
export async function getEnabledProgrammeFlags(): Promise<Map<ProgrammeSlug, boolean>> {
  const all = await getAllProgrammesWithVisibility();
  const out = new Map<ProgrammeSlug, boolean>();
  for (const p of PROGRAMMES) {
    const row = all.find((r) => r.slug === p.slug);
    out.set(p.slug, row ? row.enabled : true);
  }
  return out;
}
