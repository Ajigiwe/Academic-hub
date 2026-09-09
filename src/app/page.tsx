import Link from "next/link";
import { BrowsePicker } from "@/components/browse-picker";
import { getEnabledProgrammeSlugs } from "@/lib/settings";

export default async function HomePage() {
  const enabledSlugs = await getEnabledProgrammeSlugs();
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
              <a
                href="#browse"
                className="group rounded-2xl border border-neutral-200 bg-white p-6 text-left shadow-md transition-all hover:border-brand-500 hover:shadow-lift sm:p-7"
              >
                <span className="grid h-14 w-14 place-items-center rounded-xl bg-brand-700 text-white transition-colors group-hover:bg-brand-800">
                  <svg className="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                    <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                    <path d="M14 2v6h6" />
                  </svg>
                </span>
                <p className="mt-4 text-lg font-bold text-neutral-900">
                  Past questions
                </p>
                <p className="mt-1 text-sm text-neutral-600">
                  Exam papers by course & year — for sale.
                </p>
              </a>
              <Link
                href="/materials"
                className="group rounded-2xl border border-neutral-200 bg-white p-6 text-left shadow-md transition-all hover:border-amber-400 hover:shadow-lift sm:p-7"
              >
                <span className="grid h-14 w-14 place-items-center rounded-xl bg-amber-500 text-white transition-colors group-hover:bg-amber-600">
                  <svg className="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                    <rect x="3" y="3" width="18" height="18" rx="2" />
                    <path d="M7 8h10M7 12h10M7 16h6" />
                  </svg>
                </span>
                <p className="mt-4 text-lg font-bold text-neutral-900">
                  Course materials
                </p>
                <p className="mt-1 text-sm text-neutral-600">
                  Slides & notes — free to download.
                </p>
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* Guided picker — intent → year → semester → programme. The main
          event of the homepage: no bundles are shown until the visitor
          declares what they want. */}
      <section id="browse" className="container-page scroll-mt-20 py-12">
        <div className="mx-auto max-w-3xl">
          <h2 className="text-center text-xl font-bold tracking-tight text-neutral-900">
            What are you here for?
          </h2>
          <p className="mx-auto mt-1 max-w-md text-center text-sm text-neutral-500">
            Choose past questions or course materials, then your year,
            semester, and programme.
          </p>
          <div className="mt-6">
            <BrowsePicker enabledSlugs={enabledSlugs} />
          </div>
        </div>
      </section>
    </div>
  );
}
