import { prisma } from "@/lib/db";
import { createHmac, timingSafeEqual } from "crypto";

/**
 * Payment abstraction (spec §11): the checkout flow depends on this
 * interface only, so providers (mock → Paystack → …) are swappable
 * without touching order/entitlement logic.
 */

export interface InitiatedPayment {
  /** URL the frontend must redirect the student to. */
  redirectUrl: string;
  /** Provider-side transaction reference. */
  providerRef: string;
  /** Channel this provider collects through. */
  channel: "MOCK" | "MOBILE_MONEY" | "CARD";
}

export interface VerifiedPayment {
  successful: boolean;
  providerRef: string;
  channel: "MOCK" | "MOBILE_MONEY" | "CARD";
  amountPesewas: number;
  currency: string;
  failureReason?: string;
}

export interface PaymentProvider {
  readonly name: string;
  initiatePayment(input: {
    orderRef: string;
    amountPesewas: number;
    currency: string;
    customerEmail: string;
    callbackUrl: string;
    /** Origin the request actually arrived on — used to build redirects. */
    origin: string;
  }): Promise<InitiatedPayment>;
  /** Returns a verified snapshot from the gateway's own API. */
  verifyPayment(orderRef: string): Promise<VerifiedPayment>;
}

const WEBHOOK_SECRET = process.env.PAYMENT_WEBHOOK_SECRET ?? "";

/** HMAC-SHA256 of the raw body — verify before parsing JSON. */
export function signPayload(rawBody: string): string {
  return createHmac("sha256", WEBHOOK_SECRET).update(rawBody).digest("hex");
}

export function verifySignature(rawBody: string, signature: string): boolean {
  if (!WEBHOOK_SECRET || !signature) return false;
  const expected = Buffer.from(signPayload(rawBody));
  const provided = Buffer.from(signature);
  if (expected.length !== provided.length) return false;
  return timingSafeEqual(expected, provided);
}

// ─────────────────────────────────────────────────────────────────
// Mock provider — sandbox gateway for development, no real money.
// Simulates a hosted checkout page + server-to-server webhook, so the
// swap to Paystack later changes zero call sites.
// ─────────────────────────────────────────────────────────────────

const pendingMock = new Map<string, { amountPesewas: number; currency: string }>();

const mockProvider: PaymentProvider = {
  name: "mock",

  async initiatePayment({ orderRef, amountPesewas, currency, callbackUrl, origin }) {
    const providerRef = `mock_${orderRef}`;
    pendingMock.set(orderRef, { amountPesewas, currency });
    const base = origin.replace(/\/$/, "");
    const redirectUrl =
      `${base}/payment/mock-checkout?ref=${encodeURIComponent(orderRef)}` +
      `&amount=${amountPesewas}&callback=${encodeURIComponent(callbackUrl)}`;
    return { redirectUrl, providerRef, channel: "MOCK" as const };
  },

  async verifyPayment(orderRef) {
    const session = pendingMock.get(orderRef);
    if (session) {
      return {
        successful: true,
        providerRef: `mock_${orderRef}`,
        channel: "MOCK",
        amountPesewas: session.amountPesewas,
        currency: session.currency,
      };
    }

    // Serverless-safe fallback: pendingMock lives in one request
    // instance, but order creation and verification can hit different
    // lambdas on serverless hosts. The mock gateway already persists a
    // `payment.success` webhook event for this reference before
    // fulfillment runs — that row is a durable, cross-instance
    // acknowledgement that the student approved, so treat it as
    // verified (the amount still comes from our own Payment record).
    const [payment, events] = await Promise.all([
      prisma.payment.findFirst({
        where: { reference: orderRef },
        select: { amountPesewas: true, currency: true },
      }),
      prisma.webhookEvent.findMany({
        where: { provider: "mock", type: "payment.success" },
        orderBy: { createdAt: "desc" },
        take: 100,
        select: { payload: true },
      }),
    ]);
    const acknowledged = events.some((e) => {
      const p = e.payload as { reference?: unknown } | null;
      return typeof p?.reference === "string" && p.reference === orderRef;
    });
    if (payment && acknowledged) {
      return {
        successful: true,
        providerRef: `mock_${orderRef}`,
        channel: "MOCK",
        amountPesewas: payment.amountPesewas,
        currency: payment.currency,
      };
    }

    return {
      successful: false,
      providerRef: `mock_${orderRef}`,
      channel: "MOCK",
      amountPesewas: 0,
      currency: "GHS",
      failureReason: "Unknown or expired mock transaction.",
    };
  },
};

// ─────────────────────────────────────────────────────────────────

export function getPaymentProvider(): PaymentProvider {
  switch (process.env.PAYMENT_PROVIDER) {
    case "moolre":
      // Lazy import keeps Moolre env requirements out of the mock path.
      // eslint-disable-next-line @typescript-eslint/no-require-imports
      return (require("@/lib/moolre") as { moolreProvider: PaymentProvider }).moolreProvider;
    // case "paystack": return paystackProvider; // added when credentials exist
    default:
      return mockProvider;
  }
}

export interface OrderFulfillment {
  orderId: string;
  orderReference: string;
  entitlementResourceIds: string[];
  alreadyFulfilled: boolean;
}

/**
 * Single fulfillment path shared by webhook and verify-callback.
 * Idempotent: re-running for a paid order is a no-op.
 *
 * Items reference BUNDLES (the unit of sale); fulfillment expands each
 * bundle into entitlements for every paper inside it.
 */
export async function fulfillOrder(reference: string): Promise<OrderFulfillment> {
  const { grantEntitlement } = await import("@/lib/entitlement-grant");

  const order = await prisma.order.findUnique({
    where: { reference },
    include: { items: true },
  });
  if (!order) throw new Error(`Order not found: ${reference}`);

  const bundleIds = [...new Set(order.items.map((i) => i.bundleId))];
  const bundles = bundleIds.length
    ? await prisma.bundle.findMany({
        where: { id: { in: bundleIds } },
        include: { resources: { select: { id: true } } },
      })
    : [];
  const resourceIds = bundles.flatMap((b) => b.resources.map((r) => r.id));

  if (order.status === "PAID" || resourceIds.length === 0) {
    return {
      orderId: order.id,
      orderReference: order.reference,
      entitlementResourceIds: resourceIds,
      alreadyFulfilled: true,
    };
  }

  const updated = await prisma.order.updateMany({
    where: { id: order.id, status: { not: "PAID" } },
    data: { status: "PAID", paidAt: new Date() },
  });
  if (updated.count === 0) {
    // Lost a race — the order was paid concurrently.
    return {
      orderId: order.id,
      orderReference: order.reference,
      entitlementResourceIds: resourceIds,
      alreadyFulfilled: true,
    };
  }

  for (const resourceId of resourceIds) {
    await grantEntitlement({
      userId: order.userId,
      resourceId,
      orderId: order.id,
    });
  }

  await prisma.notification.create({
    data: {
      userId: order.userId,
      type: "PURCHASE",
      title: "Purchase confirmed",
      body: `Order ${order.reference} is complete — your papers are in My Library.`,
    },
  });

  return {
    orderId: order.id,
    orderReference: order.reference,
    entitlementResourceIds: resourceIds,
    alreadyFulfilled: false,
  };
}
