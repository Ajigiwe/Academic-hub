import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { getCurrentUser } from "@/lib/auth";
import { hasEntitlement } from "@/lib/entitlements";
import { formatPrice, getPublishedResourceBySlug } from "@/lib/resources";

interface Params {
  slug: string;
}

export async function generateMetadata({
  params,
}: {
  params: Promise<Params>;
}): Promise<Metadata> {
  const { slug } = await params;
  const resource = await getPublishedResourceBySlug(slug);
  if (!resource || resource.status !== "PUBLISHED") {
    return { title: "Resource not found" };
  }
  return {
    title: `${resource.title}`,
    description: resource.description ?? undefined,
  };
}

const typeLabel: Record<string, string> = {
  PAST_QUESTION: "Past Question",
  LECTURE_NOTES: "Lecture Notes",
  REVISION: "Revision Material",
  PRACTICE: "Practice Set",
};

export default async function ResourceDetailPage({
  params,
}: {
  params: Promise<Params>;
}) {
  const { slug } = await params;
  const [user, resource] = await Promise.all([
    getCurrentUser(),
    getPublishedResourceBySlug(slug),
  ]);

  if (!resource) notFound();

  const owned = user ? await hasEntitlement(user, resource.id) : false;
  if (resource.status === "UNPUBLISHED" && !owned) notFound();

  const bundle = resource.bundle;
  const price = bundle ? formatPrice(bundle.pricePesewas) : null;

  return (
    <div className="border-b border-neutral-200 bg-gradient-to-b from-brand-50/60 to-neutral-50">
      <div className="container-page max-w-5xl py-8">
        <nav className="text-sm text-neutral-500" aria-label="Breadcrumb">
          <Link href="/search" className="transition-colors hover:text-brand-700">
            Browse
          </Link>
          <span className="mx-1.5 text-neutral-300">/</span>
          <span className="text-neutral-700">{resource.course.code}</span>
        </nav>

        <div className="mt-5 grid gap-6 lg:grid-cols-[1fr_320px]">
          {/* Main column */}
          <div>
            <div className="flex flex-wrap items-center gap-1.5">
              <span className="badge-brand font-semibold">{resource.course.code}</span>
              <span className="badge-neutral">Level {resource.level}</span>
              <span className="badge-neutral">
                {resource.semester === 2 ? "Second" : "First"} Semester
              </span>
              <span className="badge-neutral">{resource.academicYear}</span>
              {resource.status === "UNPUBLISHED" && (
                <span className="badge-danger">Unavailable publicly</span>
              )}
            </div>

            <h1 className="mt-3 text-2xl font-bold tracking-tight text-neutral-900 sm:text-3xl">
              {resource.title}
            </h1>

            <p className="mt-2 text-sm font-medium text-neutral-600">
              {resource.programme.name} · {resource.course.title}
            </p>

            {resource.description && (
              <p className="mt-4 whitespace-pre-line leading-relaxed text-neutral-700">
                {resource.description}
              </p>
            )}

            <dl className="mt-6 grid grid-cols-3 gap-3">
              {[
                { label: "Pages", value: resource.pageCount ?? "—" },
                {
                  label: "Type",
                  value: typeLabel[resource.type] ?? resource.type,
                },
                { label: "Year", value: resource.academicYear },
              ].map((s) => (
                <div key={s.label} className="card px-4 py-3">
                  <dt className="text-[11px] font-semibold uppercase tracking-wide text-neutral-500">
                    {s.label}
                  </dt>
                  <dd className="mt-0.5 text-sm font-semibold text-neutral-900">
                    {s.value}
                  </dd>
                </div>
              ))}
            </dl>
          </div>

          {/* Buy box — papers are sold as part of their bundle */}
          <aside className="lg:sticky lg:top-24 lg:self-start">
            <div className="card overflow-hidden">
              <div className="h-1 w-full bg-gradient-to-r from-brand-600 to-gold-400" />
              <div className="p-5">
                {price && (
                  <p className="text-3xl font-extrabold tracking-tight text-neutral-900">
                    {price}
                  </p>
                )}
                <p className="mt-1 text-xs text-neutral-500">
                  Sold as part of the {bundle?.title ?? "course"} bundle
                </p>

                {owned ? (
                  <>
                    <Link
                      href={`/viewer/${resource.slug}`}
                      className="btn-primary mt-5 w-full"
                    >
                      Open in Library
                    </Link>
                    <p className="mt-3 flex items-center justify-center gap-1.5 text-xs font-medium text-brand-700">
                      <svg className="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                        <path d="M20 6 9 17l-5-5" />
                      </svg>
                      You own this paper
                    </p>
                  </>
                ) : bundle ? (
                  <>
                    <Link
                      href={`/bundles/${bundle.slug}`}
                      className="btn-gold mt-5 w-full"
                    >
                      View the bundle
                    </Link>
                    <Link
                      href={`/checkout?bundle=${bundle.id}`}
                      className="btn-secondary mt-2 w-full"
                    >
                      Buy Bundle · {price}
                    </Link>
                    <p className="mt-3 text-center text-[11px] text-neutral-500">
                      One payment unlocks every paper in the bundle
                    </p>
                  </>
                ) : (
                  <p className="mt-5 rounded-lg bg-neutral-50 px-4 py-3 text-center text-xs text-neutral-500">
                    This paper is not part of a sale bundle yet.
                  </p>
                )}

                <ul className="mt-5 space-y-2.5 border-t border-neutral-100 pt-4 text-xs text-neutral-600">
                  {[
                    "Instant access after payment",
                    "Read on any device, forever",
                    "Verified official past paper",
                  ].map((t) => (
                    <li key={t} className="flex items-start gap-2">
                      <svg className="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden>
                        <path d="M20 6 9 17l-5-5" />
                      </svg>
                      {t}
                    </li>
                  ))}
                </ul>
              </div>
            </div>

            {resource.previewPages > 0 && (
              <p className="mt-3 text-center text-xs text-neutral-500">
                Includes a {resource.previewPages}-page free preview.
              </p>
            )}
          </aside>
        </div>
      </div>
    </div>
  );
}
