import Link from "next/link";
import { prisma } from "@/lib/db";
import { BundleUploadForm } from "@/components/bundle-upload-form";
import { FreeMaterialUploadForm } from "@/components/free-material-upload-form";
import { AdminResourceFilters } from "@/components/admin-resource-filters";
import { setResourceStatusAction, deleteDraftAction } from "@/lib/admin-actions";
import { getEnabledProgrammes } from "@/lib/settings";

export const dynamic = "force-dynamic";

const statusBadge: Record<string, string> = {
  PUBLISHED: "badge-success",
  DRAFT: "badge-neutral",
  UNPUBLISHED: "badge-gold",
  ARCHIVED: "badge-danger",
};

const RESOURCE_STATUSES = ["PUBLISHED", "DRAFT", "UNPUBLISHED", "ARCHIVED"] as const;

export default async function AdminResourcesPage({
  searchParams,
}: {
  searchParams: Promise<{ q?: string; programme?: string; status?: string }>;
}) {
  const sp = await searchParams;
  const q = sp.q?.trim() ?? "";
  const programme = sp.programme?.trim() ?? "";
  const status = RESOURCE_STATUSES.find((s) => s === sp.status?.trim()) ?? "";

  // Build the filter clause from the URL — filtering happens in the
  // database, not after fetching, so this scales past the take(200).
  const where: {
    OR?: Array<Record<string, unknown>>;
    programme?: { name: string };
    status?: (typeof RESOURCE_STATUSES)[number];
  } = {};
  if (q) {
    where.OR = [
      { title: { contains: q, mode: "insensitive" as const } },
      { course: { code: { contains: q, mode: "insensitive" as const } } },
      { course: { title: { contains: q, mode: "insensitive" as const } } },
    ];
  }
  if (programme) where.programme = { name: programme };
  if (status) where.status = status;

  const [resources, courses, programmes, bundles, enabledProgrammes] = await Promise.all([
    prisma.resource.findMany({
      where,
      orderBy: { createdAt: "desc" },
      take: 200,
      include: {
        course: { select: { code: true, title: true } },
        programme: { select: { name: true } },
        bundle: {
          select: { id: true, title: true, slug: true, status: true },
        },
        files: {
          where: { isCurrent: true },
          select: { id: true, sizeBytes: true, originalName: true, createdAt: true },
          take: 1,
        },
        _count: { select: { entitlements: true } },
      },
    }),
    prisma.course.findMany({
      orderBy: { code: "asc" },
      take: 200,
      select: { code: true, title: true },
    }),
    prisma.programme.findMany({
      orderBy: { name: "asc" },
      select: { name: true },
    }),
    prisma.bundle.findMany({
      orderBy: { createdAt: "desc" },
      take: 200,
      select: {
        id: true,
        title: true,
        academicYear: true,
        status: true,
        course: { select: { code: true } },
      },
    }),
    getEnabledProgrammes(),
  ]);

  const suggestions = courses.map((c) => ({
    code: c.code,
    title: c.title,
    programmes: programmes.map((p) => ({ name: p.name })),
  }));

  const isFiltered = Boolean(q || programme || status);

  return (
    <div>
      <h1 className="text-xl font-bold text-neutral-900">Resources</h1>
      <p className="mt-1 text-sm text-neutral-600">
        Papers live inside bundles — students buy one bundle per course and
        academic year, so upload PDFs in bulk. Papers stay drafts until the
        bundle is published.
      </p>

      {/* ── Bulk upload ─────────────────────────────────────────── */}
      <section className="card mt-5 p-5">
        <h2 className="text-base font-semibold text-neutral-900">
          Upload PDFs in bulk
        </h2>
        <p className="mt-1 mb-4 text-sm text-neutral-600">
          Each file is sniffed and probed server-side (magic bytes, page
          count, encryption) before anything is stored.
        </p>
        <BundleUploadForm
          bundles={bundles}
          suggestions={suggestions}
          programmes={enabledProgrammes.map((p) => ({ slug: p.slug, name: p.name }))}
        />
      </section>

      {/* ── Free materials upload ─────────────────────────────── */}
      <section className="card mt-5 p-5">
        <h2 className="text-base font-semibold text-neutral-900">
          Upload free materials (not for sale)
        </h2>
        <p className="mt-1 mb-4 text-sm text-neutral-600">
          Slides, lecture notes, and revision packs. Students download these
          for free — no bundle, no payment. They are tagged with a course,
          programme track, level, and semester so they show up in the same
          browse flow as paid papers.
        </p>
        <FreeMaterialUploadForm
          suggestions={courses.map((c) => ({ code: c.code, title: c.title }))}
          programmes={enabledProgrammes.map((p) => ({ slug: p.slug, name: p.name }))}
        />
      </section>

      {/* ── Catalog table ──────────────────────────────────────── */}
      <div className="mt-8 flex flex-wrap items-baseline justify-between gap-2">
        <h2 className="text-base font-semibold text-neutral-900">
          Catalog ({resources.length}
          {isFiltered ? " matching" : ""})
        </h2>
        {isFiltered && (
          <p className="text-xs text-neutral-500">
            Filters applied from the bar below.
          </p>
        )}
      </div>

      <AdminResourceFilters programmes={programmes.map((p) => p.name)} />

      <div className="card mt-3 md:overflow-x-auto">
        <table className="table-base table-responsive">
          <thead>
            <tr>
              <th>Paper</th>
              <th>Course</th>
              <th>Bundle</th>
              <th>Programme</th>
              <th>Sales</th>
              <th>File</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {resources.length === 0 && (
              <tr>
                <td colSpan={8} className="py-10 text-center text-neutral-500">
                  {isFiltered
                    ? "No papers match these filters — try clearing them."
                    : "No papers yet — upload your first batch above."}
                </td>
              </tr>
            )}
            {resources.map((r) => {
              const file = r.files[0];
              const fileNote = file
                ? `${(file.sizeBytes / 1024 / 1024).toFixed(1)} MB · ${file.createdAt.toLocaleDateString("en-GB")}`
                : "none";
              return (
                <tr key={r.id}>
                  <td data-label="Paper">
                    <div className="text-right">
                      <Link
                        href={`/resources/${r.slug}`}
                        className="font-medium text-brand-800 hover:underline"
                      >
                        {r.title}
                      </Link>
                      <p className="text-xs text-neutral-500">
                        Level {r.level} · Sem {r.semester} · {r.academicYear}
                        {r.pageCount ? ` · ${r.pageCount} pages` : ""}
                      </p>
                    </div>
                  </td>
                  <td data-label="Course" className="text-xs">
                    <span className="badge-brand">{r.course.code}</span>
                  </td>
                  <td data-label="Bundle" className="text-xs">
                    {r.bundle ? (
                      <Link
                        href={`/admin/bundles`}
                        className="font-medium text-brand-800 hover:underline"
                      >
                        {r.bundle.title}
                      </Link>
                    ) : (
                      <span className="badge-gold">Free download</span>
                    )}
                  </td>
                  <td data-label="Programme" className="text-xs text-neutral-600">{r.programme.name}</td>
                  <td data-label="Sales" className="tabular-nums">{r._count.entitlements}</td>
                  <td data-label="File" className="text-xs text-neutral-600">
                    {file ? (
                      <span className="badge-success">PDF</span>
                    ) : (
                      <span className="badge-neutral">—</span>
                    )}{" "}
                    {fileNote}
                  </td>
                  <td data-label="Status">
                    <span className={statusBadge[r.status] ?? "badge-neutral"}>
                      {r.status}
                    </span>
                  </td>
                  <td data-label="" className="whitespace-nowrap">
                    <Link
                      href={`/admin/resources/${r.id}`}
                      className="btn-secondary btn-sm mr-1.5"
                    >
                      Edit
                    </Link>
                    {r.status !== "PUBLISHED" && r.files.length > 0 && (
                      <form action={setResourceStatusAction} className="inline">
                        <input type="hidden" name="resourceId" value={r.id} />
                        <input type="hidden" name="next" value="PUBLISHED" />
                        <button className="btn-primary btn-sm mr-1.5">Publish</button>
                      </form>
                    )}
                    {r.status === "PUBLISHED" && (
                      <form action={setResourceStatusAction} className="inline">
                        <input type="hidden" name="resourceId" value={r.id} />
                        <input type="hidden" name="next" value="UNPUBLISHED" />
                        <button className="btn-secondary btn-sm mr-1.5">Unpublish</button>
                      </form>
                    )}
                    {r.status === "DRAFT" && (
                      <form action={deleteDraftAction} className="inline">
                        <input type="hidden" name="resourceId" value={r.id} />
                        <button className="btn-danger btn-sm">Delete</button>
                      </form>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}