import { prisma } from "@/lib/db";
import { getCurrentUser } from "@/lib/auth";
import { processSuccessfulPayment } from "@/lib/payment-processing";
import { getAppOrigin } from "@/lib/app-url";
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

  const appUrl = getAppOrigin(req);
  const fail = (reason: string) =>
    NextResponse.redirect(`${appUrl}/payment/result?status=failed&reason=${encodeURIComponent(reason)}`);

  if (!reference) return fail("Missing payment reference.");

  const user = await getCurrentUser();
  if (!user) {
    const returnTo = `/payment/result?status=success&ref=${encodeURIComponent(reference)}`;
    return NextResponse.redirect(
      `${appUrl}/login?next=${encodeURIComponent(returnTo)}`,
    );
  }

  const order = await prisma.order.findUnique({
    where: { reference },
    select: {
      id: true,
      userId: true,
      status: true,
      items: { select: { bundle: { select: { slug: true } } } },
    },
  });
  if (!order || order.userId !== user.id) return fail("Order not found.");

  // Single-bundle orders deep-link the success screen to the bundle page
  // ("Start reading now"); multi-bundle orders just go to the library.
  const bundleSlug =
    order.items.length === 1 ? order.items[0].bundle.slug : null;
  const successUrl = (withBundle: boolean) => {
    const params = new URLSearchParams({ status: "success", ref: reference });
    if (withBundle && bundleSlug) params.set("bundle", `/bundles/${bundleSlug}`);
    return `${appUrl}/payment/result?${params.toString()}`;
  };

  if (order.status === "PAID") {
    return NextResponse.redirect(successUrl(true));
  }

  try {
    await processSuccessfulPayment(reference);
    return NextResponse.redirect(successUrl(true));
  } catch (err) {
    console.error("Verify callback failed", err);
    return fail("We could not confirm your payment.");
  }
}
