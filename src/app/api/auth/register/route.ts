import { prisma } from "@/lib/db";
import { createSession, hashPassword } from "@/lib/auth";
import { requestMetaFrom } from "@/lib/access-log";
import { registerSchema } from "@/lib/validation";
import { NextResponse } from "next/server";

export async function POST(req: Request) {
  let body: unknown;
  try {
    body = await req.json();
  } catch {
    return NextResponse.json({ error: "Invalid JSON body." }, { status: 400 });
  }

  const parsed = registerSchema.safeParse(body);
  if (!parsed.success) {
    return NextResponse.json(
      { error: parsed.error.issues[0]?.message ?? "Invalid input." },
      { status: 400 },
    );
  }

  const { email, password, firstName, lastName, phone } = parsed.data;
  const normalizedEmail = email.toLowerCase().trim();

  const existing = await prisma.user.findUnique({ where: { email: normalizedEmail } });
  if (existing) {
    return NextResponse.json(
      { error: "An account with this email already exists." },
      { status: 409 },
    );
  }

  const user = await prisma.user.create({
    data: {
      email: normalizedEmail,
      passwordHash: await hashPassword(password),
      firstName: firstName.trim(),
      lastName: lastName.trim(),
      phone: phone ?? null,
    },
    select: { id: true },
  });

  await createSession(user.id, requestMetaFrom(req));
  return NextResponse.json({ ok: true }, { status: 201 });
}
