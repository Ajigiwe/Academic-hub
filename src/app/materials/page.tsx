import Link from "next/link";
import { getFreeMaterials } from "@/lib/resources";
import { programmeBySlug, isProgrammeSlug, isLevel } from "@/lib/programmes";
import { getEnabledProgrammes } from "@/lib/settings";
import { FreeMaterialCard } from "@/components/free-material-card";
import { RefineBar } from "@/components/refine-bar";

interface SearchParams {
  programme?: string;
  level?: string;
  semester?: string;
}

const SEMESTER_LABEL = { 1: "First Semester", 2: "Second Semester" } as const;

export const metadata = {
  title: "Course Materials — Academic Resource Hub",
  description:
    "Free slides, lecture notes, and revision packs by programme, level, and semester.",
};

export default async function MaterialsPage({
  searchParams,
}: {
  searchParams: Promise<SearchParams>;
}) {
  const sp = await searchParams;
  const enabledProgrammes = await getEnabledProgrammes();
  const enabledSlugs = enabledProgrammes.map((p) => p.slug);
  const programme =
    isProgrammeSlug(sp.programme) && enabledSlugs.includes(sp.programme)
      ? sp.programme
      : undefined;
  const level = isLevel(Number(sp.level)) ? Number(sp.level) : undefined;
  const semester =
    sp.semester === "1" || sp.semester === "2" ? Number(sp.semester) : undefined;

  const filtered = Boolean(programme || level || semester);
  const programmeMeta = programmeBySlug(programme);

  const materials = await getFreeMaterials({ programme, level, semester }, enabledSlugs);

  const chips = [
    programmeMeta ? programmeMeta.short : null,
    level ? `Level ${level}` : null,
    semester ? SEMESTER_LABEL[semester as 1 | 2] : null,
  ].filter(Boolean) as string[];

  return (
    <div className="container-page py-8">
      {/* Breadcrumb + context */}
      <nav className="text-sm text-neutral-500" aria-label="Breadcrumb">
        <Link href="/" className="transition-colors hover:text-brand-700">
          Home
        </Link>
        <span className="mx-1.5 text-neutral-300">/</span>
        <span className="text-neutral-700">Course materials</span>
      </nav>

      <div className="mt-3 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl">
            {filtered
              ? `Materials · ${chips.join(" · ")}`
              : "Course materials"}
          </h1>
          <p className="mt-1 text-sm text-neutral-600">
            Slides, lecture notes, and revision packs —{" "}
            {materials.length} free download{materials.length === 1 ? "" : "s"}
            {filtered ? " for your selection." : " across every programme."}
          </p>
        </div>
        <Link href="/#browse" className="btn-secondary btn-sm">
          Guided picker
        </Link>
      </div>

      {/* In-place refinement — works with any combination of filters */}
      <RefineBar
        base="/materials"
        programmes={enabledProgrammes.map((p) => ({ slug: p.slug, short: p.short }))}
        programme={programme}
        level={level}
        semester={semester}
        resultLabel={`${materials.length} free download${materials.length === 1 ? "" : "s"}`}
      />

      {materials.length === 0 ? (
        <div className="card-padded mt-6 py-16 text-center">
          <p className="font-medium text-neutral-800">
            {filtered
              ? "No materials for this selection yet"
              : "No materials published yet"}
          </p>
          <p className="mx-auto mt-1 max-w-md text-sm text-neutral-600">
            {filtered
              ? `Slides and notes for ${chips.join(" · ")} are being prepared — check back soon, or look at all materials.`
              : "Slides, lecture notes, and revision packs will appear here as soon as they're uploaded."}
          </p>
          <div className="mt-5 flex flex-wrap justify-center gap-3">
            {filtered ? (
              <Link href="/materials" className="btn-secondary">
                Browse all materials
              </Link>
            ) : null}
            <Link href="/courses" className="btn-primary">
              Looking for past questions instead?
            </Link>
          </div>
        </div>
      ) : (
        <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {materials.map((m) => (
            <FreeMaterialCard key={m.id} material={m} />
          ))}
        </div>
      )}
    </div>
  );
}
