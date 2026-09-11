import Link from "next/link";
import { prisma } from "@/lib/db";
import { formatPrice } from "@/lib/resources";
import {
  setBundleStatusAction,
  updateBundlePriceAction,
  deleteBundleAction,
} from "@/lib/admin-actions";
import { ConfirmSubmit } from "@/components/confirm-submit";

export const dynamic = "force-dynamic";

const statusBadge: Record<string, string> = {
  PUBLISHED: "badge-success",
  DRAFT: "badge-neutral",
  UNPUBLISHED: "badge-gold",
  ARCHIVED: "badge-danger",
};

export default async function AdminBundlesPage({
  searchParams,
}: {
  searchParams: Promise<Record<string, string | string[] | undefined>>;
}) {
  // Outcome of the last paper/bundle delete attempt (see admin-actions).
  const sp = await searchParams;
  const notice = typeof sp.notice === "string" ? sp.notice : undefined;
  const noticeKind = typeof sp.kind === "string" ? sp.kind : undefined;
  const noticeName = typeof sp.name === "string" ? sp.name : undefined;
  const noticeSold = Number(sp.sold ?? 0);

  const bundles = await prisma.bundle.findMany({
    orderBy: [{ status: "asc" }, { createdAt: "desc" }],
    include: {
      course: { select: { code: true, title: true } },
      programme: { select: { name: true } },
      _count: {
        select: {
          resources: true,
          orderItems: true,
        },
      },
    },
  });

  const noticeBanner =
    notice === "deleted" ? (
      <p className="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
        {noticeKind === "bundle"
          ? noticeName
            ? `Bundle “${noticeName}” and its papers were deleted.`
            : "Bundle deleted."
          : noticeName
            ? `Paper “${noticeName}” was deleted.`
            : "Paper deleted."}
      </p>
    ) : notice === "blocked" ? (
      <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Couldn’t delete {noticeName ? `“${noticeName}”` : "it"} — it appears on
        {" "}
        {noticeSold} order{noticeSold === 1 ? "" : "s"}. Purchase records are
        permanent; unpublish or archive instead.
      </p>
    ) : notice === "denied" ? (
      <p className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        Admin access required.
      </p>
    ) : notice === "missing" ? (
      <p className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        Nothing was selected for deletion.
      </p>
    ) : null;

  return (
    <div>
      <h1 className="text-xl font-bold text-neutral-900">Bundles</h1>
      <p className="mt-1 text-sm text-neutral-600">
        The unit of sale — students pay once for a bundle (course + academic
        year) and unlock every paper inside it. Upload papers in bulk from the{" "}
        <Link href="/admin/resources" className="font-medium text-brand-700 hover:underline">
          Resources
        </Link>{" "}
        page, then publish the bundle here to put everything on sale.
      </p>

      {noticeBanner}

      <div className="card mt-5 md:overflow-x-auto">
        <table className="table-base table-responsive">
          <thead>
            <tr>
              <th>Bundle</th>
              <th>Course</th>
              <th>Papers</th>
              <th>Sales</th>
              <th>Price (GH₵)</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            {bundles.length === 0 && (
              <tr>
                <td colSpan={7} className="py-10 text-center text-neutral-500">
                  No bundles yet — create one from the upload form on the{" "}
                  <Link href="/admin/resources" className="font-medium text-brand-700 hover:underline">
                    Resources
                  </Link>{" "}
                  page.
                </td>
              </tr>
            )}
            {bundles.map((b) => (
              <tr key={b.id}>
                <td data-label="Bundle">
                  <div className="text-right">
                    <Link
                      href={`/bundles/${b.slug}`}
                      className="font-medium text-brand-800 hover:underline"
                    >
                      {b.title}
                    </Link>
                    <p className="text-xs text-neutral-500">
                      {b.programme.name} · Level {b.level} · {b.academicYear}
                    </p>
                  </div>
                </td>
                <td data-label="Course" className="text-xs">
                  <span className="badge-brand">{b.course.code}</span>
                  <p className="mt-1 max-w-[10rem] text-neutral-500">
                    {b.course.title}
                  </p>
                </td>
                <td data-label="Papers" className="tabular-nums">
                  {b._count.resources}
                </td>
                <td data-label="Sales" className="tabular-nums">
                  {b._count.orderItems}
                </td>
                <td data-label="Price" className="whitespace-nowrap">
                  <form action={updateBundlePriceAction} className="flex items-center gap-1.5">
                    <input type="hidden" name="bundleId" value={b.id} />
                    <input
                      name="price"
                      type="number"
                      min="0"
                      max="1000"
                      step="0.50"
                      defaultValue={(b.pricePesewas / 100).toFixed(2)}
                      className="w-20 rounded-lg border border-neutral-300 px-2 py-1 text-right text-sm tabular-nums"
                      aria-label={`Price for ${b.title}`}
                    />
                    <button
                      type="submit"
                      className="btn-secondary btn-sm"
                      title="Save price"
                    >
                      Save
                    </button>
                  </form>
                  <p className="mt-1 text-xs text-neutral-500">
                    {formatPrice(b.pricePesewas)} today
                  </p>
                </td>
                <td data-label="Status">
                  <span className={statusBadge[b.status] ?? "badge-neutral"}>
                    {b.status}
                  </span>
                </td>
                <td data-label="" className="whitespace-nowrap">
                  <Link
                    href={`/admin/resources`}
                    className="btn-secondary btn-sm mr-1.5"
                  >
                    Add papers
                  </Link>
                  {b.status !== "PUBLISHED" && (
                    <form action={setBundleStatusAction} className="inline">
                      <input type="hidden" name="bundleId" value={b.id} />
                      <input type="hidden" name="next" value="PUBLISHED" />
                      <button
                        className="btn-primary btn-sm mr-1.5"
                        disabled={b._count.resources === 0}
                        title={
                          b._count.resources === 0
                            ? "Add at least one paper before publishing"
                            : "Publish bundle and all its papers"
                        }
                      >
                        Publish
                      </button>
                    </form>
                  )}
                  {b.status === "PUBLISHED" && (
                    <form action={setBundleStatusAction} className="inline">
                      <input type="hidden" name="bundleId" value={b.id} />
                      <input type="hidden" name="next" value="UNPUBLISHED" />
                      <button className="btn-secondary btn-sm mr-1.5">Unpublish</button>
                    </form>
                  )}
                  <form action={deleteBundleAction} className="inline">
                    <input type="hidden" name="bundleId" value={b.id} />
                    <ConfirmSubmit
                      className="btn-danger btn-sm"
                      disabled={b._count.orderItems > 0}
                      title={
                        b._count.orderItems > 0
                          ? `Has ${b._count.orderItems} order${b._count.orderItems === 1 ? "" : "s"} — cannot be deleted`
                          : `Permanently delete “${b.title}” and its ${b._count.resources} paper${b._count.resources === 1 ? "" : "s"}`
                      }
                      message={`Delete “${b.title}” and its ${b._count.resources} paper${b._count.resources === 1 ? "" : "s"}? This cannot be undone.`}
                    >
                      Delete
                    </ConfirmSubmit>
                  </form>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <p className="mt-3 text-xs text-neutral-500">
        Publishing a bundle also publishes every paper inside it; unpublishing
        hides the bundle from the catalog while keeping existing owners&apos; access.
        Papers published later are granted automatically to everyone who already
        bought the bundle — late uploads reach past buyers with no extra steps.
        Deleting a bundle also deletes its papers, and is only possible while
        nothing has been sold — purchase records are permanent.
      </p>
    </div>
  );
}