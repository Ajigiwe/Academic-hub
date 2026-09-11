import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { prisma } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";
import { formatPrice, getPublishedBundleBySlug } from "@/lib/resources";
import { isProgrammeEnabled } from "@/lib/settings";
import { MobileBuyBar } from "@/components/mobile-buy-bar";

interface Params {
  slug: string;
}

export async function generateMetadata({
  params,
}: {
  params: Promise<Params>;
}): Promise<Metadata> {
  const { slug } = await params;
  const bundle = await getPublishedBundleBySlug(slug);
  if (
    !bundle ||
    bundle.status !== "PUBLISHED" ||
    !(await isProgrammeEnabled(bundle.programme.slug))
  ) {
    return { title: "Bundle not found" };
  }
  return {
    title: bundle.title,
    description: bundle.description ?? undefined,
  };
}

export default async function BundleDetailPage({
  params,
}: {
  params: Promise<Params>;
}) {
  const { slug } = await params;
  const [user, bundle] = await Promise.all([
    getCurrentUser(),
    getPublishedBundleBySlug(slug),
  ]);

  if (!bundle) notFound();
  if (bundle.status === "UNPUBLISHED") notFound();

  const papers = bundle.resources;
  const semesters = [...new Set(papers.map((p) => p.semester))].sort();

  // Featured preview: the first published paper that actually has a file
  // behind it (seeded placeholders have none and would 404).
  const previewPaper = papers.find((p) => (p.pageCount ?? 0) > 0) ?? null;

  // A student owns the bundle when they are entitled to every published
  // paper inside it.
  let owned = false;
  if (user && papers.length > 0) {
    const ownedCount = await prisma.entitlement.count({
      where: {
        userId: user.id,
        resourceId: { in: papers.map((p) => p.id) },
        status: "active",
      },
    });
    owned = ownedCount === papers.length;
  }

  // Hidden programme: nobody reaches the page by guessing the URL — except
  // students who already own it (their purchase is never revoked).
  if (!(await isProgrammeEnabled(bundle.programme.slug)) && !owned) notFound();

  const price = formatPrice(bundle.pricePesewas);
  const purchasable = !owned && papers.length > 0;

  return (
    <div className="border-b border-neutral-200 bg-gradient-to-b from-brand-50/60 to-neutral-50">
      {purchasable && (
        <MobileBuyBar
          href={`/checkout?bundle=${bundle.id}`}
          price={price}
          papersLabel={`${papers.length} paper${papers.length === 1 ? "" : "s"} · one price`}
        />
      )}
      <div className="container-page max-w-5xl py-8">
        <nav className="text-sm text-neutral-500" aria-label="Breadcrumb">
          <Link href="/search" className="transition-colors hover:text-brand-700">
            Browse
          </Link>
          <span className="mx-1.5 text-neutral-300">/</span>
          <span className="text-neutral-700 dark:text-neutral-300">{bundle.course.code}</span>
        </nav>

        <div className="mt-5 grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]">
          {/* Main column */}
          <div>
            <div className="flex flex-wrap items-center gap-1.5">
              <span className="badge-brand font-semibold">{bundle.course.code}</span>
              <span className="badge-neutral">Level {bundle.level}</span>
              <span className="badge-neutral">{bundle.academicYear}</span>
              {bundle.solved && (
                <span className="badge-success font-semibold">Solved</span>
              )}
              {semesters.length > 0 && (
                <span className="badge-neutral">
                  {semesters.map((s) => (s === 2 ? "Second" : "First") + " Semester").join(" & ")}
                </span>
              )}
              <span className="badge-gold font-semibold">
                {papers.length} paper{papers.length === 1 ? "" : "s"} · one price
              </span>
            </div>

            <h1 className="mt-3 text-2xl font-bold tracking-tight text-neutral-900 dark:text-neutral-100 sm:text-3xl">
              {bundle.title}
            </h1>

            <p className="mt-2 text-sm font-medium text-neutral-600 dark:text-neutral-400">
              {bundle.programme.name} · {bundle.course.title}
            </p>

            {bundle.description && (
              <p className="mt-4 whitespace-pre-line leading-relaxed text-neutral-700 dark:text-neutral-300">
                {bundle.description}
              </p>
            )}

            {/* First-page preview of the featured paper */}
            {previewPaper && !owned && (
              <section id="preview" className="card mt-8 scroll-mt-24 overflow-hidden">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-neutral-100 bg-neutral-50/60 px-5 py-3 dark:border-neutral-800 dark:bg-neutral-800/50">
                  <div className="min-w-0 flex-1 basis-40">
                    <p className="text-[11px] font-bold uppercase tracking-wider text-neutral-500 dark:text-neutral-400">
                      Sneak peek — page 1 of {previewPaper.pageCount}
                    </p>
                    <p className="mt-0.5 truncate text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                      {previewPaper.title}
                    </p>
                  </div>
                  <span className="badge-gold font-semibold">Free preview</span>
                </div>
                <div className="bg-neutral-100 p-4 sm:p-6 dark:bg-neutral-800">
                  <div className="relative mx-auto max-w-sm overflow-hidden rounded-lg bg-white shadow-card ring-1 ring-neutral-200 dark:bg-neutral-900 dark:ring-neutral-700">
                    {/* The public preview endpoint renders page 1 at reduced
                        resolution with a burned-in PREVIEW mark. */}
                    {/* eslint-disable-next-line @next/next/no-img-element */}
                    <img
                      src={`/api/preview/${previewPaper.slug}`}
                      alt={`Preview of the first page of ${previewPaper.title}`}
                      loading="lazy"
                      className="block w-full"
                    />
                    {/* Fade-out implies the rest is behind the paywall. */}
                    <div className="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-white to-transparent dark:from-neutral-900" />
                  </div>
                  <p className="mt-4 text-center text-xs text-neutral-500 dark:text-neutral-400">
                    Sample page is watermarked and reduced quality — the real
                    papers are crisp, unwatermarked by anyone else, and unlock
                    instantly after payment.
                  </p>
                </div>
              </section>
            )}

            {/* Papers inside the bundle */}
            <h2 className="mt-8 flex items-center gap-2 text-base font-semibold text-neutral-900 dark:text-neutral-100">
              What's inside
              <span className="badge-neutral">{papers.length}</span>
            </h2>

            {papers.length === 0 ? (
              <p className="mt-3 rounded-xl border border-dashed border-neutral-300 bg-white p-6 text-sm text-neutral-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400">
                Papers for this bundle are being prepared — check back soon.
              </p>
            ) : (
              <ul className="card mt-3 divide-y divide-neutral-100 dark:divide-neutral-800 overflow-hidden">
                {papers.map((p) => (
                  <li key={p.id}>
                    <Link
                      href={`/resources/${p.slug}`}
                      className="flex items-center gap-3 px-4 py-3.5 transition-colors hover:bg-neutral-50 dark:hover:bg-neutral-800"
                    >
                      <span className="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-700 dark:bg-brand-950 dark:text-brand-300">
                        <svg className="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                          <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z" />
                          <path d="M14 2v6h6" />
                        </svg>
                      </span>
                      <div className="min-w-0 flex-1">
                        <p className="truncate text-sm font-medium text-neutral-900 dark:text-neutral-100">
                          {p.title}
                        </p>
                        <p className="text-xs text-neutral-500 dark:text-neutral-400">
                          {p.semester === 2 ? "Second" : "First"} Semester
                          {p.pageCount ? ` · ${p.pageCount} pages` : ""}
                        </p>
                      </div>
                      <svg className="h-4 w-4 shrink-0 text-neutral-300 dark:text-neutral-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                        <path d="m9 18 6-6-6-6" />
                      </svg>
                    </Link>
                  </li>
                ))}
              </ul>
            )}
          </div>

          {/* Buy box */}
          <aside className={`lg:sticky lg:top-24 lg:self-start ${purchasable ? "pb-16 md:pb-0" : ""}`}>
            <div className="card overflow-hidden">
              <div className="h-1 w-full bg-gradient-to-r from-brand-600 to-gold-400" />
              <div className="p-5">
                <p className="text-3xl font-extrabold tracking-tight text-neutral-900 dark:text-neutral-100">
                  {price}
                </p>
                <p className="mt-1 text-xs text-neutral-500 dark:text-neutral-400">
                  One payment · every paper inside · lifetime access
                </p>

                {owned ? (
                  <Link
                    href="/library"
                    className="btn-primary mt-5 w-full"
                  >
                    Open in Library
                  </Link>
                ) : papers.length > 0 ? (
                  <>
                    <Link
                      href={`/checkout?bundle=${bundle.id}`}
                      className="btn-gold mt-5 w-full"
                    >
                      Buy Bundle
                    </Link>
                    <p className="mt-3 text-center text-[11px] text-neutral-500 dark:text-neutral-400">
                      {papers.length} paper{papers.length === 1 ? "" : "s"} · Mobile Money &amp;
                      cards accepted
                    </p>
                  </>
                ) : null}

                <ul className="mt-5 space-y-2.5 border-t border-neutral-100 pt-4 text-xs text-neutral-600 dark:border-neutral-800 dark:text-neutral-400">
                  {[
                    "All papers unlock instantly after payment",
                    "New papers added to this bundle stay free for you",
                    "Read on any device, forever",
                  ].map((t) => (
                    <li key={t} className="flex items-start gap-2">
                      <svg className="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-600 dark:text-brand-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                        <path d="M20 6 9 17l-5-5" />
                      </svg>
                      {t}
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </aside>
        </div>
      </div>
    </div>
  );
}