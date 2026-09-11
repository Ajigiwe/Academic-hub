"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import type { SessionUser } from "@/lib/auth";
import { LogoutButton } from "./logout-button";
import { Logo } from "./logo";
import { ThemeToggle } from "./theme-toggle";

const appName = process.env.NEXT_PUBLIC_APP_NAME ?? "Academic Resource Hub";

export function SiteHeader({ user }: { user: SessionUser | null }) {
  const pathname = usePathname();
  // Admin has its own dark console chrome on mobile — the white site
  // header would stack awkwardly above it (hidden below md only).
  const inAdmin = pathname?.startsWith("/admin") ?? false;

  return (
    // Admin renders its own console chrome (dark rail + mobile app bar) —
    // the public site header is hidden there entirely.
    <header
      className={`sticky top-0 z-40 border-b border-neutral-200/80 bg-white/90 backdrop-blur-md dark:border-neutral-800/80 dark:bg-neutral-950/90 ${
        inAdmin ? "hidden" : ""
      }`}
    >
      {/* Brand accent line */}
      <div className="h-0.5 w-full bg-gradient-to-r from-brand-700 via-gold-400 to-brand-950" />
      <div className="container-page flex h-16 items-center justify-between gap-4">
        <Link href="/" className="group">
          <Logo tagline className="transition-opacity group-hover:opacity-80" />
        </Link>

        {/* Desktop nav */}
        <nav className="hidden items-center gap-1 text-sm font-medium text-neutral-700 dark:text-neutral-300 md:flex">
          {[
            { href: "/browse", label: "Browse" },
            { href: "/search", label: "Search" },
            ...(user ? [{ href: "/library", label: "Library" }] : []),
            ...(user?.role === "ADMIN" ? [{ href: "/admin", label: "Admin" }] : []),
          ].map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className="rounded-lg px-3 py-2 transition-colors hover:bg-brand-50 hover:text-brand-800 dark:hover:bg-brand-950 dark:hover:text-brand-300"
            >
              {item.label}
            </Link>
          ))}
        </nav>

        <div className="hidden items-center gap-2.5 md:flex">
          <ThemeToggle />
          {user ? (
            <>
              <Link
                href="/account"
                className="flex items-center gap-2 rounded-full border border-neutral-200 bg-white py-1 pl-1 pr-3 shadow-sm transition hover:border-brand-300 dark:border-neutral-700 dark:bg-neutral-800 dark:hover:border-brand-600"
              >
                <span className="grid h-7 w-7 place-items-center rounded-full bg-brand-100 text-xs font-bold text-brand-800 dark:bg-brand-900 dark:text-brand-300">
                  {user.firstName.slice(0, 1).toUpperCase()}
                </span>
                <span className="text-sm font-medium text-neutral-700 dark:text-neutral-300">{user.firstName}</span>
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

        {/* Compact actions visible on mobile only — the desktop cluster
            (avatar, log out, sign-up) is hidden below md, so signed-in
            users need their own log-out affordance here. */}
        {user ? (
          <div className="flex items-center gap-2 md:hidden">
            <Link
              href="/account"
              aria-label="My account"
              className="grid h-8 w-8 place-items-center rounded-full bg-brand-100 text-xs font-bold text-brand-800 dark:bg-brand-900 dark:text-brand-300"
            >
              {user.firstName.slice(0, 1).toUpperCase()}
            </Link>
            <LogoutButton />
          </div>
        ) : (
          <Link href="/login" className="btn-primary btn-sm md:hidden">
            Log in
          </Link>
        )}
      </div>
    </header>
  );
}
