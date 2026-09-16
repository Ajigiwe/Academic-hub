# Laravel port — Slice 6 + brand redesign — production payments

A working Laravel 12 proof that the Next.js marketplace can be rebuilt on
PHP for shared hosting. Slices 1–5 delivered schema, auth, catalog, the
full purchase flow, the **secure viewer with per-user PDF watermarking**,
the **admin area**, and community features. Slice 6 adds the real
**Paystack provider** (Ghana card + mobile money) alongside the sandbox
mock — same verify-then-fulfill chain, provider-aware webhooks. The UI
now carries the original **Academic Hub brand**: AH-monogram logo, navy
`#0b2d5b` / blue `#2563eb` palette, Fraunces display headings, the hero
+ two-doors homepage, mobile bottom nav, and light/dark theming.

## What's implemented

| Area | Status | Notes |
| --- | --- | --- |
| Schema | ✅ | `programmes`, `courses`, `bundles`, `resources`, `orders`, `order_items`, `payments`, `webhook_events`, `entitlements`, `notifications` mirror the Prisma models. Money is integer **pesewas**, never floats. |
| Seed | ✅ | `PastqSeeder` mirrors `prisma/seed.ts` — same manifest incl. papers, idempotent upserts, same demo accounts. |
| Auth | ✅ | Session login/register/logout, suspended-account check, `admin` middleware (404s non-admins). |
| Catalog | ✅ | Home, searchable `/courses`, bundle detail. Blade + Tailwind (CDN build, dev only). |
| Payments | ✅ | Provider abstraction (`PaymentProvider` interface), sandbox **mock gateway** (hosted-checkout page + signed webhook), and the real **Paystack provider** — `transaction/initialize` at checkout, `transaction/verify/{ref}` as the server-to-server check, HMAC-SHA512 webhook verification. Verify-then-fulfill `PaymentProcessor`, idempotent `OrderFulfiller`. Readiness-aware selection (`PaymentManager::provider()`): a driver that cannot charge never takes orders — outside production the sandbox steps in, in production checkout is refused. Admin → Settings → Payments shows the effective driver, key mode (test/live), and the webhook + callback URLs to paste into the dashboard. |
| Guarantees | ✅ | Webhook signature verified over the RAW body (timing-safe) — **per-provider scheme**: HMAC-SHA256 (`x-pastq-signature`) for the mock, HMAC-SHA512 (`x-paystack-signature`) for Paystack; the endpoint identifies the delivering provider by which header is present, then verifies before parsing. Insert-first `webhook_events` dedupe (`ON CONFLICT DO NOTHING`), shared across providers, with the Paystack event-id format (`paystack:charge.success:<id>`); amount/currency tamper check against the order; fulfillment gated by paid-status guard under lock + unique `(user_id, resource_id)`; browser-return callback re-verifies server-side and is a fallback only; processing failures leave `processed_at` null so Paystack can retry. |
| Library | ✅ | `/library` lists owned papers via the `Entitlements` engine, with **Read** buttons per paper. |
| Viewer | ✅ | `GET /view/{slug}` runs authenticated → entitled → ARCHIVED check → current-file → viewing-session, and renders the reading page. The page embeds a **signed temporary URL** (`viewer.document`, `signed` middleware, 10-min TTL) bound to user + resource + session; the document endpoint re-checks signature, entitlement, foreign-session (403), and session freshness (428) on every fetch, then serves the FPDI/TCPDF **watermarked PDF** (`no-store`). Reading happens **in the page**: `public/js/pdf-reader.js` draws the watermarked PDF to `<canvas>` with vendored PDF.js (see `public/vendor/pdfjs/README.md`), so the browser's own PDF viewer — and its download, print and share menu — never appears, and the paper is readable on Chrome for Android, which blocks in-page PDFs outright. Pages render lazily as they scroll, at device pixel ratio, with page navigation and zoom; the canvas keeps its context menu to itself. Two escapes keep it robust: `GET /view/{slug}/document` (`viewer.open`) re-runs the whole chain and 302s to a freshly signed URL, and `GET /view/{slug}/document-url` returns the same as JSON for the reader's silent renewal when a link lapses mid-session. No JavaScript (or a failed load) leaves the server-rendered *open the document* card in place. The page itself is **immersive** — it declares `@section('immersive')`, which drops the site header and bottom nav, and keeps only a title bar plus the reader toolbar, both sticky — and the **document scrolls vertically with the page** (no nested scroll pane), so a phone swipe, a scroll wheel, keyboard paging and the browser's own scrollbar all behave natively. Where the browser supports it (Chrome for Android, iPadOS, desktop) a control inside the reader takes it *truly* full screen, sitting inside the reader on purpose so an exit is always reachable. |
| Watermark | ✅ | Staggered diagonal identity tiles (name · account · order ref) + footer license line on every page, drawn with a UTF-8 font (Ghanaian names render). Cache key includes the account id — stamped bytes are never shared between users. Original PDFs live in private storage under content-hash sharded keys; the client never receives them. |
| Audit | ✅ | `access_logs` (STARTED_VIEWER / PAGE_VIEWED / ACCESS_DENIED), `viewing_sessions` with IP + UA, `recently_viewed` upsert — all mirroring spec §12/§26/§27. |
| Admin | ✅ | Dashboard metrics; bundles (publish/unpublish with empty-bundle guard, delete blocked once sold — pricing is platform-wide, see Settings); papers (upload PDF → DRAFT, publish guarded on file presence, replace PDF with versioning, delete blocked once owned); orders (search + status filter); students (search, suspend/reactivate — suspension blocks login; admins untouchable). Every destructive action reports its outcome — no silent no-ops. |
| Late uploads | ✅ | `EntitlementBackfill` (port of `grantBundlePapersToPastBuyers`): publishing a paper reaches everyone who already bought the bundle, idempotently. |
| Free materials | ✅ | Bundle-less published resources (`bundle_id null`) served via the **public** download route — guests included. Admin upload form (port of `uploadFreeMaterial`) upserts the course by code, sniffs the PDF, and publishes immediately. Paid papers are structurally excluded from the public route. Safe download filenames (`COURSE-slug.pdf`), best-effort download counter. |
| Platform pricing | ✅ | **One price for every bundle**, set in Admin → Settings (`settings` key `pricing.bundle_price_pesewas`, integer pesewas). `BundlePricing` is the source of truth; `bundles.price_pesewas` is a denormalised mirror re-synced on every save, and checkout reads the service so a stale row can never charge a different price. `order_items.unit_price_pesewas` snapshots the price at sale, so past orders are unaffected by a re-price. |
| Programme settings | ✅ | `settings` key/value table + `ProgrammeSettings` service (port of `src/lib/settings.ts`): missing row = enabled, `programme.<slug>.enabled` convention. Hidden tracks disappear from home/courses/materials, and their bundles + downloads are bare 404s. Admin → Settings toggle page. |
| Notifications | ✅ | The Next.js app created PURCHASE notifications but never displayed them. Here: bell with unread badge in the header, `/notifications` grouped by day with type icons, per-row open (clears the unread mark and follows the row's destination) plus mark-all-read. Copy names what was bought and the amount — built from our own records in `App\Support\NotificationCopy` — and every row carries a relative `url` (a purchase points at its receipt). Created by `OrderFulfiller`/`RefundProcessor` via `App\Services\Notifications`. |
| Favourites | ✅ | Composite-key `favourites` table (Prisma `Favourite` model, unused in Next.js). ☆ toggle on owned papers, saved free materials section in the library, `/favourites` page with per-item actions. |
| Refunds | ✅ | `refund.pending`/`processing`/`processed`/`failed` webhooks, each re-verified against `GET /refund/:id` before anything changes. One `refunds` row per gateway refund (idempotency key), monotonic status, full refunds flip order + payment to `REFUNDED`, revoke the granted entitlements, and notify the student; partial refunds are recorded and keep access. Unmatched refunds are logged and ACKed; unverifiable ones stay open for retry. |
| Tests | ✅ | 146 passing feature tests (1 skipped): payments (11) + Paystack (21) + refunds (17) + provider wiring (11) + viewer (12) + admin (15) + community features (18) + auth/catalog. Paystack tests stub only the HTTP API (container-bound subclass) while signature verification stays fully real HMAC-SHA512; the wiring tests cover selection, the production guard, callback shapes, and the admin card, the Paystack tests cover 400/404 answers, in-flight mobile money, and late-success recovery, and the refund tests cover full/partial/pending/failed deliveries, replay, and revocation. |

## Running it (this machine, Windows)

PHP 8.2 (XAMPP) ships `pdo_pgsql` disabled, so the driver is loaded
project-locally — the global `php.ini` is never touched.

```bash
# one-time setup (from repo root)
php composer.phar install --working-dir=php      # already done
php -d extension=pdo_pgsql artisan migrate:fresh --seed   # already done

# serve (from repo root)
php\serve-dev.cmd        # http://127.0.0.1:8080
```

`serve-dev.cmd` runs `php -d extension=pdo_pgsql -d extension=pgsql -S
0.0.0.0:8080 -t public router.php` — `router.php` streams real static
files (icon.svg, images) directly and hands everything else to Laravel,
which `public/index.php` alone does not do.

Tests (on either database):

```bash
cd php
# MySQL (default)
DB_CONNECTION=mysql DB_DATABASE=pastq_mysql_test php vendor/bin/phpunit
# Postgres
DB_CONNECTION=pgsql DB_DATABASE=pastq_php_test php -d extension=pdo_pgsql vendor/bin/phpunit
```

## Demo accounts (identical to the Next.js app)

| Role    | Email                | Password      |
| ------- | -------------------- | ------------- |
| Admin   | `admin@pastq.test`   | `Admin@12345` |
| Student | `student@pastq.test` | `Student@123` |

## Databases: MySQL (shared-host target) and Postgres

The app runs on **MySQL/MariaDB** (the shared-hosting target) and
Postgres interchangeably — all 51 tests pass on both. Only the `DB_*`
vars in `.env` change:

| | MySQL/MariaDB (default now) | Postgres |
| --- | --- | --- |
| Database | `pastq_mysql` (+ `_test`) | `pastq_php` (+ `pastq_php_test`) |
| Server | XAMPP MariaDB 10.4 on `:3306` | `npm run services:up` on `:5432` |
| PHP driver | `pdo_mysql` (enabled by default) | `pdo_pgsql` (loaded via project ini / `-d` flags) |
| Search | `LIKE` (CI via `utf8mb4_unicode_ci`) | `LIKE` (CI via collation) |

Portability rules that keep this true:

- No `ilike` — case-insensitivity comes from the collation on both DBs.
- No JSON path queries — `webhook_events` carries a denormalized indexed
  `reference` column; the JSON `payload` stays pure audit data.
- Dedupe uses `insertOrIgnore` (`ON CONFLICT` / `INSERT IGNORE` per
  driver) — never a caught unique-violation inside a transaction.

## Deliberate deviations from the Next.js app

- **Sessions/DB vs Next's custom table.** Laravel's DB session driver is
  the equivalent of the hashed-token sessions; revisit hardening
  (rotation, device binding) when payments land.
- **Tailwind via CDN.** Fine for the pattern-proof; wire Vite before
  production.

## The purchase flow

1. Log in as the student → open any bundle → **Buy now**.
2. `POST /checkout` validates ownership/price, creates the order
   (unique `PQ-XXXXXX` reference) and a PENDING payment, then calls
   `initiatePayment` on the configured provider:
   - **mock**: redirects to the sandbox hosted-checkout page.
   - **paystack**: `POST /transaction/initialize` (email, integer
     pesewas amount, reference, mobile_money+card channels, callback)
     and redirects to the returned `authorization_url`.
3. On payment, the gateway delivers a **signed webhook** (mock:
   in-process on this single-worker dev server; Paystack: over the
   network). The endpoint identifies the provider by signature header,
   verifies HMAC over the RAW body, dedupes on the provider's event-id
   format, re-verifies with the SAME provider server-side, checks the
   amount against the order, and fulfills: order → PAID, one
   entitlement per paper in the bundle, purchase notification.
4. The student lands on `/payments/verify/<reference>`, which
   **re-verifies server-side** before showing success, then My Library
   lists the unlocked papers. The reference rides in the PATH because
   Paystack appends its own `?trxref=…&reference=…` to the callback URL;
   `/payments/verify?ref=…` and Paystack's own parameters are still
   accepted.

### Switching providers

```bash
# .env — sandbox only
PAYMENT_PROVIDER=mock
PAYMENT_WEBHOOK_SECRET=<a real random secret>

# .env — Paystack (sk_test_… for test mode, sk_live_… for live)
PAYMENT_PROVIDER=paystack
PAYSTACK_SECRET_KEY=sk_test_xxx
```

Two URLs come out of the app and go into the Paystack dashboard:
**webhook** `https://<your-host>/payments/webhook` and **callback**
`https://<your-host>/payments/verify`. Both are shown, ready to copy, in
**Admin → Settings → Payments**, along with which driver is actually
handling checkout and whether the key is test or live.

Readiness rules — a gateway that cannot charge must never take orders:

- Selecting `paystack` **without** `PAYSTACK_SECRET_KEY` keeps
  non-production environments on the sandbox (checkout still works, and
  the admin card says it is falling back). In `APP_ENV=production` a
  checkout is refused with a clear message and the order is cancelled —
  never a 500, never a free unlock.
- The sandbox is **disabled** in production unless `PAYMENT_PROVIDER=mock`
  is set *and* `PAYMENT_WEBHOOK_SECRET` is a real secret: its shipped
  default is public, so a forged `payment.success` could otherwise
  unlock a bundle for nothing. Both the sandbox pages and its
  approve/fail endpoint 404 in that state.
- The mock sandbox keeps working while `PAYMENT_PROVIDER=paystack`
  outside production — webhook dispatch is scheme-aware, not
  config-bound. No code changes are needed to go live.

Local Paystack checkouts redirect the student's own browser back to
`localhost` fine, but **webhooks need a public URL** — expose the dev
server with a tunnel (or deploy), then paste that host's webhook URL in
the dashboard. Without a webhook the browser-return callback still
verifies server-side with the live key and fulfils, so a purchase
completes either way.

Response handling was checked against the live API, not just docs:
`transaction/verify` answers **`400` `Transaction reference not found`**
(not 404) for an unknown reference, which is treated as a permanent
answer — the student gets a clear failure and a webhook does not spend
days retrying — while `401`/5xx stay errors so a misconfigured key is
loud and deliveries retry. Async mobile money that is still in flight
(`ongoing`, `pending`, `processing`, `queued`) is reported as *pending*:
the payment stays `PENDING`, nothing unlocks, the student is told it is
being confirmed, and the webhook finishes the job. A later success can
also settle a payment row an earlier callback had marked `FAILED`, so an
order can never end up `PAID` beside a failed payment.

### Refunds

Paystack drives refunds (its dashboard or its API); the app reacts to
`refund.pending` / `refund.processing` / `refund.processed` /
`refund.failed` deliveries. Same rules as the charge path:

- The refund is **re-fetched from the gateway** (`GET /refund/:id`) before
  anything changes — the webhook body alone never moves money state. A
  bare transaction id in that object is resolved with `GET
  /transaction/:id`, so a refund can be matched to its order without one.
- The gateway's refund id is the **idempotency key**
  (`refunds` unique on provider + provider_ref): replays, redeliveries,
  and a pending → processed sequence all update one row. Refund status
  only moves forward — a late `refund.processing` cannot demote a
  processed refund.
- Only `PROCESSED` changes anything. A **full** refund flips the order to
  `REFUNDED` and the payment to `REFUNDED`, revokes the entitlements that
  order granted (`status = revoked` + `revoked_at`, so the library, the
  viewer, and the bundle-ownership fallback all close at once), and
  notifies the student. A **partial** refund is recorded as
  `PARTIALLY_REFUNDED` and access stays — papers are not divisible, and
  the student did pay for part of them. Two partials that add up to the
  order amount do revoke.
- A refund that cannot be matched to any order is **acknowledged and
  logged** rather than retried for 72 hours (the money has already moved;
  retrying will not create the order), while a refund that cannot be
  **verified** keeps its event open so Paystack retries.
- Admin → Orders shows the refunded amount and the refund's state on the
  order row.

## The viewer flow

1. My Library → **Read** on an owned paper → `GET /view/{slug}`.
2. The authorization chain runs and a `viewing_sessions` row is created;
   the page embeds a signed URL expiring in 10 minutes.
3. The iframe fetches the document: signature → entitlement →
   foreign-session/freshness checks → FPDI stamps the user's identity
   onto every page → streamed inline with `no-store`.
4. Reload the viewer page for a fresh URL; denials land in
   `access_logs`.

Dev utility: `php artisan demo:generate-pdfs` attaches generated demo
PDFs to every seeded resource — bundle papers **and** free materials
(mirrors `scripts/prepare-demo.ts`).

## Deviations from the Next.js viewer

- **Watermarked whole PDF instead of per-page PNGs.** The Node app
  rasterizes pages with pdf.js + canvas; PHP has no equivalent pipeline
  on shared hosting, so FPDI/TCPDF stamps the watermark into the PDF
  itself. Same threat model (identity is in the document bytes, not a
  DOM overlay), different artifact.
- **One signed URL for the document instead of per-page tokens.**
  Laravel's `signed` middleware + a DB-backed live-session check
  replace the per-page HMAC token mint/verify pair.
- **Local disk now, S3 later.** Keys are already S3-shaped
  (`private/resources/xx/yy/<hash>.bin`); switching to R2/MinIO is a
  flysystem disk swap.

## Remaining gaps vs the Next.js app

- PWA shell + offline caching.

## Next slices

7. Production hardening: session hardening, Vite asset build, cPanel
   deployment guide.
