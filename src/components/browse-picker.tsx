"use client";

import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { PROGRAMMES, LEVELS, programmeBySlug, type ProgrammeSlug } from "@/lib/programmes";

type Intent = "papers" | "materials";
const STEPS: Record<Intent, string[]> = {
  papers: ["Year", "Semester", "Programme"],
  materials: ["Year", "Semester", "Programme"],
};

const STORAGE_KEY = "arh.browse.v1";

interface SavedChoice {
  intent: Intent;
  level: number;
  semester: number;
  programme: ProgrammeSlug;
  savedAt: number;
}

function loadSaved(): SavedChoice | null {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw) as Partial<SavedChoice>;
    const valid =
      (parsed.intent === "papers" || parsed.intent === "materials") &&
      (LEVELS as readonly number[]).includes(parsed.level as never) &&
      (parsed.semester === 1 || parsed.semester === 2) &&
      !!programmeBySlug(parsed.programme);
    if (!valid) return null;
    return parsed as SavedChoice;
  } catch {
    return null;
  }
}

function saveChoice(c: Omit<SavedChoice, "savedAt">): void {
  try {
    localStorage.setItem(
      STORAGE_KEY,
      JSON.stringify({ ...c, savedAt: Date.now() } satisfies SavedChoice),
    );
  } catch {
    // Private mode / storage full — remembering is best-effort.
  }
}

/**
 * Primary homepage flow. The visitor first declares what they came for —
 * past questions (paid, guided picker → /courses) or course materials
 * like slides (free, → /materials) — then narrows by year, semester,
 * and programme. Returning visitors get their last completed selection
 * prefilled from localStorage as a one-tap "welcome back" shortcut.
 */
