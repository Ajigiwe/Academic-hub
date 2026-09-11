"use client";

import { useState, useRef, type FormEvent } from "react";
import { editProgrammeAction } from "@/lib/admin-actions";

export function EditProgrammeModal({
  slug,
  currentName,
}: {
  slug: string;
  currentName: string;
}) {
  const [open, setOpen] = useState(false);
  const [name, setName] = useState(currentName);
  const formRef = useRef<HTMLFormElement>(null);

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    const fd = new FormData();
    fd.set("slug", slug);
    fd.set("name", name);
    editProgrammeAction(fd);
    setOpen(false);
  }

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        className="btn-secondary btn-sm"
        title={`Rename ${currentName}`}
      >
        Edit
      </button>

      {open && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm"
          onClick={(e) => {
            if (e.target === e.currentTarget) setOpen(false);
          }}
        >
          <div className="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl">
            <h3 className="text-lg font-semibold text-neutral-900">
              Rename programme
            </h3>
            <p className="mt-2 text-sm text-neutral-600">
              Current name: <span className="font-medium">{currentName}</span>
            </p>
            <form ref={formRef} onSubmit={handleSubmit} className="mt-4">
              <input
                value={name}
                onChange={(e) => setName(e.target.value)}
                className="input w-full text-center"
                maxLength={120}
                required
                autoFocus
              />
            </form>
            <div className="mt-5 flex justify-center gap-3">
              <button
                type="button"
                onClick={() => setOpen(false)}
                className="rounded-xl border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50"
              >
                Cancel
              </button>
              <button
                type="submit"
                form={undefined as never}
                onClick={() => {
                  formRef.current?.requestSubmit();
                }}
                className="rounded-xl bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700"
              >
                Save
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
