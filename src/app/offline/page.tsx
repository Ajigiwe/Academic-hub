import type { Metadata } from "next";

export const metadata: Metadata = { title: "You are offline" };

export default function OfflinePage() {
  return (
    <div className="container-page flex flex-col items-center py-24 text-center">
      <span
        aria-hidden
        className="grid h-16 w-16 place-items-center rounded-full bg-brand-50 text-brand-600 ring-8 ring-brand-50/50"
      >
        <svg
          className="h-8 w-8"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
          aria-hidden
        >
          <path d="M12 20h.01" />
          <path d="M8.5 16.4a4 4 0 0 1 7 0" />
          <path d="M5.3 13.3a8 8 0 0 1 13.4 0" />
          <path d="M2 10.1a12 12 0 0 1 20 0" />
          <path d="m2 2 20 20" />
        </svg>
      </span>
      <h1 className="mt-4 text-xl font-bold">You are offline</h1>
      <p className="mt-2 max-w-sm text-sm text-neutral-600">
        It looks like you have no internet connection. Reconnect to browse and
        read your resources. Controlled offline reading is coming in a later
        update.
      </p>
    </div>
  );
}
