"use client";

import { useState } from "react";

export function CheckoutButton({ bundleId }: { bundleId: string }) {
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function pay() {
    setPending(true);
    setError(null);
    try {
      const res = await fetch("/api/orders", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ bundleId }),
      });
      let data: { redirectUrl?: string; error?: string };
      try {
        data = await res.json();
      } catch {
        setError("Something went wrong. Please try again.");
        setPending(false);
        return;
      }
      if (data.redirectUrl) {
        window.location.href = data.redirectUrl;
        return;
      }
      setError(data.error ?? "Could not start checkout. Please try again.");
      setPending(false);
    } catch {
      setError("Network error. Please check your connection and try again.");
      setPending(false);
    }
  }

  return (
    <div className="mt-4">
      <button className="btn-gold w-full" onClick={pay} disabled={pending}>
        {pending ? "Starting secure checkout…" : "Pay Now"}
      </button>
      {error && (
        <p role="alert" className="mt-3 rounded-lg bg-red-50 px-3.5 py-2.5 text-sm text-red-700">
          {error}
        </p>
      )}
    </div>
  );
}