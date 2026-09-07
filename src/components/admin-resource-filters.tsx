"use client";

import { useEffect, useRef, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";

const STATUSES = [
  { value: "", label: "All statuses" },
  { value: "PUBLISHED", label: "Published" },
  { value: "DRAFT", label: "Draft" },
  { value: "UNPUBLISHED", label: "Unpublished" },
  { value: "ARCHIVED", label: "Archived" },
];

export function AdminResourceFilters({
  programmes,
}: {
  programmes: string[];
}) {
  const router = useRouter();
  const sp = useSearchParams();

  const currentQ = sp.get("q") ?? "";
  const currentProgramme = sp.get("programme") ?? "";
  const currentStatus = sp.get("status") ?? "";

  const [q, setQ] = useState(currentQ);
  const debounce = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Keep the input in sync if the URL changes externally (back/forward).
  useEffect(() => {
    setQ(sp.get("q") ?? "");
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [sp]);

  function push(next: URLSearchParams) {
    next.delete("page");
    const qs = next.toString();
    router.push(qs ? `/admin/resources?${qs}` : "/admin/resources");
  }

  function onSearchChange(value: string) {
    setQ(value);
    if (debounce.current) clearTimeout(debounce.current);
    debounce.current = setTimeout(() => {
      const next = new URLSearchParams(sp.toString());
      if (value.trim()) next.set("q", value.trim());
      else next.delete("q");
      push(next);
    }, 350);
  }

  function onSelectChange(key: "programme" | "status", value: string) {
    if (debounce.current) clearTimeout(debounce.current); // don't fire a stale search push
    const next = new URLSearchParams(sp.toString());
    if (value) next.set(key, value);
    else next.delete(key);
    push(next);
  }

  const hasFilters = Boolean(currentQ || currentProgramme || currentStatus);

  return (
    <div className="card mt-3 p-4">
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_220px_180px]">
        <label className="relative block">
          <span className="sr-only">Search resources</span>
          <svg
            className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
          >
            <circle cx="11" cy="11" r="7" />
            <path d="m20 20-3.5-3.5" />
          </svg>
          <input
            type="search"
            value={q}
            onChange={(e) => onSearchChange(e.target.value)}
            placeholder="Search title or course code…"
            className="w-full rounded-lg border border-neutral-300 bg-white py-2 pl-9 pr-3 text-sm"
          />
        </label>

        <select
          value={currentProgramme}
          onChange={(e) => onSelectChange("programme", e.target.value)}
          className="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm"
          aria-label="Filter by programme"
        >
          <option value="">All programmes</option>
          {programmes.map((p) => (
            <option key={p} value={p}>
              {p}
            </option>
          ))}
        </select>

        <select
          value={currentStatus}
          onChange={(e) => onSelectChange("status", e.target.value)}
          className="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm"
          aria-label="Filter by status"
        >
          {STATUSES.map((s) => (
            <option key={s.value} value={s.value}>
              {s.label}
            </option>
          ))}
        </select>
      </div>

      {hasFilters && (
        <div className="mt-3 flex items-center justify-between border-t border-neutral-100 pt-3">
          <p className="text-xs text-neutral-500">
            Filters active — results update as you type.
          </p>
          <button
            type="button"
            onClick={() => push(new URLSearchParams())}
            className="text-xs font-semibold text-brand-700 hover:underline"
          >
            Clear all
          </button>
        </div>
      )}
    </div>
  );
}
