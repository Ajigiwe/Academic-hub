"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import Link from "next/link";
import { Logo } from "./logo";

export function AuthForm({ mode }: { mode: "login" | "register" }) {
  const router = useRouter();
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const isRegister = mode === "register";

  async function onSubmit(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setError(null);
    setPending(true);

    const form = new FormData(e.currentTarget);
    const payload = Object.fromEntries(form.entries());

    try {
      const res = await fetch(`/api/auth/${mode}`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const data = (await res.json()) as { error?: string; role?: string };

      if (!res.ok) {
        setError(data.error ?? "Something went wrong. Please try again.");
        return;
      }
      router.push(data.role === "ADMIN" ? "/admin" : "/library");
      router.refresh();
    } catch {
      setError("Network error. Please check your connection and try again.");
    } finally {
      setPending(false);
    }
  }

  return (
    <div className="card-padded w-full max-w-md">
      <div className="mb-6 flex flex-col items-center">
        <Logo tagline className="text-lg" />
      </div>
      <h1 className="text-center text-xl font-bold text-neutral-900">
        {isRegister ? "Create your account" : "Welcome back"}
      </h1>
      <p className="mt-1 text-center text-sm text-neutral-600">
        {isRegister
          ? "Find, purchase, and study past questions."
          : "Log in to access your library."}
      </p>

      <form onSubmit={onSubmit} className="mt-6 space-y-4">
        {isRegister && (
          <>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="label" htmlFor="firstName">First name</label>
                <input id="firstName" name="firstName" className="input" required autoComplete="given-name" />
              </div>
              <div>
                <label className="label" htmlFor="lastName">Last name</label>
                <input id="lastName" name="lastName" className="input" required autoComplete="family-name" />
              </div>
            </div>
            <div>
              <label className="label" htmlFor="phone">Phone (optional)</label>
              <input id="phone" name="phone" className="input" autoComplete="tel" placeholder="024 000 0000" />
            </div>
          </>
        )}

        <div>
          <label className="label" htmlFor="email">Email</label>
          <input id="email" name="email" type="email" className="input" required autoComplete="email" />
        </div>

        <div>
          <label className="label" htmlFor="password">Password</label>
          <input
            id="password"
            name="password"
            type="password"
            className="input"
            required
            minLength={isRegister ? 8 : 1}
            autoComplete={isRegister ? "new-password" : "current-password"}
          />
          {isRegister && (
            <p className="mt-1 text-xs text-neutral-500">At least 8 characters.</p>
          )}
        </div>

        {error && (
          <p role="alert" className="rounded-lg bg-red-50 px-3.5 py-2.5 text-sm text-red-700">
            {error}
          </p>
        )}

        <button type="submit" className="btn-primary w-full" disabled={pending}>
          {pending
            ? "Please wait…"
            : isRegister
              ? "Create account"
              : "Log in"}
        </button>
      </form>

      <p className="mt-4 text-center text-sm text-neutral-600">
        {isRegister ? (
          <>Already have an account? <Link className="font-medium text-brand-700 hover:underline" href="/login">Log in</Link></>
        ) : (
          <>New here? <Link className="font-medium text-brand-700 hover:underline" href="/register">Create an account</Link></>
        )}
      </p>
    </div>
  );
}