export function BrowsePicker({
  enabledSlugs,
}: {
  /** Programme tracks the visitor may pick (disabled ones are hidden). */
  enabledSlugs: ProgrammeSlug[];
}) {
  const router = useRouter();
  const programmes = PROGRAMMES.filter((p) => enabledSlugs.includes(p.slug));
  const [intent, setIntent] = useState<Intent | null>(null);
  const [step, setStep] = useState(0);
  const [level, setLevel] = useState<number | null>(null);
  const [semester, setSemester] = useState<number | null>(null);
  const [programme, setProgramme] = useState<ProgrammeSlug | null>(null);

  // Restored only after mount (localStorage is client-only) — and only
  // surfaced once, via the welcome-back card.
  const [saved, setSaved] = useState<SavedChoice | null>(null);
  const restoredOnce = useRef(false);

  useEffect(() => {
    if (restoredOnce.current) return;
    restoredOnce.current = true;
    // Only offer a welcome-back shortcut if the saved programme is still
    // enabled — a disabled track is not something students should resume.
    const savedChoice = loadSaved();
    setSaved(
      savedChoice && enabledSlugs.includes(savedChoice.programme) ? savedChoice : null,
    );
  }, [enabledSlugs]);

  const canNext =
    (step === 0 && level !== null) ||
    (step === 1 && semester !== null) ||
    (step === 2 && programme !== null);

  function go() {
    if (step < 2) {
      setStep((s) => s + 1);
      return;
    }
    if (level === null || semester === null || programme === null || intent === null) return;
    saveChoice({ intent, level, semester, programme });
    const qs = `level=${level}&semester=${semester}&programme=${programme}`;
    router.push(intent === "materials" ? `/materials?${qs}` : `/courses?${qs}`);
  }

  function reset() {
    setIntent(null);
    setStep(0);
    setLevel(null);
    setSemester(null);
    setProgramme(null);
  }

  /** Jump straight to the results for a saved (or current) selection. */
  function resumeWith(i: Intent, l: number, s: number, p: ProgrammeSlug) {
    saveChoice({ intent: i, level: l, semester: s, programme: p });
    const qs = `level=${l}&semester=${s}&programme=${p}`;
    router.push(i === "materials" ? `/materials?${qs}` : `/courses?${qs}`);
  }

  function startFreshWith(i: Intent) {
    setIntent(i);
    setStep(0);
    setLevel(null);
    setSemester(null);
    setProgramme(null);
  }

  const stepLabels = intent ? STEPS[intent] : ["Year", "Semester", "Programme"];
  const savedProgramme = saved ? programmeBySlug(saved.programme) : undefined;

  return (
    <div className="card-padded overflow-hidden">
      {/* ── Welcome back: one-tap resume of the last selection ────── */}
      {intent === null && saved && (
        <div className="mb-5 rounded-xl border border-brand-200 bg-brand-50/60 p-4">
          <p className="text-sm font-semibold text-neutral-900">
            Welcome back! Last time you looked at
          </p>
          <p className="mt-1 text-sm text-neutral-700">
            <span className="font-semibold">
              {saved.intent === "papers" ? "Past questions" : "Course materials"}
            </span>{" "}
            · Level {saved.level} ·{" "}
            {saved.semester === 1 ? "First" : "Second"} Semester ·{" "}
            {savedProgramme?.short ?? saved.programme}
          </p>
          <div className="mt-3 flex flex-wrap items-center gap-2">
            <button
              type="button"
              onClick={() => resumeWith(saved.intent, saved.level, saved.semester, saved.programme)}
              className="btn-primary btn-sm"
            >
              Show that again →
            </button>
            <div className="flex flex-wrap items-center gap-2">
              <button
                type="button"
                onClick={() => {
                  // Same filters, other content type.
                  resumeWith(
                    saved.intent === "papers" ? "materials" : "papers",
                    saved.level,
                    saved.semester,
                    saved.programme,
                  );
                }}
                className="btn-secondary btn-sm"
              >
                Same filters, {saved.intent === "papers" ? "materials" : "past questions"}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ── Intent choice ─────────────────────────────────────────── */}
      {intent === null ? (
        <div>
          <h3 className="text-base font-semibold text-neutral-900">
            What are you here for?
          </h3>
          <p className="mt-0.5 text-sm text-neutral-500">
            Two doors: exam preparation or course materials.
          </p>
          <div className="mt-5 grid gap-3 sm:grid-cols-2">
            <button
              type="button"
              onClick={() => startFreshWith("papers")}
              className="group rounded-xl border-2 border-neutral-200 bg-white p-5 text-left transition-all hover:border-brand-500 hover:shadow-card"
            >
              <span className="grid h-11 w-11 place-items-center rounded-xl bg-brand-50 text-brand-700 transition-colors group-hover:bg-brand-700 group-hover:text-white">
                <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                  <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                  <path d="M14 2v6h6" />
                  <path d="M9 13h6M9 17h4" />
                </svg>
              </span>
              <p className="mt-3 font-semibold text-neutral-900">
                Past questions
              </p>
              <p className="mt-0.5 text-sm text-neutral-600">
                Official exam papers, grouped by course — buy a bundle and
                unlock every paper.
              </p>
              <span className="badge-brand mt-3 inline-flex font-semibold">
                For sale
              </span>
            </button>

            <button
              type="button"
              onClick={() => startFreshWith("materials")}
              className="group rounded-xl border-2 border-neutral-200 bg-white p-5 text-left transition-all hover:border-brand-500 hover:shadow-card"
            >
              <span className="grid h-11 w-11 place-items-center rounded-xl bg-amber-50 text-amber-700 transition-colors group-hover:bg-amber-500 group-hover:text-white">
                <svg className="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                  <rect x="3" y="3" width="18" height="18" rx="2" />
                  <path d="M7 8h10M7 12h10M7 16h6" />
                </svg>
              </span>
              <p className="mt-3 font-semibold text-neutral-900">
                Course materials
              </p>
              <p className="mt-0.5 text-sm text-neutral-600">
                Slides, lecture notes, and revision packs — free to download.
              </p>
              <span className="badge-gold mt-3 inline-flex font-semibold">
                Free
              </span>
            </button>
          </div>
        </div>
      ) : (
        <>
          {/* ── Step indicator ────────────────────────────────────── */}
          <div className="flex items-center justify-between gap-2">
            {stepLabels.map((label, i) => (
              <div key={label} className="flex flex-1 items-center gap-2">
                <button
                  type="button"
                  onClick={() => i < step && setStep(i)}
                  className={`grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-bold transition-colors ${
                    i <= step ? "bg-brand-700 text-white" : "bg-neutral-100 text-neutral-400"
                  } ${i < step ? "cursor-pointer hover:bg-brand-800" : "cursor-default"}`}
                  aria-label={`Step ${i + 1}: ${label}`}
                >
                  {i < step ? "✓" : i + 1}
                </button>
                <span
                  className={`hidden text-xs font-semibold sm:block ${
                    i <= step ? "text-neutral-900" : "text-neutral-400"
                  }`}
                >
                  {label}
                </span>
                {i < stepLabels.length - 1 && (
                  <span
                    className={`h-px flex-1 ${
                      i < step ? "bg-brand-600" : "bg-neutral-200"
                    }`}
                  />
                )}
              </div>
            ))}
          </div>

          {/* ── Step content ──────────────────────────────────────── */}
          <div className="mt-6 min-h-[200px]">
            {step === 0 && (
              <div>
                <h3 className="text-sm font-semibold text-neutral-900">
                  What year are you in?
                </h3>
                <p className="mt-0.5 text-xs text-neutral-500">
                  {intent === "papers"
                    ? "Pick your level to see the past questions for that year."
                    : "Pick your level to see the materials for that year."}
                </p>
                <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                  {LEVELS.map((l) => {
                    const active = level === l;
                    return (
                      <button
                        key={l}
                        type="button"
                        onClick={() => {
                          setLevel(l);
                          setStep(1);
                        }}
                        className={`rounded-xl border-2 py-4 text-center transition-all ${
                          active
                            ? "border-brand-600 bg-brand-50 shadow-sm"
                            : "border-neutral-200 bg-white hover:border-brand-300"
                        }`}
                      >
                        <span className="block text-xl font-bold tracking-tight text-neutral-900">
                          {l}
                        </span>
                        <span className="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">
                          {l === 100 ? "First year" : l === 200 ? "Second year" : l === 300 ? "Third year" : "Fourth year"}
                        </span>
                      </button>
                    );
                  })}
                </div>
              </div>
            )}

            {step === 1 && (
              <div>
                <h3 className="text-sm font-semibold text-neutral-900">
                  Which semester?
                </h3>
                <p className="mt-0.5 text-xs text-neutral-500">
                  {intent === "papers"
                    ? "Papers are tagged by semester — pick the one you're studying."
                    : "Materials are tagged by semester — pick the one you're studying."}
                </p>
                <div className="mt-4 grid grid-cols-2 gap-3 sm:max-w-md">
                  {[
                    { value: 1, label: "First Semester", sub: "Sept – Dec" },
                    { value: 2, label: "Second Semester", sub: "Jan – May" },
                  ].map((s) => {
                    const active = semester === s.value;
                    return (
                      <button
                        key={s.value}
                        type="button"
                        onClick={() => {
                          setSemester(s.value);
                          setStep(2);
                        }}
                        className={`rounded-xl border-2 p-4 text-left transition-all ${
                          active
                            ? "border-brand-600 bg-brand-50 shadow-sm"
                            : "border-neutral-200 bg-white hover:border-brand-300"
                        }`}
                      >
                        <span className="block text-sm font-bold text-neutral-900">
                          {s.label}
                        </span>
                        <span className="text-xs text-neutral-500">{s.sub}</span>
                      </button>
                    );
                  })}
                </div>
              </div>
            )}

            {step === 2 && (
              <div>
                <h3 className="text-sm font-semibold text-neutral-900">
                  Which programme are you on?
                </h3>
                <p className="mt-0.5 text-xs text-neutral-500">
                  {intent === "papers"
                    ? "Last step — your track determines the courses shown."
                    : "Last step — your track determines the materials shown."}
                </p>
                <div
                  className={`mt-4 grid gap-3 ${
                    programmes.length === 1
                      ? "sm:grid-cols-1 sm:max-w-md"
                      : programmes.length === 2
                        ? "sm:grid-cols-2"
                        : "sm:grid-cols-3"
                  }`}
                >
                  {programmes.map((p) => {
                    const active = programme === p.slug;
                    return (
                      <button
                        key={p.slug}
                        type="button"
                        onClick={() => setProgramme(p.slug)}
                        className={`rounded-xl border-2 p-4 text-left transition-all ${
                          active
                            ? "border-brand-600 bg-brand-50 shadow-sm"
                            : "border-neutral-200 bg-white hover:border-brand-300"
                        }`}
                      >
                        <span
                          className={`inline-block rounded-md px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide ${
                            active ? "bg-brand-700 text-white" : "bg-neutral-100 text-neutral-600"
                          }`}
                        >
                          {p.short}
                        </span>
                        <p className="mt-2 text-sm font-semibold leading-snug text-neutral-900">
                          {p.name}
                        </p>
                      </button>
                    );
                  })}
                </div>
              </div>
            )}
          </div>

          {/* ── Controls ──────────────────────────────────────────── */}
          <div className="mt-6 flex items-center justify-between gap-3 border-t border-neutral-100 pt-4">
            {step > 0 ? (
              <button
                type="button"
                onClick={() => setStep((s) => s - 1)}
                className="btn-secondary"
              >
                Back
              </button>
            ) : (
              <button
                type="button"
                onClick={reset}
                className="text-xs font-medium text-neutral-500 hover:text-brand-700"
              >
                ← Change what I'm looking for
              </button>
            )}
            <button
              type="button"
              onClick={go}
              disabled={!canNext}
              className="btn-primary"
            >
              {step < 2
                ? "Continue"
                : intent === "papers"
                  ? "Show my past questions →"
                  : "Show my materials →"}
            </button>
          </div>
        </>
      )}
    </div>
  );
}
