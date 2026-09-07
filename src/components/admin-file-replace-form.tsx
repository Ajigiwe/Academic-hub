"use client";

import { useActionState } from "react";
import { useFormStatus } from "react-dom";
import { replaceResourceFileAction, type EditResourceState } from "@/lib/admin-actions";

function ReplaceButton() {
  const { pending } = useFormStatus();
  return (
    <button type="submit" className="btn-secondary" disabled={pending}>
      {pending ? "Uploading…" : "Upload new version"}
    </button>
  );
}

export function AdminFileReplaceForm({ resourceId }: { resourceId: string }) {
  const [state, formAction] = useActionState<EditResourceState, FormData>(
    replaceResourceFileAction,
    {},
  );

  return (
    <form action={formAction} className="space-y-3">
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

      <input
        name="file"
        type="file"
        accept="application/pdf,.pdf"
        required
        className="block w-full cursor-pointer rounded-lg border border-neutral-300 bg-white text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-800 file:px-3 file:py-1.5 file:text-white hover:file:bg-brand-900"
      />
      <p className="text-xs text-neutral-500">
        Max 30 MB · max 120 pages · not password-protected. The new file goes
        through the same server-side sniffing and probing as the first upload.
      </p>

      <ReplaceButton />
    </form>
  );
}
