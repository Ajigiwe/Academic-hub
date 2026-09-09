import { prisma } from "@/lib/db";
import { paystackEventId, verifyPaystackSignature } from "@/lib/paystack";
import { processSuccessfulPayment } from "@/lib/payment-processing";
import { NextResponse } from "next/server";

interface PaystackWebhookBody {
  event?: string;
  data?: {
    id?: unknown;
    reference?: unknown;
    status?: unknown;
    [key: string]: unknown;
  };
  [key: string]: unknown;
}

/**
 * Paystack webhook endpoint.
 *
 * Paystack signs the raw request body with HMAC-SHA512. Events are persisted
 * before fulfillment so retries are idempotent and auditable.
 */
export async function POST(req: Request) {
  const rawBody = await req.text();
  const signature = req.headers.get("x-paystack-signature") ?? "";

  if (!verifyPaystackSignature(rawBody, signature)) {
    return NextResponse.json({ error: "Invalid signature." }, { status: 401 });
  }

  let body: PaystackWebhookBody;
  try {
    body = JSON.parse(rawBody) as PaystackWebhookBody;
  } catch {
    return NextResponse.json({ error: "Invalid JSON." }, { status: 400 });
  }

  const event = body.event;
  const data = body.data ?? {};
  if (!event || !data.reference) {
    return NextResponse.json({ error: "Missing Paystack event/reference." }, { status: 400 });
  }

  const eventId = paystackEventId(event, data);
  const inserted = await prisma.webhookEvent
    .create({
      data: {
        provider: "paystack",
        eventId,
        type: event,
        payload: body as object,
      },
    })
    .catch(() => null);

  if (!inserted) {
    return NextResponse.json({ ok: true, deduplicated: true });
  }

  if (event !== "charge.success") {
    await prisma.webhookEvent.update({
      where: { eventId },
      data: { processedAt: new Date() },
    });
    return NextResponse.json({ ok: true, ignored: true });
  }

  try {
    await processSuccessfulPayment(String(data.reference));
    await prisma.webhookEvent.update({
      where: { eventId },
      data: { processedAt: new Date() },
    });
    return NextResponse.json({ ok: true });
  } catch (err) {
    // Keep processedAt null so Paystack can retry after a transient failure.
    console.error("Paystack webhook processing failed", err);
    return NextResponse.json(
      { error: "Processing failed; will retry." },
      { status: 500 },
    );
  }
}
