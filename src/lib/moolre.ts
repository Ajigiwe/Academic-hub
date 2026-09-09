import { prisma } from "@/lib/db";
import type { PaymentProvider } from "@/lib/payments";

/**
 * Moolre payment provider (docs.moolre.com).
 *
 * Flow: our order ref is passed as `externalref` to the hosted payment
 * link API; verification queries their Payment Status API by the same
 * externalref. Moolre amounts are decimal major-unit strings (e.g.
 * "12.50"); our DB stores pesewas — conversion happens only here.
 *
 * Credentials: X-API-USER + X-API-PUBKEY headers, plus the merchant
 * accountnumber in each body (dashboard → developer tools).
 */

const API_BASE = process.env.MOOLRE_API_BASE ?? "https://api.moolre.com";

function credentials(): { user: string; pubkey: string; account: string } {
  const user = process.env.MOOLRE_API_USER ?? "";
  const pubkey = process.env.MOOLRE_API_PUBKEY ?? "";
  const account = process.env.MOOLRE_ACCOUNT_NUMBER ?? "";
  if (!user || !pubkey || !account) {
    throw new Error(
      "Moolre is not configured: set MOOLRE_API_USER, MOOLRE_API_PUBKEY and MOOLRE_ACCOUNT_NUMBER.",
    );
  }
  return { user, pubkey, account };
}

async function moolrePost<T>(path: string, body: Record<string, unknown>): Promise<T> {
  const { user, pubkey } = credentials();
  const res = await fetch(`${API_BASE}${path}`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-API-USER": user,
      "X-API-PUBKEY": pubkey,
    },
    body: JSON.stringify(body),
    cache: "no-store",
  });
  const json = (await res.json().catch(() => null)) as
    | (T & { status?: number; message?: string })
    | null;
  if (!res.ok || !json || json.status === 0) {
    throw new Error(
      `Moolre ${path} failed: ${json?.message ?? `HTTP ${res.status}`}`,
    );
  }
  return json;
}

/** Pesewas → "12.50". Moolre rejects more than two decimal places. */
function toMajor(pesewas: number): string {
  return (pesewas / 100).toFixed(2);
}

interface PaymentLinkResponse {
  status: number;
  data: { authorization_url: string; reference: string };
}

interface PaymentStatusResponse {
  status: number;
  data: {
    // 1 = successful (docs: "txstatus" on Payment Status API)
    txstatus: number;
    amount: string;
    transactionid: string;
    externalref: string;
    currency?: string;
  };
}

export const moolreProvider: PaymentProvider = {
  name: "moolre",

  async initiatePayment({ orderRef, amountPesewas, currency, customerEmail, callbackUrl, origin }) {
    // `callback` = server-to-server webhook, `redirect` = browser return.
    // Both land back in our existing pipeline; the redirect points at
    // the same verify endpoint the mock/paystack providers use.
    const link = await moolrePost<PaymentLinkResponse>("/embed/link", {
      type: 1,
      amount: toMajor(amountPesewas),
      currency,
      externalref: orderRef,
      reusable: "0",
      expiration_time: 60,
      // Required by /embed/link — we pass the paying student's email.
      email: customerEmail,
      callback: `${origin.replace(/\/$/, "")}/api/payments/moolre/webhook`,
      redirect: callbackUrl,
      accountnumber: credentials().account,
      metadata: { orderRef },
    });

    return {
      redirectUrl: link.data.authorization_url,
      // Moolre echoes a gateway reference; keep ours authoritative as
      // fallback so providerRef is never null in our Payment row.
      providerRef: link.data.reference || `moolre_${orderRef}`,
      channel: "MOBILE_MONEY" as const,
    };
  },

  async verifyPayment(orderRef) {
    const status = await moolrePost<PaymentStatusResponse>("/open/transact/status", {
      type: 1,
      idtype: 1, // 1 = our unique externalref
      id: orderRef,
      accountnumber: credentials().account,
    });

    const d = status.data;
    if (d?.txstatus !== 1) {
      return {
        successful: false,
        providerRef: d?.transactionid || `moolre_${orderRef}`,
        channel: "MOBILE_MONEY" as const,
        amountPesewas: 0,
        currency: "GHS",
        failureReason:
          d?.txstatus === 0
            ? "Payment pending or not found at Moolre."
            : `Moolre txstatus ${d?.txstatus ?? "unknown"}.`,
      };
    }

    return {
      successful: true,
      providerRef: d.transactionid || `moolre_${orderRef}`,
      channel: "MOBILE_MONEY" as const,
      // Re-derive pesewas from the gateway's own amount string; the
      // tamper-check in payment-processing compares this to the order.
      amountPesewas: Math.round(parseFloat(d.amount) * 100),
      currency: d.currency || "GHS",
    };
  },
};

/**
 * Record a raw Moolre webhook, deduped on the gateway transactionid
 * (their callbacks carry no separate event id). Returns the stored row,
 * or null when this transaction was already delivered (replay).
 */
export async function recordMoolreWebhook(
  payload: object,
  transactionId: string,
): Promise<{ id: string } | null> {
  return prisma.webhookEvent
    .create({
      data: {
        provider: "moolre",
        eventId: transactionId,
        type: "payment.callback",
        payload,
      },
      select: { id: true },
    })
    .catch(() => null);
}
