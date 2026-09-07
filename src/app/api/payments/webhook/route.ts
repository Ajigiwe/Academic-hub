import { prisma } from "@/lib/db";
import { verifySignature } from "@/lib/payments";
import { processSuccessfulPayment } from "@/lib/payment-processing";
import { NextResponse } from "next/server";

interface WebhookBody {
  event?: string;
  eventId?: string;
  reference?: string;
  [key: string]: unknown;
}

/**
 * Payment webhook (spec §11).
 *
 * Contract for every provider adapter:
 * 1. Verify the HMAC signature over the RAW body before parsing.
 * 2. Persist the raw event with its provider event id (unique) so
 *    replays are deduplicated (idempotency).
 * 3. Only `payment.success` events trigger fulfillment, which itself
 *    re-verifies with the gateway server-side.
 */
export async function POST(req: Request) {
  const rawBody = await req.text();
  const signature =
    req.headers.get("x-pastq-signature") ??
    req.headers.get("x-paystack-signature") ??
    "";

  if (!verifySignature(rawBody, signature)) {
    return NextResponse.json({ error: "Invalid signature." }, { status: 401 });
  }

  let body: WebhookBody;
  try {
    body = JSON.parse(rawBody) as WebhookBody;
  } catch {
    return NextResponse.json({ error: "Invalid JSON." }, { status: 400 });
  }

  if (!body.eventId) {
    return NextResponse.json({ error: "Missing eventId." }, { status: 400 });
  }

  // Idempotency: unique eventId. First insert wins; replays no-op.
  const inserted = await prisma.webhookEvent
    .create({
      data: {
        provider: "mock",
        eventId: body.eventId,
        type: body.event ?? "unknown",
        payload: body as object,
      },
    })
    .catch(() => null);

  if (!inserted) {
    return NextResponse.json({ ok: true, deduplicated: true });
  }

  if (body.event !== "payment.success" || !body.reference) {
    await prisma.webhookEvent.update({
      where: { eventId: body.eventId },
      data: { processedAt: new Date() },
    });
    return NextResponse.json({ ok: true, ignored: true });
  }

  try {
    await processSuccessfulPayment(body.reference);
    await prisma.webhookEvent.update({
      where: { eventId: body.eventId },
      data: { processedAt: new Date() },
    });
    return NextResponse.json({ ok: true });
  } catch (err) {
    // Event stays unprocessed (processedAt null) → provider retry can heal us.
    console.error("Webhook processing failed", err);
    return NextResponse.json(
      { error: "Processing failed; will retry." },
      { status: 500 },
    );
  }
}
