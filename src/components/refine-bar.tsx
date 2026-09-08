"use client";

import { useRouter } from "next/navigation";
import { PROGRAMMES, LEVELS } from "@/lib/programmes";

/**
 * Compact refine bar for the results pages (/courses, /materials): change
 * programme, level, or semester in place instead of walking back through
 * the homepage picker. Any select change navigates immediately; choosing
 * "All" drops that filter. Everything downstream is server-rendered, so
 * this component only builds URLs.
 *
 * `base` is the results route ("/courses" or "/materials"). Other query
 * params present on the page (none currently, but future-proof) are
 * preserved via the `preserve` record.
 */
export function RefineBar({
  base,
  programme,
  level,
  semester,
  resultLabel,
}: {
  base: "/courses" | "/materials";
  programme?: string;
  level?: number;
  semester?: number;
  /** What the filters act on, e.g. "course bundles" or "materials". */
  resultLabel: string;
}) {
  const router = useRouter();

  function navigate(next: {
    programme?: string;
    level?: number;
    semester?: number;
  }) {
    const params = new URLSearchParams();
    if (next.programme) params.set("programme", next.programme);
    if (next.level) params.set("level", String(next.level));
    if (next.semester) params.set("semester", String(next.semester));
    const qs = params.toString();
    router.push(qs ? `${base}?${qs}` : base);
  }

  const selectCls =
    "input h-9 w-auto min-w-0 max-w-[10rem] cursor-pointer py-1 pl-3 pr-7 text-sm";

  return (
    <div className="card mt-5 flex flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3">
      <span className="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-neutral-500">
        <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
          <path d="M3 6h18M7 12h10M10 18h4" />
        </svg>
        Refine
      </span>

      <label className="flex items-center gap-1.5 text-xs font-medium text-neutral-600">
        <span className="sr-only">Programme</span>
        <select
          className={selectCls}
          value={programme ?? ""}
          onChange={(e) => navigate({ programme: e.target.value || undefined, level, semester })}
        >
          <option value="">All programmes</option>
          {PROGRAMMES.map((p) => (
            <option key={p.slug} value={p.slug}>
              {p.short}
            </option>
          ))}
        </select>
      </label>

      <label className="flex items-center gap-1.5 text-xs font-medium text-neutral-600">
        <span className="sr-only">Level</span>
        <select
          className={selectCls}
          value={level ?? ""}
          onChange={(e) =>
            navigate({
              programme,
              level: e.target.value ? Number(e.target.value) : undefined,
              semester,
            })
          }
        >
          <option value="">All levels</option>
          {LEVELS.map((l) => (
            <option key={l} value={l}>
              Level {l}
            </option>
          ))}
        </select>
      </label>

      <label className="flex items-center gap-1.5 text-xs font-medium text-neutral-600">
        <span className="sr-only">Semester</span>
        <select
          className={selectCls}
          value={semester ?? ""}
          onChange={(e) =>
            navigate({
              programme,
              level,
              semester: e.target.value ? Number(e.target.value) : undefined,
            })
          }
        >
          <option value="">Both semesters</option>
          <option value="1">First Semester</option>
          <option value="2">Second Semester</option>
        </select>
      </label>

      <span className="ml-auto hidden text-xs text-neutral-500 sm:block">
        {resultLabel}
      </span>
    </div>
  );
}
