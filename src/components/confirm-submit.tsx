"use client";

import { useState, useRef } from "react";

export function ConfirmSubmit({
  message,
  className,
  disabled,
  title,
  children,
}: {
  message: string;
  className?: string;
  disabled?: boolean;
  title?: string;
  children: React.ReactNode;
}) {
  const [open, setOpen] = useState(false);
  const btnRef = useRef<HTMLButtonElement>(null);

  function handleSubmit() {
    const form = btnRef.current?.closest("form");
    if (form) form.requestSubmit();
  }

  return (
    <>
      <button
        ref={btnRef}
        type="button"
        className={className}
        disabled={disabled}
        title={title}
        onClick={() => setOpen(true)}
      >
        {children}
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
              Confirm deletion
            </h3>
            <p className="mt-2 text-sm text-neutral-600">{message}</p>
            <div className="mt-6 flex justify-center gap-3">
              <button
                type="button"
                onClick={() => setOpen(false)}
                className="rounded-xl border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleSubmit}
                className="rounded-xl bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  );
}
