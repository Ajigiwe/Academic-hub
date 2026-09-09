"use client";

import { useActionState } from "react";
import { useFormStatus } from "react-dom";
import Link from "next/link";
import { uploadFreeMaterialAction, type FreeUploadState } from "@/lib/admin-actions";

function SubmitButton() {
  const { pending } = useFormStatus();
  return (
    <button type="submit" className="btn-secondary" disabled={pending}>
      {pending ? "Publishing…" : "Publish free material"}
    </button>
  );
}

export interface CourseSuggestion {
  code: string;
  title: string;
}

/**
 * Upload a FREE downloadable material (slides, lecture notes, revision
 * packs). Not part of any sale bundle — published immediately and served
 * through the public download route. Grouped by course, programme track,
 * level, and semester like everything else in the catalogue.
 */
export function FreeMaterialUploadForm({
  suggestions,
  programmes,
}: {
  suggestions: CourseSuggestion[];
  /** Programme tracks available for new materials (disabled ones are hidden). */
  programmes: { slug: string; name: string }[];
}) {
  const [state, formAction] = useActionState<FreeUploadState, FormData>(
    uploadFreeMaterialAction,
    {},
  );

  return (
    <form action={formAction} className="space-y-4">
      {state.message && (
        <div
          className={
            state.ok
              ? "rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900"
              : "rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"
          }
          role="status"
        >
          {state.message}{" "}
          {state.ok && state.resourceSlug && (
            <Link href={`/resources/${state.resourceSlug}`} className="font-semibold underline">
              View material →
            </Link>
          )}
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <label className="mb-1.5 block text-sm font-medium" htmlFor="free-title">
            Title <span className="text-red-600">*</span>
          </label>
          <input
            id="free-title"
            name="title"
            required
            minLength={6}
            maxLength={160}
            placeholder="ICT 201 — Database Systems Lecture Slides (Sem 1)"
            className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
          />
        </div>
        <div>
          <label className="mb-1.5 block text-sm font-medium" htmlFor="free-type">
            Material type <span className="text-red-600">*</span>
          </label>
          <select
            id="free-type"
            name="type"
            defaultValue="SLIDES"
            className="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm"
          >
            <option value="SLIDES">Slides</option>
            <option value="LECTURE_NOTES">Lecture notes</option>
            <option value="REVISION">Revision material</option>
            <option value="PRACTICE">Practice set</option>
          </select>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <label className="mb-1.5 block text-sm font-medium" htmlFor="free-course-code">
            Course code <span className="text-red-600">*</span>
          </label>
          <input
            id="free-course-code"
            name="courseCode"
            required
            list="free-course-codes"
            placeholder="ICT 201"
            className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm uppercase"
          />
          <datalist id="free-course-codes">
            {suggestions.map((s) => (
              <option key={s.code} value={s.code}>{s.title}</option>
            ))}
          </datalist>
        </div>
        <div>
          <label className="mb-1.5 block text-sm font-medium" htmlFor="free-course-title">
            Course title <span className="text-red-600">*</span>
          </label>
          <input
            id="free-course-title"
            name="courseTitle"
            required
            minLength={3}
            maxLength={120}
            placeholder="Database Systems"
            className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
          />
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-3">
        <div>
          <label className="mb-1.5 block text-sm font-medium" htmlFor="free-programme">
            Programme track <span className="text-red-600">*</span>
          </label>
          <select
            id="free-programme"
            name="programmeName"
            required
            defaultValue={programmes[0]?.name ?? ""}
            className="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm"
          >
            {programmes.map((p) => (
              <option key={p.slug} value={p.name}>
                {p.name}
              </option>
            ))}
          </select>
        </div>
        <div>
          <label className="mb-1.5 block text-sm font-medium" htmlFor="free-level">
            Level <span className="text-red-600">*</span>
          </label>
          <select
            id="free-level"
            name="level"
            required
            defaultValue="200"
            className="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm"
          >
            {[100, 200, 300, 400].map((l) => (
              <option key={l} value={l}>Level {l}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="mb-1.5 block text-sm font-medium" htmlFor="free-semester">
            Semester <span className="text-red-600">*</span>
          </label>
          <select
            id="free-semester"
            name="semester"
            required
            defaultValue="1"
            className="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm"
          >
            <option value="1">First (1)</option>
            <option value="2">Second (2)</option>
          </select>
        </div>
      </div>

      <div>
        <label className="mb-1.5 block text-sm font-medium" htmlFor="free-file">
          PDF file <span className="text-red-600">*</span>
        </label>
        <input
          id="free-file"
          name="file"
          type="file"
          accept="application/pdf,.pdf"
          required
          className="block w-full cursor-pointer rounded-lg border border-neutral-300 bg-white text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-800 file:px-3 file:py-1.5 file:text-white hover:file:bg-brand-900"
        />
        <p className="mt-1 text-xs text-neutral-500">
          One PDF — slides, notes, or a revision pack. Max 30 MB · 120 pages.
        </p>
      </div>

      <div>
        <label className="mb-1.5 block text-sm font-medium" htmlFor="free-description">
          Description
        </label>
        <textarea
          id="free-description"
          name="description"
          rows={3}
          maxLength={2000}
          placeholder="What students get — topics covered, semester taught, etc."
          className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
        />
      </div>

      <div className="flex items-center gap-3 pt-1">
        <SubmitButton />
        <p className="text-xs text-neutral-500">
          Published instantly — students can download it without paying.
        </p>
      </div>
    </form>
  );
}