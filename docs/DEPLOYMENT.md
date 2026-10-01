# Deployment

The project uses two compose files: `docker-compose.yml` for the local
environment and `docker-compose.prod.yml` for the server. They are independent
files, not an override, so no development volume can overwrite the code that
ships inside the image.

---

## Differences between environments

| Aspect | Local | Production |
|---|---|---|
| Backend | `backend/` mounted as a volume | copied into the `app` image |
| Frontend | Vite on 5173 with hot reload + `frontend/dist` on 8080 | compiled into the `web` image (Nginx) |
| Dockerfile stage | `development` | `production` |
| Web port | `0.0.0.0:8080` | `127.0.0.1:8080` behind the proxy |
| OPcache | revalidates files | fixed cache, no revalidation |
| Adminer | published on 8081 | not started |
| MySQL port | published on 3307 | not published |
| `APP_DEBUG` | `true` | `false` |
| `SESSION_SECURE` | `false` | `true` |
| Restart policy | `unless-stopped` | `always` |

---

## Pre-deployment checklist

1. `APP_ENV=production` and `APP_DEBUG=false`.
2. `APP_KEY` generated with `php bin/console key`.
3. Database passwords different from the ones in `.env.example`.
4. Demo passwords changed or demo accounts deleted.
5. `SESSION_SECURE=true` and a TLS certificate in front of Nginx.
6. `APP_URL` set to the real domain.
7. Scheduled backup of the `mysql_data` volume.
8. Test suite passing: `php bin/test`.
9. If you report RIPS: fill in *Settings → RIPS* (NIT, REPS provider code,
   service code, environment and validator URL) and add each professional's ID
   document.

---

## First deployment

```bash
git clone https://github.com/stufonn-cell/psiclinic.git
cd psiclinic

cp .env.example .env
nano .env                      # adjust following the checklist

docker compose -f docker-compose.prod.yml up -d --build
docker compose exec app php bin/console migrate
```

In production you run `migrate`, not `install`: demo data must not be loaded.
`migrate` also loads the ICD-11 catalog from `backend/database/data` the first
time.

Create the first administrator account:

```bash
docker compose exec app php -r '
require "/var/www/html/src/autoload.php";
PsiClinic\Core\Env::load("/var/www/html/.env");
PsiClinic\Core\Database::insert("users", [
    "uuid" => uuid(),
    "username" => "admin",
    "email" => "admin@yourdomain.com",
    "password_hash" => password_hash("CHANGE-THIS-PASSWORD", PASSWORD_DEFAULT),
    "full_name" => "Administrator",
    "role" => "admin",
]);
echo "Account created", PHP_EOL;
'
```

---

## Updates

```bash
git pull
docker compose -f docker-compose.prod.yml up -d --build
docker compose exec app php bin/console migrate
```

Migrations are idempotent: running them again on an existing database does not
duplicate tables or constraints.

---

## HTTPS

The simplest way is to put a reverse proxy in front:

```nginx
server {
    listen 443 ssl http2;
    server_name psiclinic.yourdomain.com;

    ssl_certificate     /etc/letsencrypt/live/psiclinic.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/psiclinic.yourdomain.com/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host              $host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

With the proxy running, set `SESSION_SECURE=true` and restart `app`.

The Nginx in the `web` container takes the visitor's real IP from
`X-Forwarded-For` (only when the request comes from a private network, such as
the proxy). This matters: the limits on sign-in attempts and appointment
requests are counted per IP. If the proxy does not forward that header, every
visitor would seem to come from the same address.

---

## Backups

```bash
# Daily dump
docker compose exec -T mysql mysqldump \
  -u root -p"$DB_ROOT_PASSWORD" --single-transaction psiclinic \
  | gzip > "backup-$(date +%F).sql.gz"

# Uploaded files
tar czf "uploads-$(date +%F).tar.gz" backend/storage/uploads
```

Restore:

```bash
gunzip < backup-2026-08-01.sql.gz \
  | docker compose exec -T mysql mysql -u root -p"$DB_ROOT_PASSWORD" psiclinic
```

---

## Continuous integration

`.github/workflows/ci.yml` runs on every push and pull request to `main`:

1. `php -l` over the whole backend and the test suite against MySQL 8.4.
2. Frontend: `npm ci`, type check, Vitest and build.
3. Build of the two production images (`app` and `web`).

---

## Moving to PostgreSQL

All data access goes through PDO, so the change is limited to three places:

1. `Database::connection()`: change the DSN to `pgsql:`.
2. `database/migrations/*.sql`: replace `ENUM` with `VARCHAR` plus `CHECK`,
   `AUTO_INCREMENT` with `GENERATED ALWAYS AS IDENTITY` and `JSON` with
   `JSONB`. The `information_schema` checks in `003_icd11_rips.sql` also need
   PostgreSQL syntax.
3. `Settings::put()` and `Installer`: change `ON DUPLICATE KEY UPDATE` to
   `ON CONFLICT (setting_key) DO UPDATE SET`.

The rest of the domain uses standard SQL except `DATE_FORMAT`, `DATE_SUB` and
`FIELD` in `Metrics` and `DashboardController`, which have direct equivalents
in PostgreSQL.

---

Made by Salinas — [github.com/stufonn-cell](https://github.com/stufonn-cell)
