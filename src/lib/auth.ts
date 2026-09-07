import { prisma } from "@/lib/db";
import { createHash, randomBytes, timingSafeEqual } from "crypto";
import { cookies } from "next/headers";
import { cache } from "react";
import bcrypt from "bcryptjs";

const SESSION_COOKIE = "pastq_session";
const SESSION_TTL_MS = 1000 * 60 * 60 * 24 * 30; // 30 days

export interface SessionUser {
  id: string;
  email: string;
  firstName: string;
  lastName: string;
  role: "STUDENT" | "ADMIN";
}

function hashToken(token: string): string {
  return createHash("sha256").update(token).digest("hex");
}

export function safeEqual(a: string, b: string): boolean {
  const bufA = Buffer.from(a);
  const bufB = Buffer.from(b);
  if (bufA.length !== bufB.length) return false;
  return timingSafeEqual(bufA, bufB);
}

export async function hashPassword(password: string): Promise<string> {
  return bcrypt.hash(password, 12);
}

export async function verifyPassword(
  password: string,
  passwordHash: string,
): Promise<boolean> {
  return bcrypt.compare(password, passwordHash);
}

/** Creates a DB-backed session and sets the httpOnly cookie. */
export async function createSession(
  userId: string,
  meta?: { userAgent?: string; ipAddress?: string },
): Promise<void> {
  const token = randomBytes(32).toString("base64url");
  const expiresAt = new Date(Date.now() + SESSION_TTL_MS);

  await prisma.session.create({
    data: {
      userId,
      tokenHash: hashToken(token),
      expiresAt,
      userAgent: meta?.userAgent ?? null,
      ipAddress: meta?.ipAddress ?? null,
    },
  });

  const cookieStore = await cookies();
  cookieStore.set(SESSION_COOKIE, token, {
    httpOnly: true,
    sameSite: "lax",
    secure: process.env.NODE_ENV === "production",
    expires: expiresAt,
    path: "/",
  });
}

export async function destroySession(): Promise<void> {
  const cookieStore = await cookies();
  const token = cookieStore.get(SESSION_COOKIE)?.value;
  if (token) {
    await prisma.session.deleteMany({ where: { tokenHash: hashToken(token) } });
  }
  cookieStore.delete(SESSION_COOKIE);
}

/**
 * Returns the current user, or null.
 * - React `cache` dedupes per-request; the cookie read and the DB hit
 *   happen once per render even if called from many server components.
 * - In non-RSC contexts (route handlers) `cache` is a passthrough.
 */
export const getCurrentUser = cache(async (): Promise<SessionUser | null> => {
  const cookieStore = await cookies();
  const token = cookieStore.get(SESSION_COOKIE)?.value;
  if (!token) return null;

  const session = await prisma.session.findUnique({
    where: { tokenHash: hashToken(token) },
    include: { user: true },
  });

  if (!session) return null;
  if (session.expiresAt < new Date()) {
    await prisma.session
      .delete({ where: { id: session.id } })
      .catch(() => undefined);
    return null;
  }

  if (session.user.status === "SUSPENDED") return null;

  return {
    id: session.user.id,
    email: session.user.email,
    firstName: session.user.firstName,
    lastName: session.user.lastName,
    role: session.user.role,
  };
});

/** Route-handler guard. Returns the user or a Response ready to return. */
export async function requireUser(): Promise<
  { user: SessionUser; error?: never } | { user?: never; error: Response }
> {
  const user = await getCurrentUser();
  if (!user) {
    return {
      error: Response.json(
        { error: "Authentication required." },
        { status: 401 },
      ),
    };
  }
  return { user };
}

export async function requireAdmin(): Promise<
  { user: SessionUser; error?: never } | { user?: never; error: Response }
> {
  const result = await requireUser();
  if (result.error) return result;
  if (result.user.role !== "ADMIN") {
    return {
      error: Response.json({ error: "Forbidden." }, { status: 403 }),
    };
  }
  return result;
}
