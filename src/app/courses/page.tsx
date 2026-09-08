import Link from "next/link";
import {
  searchBundles,
  getFreeMaterials,
  type SearchBundleItem,
} from "@/lib/resources";
import {
  PROGRAMMES,
  programmeBySlug,
  isProgrammeSlug,
  isLevel,
} from "@/lib/programmes";
import { BundleCard } from "@/components/bundle-card";
import { FreeMaterialCard } from "@/components/free-material-card";
import { RefineBar } from "@/components/refine-bar";

interface SearchParams {
  programme?: string;
  level?: string;
  semester?: string;
}

const SEMESTER_LABEL = { 1: "First Semester", 2: "Second Semester" } as const;

export const metadata = {
  title: "Courses — Academic Resource Hub",
  description:
    "Find past questions and free study materials by programme, level, and semester.",
};

export default async function CoursesPage({
  searchParams,
}: {
  searchParams: Promise<SearchParams>;
}) {
  const sp = await searchParams;
  const programme = isProgrammeSlug(sp.programme) ? sp.programme : undefined;
  const level = isLevel(Number(sp.level)) ? Number(sp.level) : undefined;
  const semester = sp.semester === "1" || sp.semester === "2" ? Number(sp.semester) : undefined;

  const guided = Boolean(programme && level && semester);
  const anyFilter = Boolean(programme || level || semester);
  const programmeMeta = programmeBySlug(programme);

  const [{ items, total }, freeMaterials] = await Promise.all([
    anyFilter
      ? searchBundles({ programme, level, semester, perPage: 50 })
      : Promise.resolve({ items: [], total: 0 }),
    getFreeMaterials({ programme, level, semester }),
  ]);

  const grouped = new Map<string, SearchBundleItem[]>();
  for (const b of items) {
    const code = b.course?.code ?? "Other";
    if (!grouped.has(code)) grouped.set(code, []);
    grouped.get(code)!.push(b);
  }

  return (
    <div className="container-page py-8">
      {/* Breadcrumb + context */}
      <nav className="text-sm text-neutral-500" aria-label="Breadcrumb">
        <Link href="/" className="transition-colors hover:text-brand-700">
          Home
        </Link>
        <span className="mx-1.5 text-neutral-300">/</span>
        <span className="text-neutral-700">Courses</span>
      </nav>

      <div className="mt-3 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl">
            {anyFilter
              ? [
                  programmeMeta?.short,
                  level ? `Level ${level}` : undefined,
                  semester ? SEMESTER_LABEL[semester as 1 | 2] : undefined,
                ]
                  .filter(Boolean)
                  .join(" · ")
              : "Courses & materials"}
          </h1>
          <p className="mt-1 text-sm text-neutral-600">
            {anyFilter
              ? `${total} course bundle${total === 1 ? "" : "s"} for sale, plus free materials — one price unlocks every paper in a bundle.`
              : "Filter by programme, year, and semester to see the courses you can pay for."}
          </p>
        </div>
        <Link href="/#browse" className="btn-secondary btn-sm">
          Guided picker
        </Link>
      </div>

      {/* In-place refinement — works with any combination of filters */}
      <RefineBar
        base="/courses"
        programme={programme}
        level={level}
        semester={semester}
        resultLabel={`${total} bundle${total === 1 ? "" : "s"} · ${freeMaterials.length} free material${freeMaterials.length === 1 ? "" : "s"}`}
      />

      {!anyFilter ? (
        <div className="card-padded mt-6 py-16 text-center">
          <p className="font-medium text-neutral-800">
            Choose your programme, year, and semester above
          </p>
          <p className="mx-auto mt-1 max-w-md text-sm text-neutral-600">
            Or use the guided picker on the homepage — it walks you through
            the three steps and shows the courses you can pay for.
          </p>
          <Link href="/#browse" className="btn-primary mt-5">
            Open the guided picker →
          </Link>
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
                Papers for {programmeMeta?.short} · Level {level} ·{" "}
                {SEMESTER_LABEL[semester as 1 | 2]} are on the way — free
                materials below, or check back soon.
              </p>
            </section>
          )}

          {/* Free materials */}
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
                Slides, notes, and revision packs — download them free.
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
              Nothing here yet — try another year or semester.
            </p>
          )}
        </>
      )}
    </div>
  );
}

