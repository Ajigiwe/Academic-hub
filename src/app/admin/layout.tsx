import Link from "next/link";
import { redirect } from "next/navigation";
import { getCurrentUser } from "@/lib/auth";
import { AdminSidebarNav, AdminMobileNav } from "@/components/admin-nav";
import { LogoutButton } from "@/components/logout-button";

export default async function AdminLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const user = await getCurrentUser();
  if (!user) redirect("/login?next=/admin");
  if (user.role !== "ADMIN") redirect("/"); // server-side role gate (§31)

  return (
    <div className="min-h-screen bg-neutral-100">
      {/* Dark console rail (desktop, pinned to the viewport edge) */}
      <aside className="fixed bottom-0 left-0 top-0 z-30 hidden w-60 md:block">
        <div className="flex h-full flex-col overflow-y-auto bg-neutral-950">
          <div className="border-b border-white/10 px-5 py-5">
            <p className="text-[11px] font-bold uppercase tracking-[0.16em] text-gold-400">
              Administration
            </p>
            <p className="mt-1 text-sm font-semibold text-white">Control Center</p>
          </div>
          <nav className="flex-1 px-3 py-4">
            <AdminSidebarNav />
          </nav>
          <div className="border-t border-white/10 px-3 py-3">
            <Link
              href="/"
              className="flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium text-neutral-400 transition-colors hover:bg-white/10 hover:text-white"
            >
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                <path d="m12 19-7-7 7-7" />
                <path d="M19 12H5" />
              </svg>
              Back to site
            </Link>
            <LogoutButton className="mt-1 inline-flex w-full items-center justify-center gap-2 rounded-lg px-3 py-2 text-xs font-semibold text-neutral-300 transition-colors hover:bg-white/10 hover:text-white" />
          </div>
        </div>
      </aside>

      {/* Content column */}
      <div className="md:pl-60">
        {/* Mobile console app bar (replaces the site header on admin) */}
        <div className="sticky top-0 z-40 bg-neutral-950 px-4 py-3 md:hidden">
          <div className="flex items-center gap-3">
            <AdminMobileNav userName={`${user.firstName} ${user.lastName}`} userEmail={user.email} />
            <Link href="/" className="flex flex-1 flex-col">
              <span className="text-[10px] font-bold uppercase tracking-[0.16em] text-gold-400">
                Administration
              </span>
              <span className="text-sm font-semibold text-white">Control Center</span>
            </Link>
          </div>
        </div>

        <div className="container-admin py-6">{children}</div>
      </div>
    </div>
  );
}
