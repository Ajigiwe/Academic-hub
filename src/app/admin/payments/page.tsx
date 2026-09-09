import { prisma } from "@/lib/db";
import { formatPrice } from "@/lib/resources";

export const dynamic = "force-dynamic";

/**
 * Payments console — the debugging companion to /admin/orders.
 * Orders answers "who bought what"; this page answers "what did the
 * gateway say": per-payment provider state (including failure reasons)
 * and the raw webhook deliveries from Moolre, with the untouched JSON
 * payload for inspecting unexpected callbacks.
 */

function statusBadge(status: string): string {
  if (status === "SUCCESSFUL") return "badge-success";
  if (status === "PENDING") return "badge-gold";
  return "badge-danger";
}

function eventBadge(type: string): string {
  return type === "payment.callback" || type === "payment.success"
    ? "badge-success"
    : "badge-neutral";
}

export default async function AdminPaymentsPage() {
  const [payments, webhookEvents] = await Promise.all([
    prisma.payment.findMany({
      orderBy: { createdAt: "desc" },
      take: 100,
      include: {
        order: {
          select: {
            reference: true,
            status: true,
            user: { select: { firstName: true, lastName: true, email: true } },
          },
        },
      },
    }),
    prisma.webhookEvent.findMany({
      orderBy: { createdAt: "desc" },
      take: 50,
    }),
  ]);

  const pendingCount = payments.filter((p) => p.status === "PENDING").length;
  const failedCount = payments.filter((p) => p.status === "FAILED").length;
  const unprocessedEvents = webhookEvents.filter((e) => !e.processedAt).length;

  return (
    <div>
      <h1 className="text-xl font-bold text-neutral-900">Payments</h1>
      <p className="mt-1 text-sm text-neutral-600">
        Gateway-side payment records and raw provider webhook deliveries.
      </p>

      {/* Health counters — spot a stuck pipeline at a glance. */}
      <div className="mt-4 grid gap-3 sm:grid-cols-4">
        <div className="card px-4 py-3">
          <p className="text-[11px] font-bold uppercase tracking-wider text-neutral-500">
            Recorded payments
          </p>
          <p className="mt-1 text-2xl font-bold text-neutral-900">{payments.length}</p>
        </div>
        <div className="card px-4 py-3">
          <p className="text-[11px] font-bold uppercase tracking-wider text-neutral-500">
            Pending
          </p>
          <p className="mt-1 text-2xl font-bold text-gold-600">{pendingCount}</p>
        </div>
        <div className="card px-4 py-3">
          <p className="text-[11px] font-bold uppercase tracking-wider text-neutral-500">
            Failed
          </p>
          <p className="mt-1 text-2xl font-bold text-red-600">{failedCount}</p>
        </div>
        <div className="card px-4 py-3">
          <p className="text-[11px] font-bold uppercase tracking-wider text-neutral-500">
            Unprocessed webhooks
          </p>
          <p className={`mt-1 text-2xl font-bold ${unprocessedEvents > 0 ? "text-red-600" : "text-emerald-600"}`}>
            {unprocessedEvents}
          </p>
        </div>
      </div>

      {/* Payment records */}
      <h2 className="mt-8 text-sm font-bold uppercase tracking-wider text-neutral-500">
        Payment records (latest 100)
      </h2>
      <div className="card mt-3 md:overflow-x-auto">
        <table className="table-base table-responsive">
          <thead>
            <tr>
              <th>Order</th>
              <th>Student</th>
              <th>Amount</th>
              <th>Provider</th>
              <th>Channel</th>
              <th>Status</th>
              <th>Provider ref</th>
              <th>Failure reason</th>
              <th>Verified</th>
              <th>Created</th>
            </tr>
          </thead>
          <tbody>
            {payments.length === 0 && (
              <tr>
                <td colSpan={10} className="py-10 text-center text-neutral-500">
                  No payment attempts recorded yet.
                </td>
              </tr>
            )}
            {payments.map((p) => (
              <tr key={p.id}>
                <td data-label="Order" className="font-mono text-xs">{p.order.reference}</td>
                <td data-label="Student">
                  <p className="text-right font-medium">
                    {p.order.user.firstName} {p.order.user.lastName}
                  </p>
                  <p className="text-right text-xs text-neutral-500">{p.order.user.email}</p>
                </td>
                <td data-label="Amount" className="whitespace-nowrap font-medium">
                  {formatPrice(p.amountPesewas)}
                  <span className="ml-1 text-xs text-neutral-400">{p.currency}</span>
                </td>
                <td data-label="Provider" className="text-xs">{p.provider}</td>
                <td data-label="Channel" className="text-xs">{p.channel}</td>
                <td data-label="Status">
                  <span className={statusBadge(p.status)}>{p.status}</span>
                </td>
                <td data-label="Provider ref" className="max-w-40 truncate font-mono text-xs" title={p.providerRef ?? undefined}>
                  {p.providerRef ?? "—"}
                </td>
                <td data-label="Failure reason" className="max-w-52 text-xs text-red-700" title={p.failureReason ?? undefined}>
                  {p.failureReason ?? "—"}
                </td>
                <td data-label="Verified" className="whitespace-nowrap text-xs text-neutral-600">
                  {p.verifiedAt
                    ? p.verifiedAt.toLocaleString("en-GB", { dateStyle: "short", timeStyle: "short" })
                    : "—"}
                </td>
                <td data-label="Created" className="whitespace-nowrap text-neutral-600">
                  {p.createdAt.toLocaleString("en-GB", { dateStyle: "short", timeStyle: "short" })}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Raw webhook deliveries */}
      <h2 className="mt-8 text-sm font-bold uppercase tracking-wider text-neutral-500">
        Raw webhook events (latest 50)
      </h2>
      <div className="card mt-3 divide-y divide-neutral-100">
        {webhookEvents.length === 0 && (
          <p className="py-10 text-center text-neutral-500">
            No webhook deliveries recorded yet.
          </p>
        )}
        {webhookEvents.map((e) => (
          <details key={e.id} className="group px-4 py-3">
            <summary className="flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-1 text-xs">
              <span className="font-semibold text-neutral-900">{e.provider}</span>
              <span className={eventBadge(e.type)}>{e.type}</span>
              <span className="font-mono text-neutral-500">{e.eventId}</span>
              {e.processedAt ? (
                <span className="badge-success">processed</span>
              ) : (
                <span className="badge-danger">unprocessed</span>
              )}
              <span className="ml-auto whitespace-nowrap text-neutral-400">
                {e.createdAt.toLocaleString("en-GB", { dateStyle: "short", timeStyle: "short" })}
              </span>
            </summary>
            <pre className="mt-2 max-h-64 overflow-auto rounded-lg bg-neutral-950 p-3 text-[11px] leading-relaxed text-emerald-300">
              {JSON.stringify(e.payload, null, 2)}
            </pre>
          </details>
        ))}
      </div>
    </div>
  );
}
