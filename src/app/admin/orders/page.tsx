import Link from "next/link";
import { prisma } from "@/lib/db";
import { formatPrice } from "@/lib/resources";

export const dynamic = "force-dynamic";

export default async function AdminOrdersPage() {
  const orders = await prisma.order.findMany({
    orderBy: { createdAt: "desc" },
    take: 100,
    include: {
      user: { select: { firstName: true, lastName: true, email: true } },
      items: { include: { bundle: { select: { title: true, slug: true } } } },
      payments: { select: { reference: true, provider: true, status: true, channel: true } },
    },
  });

  return (
    <div>
      <h1 className="text-xl font-bold text-neutral-900">Orders</h1>
      <p className="mt-1 text-sm text-neutral-600">
        Latest 100 orders with their payment records.
      </p>

      <div className="card mt-5 md:overflow-x-auto">
        <table className="table-base table-responsive">
          <thead>
            <tr>
              <th>Order</th>
              <th>Student</th>
              <th>Items</th>
              <th>Amount</th>
              <th>Method</th>
              <th>Payment</th>
              <th>Order status</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            {orders.length === 0 && (
              <tr>
                <td colSpan={8} className="py-10 text-center text-neutral-500">
                  No orders yet.
                </td>
              </tr>
            )}
            {orders.map((o) => {
              const payment = o.payments[0];
              return (
                <tr key={o.id}>
                  <td data-label="Order" className="font-mono text-xs">{o.reference}</td>
                  <td data-label="Student">
                    <p className="text-right font-medium">
                      {o.user.firstName} {o.user.lastName}
                    </p>
                    <p className="text-right text-xs text-neutral-500">{o.user.email}</p>
                  </td>
                  <td data-label="Items">
                    <div className="max-w-52 text-xs">
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
                  <td data-label="Method" className="text-xs">
                    {payment ? `${payment.provider} / ${payment.channel}` : "—"}
                  </td>
                  <td data-label="Payment" className="text-xs">
                    {payment ? (
                      <span
                        className={
                          payment.status === "SUCCESSFUL"
                            ? "badge-success"
                            : payment.status === "PENDING"
                              ? "badge-gold"
                              : "badge-danger"
                        }
                      >
                        {payment.status}
                      </span>
                    ) : (
                      <span className="text-neutral-400">no attempt</span>
                    )}
                  </td>
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
                    {o.createdAt.toLocaleString("en-GB", {
                      dateStyle: "short",
                      timeStyle: "short",
                    })}
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
