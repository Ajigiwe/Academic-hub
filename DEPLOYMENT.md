# Deployment — Shared cPanel Hosting

The app is a standard Laravel 12 project. It needs no Node build (views use
the Tailwind CDN and inline scripts), so deployment is: **composer install
--no-dev → upload → MySQL env → migrate → cache**.

Requirements: PHP **8.2+** with `pdo_mysql`, `mbstring`, `openssl`, `ctype`,
`fileinfo`, `tokenizer`, `xml`, `curl`, `dom`. All are enabled by default on
most cPanel hosts — check in cPanel → *Select PHP Version* → *Extensions*.

---

## 1. Prepare the build locally

From the project root (this machine: `C:\xampp\htdocs\Academic-hub-main`):

```bash
# Install production-only dependencies (strips phpunit, faker, sail…)
composer install --no-dev --optimize-autoloader --no-interaction

# Clear anything cached from local dev
php artisan config:clear && php artisan route:clear && php artisan view:clear
```

Do **not** upload your local `.env` — the server gets its own (step 4).
Do upload `storage/` (your paid PDFs live in `storage/app/private/resources/`).

## 2. Upload the project

Two layouts work on shared cPanel — pick based on how much control you have
over the document root.

### Option A — Document root can point at `/public` (addon domains, some VPSes)

In cPanel → *Domains* → (addon domain) → set the document root to the
project's `public/` folder directly. Nothing to edit — Laravel's `public/index.php`
already resolves `../vendor` and `../bootstrap`.

Upload the whole project (including `public/`) to e.g. `/home/CPUSER/apps/academic-hub`,
then point the domain's docroot at `/home/CPUSER/apps/academic-hub/public`.

### Option B — Fixed `public_html` (most shared hosts)

The framework must not be web-accessible, so split it:

```
/home/CPUSER/academic-hub/          ← everything EXCEPT the contents of public/
/home/CPUSER/public_html/           ← the CONTENTS of public/ (index.php, sw.js, manifest.json, icons/…)
```

1. Upload the project root to `/home/CPUSER/academic-hub/` (rename your
   existing `public/` folder on your machine to something else first, or just
   move its files after uploading).
2. Move the **contents** of `academic-hub/public/` into `public_html/`.
3. Edit `public_html/index.php` — the two `__DIR__` paths now point up two
   levels into the app folder. Replace:

   ```php
   require __DIR__.'/../vendor/autoload.php';
   ...
   $app->handleRequest(Request::capture());
   ```

   with (adjust `CPUSER` to your cPanel username):

   ```php
   require __DIR__.'/../academic-hub/vendor/autoload.php';
   ...
   $app->handleRequest(Request::capture());
   ```

   i.e. every `__DIR__.'/../x` becomes `__DIR__.'/../academic-hub/x`.

Upload with cPanel *File Manager* (zip + extract) or SFTP. **Never** commit
`vendor/` from a `--dev` install; the server copy must come from step 1.

## 3. Create the database (cPanel → *MySQL® Databases*)

1. Create a database, e.g. `cpuser_academichub`.
2. Create a DB user with a strong password.
3. **Add User to Database → ALL PRIVILEGES.**

cPanel prefixes both names with your username — use the full prefixed names
in `.env` (`cpuser_academichub`, not `academichub`).

## 4. Environment file

Upload [`env.production.example`](env.production.example) as `.env` in the
**app root** (`/home/CPUSER/academic-hub/.env` — not in `public_html/`), then
fill in the values:

- `APP_URL=https://yourdomain.com` — must be the exact public URL, HTTPS.
- `DB_*` — the cPanel database from step 3.
- `APP_KEY` — generate on the server (step 6) or paste your local key.
- `PAYSTACK_SECRET_KEY` — the **live** key for production.

## 5. Permissions

In File Manager or SSH, from the app root:

```bash
chmod -R 755 storage bootstrap/cache
# if you get 500 errors with suPHP/CGI, use 750 or 775 instead
```

## 6. First run (cPanel Terminal or SSH)

```bash
cd ~/academic-hub

# generate the app key (if .env APP_KEY is empty)
php artisan key:generate

# build the schema (fresh MySQL — SQLite data does not carry over,
# so re-seed demo/test accounts as needed)
php artisan migrate --force
php artisan db:seed --force        # omit for an empty production DB

# prime the caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

`SESSION_DRIVER=database` uses the `sessions` table created by the migrations
— nothing extra to set up.

## 7. HTTPS + PWA

- cPanel → *SSL/TLS Status* → run **AutoSSL** (Let's Encrypt) for the domain.
  Service workers and the install prompt **require HTTPS** — the PWA features
  will not work on plain HTTP.
- `sw.js`, `manifest.json` and `icons/` are inside `public/` so they end up in
  `public_html/` and are served at the site root — already handled by the
  layout's `<link rel="manifest">` and the inline SW registration.

## 8. Payments

Paystack webhook (server-to-server, CSRF-exempt) is at:

```
POST https://yourdomain.com/payments/webhook
```

Set this in the Paystack dashboard → *Settings → API Keys & Webhooks*. It must
be the live URL once you switch `PAYSTACK_SECRET_KEY` to the live key. Ensure
`APP_URL` matches, so payment return redirects land on the right host.

## 9. Updating a deployment

1. Repeat step 1 locally (or run it on the server if it has Composer + SSH).
2. Upload changed files (or the whole tree, excluding `.env`).
3. On the server:
   ```bash
   php artisan migrate --force   # only if there are new migrations
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
4. If PHP OPcache is enabled and changes don't appear, hit
   `https://yourdomain.com/opcache-reset.php?token=YOUR_TOKEN` (set the token
   inside the file first — see the comment at its top — then delete the file
   if you don't want it around).

## Troubleshooting

| Symptom | Fix |
|---|---|
| 500 error, no page | `storage/` or `bootstrap/cache/` not writable (step 5); check `storage/logs/laravel.log` |
| 404 on every page | `public_html/.htaccess` missing (it comes from `public/.htaccess` — re-upload it), or `mod_rewrite` off → ask host / use *MultiViews off* |
| Styles/scripts 404 | Files from `public/` were not moved into `public_html/` (Option B, step 2) |
| "No application encryption key" | Run `php artisan key:generate` with the server `.env` |
| Manifest/SW not working | Site not on HTTPS yet — finish AutoSSL (step 7) |
| Old code still runs | OPcache — use `opcache-reset.php` or ask the host to restart PHP |
