/**
 * Public origin for absolute URLs built inside route handlers.
 *
 * Next.js reconstructs `req.url` from the internal server address
 * (e.g. http://0.0.0.0:3000), ignoring the Host the reverse proxy
 * forwarded — so `new URL(req.url).origin` produces dead redirects
 * behind nginx/Docker. Trust the proxy headers instead, with the raw
 * req.url origin as a last-resort fallback for direct dev access.
 */
export function getAppOrigin(req: Request): string {
  const forwardedHost =
    req.headers.get("x-forwarded-host") ?? req.headers.get("host");
  if (forwardedHost) {
    const proto =
      req.headers.get("x-forwarded-proto") ??
      (forwardedHost.startsWith("localhost") || forwardedHost.startsWith("127.0.0.1")
        ? "http"
        : "https");
    return `${proto}://${forwardedHost}`;
  }
  return new URL(req.url).origin;
}
