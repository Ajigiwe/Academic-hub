import Link from "next/link";

export default function HomePage() {
  return (
    <div>
      {/* Hero + intent entry (client flow: choose what you came for) */}
      <section className="relative overflow-hidden border-b border-neutral-200">
        {/* Layered brand backdrop */}
        <div className="absolute inset-0 bg-gradient-to-b from-brand-50 via-white to-neutral-50" />
        <div
          aria-hidden
          className="absolute inset-0 opacity-[0.35]"
          style={{
            backgroundImage:
              "radial-gradient(circle at 15% 20%, rgb(37 99 235 / 0.08) 0, transparent 40%), radial-gradient(circle at 85% 10%, rgb(11 45 91 / 0.08) 0, transparent 45%)",
          }}
        />
        <div className="container-page relative py-14 sm:py-16 lg:py-20">
          <div className="text-center">
            <h1 className="mx-auto max-w-2xl text-balance text-4xl font-extrabold tracking-[-0.035em] text-neutral-900 sm:text-6xl">
              Find the Past Questions{" "}
              <span className="bg-gradient-to-r from-brand-700 to-brand-500 bg-clip-text text-transparent">
                You Need.
              </span>
            </h1>
            <p className="mx-auto mt-4 max-w-xl text-base text-neutral-600 sm:text-lg">
              Past questions for your course and year, plus free slides and
              notes — tell us what you came for and we'll take you there.
            </p>

            {/* Two doors: the visitor picks their intent immediately */}
            <div className="mx-auto mt-8 grid max-w-2xl gap-4 sm:grid-cols-2 sm:gap-5">
              <Link
                href="/courses"
                className="group relative overflow-hidden rounded-2xl border border-neutral-200 bg-white p-6 text-left shadow-md transition-all duration-200 hover:-translate-y-1 hover:border-brand-500 hover:shadow-lift focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 sm:p-7"
              >
                {/* Soft glow that fades in behind the icon on hover */}
                <div
                  aria-hidden
                  className="pointer-events-none absolute -right-12 -top-12 h-36 w-36 rounded-full bg-brand-500/15 opacity-0 blur-2xl transition-opacity duration-300 group-hover:opacity-100"
                />
                <span className="relative grid h-14 w-14 place-items-center rounded-xl bg-gradient-to-br from-brand-600 to-brand-800 text-white shadow-card ring-1 ring-white/25 transition-transform duration-200 group-hover:-rotate-3 group-hover:scale-105">
                  <svg className="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                    <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                    <path d="M14 2v6h6" />
                  </svg>
                </span>
                <p className="relative mt-4 text-lg font-bold text-neutral-900">
                  Past questions
                </p>
                <p className="relative mt-1 text-sm text-neutral-600">
                  Exam papers by course & year — for sale.
                </p>
                <span className="relative mt-5 flex items-center justify-between">
                  <span className="badge-brand font-semibold">For sale</span>
                  <span
                    aria-hidden
                    className="inline-flex items-center gap-1 text-sm font-semibold text-brand-700 transition-transform duration-200 group-hover:translate-x-1"
                  >
                    Browse papers →
                  </span>
                </span>
              </Link>
              <Link
                href="/materials"
                className="group relative overflow-hidden rounded-2xl border border-neutral-200 bg-white p-6 text-left shadow-md transition-all duration-200 hover:-translate-y-1 hover:border-amber-400 hover:shadow-lift focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500 sm:p-7"
              >
                <div
                  aria-hidden
                  className="pointer-events-none absolute -right-12 -top-12 h-36 w-36 rounded-full bg-amber-400/20 opacity-0 blur-2xl transition-opacity duration-300 group-hover:opacity-100"
                />
                <span className="relative grid h-14 w-14 place-items-center rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 text-white shadow-card ring-1 ring-white/25 transition-transform duration-200 group-hover:rotate-3 group-hover:scale-105">
                  <svg className="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                    <rect x="3" y="3" width="18" height="18" rx="2" />
                    <path d="M7 8h10M7 12h10M7 16h6" />
                  </svg>
                </span>
                <p className="relative mt-4 text-lg font-bold text-neutral-900">
                  Course materials
                </p>
                <p className="relative mt-1 text-sm text-neutral-600">
                  Slides & notes — free to download.
                </p>
                <span className="relative mt-5 flex items-center justify-between">
                  <span className="badge-gold font-semibold">Free</span>
                  <span
                    aria-hidden
                    className="inline-flex items-center gap-1 text-sm font-semibold text-amber-700 transition-transform duration-200 group-hover:translate-x-1"
                  >
                    Browse materials →
                  </span>
                </span>
              </Link>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}
