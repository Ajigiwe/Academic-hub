import Link from "next/link";
import {
  searchBundles,
  getFreeMaterials,
  getProgrammeContentCounts,
  type SearchBundleItem,
} from "@/lib/resources";
import { isLevel, programmeBySlug } from "@/lib/programmes";
import { getEnabledProgrammes } from "@/lib/settings";
import { BundleCard } from "@/components/bundle-card";
import { FreeMaterialCard } from "@/components/free-material-card";
import { BrowseFlow } from "@/components/browse-flow";

interface SearchParams {
  programme?: string;
  level?: string;
  semester?: string;
}

const SEMESTER_LABEL = { 1: "First Semester", 2: "Second Semester" } as const;

export const metadata = {
  title: "Past Questions — Academic Resource Hub",
  description:
    "Pick your programme, level, and semester to find past questions for your courses.",
};

export default async function CoursesPage({
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
  const semester = sp.semester === "1" || sp.semester === "2" ? Number(sp.semester) : undefined;

  const anyFilter = Boolean(programme || level || semester);
  const programmeMeta = programmeBySlug(programme);

  // The library only renders once ALL THREE steps are complete.
  const guided = Boolean(programme && level && semester);

  const [{ items, total }, freeMaterials, contentCounts] = await Promise.all([
    guided
      ? searchBundles({ programme, level, semester, perPage: 50 }, enabledSlugs)
      : Promise.resolve({ items: [], total: 0 }),
    guided
      ? getFreeMaterials({ programme, level, semester }, enabledSlugs)
      : Promise.resolve([]),
    getProgrammeContentCounts(enabledSlugs),
  ]);

  const grouped = new Map<string, SearchBundleItem[]>();
  for (const b of items) {
    const code = b.course?.code ?? "Other";
    if (!grouped.has(code)) grouped.set(code, []);
    grouped.get(code)!.push(b);
  }

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
        <span className="text-neutral-700">Past questions</span>
      </nav>

      <div className="mt-3">
        <h1 className="text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl">
          {guided ? chips.join(" · ") : "Past questions"}
        </h1>
        <p className="mt-1 text-sm text-neutral-600">
          {guided
            ? `${total} course bundle${total === 1 ? "" : "s"} for sale, plus free materials — one price unlocks every paper in a bundle.`
            : "Tell us your programme, level, and semester — we'll show your library."}
        </p>
      </div>

      {/* The 3-step flow. Once all three are committed the flow collapses
          into a summary bar and the library appears below. */}
      <div className="mt-6">
        <BrowseFlow
          base="/courses"
          programmes={enabledProgrammes.map((p) => ({
            slug: p.slug,
            name: p.name,
            bundles: contentCounts.get(p.slug)?.bundles ?? 0,
            materials: contentCounts.get(p.slug)?.materials ?? 0,
          }))}
          programme={programme}
          level={level}
          semester={semester}
          doneLabel="Show my past questions →"
        />
      </div>

      {!guided ? (
        <div className="card-padded mt-6 py-12 text-center">
          <p className="font-medium text-neutral-800">
            Complete the three steps above
          </p>
          <p className="mx-auto mt-1 max-w-md text-sm text-neutral-600">
            {anyFilter
              ? "One more selection and we'll show the bundles for your course."
              : "Your programme, level, and semester — then the library opens."}
          </p>
        </div>
      ) : (
        <>
          {/* Paid bundles — grouped by course */}
          {grouped.size > 0 ? (
            <section className="mt-8">
              <h2 className="text-lg font-bold tracking-tight text-neutral-900">
                Past-question bundles for sale
              </h2>
              <p className="mt-0.5 text-sm text-neutral-500">
                Buy one bundle per course and unlock every paper inside it.
              </p>
              {[...grouped.entries()].map(([code, bundles]) => (
                <div key={code} className="mt-6">
                  <div className="flex items-baseline gap-2">
                    <h3 className="font-semibold text-neutral-900">{code}</h3>
                    <span className="text-xs text-neutral-500">
                      {bundles[0]?.course?.title ?? ""}
                    </span>
                  </div>
                  <div className="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {bundles.map((b) => (
                      <BundleCard key={b.id} bundle={b} />
                    ))}
                  </div>
                </div>
              ))}
            </section>
          ) : (
            <section className="card-padded mt-8 py-12 text-center">
              <p className="font-medium text-neutral-800">
                No past-question bundles here yet
              </p>
              <p className="mx-auto mt-1 max-w-md text-sm text-neutral-600">
                Papers for {chips.join(" · ")} are on the way — free materials
                below, or check back soon.
              </p>
            </section>
          )}

          {/* Free materials matching the same three filters */}
          {freeMaterials.length > 0 && (
            <section className="mt-12">
              <div className="flex items-baseline justify-between gap-2">
                <h2 className="text-lg font-bold tracking-tight text-neutral-900">
                  Free study materials
                </h2>
                <span className="text-xs text-neutral-500">
                  {freeMaterials.length} available
                </span>
              </div>
              <p className="mt-0.5 text-sm text-neutral-500">
                Slides, notes, and revision packs for your selection — free to
                download.
              </p>
              <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {freeMaterials.map((m) => (
                  <FreeMaterialCard key={m.id} material={m} />
                ))}
              </div>
            </section>
          )}

          {grouped.size === 0 && freeMaterials.length === 0 && (
            <p className="mt-8 text-center text-sm text-neutral-500">
              Nothing here yet — try another semester or level.
            </p>
          )}
        </>
      )}
    </div>
  );
}
