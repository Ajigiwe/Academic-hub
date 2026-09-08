import Link from "next/link";
import { PaperStack } from "@/components/paper-stack";
import { BrowsePicker } from "@/components/browse-picker";

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
        <div className="container-page relative grid items-center gap-10 py-14 sm:py-16 lg:grid-cols-[1.08fr_0.92fr] lg:gap-8 lg:py-20">
          <div className="text-center lg:text-left">
          <span className="badge badge-brand mb-5 px-3 py-1 shadow-sm">
            <svg
              className="mr-1.5 inline h-3.5 w-3.5"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
              aria-hidden
            >
              <path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z" />
            </svg>
            Instant access after payment
          </span>
          <h1 className="mx-auto max-w-2xl text-balance text-4xl font-extrabold tracking-[-0.035em] text-neutral-900 sm:text-6xl lg:mx-0">
            Find the Past Questions{" "}
            <span className="bg-gradient-to-r from-brand-700 to-brand-500 bg-clip-text text-transparent">
              You Need.
            </span>
          </h1>
          <p className="mx-auto mt-4 max-w-xl text-base text-neutral-600 sm:text-lg lg:mx-0">
            Past questions for your course and year, plus free slides and
            notes — tell us what you came for and we'll take you there.
          </p>

          {/* Two doors: the visitor picks their intent immediately */}
          <div className="mx-auto mt-7 grid max-w-xl gap-3 sm:grid-cols-2 lg:mx-0">
            <a
              href="#browse"
              className="group rounded-xl border border-neutral-200 bg-white p-4 text-left shadow-md transition-all hover:border-brand-500 hover:shadow-lift"
            >
              <span className="grid h-9 w-9 place-items-center rounded-lg bg-brand-700 text-white">
                <svg className="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                  <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                  <path d="M14 2v6h6" />
                </svg>
              </span>
              <p className="mt-2.5 text-sm font-bold text-neutral-900">
                Past questions
              </p>
              <p className="mt-0.5 text-xs text-neutral-600">
                Exam papers by course & year — for sale.
              </p>
            </a>
            <Link
              href="/materials"
              className="group rounded-xl border border-neutral-200 bg-white p-4 text-left shadow-md transition-all hover:border-amber-400 hover:shadow-lift"
            >
              <span className="grid h-9 w-9 place-items-center rounded-lg bg-amber-500 text-white">
                <svg className="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                  <rect x="3" y="3" width="18" height="18" rx="2" />
                  <path d="M7 8h10M7 12h10M7 16h6" />
                </svg>
              </span>
              <p className="mt-2.5 text-sm font-bold text-neutral-900">
                Course materials
              </p>
              <p className="mt-0.5 text-xs text-neutral-600">
                Slides & notes — free to download.
              </p>
            </Link>
          </div>
          </div>
          <PaperStack />
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
              <p className="font-display text-xl font-extrabold tracking-[-0.03em] text-brand-800 sm:text-2xl">
                {s.value}
              </p>
              <p className="mt-1 text-xs font-semibold uppercase tracking-[0.08em] text-neutral-600">
                {s.label}
              </p>
            </div>
          ))}
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
            <BrowsePicker />
          </div>
        </div>
      </section>

      {/* How it works */}
      <section className="border-t border-neutral-200 bg-white py-14">
        <div className="container-page">
          <h2 className="text-center text-xl font-bold tracking-tight text-neutral-900">
            How it works
          </h2>
          <p className="mx-auto mt-1 max-w-md text-center text-sm text-neutral-500">
            From choice to study in a few taps
          </p>
          <div className="mt-10 grid gap-8 sm:grid-cols-3">
            {[
              {
                n: "1",
                icon: (
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                    <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                    <path d="M14 2v6h6" />
                  </svg>
                ),
                title: "Choose",
                text: "Pick past questions or free materials, then your year, semester, and programme.",
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
                text: "Pay with Mobile Money or card — one price unlocks every paper in a bundle.",
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
