import { prisma } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";
import { processSuccessfulPayment } from "@/lib/payment-processing";
import { NextResponse } from "next/server";

/**
 * Browser-return verification endpoint (spec §11 flow:
 * "Payment Callback / Verification"). The gateway redirects the student
 * here; we STILL verify server-side with the provider before showing
 * success. The webhook is authoritative; this is a resilient fallback.
 */
export async function GET(req: Request) {
  const url = new URL(req.url);
  const reference = url.searchParams.get("ref");

  const appUrl = url.origin;
  const fail = (reason: string) =>
    NextResponse.redirect(`${appUrl}/payment/result?status=failed&reason=${encodeURIComponent(reason)}`);

  if (!reference) return fail("Missing payment reference.");

  const user = await getCurrentUser();
  if (!user) {
    return NextResponse.redirect(`${appUrl}/login?next=/payment/result?ref=${reference}`);
  }

  const order = await prisma.order.findUnique({
    where: { reference },
    select: { id: true, userId: true, status: true },
  });
  if (!order || order.userId !== user.id) return fail("Order not found.");

  if (order.status === "PAID") {
    return NextResponse.redirect(`${appUrl}/payment/result?status=success&ref=${reference}`);
  }

  try {
    await processSuccessfulPayment(reference);
    return NextResponse.redirect(`${appUrl}/payment/result?status=success&ref=${reference}`);
  } catch (err) {
    console.error("Verify callback failed", err);
    return fail("We could not confirm your payment.");
  }
}
