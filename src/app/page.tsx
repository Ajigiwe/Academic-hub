import Link from "next/link";
import { getPopularBundles, getRecentBundles } from "@/lib/resources";
import { BundleCard } from "@/components/bundle-card";

export default async function HomePage() {
  const [popular, recent] = await Promise.all([
    getPopularBundles(6),
    getRecentBundles(6),
  ]);

  return (
    <div>
      {/* Hero + search (spec §7) */}
      <section className="relative overflow-hidden border-b border-neutral-200">
        {/* Layered brand backdrop */}
        <div className="absolute inset-0 bg-gradient-to-b from-brand-50 via-white to-neutral-50" />
        <div
          aria-hidden
          className="absolute inset-0 opacity-[0.35]"
          style={{
            backgroundImage:
              "radial-gradient(circle at 15% 20%, rgb(2 122 72 / 0.08) 0, transparent 40%), radial-gradient(circle at 85% 10%, rgb(247 144 9 / 0.08) 0, transparent 45%)",
          }}
        />
        <div className="container-page relative py-14 text-center sm:py-20">
          <span className="badge badge-brand mb-5 px-3 py-1 shadow-sm">
            ⚡ Instant access after payment
          </span>
          <h1 className="mx-auto max-w-2xl text-3xl font-extrabold tracking-tight text-neutral-900 sm:text-5xl">
            Find the Past Questions{" "}
            <span className="bg-gradient-to-r from-brand-700 to-brand-500 bg-clip-text text-transparent">
              You Need.
            </span>
          </h1>
          <p className="mx-auto mt-4 max-w-xl text-base text-neutral-600 sm:text-lg">
            Buy a whole year of past questions for your course in one
            purchase — every paper included.
          </p>
          <form action="/search" className="mx-auto mt-7 flex max-w-xl gap-2">
            <div className="relative flex-1">
              <svg
                className="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden
              >
                <circle cx="11" cy="11" r="8" />
                <path d="m21 21-4.3-4.3" />
              </svg>
              <input
                name="q"
                className="input h-11 pl-10 shadow-md"
                placeholder="e.g. ICT 201, Database Systems…"
                aria-label="Search past questions"
              />
            </div>
            <button type="submit" className="btn-primary h-11 shadow-md">
              Search
            </button>
          </form>
          <div className="mt-4 flex flex-wrap justify-center gap-2 text-xs text-neutral-500">
            <span className="py-1">Try:</span>
            {["database", "ICT 201", "statistics"].map((t) => (
              <Link
                key={t}
                href={`/search?q=${encodeURIComponent(t)}`}
                className="rounded-full border border-neutral-200 bg-white px-3 py-1 shadow-sm transition hover:border-brand-300 hover:text-brand-700"
              >
                {t}
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* Trust band */}
      <section className="border-b border-neutral-200 bg-white">
        <div className="container-page grid grid-cols-3 divide-x divide-neutral-100 py-6 text-center">
          {[
            { value: "100%", label: "Official past papers" },
            { value: "< 1 min", label: "From payment to reading" },
            { value: "GH₵5+", label: "Student-friendly pricing" },
          ].map((s) => (
            <div key={s.label}>
              <p className="text-lg font-extrabold tracking-tight text-brand-800 sm:text-xl">
                {s.value}
              </p>
              <p className="mt-0.5 text-[11px] font-medium uppercase tracking-wide text-neutral-500 sm:text-xs">
                {s.label}
              </p>
            </div>
          ))}
        </div>
      </section>

      {/* Popular bundles */}
      <section className="container-page py-12">
        <div className="flex items-end justify-between">
          <div>
            <h2 className="text-xl font-bold tracking-tight text-neutral-900">
              Popular bundles
            </h2>
            <p className="mt-0.5 text-sm text-neutral-500">
              Whole years of past questions students are buying
            </p>
          </div>
          <Link
            href="/search?sort=popular"
            className="flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline"
          >
            View all
            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
              <path d="M5 12h14" />
              <path d="m12 5 7 7-7 7" />
            </svg>
          </Link>
        </div>
        {popular.length === 0 ? (
          <p className="mt-4 rounded-xl border border-dashed border-neutral-300 bg-white p-8 text-center text-sm text-neutral-500">
            The catalog is being stocked. Check back soon.
          </p>
        ) : (
          <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {popular.map((b) => (
              <BundleCard key={b.id} bundle={b} />
            ))}
          </div>
        )}
      </section>

      {/* Recently added bundles */}
      <section className="container-page pb-12">
        <div className="flex items-end justify-between">
          <div>
            <h2 className="text-xl font-bold tracking-tight text-neutral-900">
              Recently added
            </h2>
            <p className="mt-0.5 text-sm text-neutral-500">
              New year bundles from the archive
            </p>
          </div>
          <Link
            href="/search?sort=newest"
            className="flex items-center gap-1 text-sm font-semibold text-brand-700 hover:underline"
          >
            View all
            <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
              <path d="M5 12h14" />
              <path d="m12 5 7 7-7 7" />
            </svg>
          </Link>
        </div>
        {recent.length === 0 ? (
          <p className="mt-4 rounded-xl border border-dashed border-neutral-300 bg-white p-8 text-center text-sm text-neutral-500">
            New resources will appear here.
          </p>
        ) : (
          <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {recent.map((b) => (
              <BundleCard key={b.id} bundle={b} />
            ))}
          </div>
        )}
      </section>

      {/* How it works */}
      <section className="border-t border-neutral-200 bg-white py-14">
        <div className="container-page">
          <h2 className="text-center text-xl font-bold tracking-tight text-neutral-900">
            How it works
          </h2>
          <p className="mx-auto mt-1 max-w-md text-center text-sm text-neutral-500">
            From search to study in three steps
          </p>
          <div className="mt-10 grid gap-8 sm:grid-cols-3">
            {[
              {
                n: "1",
                icon: (
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                    <circle cx="11" cy="11" r="8" />
                    <path d="m21 21-4.3-4.3" />
                  </svg>
                ),
                title: "Find",
                text: "Search by course, programme, level, semester, or year.",
              },
              {
                n: "2",
                icon: (
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                    <rect x="2" y="5" width="20" height="14" rx="2" />
                    <path d="M2 10h20" />
                  </svg>
                ),
                title: "Purchase",
                text: "Pay with Mobile Money or card through secure checkout.",
              },
              {
                n: "3",
                icon: (
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                    <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20" />
                  </svg>
                ),
                title: "Study",
                text: "Read instantly in your secure in-app library — on any device.",
              },
            ].map((s) => (
              <div key={s.title} className="relative text-center">
                <div className="relative mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-gradient-to-br from-brand-600 to-brand-800 text-white shadow-card">
                  {s.icon}
                  <span className="absolute -right-1.5 -top-1.5 grid h-5 w-5 place-items-center rounded-full bg-gold-400 text-[10px] font-bold text-white shadow-sm">
                    {s.n}
                  </span>
                </div>
                <h3 className="mt-4 font-semibold text-neutral-900">{s.title}</h3>
                <p className="mx-auto mt-1 max-w-xs text-sm leading-relaxed text-neutral-600">
                  {s.text}
                </p>
              </div>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
}
