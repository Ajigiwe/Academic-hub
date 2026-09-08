/**
 * Decorative stacked-paper illustration for the homepage hero — three
 * year-bundle "books" fanned like a shelf: coloured spines with course
 * codes, first-page thumbnails with mock question layouts. Pure markup,
 * aria-hidden (it's ornament, not content).
 */
export function PaperStack({ className = "" }: { className?: string }) {
  return (
    <div
      aria-hidden
      className={`relative mx-auto h-[300px] w-[250px] sm:h-[360px] sm:w-[300px] ${className}`}
    >
      {/* Back-left paper */}
      <div className="absolute left-0 top-14 flex h-[250px] w-[180px] -rotate-7 overflow-hidden rounded-lg border border-neutral-200/80 bg-white shadow-card sm:h-[300px] sm:w-[215px]">
        <Spine code="BUS 101" tone="from-brand-800 to-brand-950" />
        <FakePage variant="muted" />
      </div>

      {/* Back-right paper */}
      <div className="absolute right-0 top-14 flex h-[250px] w-[180px] rotate-7 overflow-hidden rounded-lg border border-neutral-200/80 bg-white shadow-card sm:h-[300px] sm:w-[215px]">
        <Spine code="STAT 210" tone="from-brand-700 to-brand-900" />
        <FakePage variant="muted" />
      </div>

      {/* Front paper */}
      <div className="absolute left-1/2 top-0 z-10 flex h-[280px] w-[200px] -translate-x-1/2 overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-lift sm:h-[340px] sm:w-[245px]">
        <Spine code="ICT 201" tone="from-brand-600 to-brand-900" />
        <FakePage variant="highlight" />
      </div>
    </div>
  );
}

function Spine({ code, tone }: { code: string; tone: string }) {
  return (
    <div
      className={`flex w-8 shrink-0 flex-col items-center justify-between bg-gradient-to-b ${tone} py-2.5 text-white sm:w-9`}
    >
      <span className="text-[8px] font-bold tracking-[0.22em] [writing-mode:vertical-rl] sm:text-[9px]">
        {code}
      </span>
      <span className="grid h-4.5 w-4.5 place-items-center rounded bg-white/15 text-[6.5px] font-black sm:h-5 sm:w-5 sm:text-[7px]">
        AH
      </span>
    </div>
  );
}

function FakePage({ variant }: { variant: "muted" | "highlight" }) {
  if (variant === "muted") {
    return (
      <div className="flex flex-1 flex-col gap-1.5 p-3 sm:gap-2 sm:p-3.5">
        <div className="h-1.5 w-3/5 rounded-full bg-neutral-200" />
        <div className="h-1.5 w-4/5 rounded-full bg-neutral-200" />
        <div className="h-1.5 w-1/2 rounded-full bg-neutral-200" />
        <div className="mt-1.5 space-y-1.5">
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <div
              key={i}
              className={`h-1 rounded-full bg-neutral-100 ${
                i % 3 === 0 ? "w-4/5" : "w-full"
              }`}
            />
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="relative flex flex-1 flex-col p-3 sm:p-4">
      {/* Past-question label */}
      <div className="flex items-center gap-1 text-[7px] font-bold uppercase tracking-[0.16em] text-brand-600 sm:text-[8px]">
        <svg
          width="9"
          height="9"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="3"
          strokeLinecap="round"
          strokeLinejoin="round"
          aria-hidden
        >
          <path d="M20 6 9 17l-5-5" />
        </svg>
        Past question
      </div>

      {/* Title bars */}
      <div className="mt-2 space-y-1.5">
        <div className="h-2 w-11/12 rounded-full bg-neutral-300" />
        <div className="h-2 w-4/5 rounded-full bg-neutral-300" />
        <div className="h-2 w-3/5 rounded-full bg-neutral-300" />
      </div>

      {/* Semester chip */}
      <div className="mt-2.5 inline-flex w-fit items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-[7px] font-bold text-brand-700 sm:text-[8px]">
        <svg
          width="8"
          height="8"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2.5"
          strokeLinecap="round"
          strokeLinejoin="round"
          aria-hidden
        >
          <rect x="3" y="4" width="18" height="18" rx="2" />
          <path d="M16 2v4" />
          <path d="M8 2v4" />
          <path d="M3 10h18" />
        </svg>
        Semester 1 · 28 pages
      </div>

      {/* Question content */}
      <div className="mt-2.5 space-y-1.5">
        <div className="h-1 w-full rounded-full bg-neutral-100" />
        <div className="h-1 w-full rounded-full bg-neutral-100" />
        <div className="h-1 w-5/6 rounded-full bg-neutral-100" />
        <div className="h-1 w-11/12 rounded-full bg-neutral-100" />
        <div className="h-1 w-3/4 rounded-full bg-neutral-100" />
        <div className="h-1 w-full rounded-full bg-neutral-100" />
      </div>

      {/* Watermark */}
      <svg
        className="pointer-events-none absolute -bottom-1 -right-1 h-12 w-12 text-brand-100 sm:h-14 sm:w-14"
        viewBox="0 0 120 132"
        fill="none"
        aria-hidden
      >
        <g stroke="currentColor" strokeWidth="10" strokeLinecap="round" strokeLinejoin="round">
          <path d="M40 30 L16 90" />
          <path d="M40 30 L40 90" />
          <path d="M24 64 L44 64" strokeWidth="8.5" />
          <path d="M72 22 L72 90" strokeWidth="11" />
          <path d="M40 58 L72 58" strokeWidth="8.5" />
        </g>
      </svg>
    </div>
  );
}