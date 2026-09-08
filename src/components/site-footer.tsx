"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Logo } from "./logo";

const appName = process.env.NEXT_PUBLIC_APP_NAME ?? "Academic Resource Hub";

export function SiteFooter() {
  const pathname = usePathname();
  if (pathname?.startsWith("/admin")) return null;
  if (pathname?.startsWith("/viewer")) return null;

  return (
    <footer className="border-t border-neutral-200 bg-neutral-100">
      <div className="container-page grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <Logo className="text-base" />
          <p className="mt-3 max-w-xs text-sm leading-relaxed text-neutral-600">
            Ghana&apos;s digital academic library — find, purchase, and study
            past questions and academic resources, on any device.
          </p>
        </div>

        <div className="text-sm">
          <p className="text-xs font-semibold uppercase tracking-wider text-neutral-500">
            Platform
          </p>
          <ul className="mt-3 space-y-2 text-neutral-600">
            <li><Link href="/browse" className="transition-colors hover:text-brand-700">Browse</Link></li>
            <li><Link href="/search" className="transition-colors hover:text-brand-700">Search</Link></li>
            <li><Link href="/library" className="transition-colors hover:text-brand-700">My Library</Link></li>
          </ul>
        </div>

        <div className="text-sm">
          <p className="text-xs font-semibold uppercase tracking-wider text-neutral-500">
            Newsletter
          </p>
          <p className="mt-3 text-neutral-600">
            New past questions and study tips, monthly.
          </p>
          <form action="/help?subscribed=1" className="mt-3 flex gap-2">
            <input
              type="email"
              name="email"
              required
              placeholder="you@example.com"
              aria-label="Email address"
              className="input py-2"
            />
            <button type="submit" className="btn-primary btn-sm shrink-0">
              Join
            </button>
          </form>
        </div>

        <div className="text-sm">
          <p className="text-xs font-semibold uppercase tracking-wider text-neutral-500">
            Legal
          </p>
          <ul className="mt-3 space-y-2 text-neutral-600">
            <li><Link href="/terms" className="transition-colors hover:text-brand-700">Terms</Link></li>
            <li><Link href="/privacy" className="transition-colors hover:text-brand-700">Privacy Policy</Link></li>
            <li><Link href="/help" className="transition-colors hover:text-brand-700">Help</Link></li>
          </ul>
        </div>
      </div>

      <div className="border-t border-neutral-200">
        <p className="container-page py-5 text-xs text-neutral-500">
          © {new Date().getFullYear()} Academic Resource Hub. All rights reserved.
        </p>
      </div>
    </footer>
  );
}
