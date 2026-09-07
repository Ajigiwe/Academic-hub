"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import type { SessionUser } from "@/lib/auth";
import { LogoutButton } from "./logout-button";

const appName = process.env.NEXT_PUBLIC_APP_NAME ?? "Academic Resource Hub";

export function SiteHeader({ user }: { user: SessionUser | null }) {
  const pathname = usePathname();
  // Admin has its own dark console chrome on mobile — the white site
  // header would stack awkwardly above it (hidden below md only).
  const inAdmin = pathname?.startsWith("/admin") ?? false;

  return (
    <header
      className={`sticky top-0 z-40 border-b border-neutral-200/80 bg-white/90 backdrop-blur-md ${
        inAdmin ? "max-md:hidden" : ""
      }`}
    >
      {/* Gold accent line — brand signature */}
      <div className="h-0.5 w-full bg-gradient-to-r from-brand-700 via-gold-400 to-brand-700" />
      <div className="container-page flex h-16 items-center justify-between gap-4">
        <Link href="/" className="group flex items-center gap-2.5">
          <span className="grid h-9 w-9 place-items-center rounded-lg bg-gradient-to-br from-brand-700 to-brand-900 text-sm font-bold text-white shadow-soft transition-shadow group-hover:shadow-card">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
              <path
                d="M4 19.5V6a2 2 0 0 1 2-2h14v14H6.5a2.5 2.5 0 0 0 0 5H20"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          </span>
          <span className="hidden flex-col leading-tight sm:flex">
            <span className="text-[15px] font-bold tracking-tight text-neutral-900">
              Academic Resource Hub
            </span>
            <span className="text-[10px] font-semibold uppercase tracking-[0.14em] text-gold-600">
              Learn · Revise · Excel
            </span>
          </span>
        </Link>

        {/* Desktop nav */}
        <nav className="hidden items-center gap-1 text-sm font-medium text-neutral-700 md:flex">
          {[
            { href: "/browse", label: "Browse" },
            { href: "/search", label: "Search" },
            ...(user ? [{ href: "/library", label: "Library" }] : []),
            ...(user?.role === "ADMIN" ? [{ href: "/admin", label: "Admin" }] : []),
          ].map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className="rounded-lg px-3 py-2 transition-colors hover:bg-brand-50 hover:text-brand-800"
            >
              {item.label}
            </Link>
          ))}
        </nav>

        <div className="hidden items-center gap-2.5 md:flex">
          {user ? (
            <>
              <Link
                href="/account"
                className="flex items-center gap-2 rounded-full border border-neutral-200 bg-white py-1 pl-1 pr-3 shadow-sm transition hover:border-brand-300"
              >
                <span className="grid h-7 w-7 place-items-center rounded-full bg-brand-100 text-xs font-bold text-brand-800">
                  {user.firstName.slice(0, 1).toUpperCase()}
                </span>
                <span className="text-sm font-medium text-neutral-700">{user.firstName}</span>
              </Link>
              <LogoutButton />
            </>
          ) : (
            <>
              <Link href="/login" className="btn-secondary btn-sm">
                Log in
              </Link>
              <Link href="/register" className="btn-primary btn-sm">
                Sign up
              </Link>
            </>
          )}
        </div>

        {/* Compact sign-in visible on mobile only */}
        {!user && (
          <Link href="/login" className="btn-primary btn-sm md:hidden">
            Log in
          </Link>
        )}
      </div>
    </header>
  );
}
