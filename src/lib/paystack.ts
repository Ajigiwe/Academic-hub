import { createHmac, timingSafeEqual } from "crypto";
import type {
  InitiatedPayment,
  PaymentProvider,
  VerifiedPayment,
} from "@/lib/payments";

const API_URL = "https://api.paystack.co";

function secretKey(): string {
  const value = process.env.PAYSTACK_SECRET_KEY?.trim();
  if (!value) {
    throw new Error("Paystack is not configured: set PAYSTACK_SECRET_KEY.");
  }
  return value;
}

function channel(value: unknown): "MOBILE_MONEY" | "CARD" {
  return value === "card" ? "CARD" : "MOBILE_MONEY";
}

async function paystackRequest<T>(path: string, init: RequestInit): Promise<T> {
  const response = await fetch(`${API_URL}${path}`, {
    ...init,
    headers: {
      Authorization: `Bearer ${secretKey()}`,
      "Content-Type": "application/json",
      ...(init.headers ?? {}),
    },
    cache: "no-store",
  });
  const body = (await response.json().catch(() => null)) as
    | (T & { message?: string })
    | null;

  if (!response.ok || !body) {
    throw new Error(
      `Paystack ${path} failed: ${(body as { message?: string } | null)?.message ?? `HTTP ${response.status}`}`,
    );
  }
  return body;
}

interface InitializeResponse {
  status: boolean;
  message: string;
  data: {
    authorization_url: string;
    access_code: string;
    reference: string;
  };
}

interface VerifyResponse {
  status: boolean;
  message: string;
  data?: {
    status: string;
    reference: string;
    amount: number;
    currency: string;
    channel: string;
  };
}

/** Paystack adapter for Ghana card and mobile-money checkout. */
export const paystackProvider: PaymentProvider = {
  name: "paystack",

  async initiatePayment({
    orderRef,
    amountPesewas,
    currency,
    customerEmail,
    callbackUrl,
  }): Promise<InitiatedPayment> {
    if (!Number.isInteger(amountPesewas) || amountPesewas <= 0) {
      throw new Error("Payment amount must be a positive integer in pesewas.");
    }

    const response = await paystackRequest<InitializeResponse>(
      "/transaction/initialize",
      {
        method: "POST",
        body: JSON.stringify({
          email: customerEmail,
          amount: amountPesewas,
          currency,
          reference: orderRef,
          channels: ["mobile_money", "card"],
          callback_url: callbackUrl,
          metadata: {
            orderRef,
          },
        }),
      },
    );

    if (!response.status || !response.data?.authorization_url) {
      throw new Error(response.message || "Paystack rejected the payment.");
    }

    return {
      redirectUrl: response.data.authorization_url,
      providerRef: response.data.reference || orderRef,
      channel: "MOBILE_MONEY",
    };
  },

  async verifyPayment(orderRef): Promise<VerifiedPayment> {
    const response = await paystackRequest<VerifyResponse>(
      `/transaction/verify/${encodeURIComponent(orderRef)}`,
      { method: "GET" },
    );
    const payment = response.data;

    if (!response.status || !payment || payment.status !== "success") {
      return {
        successful: false,
        providerRef: payment?.reference || orderRef,
        channel: channel(payment?.channel),
        amountPesewas: 0,
        currency: payment?.currency || "GHS",
        failureReason: "Paystack did not report a successful payment.",
      };
    }

    return {
      successful: true,
      providerRef: payment.reference,
      channel: channel(payment.channel),
      amountPesewas: payment.amount,
      currency: payment.currency,
    };
  },
};

/** Verify Paystack's HMAC-SHA512 signature over the raw webhook body. */
export function verifyPaystackSignature(rawBody: string, signature: string): boolean {
  const key = process.env.PAYSTACK_SECRET_KEY?.trim();
  if (!key || !signature) return false;

  const expected = createHmac("sha512", key).update(rawBody).digest("hex");
  const expectedBuffer = Buffer.from(expected, "utf8");
  const providedBuffer = Buffer.from(signature, "utf8");
  return (
    expectedBuffer.length === providedBuffer.length &&
    timingSafeEqual(expectedBuffer, providedBuffer)
  );
}

export function paystackEventId(event: string, data: { id?: unknown; reference?: unknown }): string {
  const gatewayId = data.id ?? data.reference;
  return `paystack:${event}:${String(gatewayId ?? "unknown")}`;
}
