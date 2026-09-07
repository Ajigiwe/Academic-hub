import type { Metadata } from "next";
import Link from "next/link";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";

export const metadata: Metadata = { title: "My Library" };

export default async function LibraryPage() {
  const user = await getCurrentUser();
  if (!user) redirect("/login?next=/library");

  const entitlements = await prisma.entitlement.findMany({
    where: { userId: user.id, status: "active" },
    orderBy: { purchasedAt: "desc" },
    include: {
      resource: {
        include: {
          course: true,
          programme: true,
          bundle: { select: { title: true, slug: true } },
        },
      },
      order: { select: { reference: true } },
    },
  });

  return (
    <div className="container-page py-8">
      <div className="flex items-end justify-between">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-neutral-900">
            My Library
          </h1>
          <p className="mt-0.5 text-sm text-neutral-500">
            Your purchased resources, forever yours
          </p>
        </div>
        <span className="badge-neutral">
          {entitlements.length} item{entitlements.length === 1 ? "" : "s"}
        </span>
      </div>

      {entitlements.length === 0 ? (
        <div className="card mt-6 overflow-hidden py-16 text-center">
          <span className="mx-auto grid h-16 w-16 place-items-center rounded-full bg-brand-50 ring-8 ring-brand-50/50">
            <svg className="h-8 w-8 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
              <path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20" />
            </svg>
          </span>
          <p className="mt-5 font-semibold text-neutral-900">
            Your library is empty.
          </p>
          <p className="mx-auto mt-1 max-w-xs text-sm text-neutral-600">
            Purchased past questions will appear here — buy your first one to
            get started.
          </p>
          <Link href="/search" className="btn-primary mt-6">
            Browse Past Questions
          </Link>
        </div>
      ) : (
        <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {entitlements.map((e) => (
            <div key={e.id} className="group relative">
              <div className="card-padded flex h-full flex-col gap-2.5 transition-all group-hover:-translate-y-0.5 group-hover:shadow-lift">
                {/* Book spine accent */}
                <span
                  aria-hidden
                  className="absolute inset-y-4 left-0 w-1 rounded-r-full bg-gradient-to-b from-brand-600 to-brand-800"
                />
                <div className="pl-3">
                  <div className="flex flex-wrap items-center gap-1.5">
                    <span className="badge-brand font-semibold">
                      {e.resource.course.code}
                    </span>
                    <span className="badge-neutral">Level {e.resource.level}</span>
                    <span className="badge-neutral">{e.resource.academicYear}</span>
                  </div>
                  <h3 className="mt-2.5 font-semibold leading-snug text-neutral-900">
                    {e.resource.title}
                  </h3>
                  <p className="mt-0.5 line-clamp-1 text-sm text-neutral-600">
                    {e.resource.programme.name} · {e.resource.course.title}
                  </p>
                  {e.resource.bundle && (
                    <Link
                      href={`/bundles/${e.resource.bundle.slug}`}
                      className="mt-1 inline-flex items-center gap-1 text-xs font-medium text-brand-700 hover:underline"
                    >
                      <svg className="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                        <path d="M20 6 9 17l-5-5" />
                      </svg>
                      Part of {e.resource.bundle.title}
                    </Link>
                  )}
                  <p className="mt-2 text-xs text-neutral-400">
                    Purchased {e.purchasedAt.toLocaleDateString("en-GB")} · Order{" "}
                    {e.order.reference}
                  </p>
                </div>
                <Link
                  href={`/viewer/${e.resource.slug}`}
                  className="btn-primary mt-auto w-full"
                >
                  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" />
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
                  </svg>
                  Open
                </Link>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
