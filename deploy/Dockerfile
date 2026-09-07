# Academic Hub — production image (Next.js standalone server)
#
# Build:  docker build -f deploy/Dockerfile -t academic-hub .
# Run:    see deploy/docker-compose.app.yml (preferred)

# ── Stage 1: install dependencies ────────────────────────────────────
FROM node:22-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json ./
# Runs postinstall (prisma generate); needs the schema present.
COPY prisma ./prisma
RUN npm ci

# ── Stage 2: build ───────────────────────────────────────────────────
FROM node:22-alpine AS builder
WORKDIR /app
COPY --from=deps /app/node_modules ./node_modules
COPY . .
ENV NEXT_TELEMETRY_DISABLED=1
# The build does not need a live database (pages are force-dynamic).
RUN npm run build

# ── Stage 3: run ─────────────────────────────────────────────────────
FROM node:22-alpine AS runner
WORKDIR /app
ENV NODE_ENV=production
ENV NEXT_TELEMETRY_DISABLED=1
ENV PORT=3000
ENV HOSTNAME=0.0.0.0

# Standalone output bundles traced node_modules (incl. pdfjs-dist,
# @napi-rs/canvas via serverExternalPackages).
COPY --from=builder /app/.next/standalone ./
COPY --from=builder /app/.next/static ./.next/static
COPY --from=builder /app/public ./public

EXPOSE 3000
CMD ["node", "server.js"]