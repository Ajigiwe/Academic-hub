import Link from "next/link";
import { notFound } from "next/navigation";
import { prisma } from "@/lib/db";
import { AdminResourceEditForm } from "@/components/admin-resource-edit-form";
import { AdminFileReplaceForm } from "@/components/admin-file-replace-form";

export const dynamic = "force-dynamic";

const statusBadge: Record<string, string> = {
  PUBLISHED: "badge-success",
  DRAFT: "badge-neutral",
  UNPUBLISHED: "badge-gold",
  ARCHIVED: "badge-danger",
};

const typeLabel: Record<string, string> = {
  PAST_QUESTION: "Past question",
  LECTURE_NOTES: "Lecture notes",
  SLIDES: "Slides",
  REVISION: "Revision",
  PRACTICE: "Practice",
};

export default async function AdminResourceEditPage({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  const resource = await prisma.resource.findUnique({
    where: { id },
    include: {
      course: { select: { code: true, title: true } },
      programme: { select: { name: true } },
      bundle: { select: { id: true, title: true, slug: true, status: true } },
      files: { orderBy: { createdAt: "desc" } },
      _count: { select: { entitlements: true } },
    },
  });
  if (!resource) notFound();

  const programmes = await prisma.programme.findMany({
    orderBy: { name: "asc" },
    select: { name: true },
    take: 200,
  });

  const currentFile = resource.files.find((f) => f.isCurrent) ?? null;

  return (
    <div>
      <Link
        href="/admin/resources"
        className="text-sm font-medium text-neutral-500 hover:text-brand-800"
      >
        ← Back to resources
      </Link>

      {/* ── Header ─────────────────────────────────────────────── */}
      <div className="mt-3 flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h1 className="text-xl font-bold text-neutral-900">{resource.title}</h1>
            <span className={statusBadge[resource.status] ?? "badge-neutral"}>
              {resource.status}
            </span>
          </div>
          <p className="mt-1 text-sm text-neutral-600">
            <span className="badge-brand mr-2">{resource.course.code}</span>
            {resource.course.title} · Level {resource.level} · Sem{" "}
            {resource.semester} · {resource.academicYear}
          </p>
        </div>
        <div className="flex items-center gap-2">
          {resource.bundle && (
            <Link
              href={`/bundles/${resource.bundle.slug}`}
              className="btn-secondary btn-sm"
            >
              View bundle →
            </Link>
          )}
          <Link href={`/resources/${resource.slug}`} className="btn-secondary btn-sm">
            View paper →
          </Link>
        </div>
      </div>

      <div className="mt-6 grid gap-6 lg:grid-cols-5">
        {/* ── Metadata form (wider column) ─────────────────────── */}
        <section className="card p-5 lg:col-span-3">
          <h2 className="text-base font-semibold text-neutral-900">
            Catalog details
          </h2>
          <p className="mt-1 mb-4 text-sm text-neutral-600">
            Description, type, and programme are editable after creation.
            Course, level, semester, year, and bundle are fixed so existing
            orders keep their meaning.
          </p>
          <AdminResourceEditForm
            resourceId={resource.id}
            description={resource.description ?? ""}
            type={resource.type}
            programmeName={resource.programme.name}
            programmeSuggestions={programmes.map((p) => p.name)}
          />
        </section>

        {/* ── Right column: file version + stats ────────────────── */}
        <div className="space-y-6 lg:col-span-2">
          <section className="card p-5">
            <h2 className="text-base font-semibold text-neutral-900">
              Document file
            </h2>
            <div className="mt-3 rounded-lg border border-neutral-200 bg-neutral-50 p-3">
              {currentFile ? (
                <>
                  <p className="flex items-center gap-2 text-sm font-medium text-neutral-900">
                    <span className="badge-success">PDF</span>
                    {currentFile.originalName}
                  </p>
                  <p className="mt-1.5 text-xs text-neutral-600">
                    {(currentFile.sizeBytes / 1024 / 1024).toFixed(1)} MB ·{" "}
                    {resource.pageCount ?? "?"} pages · uploaded{" "}
                    {currentFile.createdAt.toLocaleDateString("en-GB")}
                  </p>
                </>
              ) : (
                <p className="text-sm text-neutral-600">
                  No file attached yet — upload one below to publish.
                </p>
              )}
            </div>

            <h3 className="mt-5 mb-2 text-sm font-semibold text-neutral-900">
              Upload a replacement version
            </h3>
            <AdminFileReplaceForm resourceId={resource.id} />

            {resource.files.length > 1 && (
              <div className="mt-5 border-t border-neutral-200 pt-4">
                <h4 className="text-[11px] font-bold uppercase tracking-wide text-neutral-500">
                  Version history ({resource.files.length})
                </h4>
                <ul className="mt-2 space-y-1.5">
                  {resource.files.map((f) => (
                    <li
                      key={f.id}
                      className="flex items-center justify-between text-xs text-neutral-600"
                    >
                      <span className="truncate">
                        {f.createdAt.toLocaleDateString("en-GB")} ·{" "}
                        {(f.sizeBytes / 1024 / 1024).toFixed(1)} MB
                      </span>
                      {f.isCurrent ? (
                        <span className="badge-success">Current</span>
                      ) : (
                        <span className="badge-neutral">Superseded</span>
                      )}
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </section>

          <section className="card p-5">
            <h2 className="text-base font-semibold text-neutral-900">Impact</h2>
            <p className="mt-1 mb-3 text-sm text-neutral-600">
              Owners = students who bought this paper's bundle.
            </p>
            <dl className="grid grid-cols-2 gap-3 text-center">
              <div className="rounded-lg border border-neutral-200 p-3">
                <dt className="text-[11px] font-bold uppercase tracking-wide text-neutral-500">
                  Owners
                </dt>
                <dd className="mt-1 text-sm font-bold text-neutral-900">
                  {resource._count.entitlements}
                </dd>
              </div>
              <div className="rounded-lg border border-neutral-200 p-3">
                <dt className="text-[11px] font-bold uppercase tracking-wide text-neutral-500">
                  Bundle
                </dt>
                <dd className="mt-1 text-sm font-bold text-neutral-900">
                  {resource.bundle ? resource.bundle.status : "—"}
                </dd>
              </div>
            </dl>
            <p className="mt-3 text-xs text-neutral-500">
              Type: {typeLabel[resource.type] ?? resource.type} · Slug:{" "}
              <code className="rounded bg-neutral-100 px-1 py-0.5">
                {resource.slug}
              </code>
            </p>
          </section>
        </div>
      </div>
    </div>
  );
}
