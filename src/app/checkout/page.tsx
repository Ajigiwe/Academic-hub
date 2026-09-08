import type { Metadata } from "next";
import Link from "next/link";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";
import { formatPrice } from "@/lib/resources";
import { CheckoutButton } from "@/components/checkout-button";

export const metadata: Metadata = { title: "Checkout" };

export default async function CheckoutPage({
  searchParams,
}: {
  searchParams: Promise<{ bundle?: string }>;
}) {
  const { bundle: bundleId } = await searchParams;

  const user = await getCurrentUser();
  if (!user)
    redirect(
      `/login?next=${encodeURIComponent(`/checkout?bundle=${bundleId ?? ""}`)}`,
    );

  if (!bundleId) redirect("/search");

  const bundle = await prisma.bundle.findFirst({
    where: { id: bundleId, status: "PUBLISHED" },
    include: {
      course: true,
      programme: true,
      resources: {
        where: { status: "PUBLISHED" },
        orderBy: [{ semester: "asc" }, { title: "asc" }],
        select: { id: true, title: true, semester: true, pageCount: true },
      },
    },
  });
  if (!bundle) redirect("/search");

  const ownedCount = await prisma.entitlement.count({
    where: {
      userId: user.id,
      resourceId: { in: bundle.resources.map((r) => r.id) },
      status: "active",
    },
  });
  if (bundle.resources.length > 0 && ownedCount === bundle.resources.length) {
    redirect("/library");
  }

  return (
    <div className="container-page max-w-lg py-10">
      {/* Steps indicator */}
      <div className="flex items-center justify-center gap-2 text-xs font-medium text-neutral-400">
        <span className="rounded-full bg-brand-50 px-2.5 py-1 font-semibold text-brand-700">
          1 · Review
        </span>
        <span className="h-px w-6 bg-neutral-300" />
        <span className="rounded-full px-2.5 py-1">2 · Pay</span>
        <span className="h-px w-6 bg-neutral-300" />
        <span className="rounded-full px-2.5 py-1">3 · Study</span>
      </div>

      <h1 className="mt-5 text-center text-2xl font-bold tracking-tight text-neutral-900">
        Checkout
      </h1>

      {/* Order summary */}
      <div className="card mt-6 overflow-hidden">
        <div className="border-b border-neutral-100 bg-neutral-50/60 px-5 py-3">
          <p className="text-[11px] font-bold uppercase tracking-wider text-neutral-500">
            Order summary
          </p>
        </div>
        <div className="p-5">
          <div className="flex items-start justify-between gap-4">
            <div>
              <p className="font-semibold leading-snug text-neutral-900">
                {bundle.title}
              </p>
              <p className="mt-1 text-sm text-neutral-600">
                {bundle.course.code} · {bundle.programme.name}
              </p>
              <p className="text-sm text-neutral-600">
                Level {bundle.level} · {bundle.academicYear}
              </p>
              <p className="mt-1 text-sm font-medium text-brand-700">
                {bundle.resources.length} paper
                {bundle.resources.length === 1 ? "" : "s"} included
              </p>
            </div>
            <p className="whitespace-nowrap font-bold text-neutral-900">
              {formatPrice(bundle.pricePesewas)}
            </p>
          </div>

          {bundle.resources.length > 0 && (
            <ul className="mt-4 space-y-1.5 border-t border-neutral-100 pt-3">
              {bundle.resources.map((r) => (
                <li key={r.id} className="flex items-center gap-2 text-xs text-neutral-600">
                  <svg className="h-3.5 w-3.5 shrink-0 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                    <path d="M20 6 9 17l-5-5" />
                  </svg>
                  <span className="truncate">{r.title}</span>
                  <span className="ml-auto shrink-0 text-neutral-400">
                    {r.semester === 2 ? "Sem 2" : "Sem 1"}
                    {r.pageCount ? ` · ${r.pageCount}p` : ""}
                  </span>
                </li>
              ))}
            </ul>
          )}

          <div className="mt-4 flex items-center justify-between border-t border-neutral-200 pt-3">
            <p className="font-semibold text-neutral-900">Total</p>
            <p className="text-xl font-extrabold tracking-tight text-brand-800">
              {formatPrice(bundle.pricePesewas)}
            </p>
          </div>
        </div>
      </div>

      {/* Payment */}
      <div className="card mt-4 overflow-hidden">
        <div className="border-b border-neutral-100 bg-neutral-50/60 px-5 py-3">
          <p className="text-[11px] font-bold uppercase tracking-wider text-neutral-500">
            Payment method
          </p>
        </div>
        <div className="p-5">
          <div className="flex items-center gap-3 rounded-lg border border-neutral-200 bg-neutral-50/50 px-3.5 py-3">
            <span className="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-brand-600 to-brand-800 text-white">
              <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                <rect x="2" y="5" width="20" height="14" rx="2" />
                <path d="M2 10h20" />
              </svg>
            </span>
            <div>
              <p className="text-sm font-semibold text-neutral-900">
                Mobile Money / Card
              </p>
              <p className="text-xs text-neutral-500">
                Secured gateway · GH₵ · sandbox during pilot
              </p>
            </div>
          </div>

          <CheckoutButton bundleId={bundle.id} />

          <p className="mt-3 flex items-center justify-center gap-1.5 text-center text-[11px] text-neutral-500">
            <svg className="h-3.5 w-3.5 shrink-0 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
              <path d="M20 6 9 17l-5-5" />
            </svg>
            Access is granted only after your payment is verified server-side.
          </p>
        </div>
      </div>

      <Link
        href={`/bundles/${bundle.slug}`}
        className="mt-5 block text-center text-sm font-medium text-neutral-600 transition-colors hover:text-brand-700"
      >
        ← Back to bundle details
      </Link>
    </div>
  );
}