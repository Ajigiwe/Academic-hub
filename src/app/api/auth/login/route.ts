import { prisma } from "@/lib/db";
import { createSession, verifyPassword } from "@/lib/auth";
import { requestMetaFrom } from "@/lib/access-log";
import { loginSchema } from "@/lib/validation";
import { NextResponse } from "next/server";

export async function POST(req: Request) {
  let body: unknown;
  try {
    body = await req.json();
  } catch {
    return NextResponse.json({ error: "Invalid JSON body." }, { status: 400 });
  }

  const parsed = loginSchema.safeParse(body);
  if (!parsed.success) {
    return NextResponse.json(
      { error: "Please provide a valid email and password." },
      { status: 400 },
    );
  }

  const { email, password } = parsed.data;
  const user = await prisma.user.findUnique({
    where: { email: email.toLowerCase().trim() },
  });

  // Generic error — never reveal whether the account exists (user enumeration).
  const invalid = NextResponse.json(
    { error: "Invalid email or password." },
    { status: 401 },
  );
  if (!user) return invalid;
  if (!(await verifyPassword(password, user.passwordHash))) return invalid;
  if (user.status === "SUSPENDED") {
    return NextResponse.json(
      { error: "This account has been suspended. Contact support." },
      { status: 403 },
    );
  }

  await createSession(user.id, requestMetaFrom(req));
  return NextResponse.json({ ok: true, role: user.role });
}
