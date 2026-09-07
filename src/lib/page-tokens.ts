import { createHmac, timingSafeEqual } from "crypto";

/**
 * Short-lived page tokens (spec §13): every page-image request must carry
 * an HMAC-signed token minted by the server. Tokens bind user + file +
 * page + viewing session, expire quickly, and are verified on every
 * request — so page URLs can't be replayed, shared, or swapped between
 * accounts even if they leak.
 */

const SECRET = process.env.PAGE_TOKEN_SECRET ?? process.env.NEXTAUTH_SECRET ?? "";

export const PAGE_TOKEN_TTL_SECONDS = 120;

function key(): string {
  // Dev fallback keeps local dev frictionless; production must set the env.
  return SECRET || "dev-only-page-token-secret";
}

export interface PageTokenPayload {
  userId: string;
  fileId: string;
  page: number;
  sessionId: string;
  exp: number; // epoch seconds
}

export function mintPageToken(payload: Omit<PageTokenPayload, "exp">): string {
  const full: PageTokenPayload = {
    ...payload,
    exp: Math.floor(Date.now() / 1000) + PAGE_TOKEN_TTL_SECONDS,
  };
  const body = Buffer.from(JSON.stringify(full)).toString("base64url");
  const sig = createHmac("sha256", key()).update(body).digest("base64url");
  return `${body}.${sig}`;
}

export function verifyPageToken(
  token: string | null,
): PageTokenPayload | null {
  if (!token) return null;
  const dot = token.lastIndexOf(".");
  if (dot <= 0) return null;
  const body = token.slice(0, dot);
  const sig = token.slice(dot + 1);

  const expected = createHmac("sha256", key()).update(body).digest();
  const actual = Buffer.from(sig, "base64url");
  if (expected.length !== actual.length || !timingSafeEqual(expected, actual)) {
    return null;
  }

  try {
    const payload = JSON.parse(
      Buffer.from(body, "base64url").toString("utf8"),
    ) as PageTokenPayload;
    if (!payload.exp || payload.exp < Math.floor(Date.now() / 1000)) {
      return null;
    }
    return payload;
  } catch {
    return null;
  }
}
