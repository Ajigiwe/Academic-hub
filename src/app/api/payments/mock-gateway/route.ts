import { NextResponse } from "next/server";
import { signPayload } from "@/lib/payments";
import { getAppOrigin } from "@/lib/app-url";

/**
 * Mock gateway "server". Simulates what a real provider does:
 * receives the student's pay/cancel action, verifies server-side, and
 * sends a signed webhook. Replaced entirely when a real provider is
 * configured — no product code depends on this existing.
 */
export async function POST(req: Request) {
  const { ref, outcome } = (await req.json()) as {
    ref?: string;
    outcome?: "success" | "failure" | "cancel";
  };
  if (!ref || !outcome) {
    return NextResponse.json({ error: "Missing ref or outcome." }, { status: 400 });
  }

  const appUrl = getAppOrigin(req);
  const callbackBase =
    process.env.NEXT_PUBLIC_PAYMENT_RETURN_URL ?? `${appUrl}/api/payments/verify`;

  if (outcome === "cancel") {
    return NextResponse.json({
      redirectUrl: `${appUrl}/payment/result?status=cancelled&ref=${ref}`,
    });
  }

  const success = outcome === "success";
  const eventId = `evt_mock_${ref}_${outcome}_${Date.now()}`;

  // Fire the signed webhook (server-to-server), as a real gateway would.
  const payload = JSON.stringify({
    event: success ? "payment.success" : "payment.failed",
    eventId,
    reference: ref,
  });
  const signature = signPayload(payload);
  const webhookUrl = new URL("/api/payments/webhook", appUrl);

  try {
    const res = await fetch(webhookUrl, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "x-pastq-signature": signature,
      },
      body: payload,
    });
    if (!res.ok) {
      console.error("Mock webhook delivery failed", res.status);
    }
  } catch (err) {
    console.error("Mock webhook delivery error", err);
  }

  return NextResponse.json({
    redirectUrl: success
      ? `${callbackBase}${callbackBase.includes("?") ? "&" : "?"}ref=${encodeURIComponent(ref)}`
      : `${appUrl}/payment/result?status=failed&ref=${ref}`,
  });
}
