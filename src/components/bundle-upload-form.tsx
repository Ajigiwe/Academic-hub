"use client";

import { useActionState, useState } from "react";
import { useFormStatus } from "react-dom";
import Link from "next/link";
import { uploadBundleFilesAction, type UploadState } from "@/lib/admin-actions";

export interface BundleOption {
  id: string;
  title: string;
  course: { code: string };
  academicYear: string;
  status: string;
}

interface Suggestion {
  code: string;
  title: string;
  programmes: { name: string }[];
}

function SubmitButton() {
  const { pending } = useFormStatus();
  return (
    <button type="submit" className="btn-primary" disabled={pending}>
      {pending ? "Uploading…" : "Upload PDFs"}
    </button>
  );
}

export function BundleUploadForm({
  bundles,
  suggestions,
  programmes,
}: {
  bundles: BundleOption[];
  suggestions: Suggestion[];
  /** Programme tracks available for new bundles (disabled ones are hidden). */
  programmes: { slug: string; name: string }[];
}) {
  const [state, formAction] = useActionState<UploadState, FormData>(
    uploadBundleFilesAction,
    {},
  );
  // The select defaults to "Create a new bundle", so the metadata section
  // must render by default — deriving it from onChange alone meant the
  // fields never mounted until the user touched the select, and a submit
  // then sent nulls for every metadata field.
  const [creatingBundle, setCreatingBundle] = useState(true);

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
          {state.ok && state.bundleSlug && (
            <Link href={`/bundles/${state.bundleSlug}`} className="font-semibold underline">
              View bundle →
            </Link>
          )}
        </div>
      )}

      <div>
        <label className="mb-1.5 block text-sm font-medium" htmlFor="upload-files">
          PDF files <span className="text-red-600">*</span>
        </label>
        <input
          id="upload-files"
          name="files"
          type="file"
          accept="application/pdf,.pdf"
          multiple
          required
          className="block w-full cursor-pointer rounded-lg border border-neutral-300 bg-white text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-800 file:px-3 file:py-1.5 file:text-white hover:file:bg-brand-900"
        />
        <p className="mt-1 text-xs text-neutral-500">
          Select several PDFs at once — each becomes its own paper inside one
          bundle. Max 30 files · 30 MB each · 120 pages · not password-protected.
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <label className="mb-1.5 block text-sm font-medium" htmlFor="upload-bundle">
            Bundle
          </label>
          <select
            id="upload-bundle"
            name="bundleId"
            className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
            defaultValue=""
            onChange={(e) => setCreatingBundle(e.target.value === "")}
          >
            <option value="">＋ Create a new bundle</option>
            {bundles.map((b) => (
              <option key={b.id} value={b.id}>
                {b.title} · {b.academicYear} ({b.status})
              </option>
            ))}
          </select>
          <p className="mt-1 text-xs text-neutral-500">
            Publish the bundle from the Bundles page when the papers are ready.
          </p>
        </div>
        <div>
          <label className="mb-1.5 block text-sm font-medium" htmlFor="upload-semester">
            Semester
          </label>
          <select id="upload-semester" name="semester" defaultValue="1" className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm">
            <option value="1">First (1)</option>
            <option value="2">Second (2)</option>
          </select>
          <p className="mt-1 text-xs text-neutral-500">
            Applies to this batch — upload each semester separately.
          </p>
        </div>
      </div>

      {creatingBundle && (
        <div className="space-y-4 rounded-lg border border-brand-200 bg-brand-50/40 p-4">
          <p className="text-xs font-semibold uppercase tracking-wide text-brand-800">
            New bundle details — sold as one unit to students
          </p>

          <div>
            <label className="mb-1.5 block text-sm font-medium" htmlFor="upload-title">
              Bundle title <span className="text-red-600">*</span>
            </label>
            <input
              id="upload-title"
              name="title"
              required
              minLength={6}
              maxLength={160}
              placeholder="ICT 201 — Database Systems · 2025/2026 Past Questions"
              className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
            />
          </div>

          <div className="grid gap-4 sm:grid-cols-3">
            <div>
              <label className="mb-1.5 block text-sm font-medium" htmlFor="upload-level">
                Level
              </label>
              <select id="upload-level" name="level" defaultValue="200" className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm">
                {[100, 200, 300, 400].map((l) => (
                  <option key={l} value={l}>{l}</option>
                ))}
              </select>
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium" htmlFor="upload-year">
                Academic year <span className="text-red-600">*</span>
              </label>
              <input
                id="upload-year"
                name="academicYear"
                required
                placeholder="2025/2026"
                pattern="\d{4}/\d{4}"
                className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
              />
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium" htmlFor="upload-price">
                Bundle price (GH₵) <span className="text-red-600">*</span>
              </label>
              <input
                id="upload-price"
                name="price"
                type="number"
                min="0"
                max="1000"
                step="0.50"
                defaultValue="25"
                required
                className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
              />
            </div>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label className="mb-1.5 block text-sm font-medium" htmlFor="upload-course-code">
                Course code <span className="text-red-600">*</span>
              </label>
              <input
                id="upload-course-code"
                name="courseCode"
                required
                list="course-codes"
                placeholder="ICT 201"
                className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm uppercase"
              />
              <datalist id="course-codes">
                {suggestions.map((s) => (
                  <option key={s.code} value={s.code}>{s.title}</option>
                ))}
              </datalist>
            </div>
            <div>
              <label className="mb-1.5 block text-sm font-medium" htmlFor="upload-course-title">
                Course title <span className="text-red-600">*</span>
              </label>
              <input
                id="upload-course-title"
                name="courseTitle"
                required
                minLength={3}
                maxLength={120}
                placeholder="Database Systems"
                className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
              />
            </div>
          </div>

          <div>
            <label className="mb-1.5 block text-sm font-medium" htmlFor="upload-programme">
              Programme track <span className="text-red-600">*</span>
            </label>
            <select
              id="upload-programme"
              name="programmeName"
              required
              defaultValue={programmes[0]?.name ?? ""}
              className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
            >
              {programmes.map((p) => (
                <option key={p.slug} value={p.name}>
                  {p.name}
                </option>
              ))}
            </select>
            <p className="mt-1 text-xs text-neutral-500">
              Papers are grouped under this track — students pick it in the
              browse flow.
            </p>
          </div>

          <div>
            <label className="mb-1.5 block text-sm font-medium" htmlFor="upload-description">
              Description
            </label>
            <textarea
              id="upload-description"
              name="description"
              rows={3}
              maxLength={2000}
              placeholder="What students get — papers included, coverage, format, etc."
              className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
            />
          </div>

          <div className="flex items-center gap-3">
            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                name="solved"
                value="true"
                className="h-4 w-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500"
              />
              <span className="text-sm font-medium text-neutral-700">Includes solutions</span>
            </label>
            <p className="text-xs text-neutral-500">
              Check if this bundle contains worked solutions alongside the past questions.
            </p>
          </div>
        </div>
      )}

      <div className="flex items-center gap-3 pt-2">
        <SubmitButton />
        <p className="text-xs text-neutral-500">
          Papers are created as drafts — publish the bundle to put everything
          on sale.
        </p>
      </div>
    </form>
  );
}