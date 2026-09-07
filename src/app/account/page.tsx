import type { Metadata } from "next";
import Link from "next/link";
import { redirect } from "next/navigation";
import { prisma } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";
import { formatPrice } from "@/lib/resources";

export const metadata: Metadata = { title: "My Account" };

const statusBadge: Record<string, string> = {
  PAID: "badge-success",
  PENDING: "badge-gold",
  FAILED: "badge-danger",
  CANCELLED: "badge-neutral",
  REFUNDED: "badge-neutral",
};

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
    take: 20,
    include: {
      items: { include: { bundle: { select: { title: true, slug: true } } } },
    },
  });

  const paidCount = orders.filter((o) => o.status === "PAID").length;
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
          </div>
        </div>
      </div>

      {/* Purchase history */}
      <div className="mt-8 flex items-end justify-between">
        <div>
          <h2 className="text-lg font-bold tracking-tight text-neutral-900">
            Purchase history
          </h2>
          <p className="mt-0.5 text-sm text-neutral-500">
            Orders and payment status
          </p>
        </div>
      </div>

      {orders.length === 0 ? (
        <div className="card mt-4 py-12 text-center">
          <p className="text-sm text-neutral-600">No purchases yet.</p>
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
              </tr>
            </thead>
            <tbody>
              {orders.map((o) => (
                <tr key={o.id} className="transition-colors hover:bg-neutral-50/70">
                  <td data-label="Order" className="font-mono text-xs">{o.reference}</td>
                  <td data-label="Items">
                    <div className="max-w-56 truncate">
                      {o.items.map((i) => (
                        <Link
                          key={i.id}
                          href={`/bundles/${i.bundle.slug}`}
                          className="transition-colors hover:text-brand-700 hover:underline"
                        >
                          {i.bundle.title}
                        </Link>
                      )).reduce((acc, jsx, idx) => (idx === 0 ? [jsx] : [...acc, ", ", jsx]), [] as React.ReactNode[])}
                    </div>
                  </td>
                  <td data-label="Amount" className="whitespace-nowrap font-medium">
                    {formatPrice(o.amountPesewas)}
                  </td>
                  <td data-label="Status">
                    <span className={statusBadge[o.status] ?? "badge-neutral"}>
                      {o.status}
                    </span>
                  </td>
                  <td data-label="Date" className="whitespace-nowrap text-neutral-600">
                    {o.createdAt.toLocaleDateString("en-GB")}
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
