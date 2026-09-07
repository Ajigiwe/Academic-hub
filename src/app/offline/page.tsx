import type { Metadata } from "next";

export const metadata: Metadata = { title: "You are offline" };

export default function OfflinePage() {
  return (
    <div className="container-page flex flex-col items-center py-24 text-center">
      <span aria-hidden className="text-5xl">📶</span>
      <h1 className="mt-4 text-xl font-bold">You are offline</h1>
      <p className="mt-2 max-w-sm text-sm text-neutral-600">
        It looks like you have no internet connection. Reconnect to browse and
        read your resources. Controlled offline reading is coming in a later
        update.
      </p>
    </div>
  );
}
