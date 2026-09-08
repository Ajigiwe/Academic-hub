"use client";

import { useActionState } from "react";
import { useFormStatus } from "react-dom";
import { updateResourceAction, type EditResourceState } from "@/lib/admin-actions";

function SaveButton() {
  const { pending } = useFormStatus();
  return (
    <button type="submit" className="btn-primary" disabled={pending}>
      {pending ? "Saving…" : "Save changes"}
    </button>
  );
}

interface EditFormProps {
  resourceId: string;
  description: string;
  type: string;
  programmeName: string;
  programmeSuggestions: string[];
}

export function AdminResourceEditForm({
  resourceId,
  description,
  type,
  programmeName,
  programmeSuggestions,
}: EditFormProps) {
  const [state, formAction] = useActionState<EditResourceState, FormData>(
    updateResourceAction,
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
          {state.message}
        </div>
      )}

      <input type="hidden" name="resourceId" value={resourceId} />

      <div>
        <label className="mb-1.5 block text-sm font-medium" htmlFor="edit-type">
          Type
        </label>
        <select
          id="edit-type"
          name="type"
          defaultValue={type}
          className="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm"
        >
          <option value="PAST_QUESTION">Past question</option>
          <option value="LECTURE_NOTES">Lecture notes</option>
          <option value="SLIDES">Slides</option>
          <option value="REVISION">Revision</option>
          <option value="PRACTICE">Practice</option>
        </select>
      </div>

      <div>
        <label className="mb-1.5 block text-sm font-medium" htmlFor="edit-programme">
          Programme <span className="text-red-600">*</span>
        </label>
        <input
          id="edit-programme"
          name="programmeName"
          required
          minLength={2}
          maxLength={120}
          defaultValue={programmeName}
          list="edit-programme-names"
          placeholder="BSc IT"
          className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
        />
        <datalist id="edit-programme-names">
          {programmeSuggestions.map((name) => (
            <option key={name} value={name} />
          ))}
        </datalist>
      </div>

      <div>
        <label className="mb-1.5 block text-sm font-medium" htmlFor="edit-description">
          Description
        </label>
        <textarea
          id="edit-description"
          name="description"
          rows={4}
          maxLength={2000}
          defaultValue={description}
          placeholder="What students get — coverage, format, solutions included, etc."
          className="w-full rounded-lg border border-neutral-300 px-3 py-2 text-sm"
        />
      </div>

      <div className="flex items-center gap-3 pt-1">
        <SaveButton />
        <p className="text-xs text-neutral-500">
          Catalog pages update immediately after saving.
        </p>
      </div>
    </form>
  );
}
