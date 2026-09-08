"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { PROGRAMMES, LEVELS, type ProgrammeSlug } from "@/lib/programmes";

const STEPS = ["Programme", "Level", "Semester"] as const;

/**
 * Guided browse flow: Programme → Level → Semester → /courses. This is
 * the primary path students take on entry — pick your track and year,
 * then the semester you're studying, and see the courses you can pay for
 * (plus free materials for that combination).
 */
export function BrowsePicker() {
  const router = useRouter();
  const [step, setStep] = useState(0);
  const [programme, setProgramme] = useState<ProgrammeSlug | null>(null);
  const [level, setLevel] = useState<number | null>(null);
  const [semester, setSemester] = useState<number | null>(null);

  const canNext =
    (step === 0 && programme !== null) ||
    (step === 1 && level !== null) ||
    (step === 2 && semester !== null);

  function go() {
    if (step < 2) {
      setStep((s) => s + 1);
    } else if (programme && level && semester) {
      router.push(
        `/courses?programme=${programme}&level=${level}&semester=${semester}`,
      );
    }
  }

  function reset() {
    setStep(0);
    setProgramme(null);
    setLevel(null);
    setSemester(null);
  }

  return (
    <div className="card-padded overflow-hidden">
      {/* Step indicator */}
      <div className="flex items-center justify-between gap-2">
        {STEPS.map((label, i) => (
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
            {i < STEPS.length - 1 && (
              <span
                className={`h-px flex-1 ${
                  i < step ? "bg-brand-600" : "bg-neutral-200"
                }`}
              />
            )}
          </div>
        ))}
      </div>

      {/* Step content */}
      <div className="mt-6 min-h-[200px]">
        {step === 0 && (
          <div>
            <h3 className="text-sm font-semibold text-neutral-900">
              Which programme are you on?
            </h3>
            <p className="mt-0.5 text-xs text-neutral-500">
              Choose your track — past questions are grouped per programme.
            </p>
            <div className="mt-4 grid gap-3 sm:grid-cols-3">
              {PROGRAMMES.map((p) => {
                const active = programme === p.slug;
                return (
                  <button
                    key={p.slug}
                    type="button"
                    onClick={() => {
                      setProgramme(p.slug);
                      setStep(1);
                    }}
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

        {step === 1 && (
          <div>
            <h3 className="text-sm font-semibold text-neutral-900">
              What year are you in?
            </h3>
            <p className="mt-0.5 text-xs text-neutral-500">
              Pick your level to see the courses for that year.
            </p>
            <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
              {LEVELS.map((l) => {
                const active = level === l;
                return (
                  <button
                    key={l}
                    type="button"
                    onClick={() => setLevel(l)}
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

        {step === 2 && (
          <div>
            <h3 className="text-sm font-semibold text-neutral-900">
              Which semester are you preparing for?
            </h3>
            <p className="mt-0.5 text-xs text-neutral-500">
              Papers are tagged by semester — pick the one you're studying now.
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
                    onClick={() => setSemester(s.value)}
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
      </div>

      {/* Controls */}
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
          <span className="text-xs text-neutral-400">
            {programme || level || semester ? (
              <button type="button" onClick={reset} className="font-medium text-brand-700 hover:underline">
                Start over
              </button>
            ) : (
              "Three quick steps"
            )}
          </span>
        )}
        <button type="button" onClick={go} disabled={!canNext} className="btn-primary">
          {step < 2
            ? "Continue"
            : programme && level && semester
              ? "Show my courses →"
              : "Continue"}
        </button>
      </div>
    </div>
  );
}