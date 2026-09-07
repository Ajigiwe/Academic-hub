import Link from "next/link";
import { prisma } from "@/lib/db";
import { formatPrice } from "@/lib/resources";
import {
  setBundleStatusAction,
  updateBundlePriceAction,
} from "@/lib/admin-actions";

export const dynamic = "force-dynamic";

const statusBadge: Record<string, string> = {
  PUBLISHED: "badge-success",
  DRAFT: "badge-neutral",
  UNPUBLISHED: "badge-gold",
  ARCHIVED: "badge-danger",
};

export default async function AdminBundlesPage() {
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
                      <button className="btn-secondary btn-sm">Unpublish</button>
                    </form>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <p className="mt-3 text-xs text-neutral-500">
        Publishing a bundle also publishes every paper inside it; unpublishing
        hides the bundle from the catalog while keeping existing owners' access.
        Papers published later are granted automatically to everyone who already
        bought the bundle — late uploads reach past buyers with no extra steps.
      </p>
    </div>
  );
}