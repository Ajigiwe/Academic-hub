import { prisma } from "@/lib/db";
import { getPaymentProvider, fulfillOrder } from "@/lib/payments";

/**
 * Verify a payment against the gateway's own API and fulfill the order.
 * This is the ONLY place fulfillment is triggered from, and it always
 * re-checks with the provider server-side (spec §11: "never trust the
 * frontend payment-success state"). Used by both the webhook route and
 * the verify-callback route.
 */
export async function processSuccessfulPayment(orderRef: string): Promise<{
  fulfilled: boolean;
  alreadyFulfilled: boolean;
}> {
  const provider = getPaymentProvider();
  const verified = await provider.verifyPayment(orderRef);

  const order = await prisma.order.findUnique({
    where: { reference: orderRef },
    include: { items: true },
  });
  if (!order) throw new Error(`Order not found: ${orderRef}`);

  if (!verified.successful) {
    await prisma.payment.updateMany({
      where: { orderId: order.id, status: "PENDING" },
      data: {
        status: "FAILED",
        failureReason: verified.failureReason ?? "Verification failed.",
      },
    });
    throw new Error(verified.failureReason ?? "Payment verification failed.");
  }

  // Amount/currency tamper check against our own order record.
  if (
    verified.amountPesewas !== order.amountPesewas ||
    verified.currency !== order.currency
  ) {
    await prisma.payment.updateMany({
      where: { orderId: order.id, status: "PENDING" },
      data: {
        status: "FAILED",
        failureReason: `Amount mismatch: expected ${order.amountPesewas} ${order.currency}, got ${verified.amountPesewas} ${verified.currency}.`,
      },
    });
    throw new Error("Payment amount mismatch.");
  }

  await prisma.payment.updateMany({
    where: { orderId: order.id, status: "PENDING" },
    data: {
      status: "SUCCESSFUL",
      providerRef: verified.providerRef,
      channel: verified.channel,
      verifiedAt: new Date(),
    },
  });

  const fulfillment = await fulfillOrder(orderRef);
  return {
    fulfilled: !fulfillment.alreadyFulfilled,
    alreadyFulfilled: fulfillment.alreadyFulfilled,
  };
}
