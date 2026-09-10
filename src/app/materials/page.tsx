import Link from "next/link";
import { getFreeMaterials, getProgrammeContentCounts } from "@/lib/resources";
import { isLevel, programmeBySlug } from "@/lib/programmes";
import { getEnabledProgrammes } from "@/lib/settings";
import { FreeMaterialCard } from "@/components/free-material-card";
import { BrowseFlow } from "@/components/browse-flow";

interface SearchParams {
  programme?: string;
  level?: string;
  semester?: string;
}

const SEMESTER_LABEL = { 1: "First Semester", 2: "Second Semester" } as const;

export const metadata = {
  title: "Course Materials — Academic Resource Hub",
  description:
    "Pick your programme, level, and semester to find free slides, lecture notes, and revision packs.",
};

export default async function MaterialsPage({
  searchParams,
}: {
  searchParams: Promise<SearchParams>;
}) {
  const sp = await searchParams;
  const enabledProgrammes = await getEnabledProgrammes();
  const enabledSlugs = enabledProgrammes.map((p) => p.slug);

  // A choice is only accepted when the programme is admin-enabled.
  const programme = enabledSlugs.includes(sp.programme ?? "")
    ? sp.programme!
    : undefined;
  const level = isLevel(Number(sp.level)) ? Number(sp.level) : undefined;
  const semester =
    sp.semester === "1" || sp.semester === "2" ? Number(sp.semester) : undefined;

  const programmeMeta = programmeBySlug(programme);

  // The library only renders once ALL THREE steps are complete.
  const guided = Boolean(programme && level && semester);

  const [materials, contentCounts] = await Promise.all([
    guided
      ? getFreeMaterials({ programme, level, semester }, enabledSlugs)
      : Promise.resolve([]),
    getProgrammeContentCounts(enabledSlugs),
  ]);

  const chips = [
    programmeMeta?.short,
    level ? `Level ${level}` : undefined,
    semester ? SEMESTER_LABEL[semester as 1 | 2] : undefined,
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

      <div className="mt-3">
        <h1 className="text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl">
          {guided ? chips.join(" · ") : "Course materials"}
        </h1>
        <p className="mt-1 text-sm text-neutral-600">
          {guided
            ? `${materials.length} free download${materials.length === 1 ? "" : "s"} for your selection — slides, lecture notes, and revision packs.`
            : "Tell us your programme, level, and semester — we'll show your library."}
        </p>
      </div>

      {/* The 3-step flow. Once all three are committed the library appears below. */}
      <div className="mt-6">
        <BrowseFlow
          base="/materials"
          programmes={enabledProgrammes.map((p) => ({
            slug: p.slug,
            name: p.name,
            bundles: contentCounts.get(p.slug)?.bundles ?? 0,
            materials: contentCounts.get(p.slug)?.materials ?? 0,
          }))}
          programme={programme}
          level={level}
          semester={semester}
          doneLabel="Show my materials →"
        />
      </div>

      {guided ? (
        materials.length === 0 ? (
          <div className="card-padded mt-6 py-12 text-center">
            <p className="font-medium text-neutral-800">
              No materials for this selection yet
            </p>
            <p className="mx-auto mt-1 max-w-md text-sm text-neutral-600">
              Slides and notes for {chips.join(" · ")} are being prepared —
              check back soon, or look at past questions instead.
            </p>
            <div className="mt-5 flex flex-wrap justify-center gap-3">
              <Link href="/courses" className="btn-primary">
                Looking for past questions instead?
              </Link>
            </div>
          </div>
        ) : (
          <div className="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {materials.map((m) => (
              <FreeMaterialCard key={m.id} material={m} />
            ))}
          </div>
        )
      ) : (
        <div className="card-padded mt-6 py-12 text-center">
          <p className="font-medium text-neutral-800">
            Complete the three steps above
          </p>
          <p className="mx-auto mt-1 max-w-md text-sm text-neutral-600">
            Your programme, level, and semester — then the free materials
            library opens.
          </p>
        </div>
      )}
    </div>
  );
}
