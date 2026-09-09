import { processSuccessfulPayment } from "@/lib/payment-processing";
import { recordMoolreWebhook } from "@/lib/moolre";
import { NextResponse } from "next/server";

/**
 * Moolre payment callback (docs.moolre.com → Payment Webhook API).
 *
 * Moolre webhooks are NOT signed — their docs instead publish the source
 * IPs POS-link callbacks originate from and recommend verifying final
 * state before acting. Defense in depth, in order:
 *   1. Source-IP allowlist (174.138.44.22 per their authentication guide).
 *   2. Dedupe on the gateway transactionid (WebhookEvent.eventId unique).
 *   3. Never trust the payload: fulfillment goes through
 *      processSuccessfulPayment, which re-verifies against the Payment
 *      Status API server-side before granting access.
 *
 * Always answer 200 quickly for accepted deliveries — non-2xx asks
 * Moolre to retry, which we only want when our own processing failed.
 */

interface MoolreCallback {
  status?: number;
  code?: string;
  message?: string;
  data?: {
    txstatus?: number;
    transactionid?: string;
    externalref?: string;
    amount?: string;
    [key: string]: unknown;
  };
}

const ALLOWED_IPS = new Set(
  (process.env.MOOLRE_CALLBACK_IPS ?? "174.138.44.22")
    .split(",")
    .map((ip) => ip.trim())
    .filter(Boolean),
);

function clientIp(req: Request): string {
  const fwd = req.headers.get("x-forwarded-for");
  if (fwd) return fwd.split(",")[0].trim();
  return req.headers.get("x-real-ip")?.trim() ?? "";
}

export async function POST(req: Request) {
  const ip = clientIp(req);
  if (process.env.NODE_ENV === "production" && !ALLOWED_IPS.has(ip)) {
    return NextResponse.json({ error: "Untrusted source." }, { status: 403 });
  }

  let body: MoolreCallback;
  try {
    body = (await req.json()) as MoolreCallback;
  } catch {
    return NextResponse.json({ error: "Invalid JSON." }, { status: 400 });
  }

  const transactionId = body.data?.transactionid;
  const orderRef = body.data?.externalref;
  if (!transactionId || !orderRef) {
    return NextResponse.json(
      { error: "Missing transactionid/externalref." },
      { status: 400 },
    );
  }

  // Idempotency: first delivery of a transactionid wins; replays no-op.
  const inserted = await recordMoolreWebhook(body as object, transactionId);
  if (!inserted) {
    return NextResponse.json({ ok: true, deduplicated: true });
  }

  // Only success callbacks (status=1, txstatus=1) trigger fulfillment;
  // everything else is stored for the admin payment log and acked.
  if (body.status !== 1 || body.data?.txstatus !== 1) {
    return NextResponse.json({ ok: true, ignored: true });
  }

  try {
    await processSuccessfulPayment(orderRef);
    return NextResponse.json({ ok: true });
  } catch (err) {
    // Row stays unprocessed; Moolre's retry + the browser-return verify
    // endpoint both re-run processSuccessfulPayment, which is idempotent.
    console.error("Moolre webhook processing failed", err);
    return NextResponse.json(
      { error: "Processing failed; will retry." },
      { status: 500 },
    );
  }
}
