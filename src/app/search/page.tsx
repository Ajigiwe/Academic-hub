import Link from "next/link";
import { searchBundles, getFreeMaterials, getFilterYears } from "@/lib/resources";
import { getEnabledProgrammes } from "@/lib/settings";
import { BundleCard } from "@/components/bundle-card";
import { FreeMaterialCard } from "@/components/free-material-card";
import { FilterPanel } from "@/components/filter-panel";

interface SearchParams {
  q?: string;
  programme?: string;
  level?: string;
  semester?: string;
  year?: string;
  sort?: string;
  page?: string;
}

export default async function SearchPage({
  searchParams,
}: {
  searchParams: Promise<SearchParams>;
}) {
  const sp = await searchParams;
  const enabledProgrammes = await getEnabledProgrammes();
  const enabledSlugs = enabledProgrammes.map((p) => p.slug);
  const programme =
    sp.programme && enabledSlugs.includes(sp.programme) ? sp.programme : undefined;
  const level = sp.level ? Number(sp.level) : undefined;
  const semester = sp.semester ? Number(sp.semester) : undefined;
  const page = sp.page ? Number(sp.page) : 1;
  const sort = (["relevance", "newest", "popular", "price_asc", "price_desc"] as const).includes(
    sp.sort as never,
  )
    ? (sp.sort as "relevance" | "newest" | "popular" | "price_asc" | "price_desc")
    : "relevance";

  const [{ items, total, pages }, freeMaterials, years] = await Promise.all([
    searchBundles({ q: sp.q, programme, level, semester, year: sp.year, sort, page }, enabledSlugs),
    // Free materials respect the same filters except academic year — they
    // are tagged with the current year, so a year filter would hide them.
    getFreeMaterials({ q: sp.q, programme, level, semester }, enabledSlugs),
    getFilterYears(),
  ]);

  const hasResults = items.length > 0;
  const activeFilterCount = [sp.q, programme, sp.level, sp.semester, sp.year].filter(Boolean).length;

  function pageHref(p: number) {
    const params = new URLSearchParams();
    if (sp.q) params.set("q", sp.q);
    if (sp.programme) params.set("programme", sp.programme);
    if (sp.level) params.set("level", sp.level);
    if (sp.semester) params.set("semester", sp.semester);
    if (sp.year) params.set("year", sp.year);
    if (sort !== "relevance") params.set("sort", sort);
    params.set("page", String(p));
    return `/search?${params.toString()}`;
  }

  return (
    <div className="container-page py-8">
      <h1 className="text-xl font-bold text-neutral-900">
        {sp.q ? `Search results for “${sp.q}”` : "Browse all bundles"}
      </h1>
      <p className="mt-1 text-sm text-neutral-600">
        {hasResults
          ? `${total} bundle${total === 1 ? "" : "s"} found — one price unlocks every paper inside`
          : null}
      </p>

      <div className="mt-6 grid grid-cols-1 gap-8 lg:grid-cols-[260px_1fr]">
        {/* Filters (collapsible) */}
        <form className="card h-fit overflow-hidden self-start" action="/search">
          <FilterPanel activeCount={activeFilterCount}>
          <input type="hidden" name="sort" value={sort} />
          <div>
            <label className="label" htmlFor="q">Search</label>
            <input id="q" name="q" defaultValue={sp.q ?? ""} className="input" placeholder="Course code, title…" />
          </div>
          <div>
            <label className="label" htmlFor="level">Level</label>
            <select id="level" name="level" defaultValue={sp.level ?? ""} className="input">
              <option value="">Any</option>
              {[100, 200, 300, 400].map((l) => (
                <option key={l} value={l}>Level {l}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="label" htmlFor="semester">Semester</label>
            <select id="semester" name="semester" defaultValue={sp.semester ?? ""} className="input">
              <option value="">Any</option>
              <option value="1">First</option>
              <option value="2">Second</option>
            </select>
          </div>
          <div>
            <label className="label" htmlFor="year">Academic year</label>
            <select id="year" name="year" defaultValue={sp.year ?? ""} className="input">
              <option value="">Any</option>
              {years.map((y) => (
                <option key={y} value={y}>{y}</option>
              ))}
            </select>
          </div>
          <div className="flex gap-2">
            <button type="submit" className="btn-primary flex-1">Apply</button>
            <Link href="/search" className="btn-secondary">Clear</Link>
          </div>
          </FilterPanel>
        </form>

        {/* Results */}
        <div>
          {!hasResults ? (
            freeMaterials.length > 0 ? (
              <div className="card-padded py-10 text-center">
                <p className="font-medium text-neutral-800">
                  No paid bundles match — but these free materials do
                </p>
                <p className="mt-1 text-sm text-neutral-600">
                  Download them below, or try different keywords.
                </p>
                <Link href="/search" className="btn-secondary mt-5">Clear Filters</Link>
              </div>
            ) : (
              <div className="card-padded py-16 text-center">
                <p className="font-medium text-neutral-800">
                  No past-question bundles found for your search.
                </p>
                <p className="mt-1 text-sm text-neutral-600">
                  Try different keywords or clear the filters.
                </p>
                <Link href="/search" className="btn-secondary mt-5">Clear Filters</Link>
              </div>
            )
          ) : (
            <>
              <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {items.map((b) => (
                  <BundleCard key={b.id} bundle={b} />
                ))}
              </div>

              {pages > 1 && (
                <nav className="mt-8 flex items-center justify-center gap-1" aria-label="Pagination">
                  {Array.from({ length: pages }, (_, i) => i + 1).map((p) => (
                    <Link
                      key={p}
                      href={pageHref(p)}
                      className={`grid h-9 w-9 place-items-center rounded-lg text-sm font-medium ${
                        p === page
                          ? "bg-brand-700 text-white"
                          : "bg-white text-neutral-700 hover:bg-neutral-100"
                      }`}
                    >
                      {p}
                    </Link>
                  ))}
                </nav>
              )}
            </>
          )}

          {/* Free downloadable materials — same filters, one place */}
          {freeMaterials.length > 0 && (
            <section className="mt-10 border-t border-neutral-200 pt-8">
              <div className="flex items-baseline justify-between gap-2">
                <h2 className="text-base font-bold tracking-tight text-neutral-900">
                  Free study materials
                </h2>
                <span className="text-xs text-neutral-500">
                  {freeMaterials.length} available
                </span>
              </div>
              <p className="mt-0.5 text-sm text-neutral-500">
                Slides, notes, and revision packs matching your search —
                download them free, no payment needed.
              </p>
              <div className="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {freeMaterials.map((m) => (
                  <FreeMaterialCard key={m.id} material={m} />
                ))}
              </div>
            </section>
          )}
        </div>
      </div>
    </div>
  );
}
