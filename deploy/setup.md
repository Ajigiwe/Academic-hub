# Deploy Academic Hub on your Contabo box

Targets the exact layout already running on your VPS (nginx on the host
terminating TLS in front of Docker services), so this app slots in as a
second project — it never touches the podiumclass stack.

```
nginx (80/443, certbot TLS)
  ├── podiumclass.online        → podium-app container (127.0.0.1:3000)
  ├── storage.podiumclass.online→ MinIO (9000)
  └── academic.yourdomain.com   → academic-hub container (127.0.0.1:3001)   ← new
```

## 0. Prereqs (one-time, ~5 min)

1. **DNS**: add an `A` record `academic.yourdomain.com` → your box's IP.
   (Replace `academic.yourdomain.com` everywhere below.)
2. **MinIO**: open `minio-console.podiumclass.online` → create bucket
   **`pastq`** → create an access key (Access Key ID + Secret). Or use
   Cloudflare R2 instead (see `.env.production.example`).
3. **Get the code onto the box** (either):
   ```bash
   git clone https://github.com/Ajigiwe/Academic-hub.git /srv/academic-hub
   ```
   or pull into an existing clone and `cd /srv/academic-hub`.

## 1. Env

```bash
cd /srv/academic-hub
cp deploy/.env.production.example deploy/.env.production
nano deploy/.env.production      # fill PG_PASSWORD, MinIO/R2 keys, PAGE_TOKEN_SECRET
# Compose reads ${PG_PASSWORD} for interpolation from the project-dir .env:
cp deploy/.env.production deploy/.env
chmod 600 deploy/.env.production deploy/.env
```

## 2. Start Postgres + the app

```bash
docker compose -f deploy/docker-compose.app.yml up -d --build
docker compose -f deploy/docker-compose.app.yml ps   # both healthy?
```

## 3. One-time data setup (host-side, against local Postgres)

The standalone image ships only production deps, so migrations/seed run
from the host with the repo's dev deps:

```bash
cd /srv/academic-hub
npm ci
export DATABASE_URL="postgresql://pastq:<PG_PASSWORD>@127.0.0.1:5433/pastq"
npx prisma migrate deploy
npx tsx prisma/seed.ts
# optional: create the demo bundle + paid order (needs the storage env vars)
S3_ENDPOINT=https://storage.podiumclass.online S3_REGION=us-east-1 \
S3_ACCESS_KEY_ID=... S3_SECRET_ACCESS_KEY=... S3_BUCKET=pastq \
npx tsx scripts/prepare-demo.ts
```

## 4. nginx + TLS

```bash
sudo cp deploy/nginx-academic.conf /etc/nginx/sites-available/academic
# edit /etc/nginx/sites-available/academic → server_name academic.yourdomain.com
sudo ln -s /etc/nginx/sites-available/academic /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d academic.yourdomain.com
```

## 5. Verify

- `https://academic.yourdomain.com` loads (admin: `admin@pastq.test / Admin@12345`).
- Log in → **Admin → Resources** → bulk-upload real PDFs (bodies up to
  100 MB now work — nginx and Next are both configured for it).
- Student: `student@pastq.test / Student@123` → My Library → open a paper.

## Updating (deploy new code)

```bash
cd /srv/academic-hub
git pull
docker compose -f deploy/docker-compose.app.yml up -d --build
# only when prisma/schema.prisma changed:
export DATABASE_URL="postgresql://pastq:<PG_PASSWORD>@127.0.0.1:5433/pastq"
npx prisma migrate deploy
```

## Backups (recommended)

Nightly Postgres dump via cron:

```bash
crontab -e
# 2am daily: dump + keep 7 days
0 2 * * * docker compose -f /srv/academic-hub/deploy/docker-compose.app.yml exec -T postgres \
  pg_dump -U pastq pastq | gzip > /var/backups/academic-$(date +\%F).sql.gz \
  && find /var/backups -name 'academic-*.sql.gz' -mtime +7 -delete
```

## Troubleshooting

| Symptom | Fix |
|---|---|
| `413 Request Entity Too Large` | `client_max_body_size` not applied — reload nginx after editing the vhost |
| Viewer errors / blank pages | Check the container includes native deps: `docker compose -f deploy/docker-compose.app.yml exec app ls node_modules/@napi-rs/canvas` |
| Page 500 on first load | Migrations not run — repeat step 3 |
| Uploads slow | Normal for big scans; `proxy_read_timeout 120s` is set; raise `NODE_OPTIONS` heap in compose if you hit memory errors |