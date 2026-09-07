# Past Question Marketplace (PWA)

A student-focused PWA for discovering, purchasing, and securely reading academic
past questions. Built for a single Ghanaian institution (MVP), designed to grow
into a multi-institution marketplace.

**Product spec:** see `design(1).md` — this codebase implements Sprint 1–5
foundations of its roadmap: auth, catalog + search, checkout with a payment
abstraction, entitlements, a secure viewer shell, admin, and the PWA base.

## Stack

- **Next.js 16** (App Router, TypeScript, Tailwind v4) — frontend + API
- **PostgreSQL 16** via **Prisma 6**
- **Private S3-compatible storage** (MinIO in dev; S3/R2 in production)
- **Payment abstraction layer** with a built-in sandbox gateway (real
  providers like Paystack plug into the same interface)

## Demo deployment (Vercel + Neon + R2)

The cheapest way to hand someone a working link: **Vercel** (free Hobby
plan) runs the Next.js app, **Neon** (free) is the Postgres, and
**Cloudflare R2** (free, S3-compatible) holds the private PDFs. The repo
is structured for exactly this — set the env vars below and deploy.

1. Push this repo to GitHub, then at vercel.com → **Add New Project** →
   import it. Framework auto-detects as Next.js; leave defaults.
2. Add the environment variables (Project Settings → Environment
   Variables) — copy values from `.env.example`:

   | Variable | Value for the demo |
   | --- | --- |
   | `DATABASE_URL` | your Neon connection string (`?sslmode=require`) |
   | `S3_ENDPOINT` | `https://<account-id>.r2.cloudflarestorage.com` |
   | `S3_REGION` | `auto` |
   | `S3_ACCESS_KEY_ID` / `S3_SECRET_ACCESS_KEY` | R2 API token (Object Read & Write) |
   | `S3_BUCKET` | `pastq-private` — **create this bucket first** in the R2 dashboard |
   | `APP_ORIGIN` | `https://<your-app>.vercel.app` (no trailing slash) |
   | `PAGE_TOKEN_SECRET` | a long random string (`openssl rand -hex 32`) |
   | `PAYMENT_PROVIDER` | leave unset / `mock` — sandbox checkout, no keys |

3. Deploy. Then run the one-time data setup **once**, with a `.env` file
   pointing `DATABASE_URL` at the Neon database (never commit it):

   ```bash
   npx prisma migrate deploy   # create the schema on the hosted DB
   npm run db:seed             # demo accounts + courses
   npx tsx --env-file=.env scripts/prepare-demo.ts
   ```

   `prepare-demo.ts` hides the seeded placeholder bundles (they have no
   PDF behind them), publishes one fully working demo bundle of sample
   papers through the real upload pipeline, and books a **paid** demo
   order for the student — so the link works end-to-end on first load.
4. Share `https://<your-app>.vercel.app`:

   | Role | Email | Password |
   | --- | --- | --- |
   | Admin | `admin@pastq.test` | `Admin@12345` |
   | Student | `student@pastq.test` | `Student@123` |

   Student demo path: **My Library** → open a paper → the secure viewer.
   Checkout uses the sandbox gateway — approve on the mock page and the
   order is fulfilled (server-side verification, entitlement grant).

Notes: the upload/viewer pipeline uses native packages (`pdfjs-dist`,
`@napi-rs/canvas`, `pdf-lib`) that run on Vercel's Node functions; the
first request after a cold start is slower. The sandbox mock gateway is
serverless-safe (its success state is persisted in the DB). When real
providers sign webhooks, set `PAYMENT_WEBHOOK_SECRET`.

## Getting started

Requires Node 20+. No Docker — Postgres and MinIO run as portable,
terminal-managed binaries via `scripts/services.mjs` (first run auto-downloads
nothing except the MinIO server, which lives in `services/minio/`).

```bash
npm install

# 1. Start Postgres + MinIO (initdb on first run; creates the private bucket)
npm run services:up

# 2. Create the schema and seed demo data
npm run db:migrate     # name it e.g. "init"
npm run db:seed

# 3. Run the app
npm run dev            # http://localhost:3000
```

Stop everything with `npm run services:down`; check state with
`npm run services:status`. MinIO console (dev only): http://localhost:9001
(`pastq-dev-key` / `pastq-dev-secret`).

### Demo accounts (from seed)

| Role    | Email                | Password      |
| ------- | -------------------- | ------------- |
| Admin   | `admin@pastq.test`   | `Admin@12345` |
| Student | `student@pastq.test` | `Student@123` |

### Buying something (sandbox)

Log in as the student → open any bundle → **Buy Now** → the sandbox
"Mobile Money" page lets you approve, fail, or cancel. Approving fires a signed
webhook → server-side verification → entitlement grant → the resource appears
in **My Library** → open the secure viewer.

## How the security model is wired

- Documents live only in **private object storage**; no public file URLs, ever.
  Storage keys are random, sharded, and content-disposition `inline`.
- Every viewer path runs the chain: **authenticated → entitled → viewing
  session → render**, all server-side (`src/lib/entitlements.ts` is the single
  source of truth).
- Payments: the frontend's "success" is never trusted. Fulfillment happens only
  after **server-to-server verification** (webhook with HMAC signature +
  `webhook_events` dedupe table for idempotency; the gateway-return callback is
  a resilient fallback that also re-verifies). Order fulfillment is idempotent,
  and the `(user, resource)` unique constraint prevents duplicate entitlements.
- All money is integer pesewas. Sessions are hashed tokens in the DB with
  httpOnly cookies. Authorization is always server-side (spec §14/§31).

## Project layout

```
prisma/schema.prisma      # domain model
src/lib/                  # db, auth, entitlements, payments, storage, validation
src/app/api/              # REST endpoints (auth, orders, payments, viewer)
src/app/(auth)/           # login / register
src/app/resources/        # catalog + detail pages
src/app/checkout, payment # checkout + gateway result flows
src/app/viewer/           # secure viewer
src/app/library, account  # student area
src/app/admin/            # admin area (dashboard, resources, orders, students)
public/sw.js              # PWA service worker (offline shell)
```

## Roadmap (from the spec)

1. **Next:** replace the sandbox gateway with a real provider (Paystack),
   PWA caching sprint + offline reading, favourites, reviews, coupons.
2. Later: multi-seller marketplace (spec §41), native apps (spec §40).

## Conventions

- Never store or compute money as floats.
- Never trust the client for authorization or payment state.
- Access-control decisions belong in `src/lib/entitlements.ts`, not pages.
