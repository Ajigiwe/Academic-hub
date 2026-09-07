"use client";

import { useState } from "react";

/**
 * Sandbox checkout page simulating a hosted gateway (Mobile Money
 * prompt). Sends success/failure/cancel to the mock gateway server,
 * which fires the signed webhook and returns the browser redirect.
 */
export function MockCheckoutForm({
  reference,
  amountPesewas,
}: {
  reference: string;
  amountPesewas: number;
}) {
  const [pending, setPending] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function act(outcome: "success" | "failure" | "cancel") {
    setPending(outcome);
    setError(null);
    try {
      const res = await fetch("/api/payments/mock-gateway", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ref: reference, outcome }),
      });
      const data = (await res.json()) as { redirectUrl?: string; error?: string };
      if (data.redirectUrl) {
        window.location.href = data.redirectUrl;
      } else {
        setError(data.error ?? "Mock gateway error.");
        setPending(null);
      }
    } catch {
      setError("Network error talking to the mock gateway.");
      setPending(null);
    }
  }

  return (
    <div className="card w-full max-w-md overflow-hidden">
      {/* Gateway chrome */}
      <div className="flex items-center justify-between border-b border-neutral-100 bg-neutral-50/70 px-5 py-3.5">
        <div className="flex items-center gap-2">
          <span className="grid h-7 w-7 place-items-center rounded-lg bg-gradient-to-br from-brand-700 to-brand-900 text-white">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" aria-hidden>
              <path
                d="M4 19.5V6a2 2 0 0 1 2-2h14v14H6.5a2.5 2.5 0 0 0 0 5H20"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          </span>
          <span className="text-sm font-bold tracking-tight text-neutral-900">
            PayHub Ghana
          </span>
        </div>
        <span className="badge-gold">SANDBOX — no real money</span>
      </div>

      <div className="p-5">
        <p className="text-[11px] font-bold uppercase tracking-wider text-neutral-500">
          Amount due
        </p>
        <p className="mt-1 text-4xl font-extrabold tracking-tight text-neutral-900">
          GH₵{(amountPesewas / 100).toFixed(2)}
        </p>
        <p className="mt-1 text-xs text-neutral-500">
          Order{" "}
          <span className="font-mono font-semibold text-neutral-700">
            {reference}
          </span>
        </p>

        {/* Simulated MOMO prompt */}
        <div className="mt-5 rounded-xl border border-brand-100 bg-brand-50/60 p-4">
          <div className="flex items-center gap-3">
            <span className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-700 text-xs font-bold text-white">
              MOMO
            </span>
            <div>
              <p className="text-sm font-semibold text-neutral-900">
                Mobile Money prompt
              </p>
              <p className="text-xs text-neutral-600">
                Approve on your phone to complete payment
              </p>
            </div>
          </div>

          <div className="mt-4 space-y-2.5">
            <button
              className="btn-primary w-full"
              disabled={pending !== null}
              onClick={() => act("success")}
            >
              {pending === "success" ? "Processing…" : "Approve payment"}
            </button>
          </div>
        </div>

        <div className="mt-4 space-y-2">
          <button
            className="btn-secondary w-full"
            disabled={pending !== null}
            onClick={() => act("failure")}
          >
            {pending === "failure" ? "Processing…" : "Simulate failed payment"}
          </button>
          <button
            className="btn-ghost w-full"
            disabled={pending !== null}
            onClick={() => act("cancel")}
          >
            Cancel and go back
          </button>
        </div>

        {error && (
          <p
            role="alert"
            className="mt-4 rounded-lg bg-red-50 px-3.5 py-2.5 text-sm text-red-700"
          >
            {error}
          </p>
        )}
      </div>
    </div>
  );
}
