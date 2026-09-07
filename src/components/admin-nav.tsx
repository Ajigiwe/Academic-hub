"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { LogoutButton } from "@/components/logout-button";
import { Logo } from "@/components/logo";

export const adminNavItems = [
  {
    href: "/admin",
    label: "Dashboard",
    icon: (
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
        <rect x="3" y="3" width="7" height="9" rx="1" />
        <rect x="14" y="3" width="7" height="5" rx="1" />
        <rect x="14" y="12" width="7" height="9" rx="1" />
        <rect x="3" y="16" width="7" height="5" rx="1" />
      </svg>
    ),
  },
  {
    href: "/admin/bundles",
    label: "Bundles",
    icon: (
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
        <path d="M16 8 4 14" />
        <path d="M20 12 8 18" />
        <path d="M8 2 16 8v8l-8 6V8Z" />
        <path d="M4 14v8l8-6" />
      </svg>
    ),
  },
  {
    href: "/admin/resources",
    label: "Resources",
    icon: (
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
        <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
        <path d="M14 2v6h6" />
      </svg>
    ),
  },
  {
    href: "/admin/orders",
    label: "Orders",
    icon: (
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
        <path d="M14 2v6h6" />
        <path d="M16 13H8" />
        <path d="M16 17H8" />
        <path d="M10 9H8" />
      </svg>
    ),
  },
  {
    href: "/admin/students",
    label: "Students",
    icon: (
      <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
        <path d="M22 10 12 5 2 10l10 5 10-5z" />
        <path d="M6 12v5c3 3 9 3 12 0v-5" />
      </svg>
    ),
  },
];

function useIsActive() {
  const pathname = usePathname();
  return (href: string) =>
    href === "/admin" ? pathname === "/admin" : pathname?.startsWith(href);
}

/** Shared link list — used by both the desktop rail and the drawer. */
function NavLinks({ onNavigate }: { onNavigate?: () => void }) {
  const isActive = useIsActive();
  return (
    <>
      {adminNavItems.map((item) => (
        <Link
          key={item.href}
          href={item.href}
          onClick={onNavigate}
          className={isActive(item.href) ? "admin-nav-link-active" : "admin-nav-link"}
        >
          {item.icon}
          {item.label}
        </Link>
      ))}
    </>
  );
}

/** Desktop rail links (always visible inside the fixed sidebar). */
export function AdminSidebarNav() {
  return (
    <nav className="space-y-1">
      <NavLinks />
    </nav>
  );
}

/**
 * Mobile: hamburger trigger + collapsible slide-in side panel with
 * brand header and account section. Closes on backdrop tap, Esc, or
 * navigation; locks body scroll while open.
 */
export function AdminMobileNav({
  userName,
  userEmail,
}: {
  userName: string;
  userEmail: string;
}) {
  const [open, setOpen] = useState(false);
  const pathname = usePathname();

  // Close whenever the route changes (nav link tapped).
  useEffect(() => {
    setOpen(false);
  }, [pathname]);

  // Esc to close + body scroll lock while open.
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") setOpen(false);
    };
    document.addEventListener("keydown", onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    return () => {
      document.removeEventListener("keydown", onKey);
      document.body.style.overflow = prev;
    };
  }, [open]);

  const close = useCallback(() => setOpen(false), []);

  return (
    <>
      {/* Hamburger trigger */}
      <button
        type="button"
        onClick={() => setOpen(true)}
        aria-expanded={open}
        aria-controls="admin-drawer"
        aria-label="Open admin menu"
        className="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-white transition-colors hover:bg-white/10"
      >
        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden>
          <path d="M4 6h16" />
          <path d="M4 12h16" />
          <path d="M4 18h16" />
        </svg>
      </button>

      {/* Slide-in panel + backdrop (mobile only) */}
      <div
        id="admin-drawer"
        className={`fixed inset-0 z-50 md:hidden ${open ? "" : "pointer-events-none"}`}
        aria-hidden={!open}
      >
        <div
          onClick={close}
          className={`absolute inset-0 bg-black/60 transition-opacity duration-200 ${
            open ? "opacity-100" : "opacity-0"
          }`}
        />
        <aside
          className={`absolute left-0 top-0 flex h-full w-64 flex-col bg-neutral-950 shadow-2xl transition-transform duration-200 ${
            open ? "translate-x-0" : "-translate-x-full"
          }`}
        >
          <div className="flex items-start justify-between border-b border-white/10 px-5 py-5">
            <Link href="/" onClick={close}>
              <Logo light />
            </Link>
            <button
              type="button"
              onClick={close}
              aria-label="Close admin menu"
              className="grid h-8 w-8 place-items-center rounded-lg text-neutral-400 transition-colors hover:bg-white/10 hover:text-white"
            >
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden>
                <path d="M18 6 6 18" />
                <path d="m6 6 12 12" />
              </svg>
            </button>
          </div>

          <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
            <NavLinks onNavigate={close} />
          </nav>

          {/* Account section */}
          <div className="border-t border-white/10 px-3 py-4">
            <div className="flex items-center gap-2.5 px-2">
              <span className="grid h-8 w-8 place-items-center rounded-full bg-white/10 text-xs font-bold text-white">
                {userName.slice(0, 1).toUpperCase()}
              </span>
              <div className="min-w-0">
                <p className="truncate text-xs font-semibold text-white">{userName}</p>
                <p className="truncate text-[11px] text-neutral-500">{userEmail}</p>
              </div>
            </div>
            <div className="mt-2 px-1">
              <LogoutButton />
            </div>
          </div>
        </aside>
      </div>
    </>
  );
}
