import Link from "next/link";
import { prisma } from "@/lib/db";
import { formatPrice } from "@/lib/resources";

export const dynamic = "force-dynamic";

export default async function AdminDashboardPage() {
  const [revenueAgg, paidSales, students, publishedBundles, pendingPayments, recentOrders, popular] =
    await Promise.all([
      prisma.order.aggregate({
        where: { status: "PAID" },
        _sum: { amountPesewas: true },
      }),
      prisma.order.count({ where: { status: "PAID" } }),
      prisma.user.count({ where: { role: "STUDENT" } }),
      prisma.bundle.count({ where: { status: "PUBLISHED" } }),
      prisma.payment.count({ where: { status: "PENDING" } }),
      prisma.order.findMany({
        orderBy: { createdAt: "desc" },
        take: 8,
        include: {
          user: { select: { firstName: true, lastName: true, email: true } },
          items: { include: { bundle: { select: { title: true, slug: true } } } },
        },
      }),
      prisma.bundle.findMany({
        where: { status: "PUBLISHED" },
        orderBy: { orderItems: { _count: "desc" } },
        take: 5,
        include: {
          course: { select: { code: true } },
          _count: { select: { orderItems: true } },
        },
      }),
    ]);

  const stats = [
    { label: "Total revenue", value: formatPrice(revenueAgg._sum.amountPesewas ?? 0) },
    { label: "Total sales", value: paidSales.toLocaleString() },
    { label: "Students", value: students.toLocaleString() },
    { label: "Published bundles", value: publishedBundles.toLocaleString() },
  ];

  return (
    <div>
      <h1 className="text-xl font-bold text-neutral-900">Dashboard</h1>

      <div className="mt-5 grid grid-cols-1 gap-3 min-[400px]:grid-cols-2 sm:gap-4 lg:grid-cols-4">
        {stats.map((s) => (
          <div key={s.label} className="card-padded min-w-0 p-4 min-[400px]:p-5">
            <p className="text-[11px] uppercase leading-snug tracking-wide text-neutral-500 sm:text-xs">
              {s.label}
            </p>
            <p className="mt-1.5 text-xl font-extrabold tabular-nums text-neutral-900 min-[400px]:text-2xl">
              {s.value}
            </p>
          </div>
        ))}
      </div>

      {pendingPayments > 0 && (
        <p className="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
          {pendingPayments} payment{pendingPayments === 1 ? "" : "s"} awaiting
          verification — they resolve automatically via webhook or when the
          student returns from the gateway.
        </p>
      )}

      <div className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="min-w-0 lg:col-span-2">
          <div className="flex items-center justify-between">
            <h2 className="font-bold text-neutral-900">Recent orders</h2>
            <Link href="/admin/orders" className="text-sm font-medium text-brand-700 hover:underline">
              View all
            </Link>
          </div>
          <div className="card mt-3">
            <table className="table-base table-responsive">
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Student</th>
                  <th>Amount</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                {recentOrders.length === 0 && (
                  <tr>
                    <td colSpan={5} className="py-8 text-center text-neutral-500">
                      No orders yet.
                    </td>
                  </tr>
                )}
                {recentOrders.map((o) => (
                  <tr key={o.id}>
                    <td data-label="Order" className="font-mono text-xs">{o.reference}</td>
                    <td data-label="Student">
                      <div className="text-right">
                        <p className="font-medium">
                          {o.user.firstName} {o.user.lastName}
                        </p>
                        <p className="text-xs text-neutral-500">{o.user.email}</p>
                      </div>
                    </td>
                    <td data-label="Amount" className="whitespace-nowrap">{formatPrice(o.amountPesewas)}</td>
                    <td data-label="Status">
                      <span
                        className={
                          o.status === "PAID"
                            ? "badge-success"
                            : o.status === "PENDING"
                              ? "badge-gold"
                              : o.status === "FAILED"
                                ? "badge-danger"
                                : "badge-neutral"
                        }
                      >
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
        </div>

        <div className="min-w-0">
          <h2 className="font-bold text-neutral-900">Popular bundles</h2>
          <div className="card mt-3 divide-y divide-neutral-100">
            {popular.length === 0 && (
              <p className="p-6 text-center text-sm text-neutral-500">
                No published bundles yet.
              </p>
            )}
            {popular.map((b, i) => (
              <div key={b.id} className="flex items-center gap-3 p-3.5">
                <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-bold text-brand-800">
                  {i + 1}
                </span>
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium text-neutral-900">
                    {b.title}
                  </p>
                  <p className="text-xs text-neutral-500">
                    {b.course.code} · {b._count.orderItems} sale
                    {b._count.orderItems === 1 ? "" : "s"}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
