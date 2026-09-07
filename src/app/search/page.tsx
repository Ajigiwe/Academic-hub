import Link from "next/link";
import { searchBundles, getProgrammes, getFilterYears } from "@/lib/resources";
import { BundleCard } from "@/components/bundle-card";
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
  const level = sp.level ? Number(sp.level) : undefined;
  const semester = sp.semester ? Number(sp.semester) : undefined;
  const page = sp.page ? Number(sp.page) : 1;
  const sort = (["relevance", "newest", "popular", "price_asc", "price_desc"] as const).includes(
    sp.sort as never,
  )
    ? (sp.sort as "relevance" | "newest" | "popular" | "price_asc" | "price_desc")
    : "relevance";

  const [{ items, total, pages }, programmes, years] = await Promise.all([
    searchBundles({ q: sp.q, programme: sp.programme, level, semester, year: sp.year, sort, page }),
    getProgrammes(),
    getFilterYears(),
  ]);

  const hasResults = items.length > 0;
  const activeFilterCount = [sp.q, sp.programme, sp.level, sp.semester, sp.year].filter(Boolean).length;

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

      <div className="mt-6 grid gap-8 lg:grid-cols-[260px_1fr]">
        {/* Filters (collapsible) */}
        <form className="card h-fit overflow-hidden self-start" action="/search">
          <FilterPanel activeCount={activeFilterCount}>
          <input type="hidden" name="sort" value={sort} />
          <div>
            <label className="label" htmlFor="q">Search</label>
            <input id="q" name="q" defaultValue={sp.q ?? ""} className="input" placeholder="Course code, title…" />
          </div>
          <div>
            <label className="label" htmlFor="programme">Programme</label>
            <select id="programme" name="programme" defaultValue={sp.programme ?? ""} className="input">
              <option value="">All programmes</option>
              {programmes.map((p) => (
                <option key={p.id} value={p.slug}>
                  {p.name} ({p._count.bundles})
                </option>
              ))}
            </select>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="label" htmlFor="level">Level</label>
              <select id="level" name="level" defaultValue={sp.level ?? ""} className="input">
                <option value="">Any</option>
                {[100, 200, 300, 400, 500, 600].map((l) => (
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
            <div className="card-padded py-16 text-center">
              <p className="font-medium text-neutral-800">
                No past-question bundles found for your search.
              </p>
              <p className="mt-1 text-sm text-neutral-600">
                Try different keywords or clear the filters.
              </p>
              <Link href="/search" className="btn-secondary mt-5">Clear Filters</Link>
            </div>
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
        </div>
      </div>
    </div>
  );
}
