import type { Metadata } from "next";
import Link from "next/link";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";
import { formatPrice } from "@/lib/resources";

export const metadata: Metadata = { title: "My Account" };

/** Human labels + badge styles for the raw OrderStatus enum. */
const statusStyle: Record<string, { label: string; badge: string }> = {
  PAID: { label: "Paid", badge: "badge-success" },
  PENDING: { label: "Pending payment", badge: "badge-gold" },
  FAILED: { label: "Failed", badge: "badge-danger" },
  CANCELLED: { label: "Cancelled", badge: "badge-neutral" },
  REFUNDED: { label: "Refunded", badge: "badge-neutral" },
};

function StatusBadge({ status }: { status: string }) {
  const s = statusStyle[status] ?? { label: status, badge: "badge-neutral" };
  return <span className={`${s.badge} font-semibold`}>{s.label}</span>;
}

export default async function AccountPage() {
  const user = await getCurrentUser();
  if (!user) redirect("/login?next=/account");

  const fullUser = await prisma.user.findUniqueOrThrow({
    where: { id: user.id },
    select: { createdAt: true },
  });

  const orders = await prisma.order.findMany({
    where: { userId: user.id },
    orderBy: { createdAt: "desc" },
    take: 50,
    include: {
      items: { include: { bundle: { select: { title: true, slug: true } } } },
    },
  });

  // Aggregates computed over the loaded rows (up to 50 — ample for a
  // student account; the header stat would otherwise undercount).
  const paidOrders = orders.filter((o) => o.status === "PAID");
  const paidCount = paidOrders.length;
  const totalSpent = paidOrders.reduce((sum, o) => sum + o.amountPesewas, 0);
  const initials = `${user.firstName[0] ?? ""}${user.lastName[0] ?? ""}`.toUpperCase();

  return (
    <div className="container-page max-w-3xl py-8">
      {/* Profile header */}
      <div className="card overflow-hidden">
        <div className="h-14 bg-gradient-to-r from-brand-700 via-brand-800 to-brand-900" />
        <div className="-mt-7 px-6 pb-5">
          <span className="grid h-14 w-14 place-items-center rounded-2xl border-4 border-white bg-gradient-to-br from-gold-400 to-gold-600 text-lg font-bold text-white shadow-card">
            {initials}
          </span>
          <div className="mt-3 flex flex-wrap items-center gap-2">
            <h1 className="text-xl font-bold tracking-tight text-neutral-900">
              {user.firstName} {user.lastName}
            </h1>
            {user.role === "ADMIN" && (
              <span className="badge-gold font-semibold">Administrator</span>
            )}
          </div>
          <p className="text-sm text-neutral-600">{user.email}</p>

          <div className="mt-4 flex flex-wrap gap-2 text-xs">
            <span className="rounded-full bg-neutral-100 px-3 py-1 font-medium text-neutral-700">
              Member since{" "}
              {fullUser.createdAt.toLocaleDateString("en-GB", {
                month: "short",
                year: "numeric",
              })}
            </span>
            <span className="rounded-full bg-neutral-100 px-3 py-1 font-medium text-neutral-700">
              {paidCount} completed purchase{paidCount === 1 ? "" : "s"}
            </span>
            {totalSpent > 0 && (
              <span className="rounded-full bg-neutral-100 px-3 py-1 font-medium text-neutral-700">
                {formatPrice(totalSpent)} spent
              </span>
            )}
          </div>
        </div>
      </div>

      {/* Order history */}
      <div className="mt-8 flex items-end justify-between">
        <div>
          <h2 className="text-lg font-bold tracking-tight text-neutral-900">
            Order history
          </h2>
          <p className="mt-0.5 text-sm text-neutral-500">
            Past purchases, payment status, and receipts
          </p>
        </div>
      </div>

      {orders.length === 0 ? (
        <div className="card mt-4 py-12 text-center">
          <p className="text-sm text-neutral-600">No orders yet.</p>
          <Link href="/search" className="btn-primary mt-5">
            Browse Past Questions
          </Link>
        </div>
      ) : (
        <div className="card mt-4">
          <table className="table-base table-responsive">
            <thead>
              <tr>
                <th>Order</th>
                <th>Items</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Date</th>
                <th>
                  <span className="sr-only">Actions</span>
                </th>
              </tr>
            </thead>
            <tbody>
              {orders.map((o) => (
                <tr
                  key={o.id}
                  className="transition-colors hover:bg-neutral-50/70"
                >
                  <td data-label="Order" className="font-mono text-xs">
                    {o.reference}
                  </td>
                  <td data-label="Items">
                    <div className="space-y-0.5">
                      {o.items.map((i) => (
                        <div key={i.id} className="min-w-0">
                          <Link
                            href={`/bundles/${i.bundle.slug}`}
                            className="text-sm transition-colors hover:text-brand-700 hover:underline"
                          >
                            {i.bundle.title}
                          </Link>
                        </div>
                      ))}
                    </div>
                  </td>
                  <td data-label="Amount" className="whitespace-nowrap font-medium">
                    {formatPrice(o.amountPesewas)}
                  </td>
                  <td data-label="Status">
                    <StatusBadge status={o.status} />
                  </td>
                  <td
                    data-label="Date"
                    className="whitespace-nowrap text-neutral-600"
                    title={
                      o.paidAt
                        ? `Paid ${o.paidAt.toLocaleString("en-GB")}`
                        : undefined
                    }
                  >
                    {o.paidAt
                      ? o.paidAt.toLocaleDateString("en-GB")
                      : o.createdAt.toLocaleDateString("en-GB")}
                  </td>
                  <td data-label="">
                    {/* Unpaid orders: a fresh checkout for the same bundle
                        re-runs payment (the stale pending order just stays
                        pending); verify route handles PAID idempotently. */}
                    {o.status === "PENDING" && o.items[0] && (
                      <Link
                        href={`/checkout?bundle=${o.items[0].bundleId}`}
                        className="text-xs font-semibold text-brand-700 hover:underline"
                      >
                        Complete payment →
                      </Link>
                    )}
                    {o.status === "FAILED" && o.items[0] && (
                      <Link
                        href={`/bundles/${o.items[0].bundle.slug}`}
                        className="text-xs font-semibold text-brand-700 hover:underline"
                      >
                        Try again →
                      </Link>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
