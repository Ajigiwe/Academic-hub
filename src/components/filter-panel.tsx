"use client";

import Link from "next/link";
import { useState } from "react";

/**
 * Collapsible filter wrapper. The <form> lives in the page (server side);
 * this component renders the toggle header and the collapsible body.
 * Collapsed by default on small screens, always open on lg+.
 */
export function FilterPanel({
  activeCount,
  children,
}: {
  activeCount: number;
  children: React.ReactNode;
}) {
  const [open, setOpen] = useState(activeCount > 0);

  return (
    <div>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        className="flex w-full items-center justify-between gap-2 px-5 py-4 text-left"
      >
        <span className="flex items-center gap-2 text-sm font-semibold text-neutral-900">
          <svg
            className="h-4 w-4 text-brand-700"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden
          >
            <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z" />
          </svg>
          Filters
          {activeCount > 0 && (
            <span className="badge-brand grid h-5 min-w-5 place-items-center rounded-full px-1.5 text-[11px] font-bold">
              {activeCount}
            </span>
          )}
        </span>
        <span className="flex items-center gap-2">
          {activeCount > 0 && (
            <Link
              href="/search"
              className="text-xs font-medium text-brand-700 hover:underline"
              onClick={(e) => e.stopPropagation()}
            >
              Clear
            </Link>
          )}
          <svg
            className={`h-4 w-4 text-neutral-400 transition-transform ${open ? "rotate-180" : ""}`}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden
          >
            <path d="m6 9 6 6 6-6" />
          </svg>
        </span>
      </button>
      <div className={`${open ? "block" : "hidden"} lg:block`}>
        <div className="space-y-4 border-t border-neutral-100 px-5 pb-5 pt-4">
          {children}
        </div>
      </div>
    </div>
  );
}
