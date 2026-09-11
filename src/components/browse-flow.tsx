"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { LEVELS } from "@/lib/programmes";

/**
 * The three-step browse flow shared by /courses (past questions) and
 * /materials (free downloads): programme → level → semester. Each choice
 * advances automatically; the parent server page renders the library once
 * all three query params are present. Changing a choice drops the steps
 * after it, so the URL always reflects a full, valid selection.
 */

export interface BrowseFlowProgramme {
  slug: string;
  name: string;
  /** Published bundle count, shown on the step-1 card when provided. */
  bundles?: number;
  /** Published free-material count, shown on the step-1 card when provided. */
  materials?: number;
}

const SEMESTERS = [
  { value: 1, label: "First Semester", sub: "Sept – Dec" },
  { value: 2, label: "Second Semester", sub: "Jan – May" },
] as const;

function StepHeader({
  n,
  title,
  hint,
}: {
  n: number;
  title: string;
  hint: string;
}) {
  return (
    <div>
      <p className="text-[11px] font-bold uppercase tracking-widest text-brand-700">
        Step {n} of 3
      </p>
      <h2 className="mt-1 text-lg font-bold tracking-tight text-neutral-900">{title}</h2>
      <p className="mt-0.5 text-sm text-neutral-600">{hint}</p>
    </div>
  );
}

function ChoiceButton({
  active,
  onClick,
  children,
  className = "",
}: {
  active: boolean;
  onClick: () => void;
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-pressed={active}
      className={`rounded-xl border-2 p-4 text-left transition-all ${className} ${
        active
          ? "border-brand-600 bg-brand-50 shadow-sm"
          : "border-neutral-200 bg-white hover:border-brand-300 hover:shadow-card"
      }`}
    >
      {children}
    </button>
  );
}

