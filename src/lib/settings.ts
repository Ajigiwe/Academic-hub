import { cache } from "react";
import { prisma } from "@/lib/db";
import { PROGRAMMES, type ProgrammeSlug } from "@/lib/programmes";

/**
 * Platform settings, stored as key/value rows (model `Setting`).
 * Programme visibility is the first consumer: students only see the
 * programme tracks the admins have switched on (Admin → Settings).
 *
 * Convention: key = `programme.<slug>.enabled`, value "true" | "false".
 * A MISSING row counts as enabled (safe default) — the migration and
 * seed both write rows so fresh installs start BTECH-only.
 */

export function programmeSettingKey(slug: string): string {
  return `programme.${slug}.enabled`;
}

/** Current visibility flag per programme slug. Cached per request. */
export const getProgrammeSettings = cache(async (): Promise<Map<ProgrammeSlug, boolean>> => {
  const keys = PROGRAMMES.map((p) => programmeSettingKey(p.slug));
  const rows = await prisma.setting.findMany({ where: { key: { in: keys } } });
  const byKey = new Map(rows.map((r) => [r.key, r.value === "true"]));
  const out = new Map<ProgrammeSlug, boolean>();
  for (const p of PROGRAMMES) {
    out.set(p.slug, byKey.get(programmeSettingKey(p.slug)) ?? true);
  }
  return out;
});

export async function getEnabledProgrammeSlugs(): Promise<ProgrammeSlug[]> {
  const flags = await getProgrammeSettings();
  return PROGRAMMES.filter((p) => flags.get(p.slug)).map((p) => p.slug);
}

export async function getEnabledProgrammes(): Promise<Array<(typeof PROGRAMMES)[number]>> {
  const enabled = new Set(await getEnabledProgrammeSlugs());
  return PROGRAMMES.filter((p) => enabled.has(p.slug));
}