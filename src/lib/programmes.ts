/**
 * The three canonical programmes. Every upload form, browse filter, and
 * picker uses this single list so the catalogue stays consistent.
 * Full name is what's stored in the Programme model; slug is used in
 * URLs and filters; short is the compact label shown in pills/badges.
 */
export const PROGRAMMES = [
  { name: "Bachelor of Technology (BTECH)", slug: "btech", short: "BTECH" },
  { name: "Diploma in Technology (Dip Tech)", slug: "dip-tech", short: "Dip Tech" },
  { name: "Higher National Diploma (HND)", slug: "hnd", short: "HND" },
] as const;

export type ProgrammeSlug = (typeof PROGRAMMES)[number]["slug"];

export const PROGRAMME_SLUGS = PROGRAMMES.map((p) => p.slug);

export function isProgrammeSlug(slug: string | undefined): slug is ProgrammeSlug {
  return !!slug && (PROGRAMME_SLUGS as readonly string[]).includes(slug);
}

export function programmeBySlug(slug: string | undefined) {
  return PROGRAMMES.find((p) => p.slug === slug);
}

/** Valid student levels — 100 to 400 in 100 steps. */
export const LEVELS = [100, 200, 300, 400] as const;

export function isLevel(level: number | undefined): level is (typeof LEVELS)[number] {
  return !!level && (LEVELS as readonly number[]).includes(level);
}

export function currentAcademicYear(): string {
  const now = new Date();
  const y = now.getFullYear();
  // An academic year starts in September (GH convention).
  return now.getMonth() >= 8 ? `${y}/${y + 1}` : `${y - 1}/${y}`;
}