export function BrowseFlow({
  base,
  programmes,
  programme,
  level,
  semester,
}: {
  /** Results route this flow feeds, e.g. "/courses" or "/materials". */
  base: string;
  /** Programme tracks the admin has enabled, in display order. */
  programmes: BrowseFlowProgramme[];
  programme?: string;
  level?: number;
  semester?: number;
}) {
  const router = useRouter();
  // Selections made inside the flow before committing (URL drives the
  // committed state once the library is shown).
  const [draftProgramme, setDraftProgramme] = useState<string | null>(null);
  const [draftLevel, setDraftLevel] = useState<number | null>(null);
  const [draftSemester, setDraftSemester] = useState<number | null>(null);

  const step = !programme ? 0 : !level ? 1 : 2;

  const selProgramme = draftProgramme ?? programme ?? null;
  const selLevel = draftLevel ?? level ?? null;
  const selSemester = draftSemester ?? semester ?? null;

  function go(next: { programme?: string; level?: number; semester?: number }) {
    const params = new URLSearchParams();
    if (next.programme) params.set("programme", next.programme);
    if (next.level) params.set("level", String(next.level));
    if (next.semester) params.set("semester", String(next.semester));
    router.push(`${base}?${params.toString()}`);
  }

  return (
    <div className="card-padded">
      {/* Step indicator */}
      <ol className="flex items-center gap-2" aria-label="Browse steps">
        {["Programme", "Level", "Semester"].map((label, i) => {
          const state = i < step ? "done" : i === step ? "current" : "todo";
          return (
            <li key={label} className="flex flex-1 items-center gap-2">
              <span
                className={`grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-bold ${
                  state === "todo"
                    ? "bg-neutral-100 text-neutral-400"
                    : "bg-brand-700 text-white"
                }`}
                aria-current={state === "current" ? "step" : undefined}
              >
                {state === "done" ? "✓" : i + 1}
              </span>
              <span
                className={`hidden text-xs font-semibold sm:block ${
                  state === "todo" ? "text-neutral-400" : "text-neutral-900"
                }`}
              >
                {label}
              </span>
              {i < 2 && (
                <span
                  className={`h-px flex-1 ${i < step ? "bg-brand-600" : "bg-neutral-200"}`}
                  aria-hidden
                />
              )}
            </li>
          );
        })}
      </ol>

      {/* Step 1 — programme */}
      {step === 0 && (
        <div className="mt-6">
          <StepHeader
            n={1}
            title="Select your programme"
            hint="The track you're enrolled on determines the courses shown."
          />
          <div
            className={`mt-4 grid gap-3 ${
              programmes.length === 1
                ? "sm:max-w-md"
                : programmes.length === 2
                  ? "sm:grid-cols-2"
                  : "sm:grid-cols-3"
            }`}
          >
            {programmes.map((p) => {
              const hasContent =
                (p.bundles ?? 0) > 0 || (p.materials ?? 0) > 0;
              return (
                <ChoiceButton
                  key={p.slug}
                  active={selProgramme === p.slug}
                  onClick={() => go({ programme: p.slug })}
                >
                  <span
                    className={`inline-block rounded-md px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide ${
                      selProgramme === p.slug
                        ? "bg-brand-700 text-white"
                        : "bg-neutral-100 text-neutral-600"
                    }`}
                  >
                    {p.slug}
                  </span>
                  <p className="mt-2 text-sm font-semibold leading-snug text-neutral-900">
                    {p.name}
                  </p>
                  <p className="mt-1 text-xs text-neutral-500">
                    {hasContent
                      ? [
                          (p.bundles ?? 0) > 0 &&
                            `${p.bundles} bundle${p.bundles === 1 ? "" : "s"}`,
                          (p.materials ?? 0) > 0 &&
                            `${p.materials} free material${p.materials === 1 ? "" : "s"}`,
                        ]
                          .filter(Boolean)
                          .join(" · ")
                      : "No content yet"}
                  </p>
                </ChoiceButton>
              );
            })}
          </div>
        </div>
      )}

      {/* Step 2 — level */}
      {step === 1 && (
        <div className="mt-6">
          <StepHeader
            n={2}
            title="Select your level"
            hint="Pick the year you're in to see its papers and materials."
          />
          <div className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            {LEVELS.map((l) => (
              <ChoiceButton
                  key={l}
                  active={selLevel === l}
                  onClick={() => programme && go({ programme, level: l })}
                  className="text-center"
                >
                <span className="block text-xl font-bold tracking-tight text-neutral-900">
                  {l}
                </span>
                <span className="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">
                  {l === 100
                    ? "First year"
                    : l === 200
                      ? "Second year"
                      : l === 300
                        ? "Third year"
                        : "Fourth year"}
                </span>
              </ChoiceButton>
            ))}
          </div>
        </div>
      )}

      {/* Step 3 — semester */}
      {step === 2 && (
        <div className="mt-6">
          <StepHeader
            n={3}
            title="Select your semester"
            hint="Content is tagged by semester — pick the one you're studying."
          />
          <div className="mt-4 grid grid-cols-2 gap-3 sm:max-w-md">
            {SEMESTERS.map((s) => (
              <ChoiceButton
                  key={s.value}
                  active={selSemester === s.value}
                  onClick={() =>
                    programme && level && go({ programme, level, semester: s.value })
                  }
                >
                <span className="block text-sm font-bold text-neutral-900">{s.label}</span>
                <span className="text-xs text-neutral-500">{s.sub}</span>
              </ChoiceButton>
            ))}
          </div>
        </div>
      )}

      {/* Controls */}
      <div className="mt-6 flex items-center justify-between gap-3 border-t border-neutral-100 pt-4">
        {step === 0 ? (
          <span className="text-xs text-neutral-500">
            {programmes.length} programme{programmes.length === 1 ? "" : "s"} available
          </span>
        ) : (
          <button
            type="button"
            onClick={() => router.push(base)}
            className="btn-secondary"
          >
            ← Back
          </button>
        )}
      </div>

    </div>
  );
}
