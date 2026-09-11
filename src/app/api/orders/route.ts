import { prisma } from "@/lib/db";
import { requireUser } from "@/lib/auth";
import { createOrderSchema } from "@/lib/validation";
import { getPaymentProvider } from "@/lib/payments";
import { getAppOrigin } from "@/lib/app-url";
import { NextResponse } from "next/server";

function generateOrderReference(): string {
  const alphabet = "ABCDEFGHJKMNPQRSTUVWXYZ23456789"; // no ambiguous chars
  let ref = "PQ-";
  for (let i = 0; i < 6; i++) {
    ref += alphabet[Math.floor(Math.random() * alphabet.length)];
  }
  return ref;
}

export async function POST(req: Request) {
  const { user, error } = await requireUser();
  if (error) return error;

  let body: unknown;
  try {
    body = await req.json();
  } catch {
    return NextResponse.json({ error: "Invalid JSON body." }, { status: 400 });
  }

  const parsed = createOrderSchema.safeParse(body);
  if (!parsed.success) {
    return NextResponse.json(
      { error: parsed.error.issues[0]?.message ?? "Invalid input." },
      { status: 400 },
    );
  }

  const bundle = await prisma.bundle.findUnique({
    where: { id: parsed.data.bundleId },
    include: {
      resources: { where: { status: "PUBLISHED" }, select: { id: true } },
    },
  });
  if (!bundle || bundle.status !== "PUBLISHED") {
    return NextResponse.json(
      { error: "This bundle is not available for purchase." },
      { status: 400 },
    );
  }

  // Block re-purchase only when the student already owns every paper in
  // the bundle — a partial owner pays once and unlocks the rest.
  if (bundle.resources.length > 0) {
    const owned = await prisma.entitlement.count({
      where: {
        userId: user.id,
        resourceId: { in: bundle.resources.map((r) => r.id) },
        status: "active",
      },
    });
    if (owned === bundle.resources.length) {
      return NextResponse.json(
        { error: "You already own every paper in this bundle — see My Library." },
        { status: 409 },
      );
    }
  }

  const amountPesewas = bundle.pricePesewas;

  // Fail fast on invalid email — Paystack rejects bad addresses at /transaction/initialize.
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(user.email)) {
    return NextResponse.json(
      { error: "Your email address is invalid. Please update your profile and try again." },
      { status: 400 },
    );
  }

  // Unique-reference retry loop.
  let reference: string | null = null;
  for (let attempt = 0; attempt < 5; attempt++) {
    const candidate = generateOrderReference();
    const clash = await prisma.order.findUnique({ where: { reference: candidate } });
    if (!clash) {
      reference = candidate;
      break;
    }
  }
  if (!reference) {
    return NextResponse.json(
      { error: "Could not allocate an order reference. Please retry." },
      { status: 500 },
    );
  }

  const order = await prisma.order.create({
    data: {
      reference,
      userId: user.id,
      amountPesewas,
      items: {
        create: {
          bundleId: bundle.id,
          unitPricePesewas: bundle.pricePesewas,
        },
      },
    },
    include: { items: true },
  });

  // Initiate payment through the abstraction layer.
  const provider = getPaymentProvider();
  // Single-origin app: the request's own origin is always the correct
  // base for callbacks/redirects (dev ports, previews, production).
  const appUrl = getAppOrigin(req);
  const callbackUrl = `${appUrl}/api/payments/verify?ref=${encodeURIComponent(order.reference)}`;

  try {
    const initiated = await provider.initiatePayment({
      orderRef: order.reference,
      amountPesewas: order.amountPesewas,
      currency: order.currency,
      customerEmail: user.email,
      callbackUrl,
      origin: appUrl,
    });

    await prisma.payment.create({
      data: {
        orderId: order.id,
        provider: provider.name,
        providerRef: initiated.providerRef,
        reference: order.reference,
        channel: initiated.channel,
        amountPesewas: order.amountPesewas,
        currency: order.currency,
      },
    });

    return NextResponse.json(
      { reference: order.reference, redirectUrl: initiated.redirectUrl },
      { status: 201 },
    );
  } catch (err) {
    await prisma.order.update({
      where: { id: order.id },
      data: { status: "CANCELLED" },
    });
    console.error("Payment initiation failed", err);
    const msg =
      err instanceof Error && err.message.includes("Invalid Email")
        ? "Your account email is invalid. Please update your profile and try again."
        : "Could not start the payment. Please try again.";
    return NextResponse.json({ error: msg }, { status: 502 });
  }
}