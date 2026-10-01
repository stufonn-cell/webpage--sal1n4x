# Security policy

## Scope

PsiClinic handles sensitive health data. This setup is meant to run on a
controlled computer or server behind HTTPS, never exposed directly to the
internet over plain HTTP.

## Before using it with real data

1. Change every demo password.
2. Generate your own `APP_KEY` with `php bin/console key`. It keys the HMACs
   used for rate-limit counters, session fingerprints and stored IPs.
3. Set `APP_ENV=production` and `APP_DEBUG=false` (`docker-compose.prod.yml`
   forces both).
4. Put an HTTPS proxy in front of Nginx. It must send `X-Forwarded-For` (real
   client IP, used by the rate limits) and `X-Forwarded-Proto: https` (turns on
   HSTS and the `Secure` session cookie). If the proxy does not reach Nginx from
   a private network, add its address to `set_real_ip_from` in
   `docker/nginx/default.conf`.
5. Keep `SESSION_SECURE` unset or `true` (it defaults to `true` in production).
6. Do not publish the MySQL or Adminer ports outside `localhost` (the local
   compose file binds them to `127.0.0.1`).
7. Keep the rate limits on (`RATE_LIMIT_ENABLED=true`).
8. Schedule backups of the `mysql_data` volume and of `backend/storage`.

## Controls in place

### Excessive requests and brute force

Two layers, both per client IP (the real one, resolved by Nginx's `real_ip`
module from trusted private networks only; PHP never reads `X-Forwarded-For`).

**Nginx** (`docker/nginx/default.conf`), the cheap first line:

| Zone | Applies to | Limit |
| --- | --- | --- |
| `psiclinic_api` | every `/api/` request | 20 requests/s, bursts of 60 |
| `psiclinic_login` | `POST /api/auth/login` | 20 requests/min, bursts of 10 |
| `psiclinic_conn` | the whole site | 30 simultaneous connections |

Refused requests get `429` with a JSON body in the API's error format
(`{"error":{"message":...}}`) and `Retry-After`. Request bodies are capped at
2 MB (11 MB for `POST /api/documents`), and slow clients are cut off by
header, body and send timeouts. Errors produced by Nginx itself (413, 429,
502-504) also answer in JSON.

**Application** (`src/Core/RateLimiter.php`, middleware `Throttle`): counters
in MySQL (table `rate_limits`, migration `005_security.sql`) because PHP-FPM
workers share no memory. Sliding window (two fixed windows, the previous one
weighted), every attempt counts, counters are stored under an HMAC (no IPs or
user ids in clear) and expired rows are deleted opportunistically. IPv6
clients are grouped by /64. If the table is unavailable the limiter fails open
and logs it; Nginx still applies its limits.

| Profile | Applies to | Default | Variable |
| --- | --- | --- | --- |
| `api` | every API route (also unknown ones), per IP | 600 / 60 s | `RATE_LIMIT_API` |
| `login` | `POST /api/auth/login`, per IP | 10 / 60 s | `RATE_LIMIT_LOGIN` |
| `public_form` | `POST /api/public/appointment-requests`, per IP | 10 / hour | `RATE_LIMIT_PUBLIC_FORM` |
| `search` | `GET /api/search`, per user | 60 / 60 s | `RATE_LIMIT_SEARCH` |
| `icd11` | `GET /api/icd11`, per user | 60 / 60 s | `RATE_LIMIT_ICD11` |
| `upload` | `POST /api/documents`, per user | 30 / 10 min | `RATE_LIMIT_UPLOAD` |
| `rips_send` | `POST /api/rips/{id}/send`, per user | 6 / 10 min | `RATE_LIMIT_RIPS_SEND` |
| `profile` | `PUT /api/profile` (password changes), per user | 20 / 10 min | `RATE_LIMIT_PROFILE` |

Values are `<requests>/<seconds>`; `off` disables one profile and
`RATE_LIMIT_ENABLED=false` disables all of them. An unreadable value falls
back to the default. The answer is `429` with `Retry-After` and a plain
message ("... Please wait about 40 seconds and try again.").

On top of that, sign-in keeps its own lockout: `LOGIN_MAX_ATTEMPTS` failures
per account (and 4x that per IP) within `LOGIN_LOCKOUT_SECONDS` lock further
attempts. Failed attempts live in the database, so deleting the cookie does
not reset them. The public form also keeps its honeypot field and its limit
of 3 accepted requests per hour per connection.

The test suite runs with the limiter off (`APP_ENV=testing`); dedicated tests
switch it on (`tests/Feature/SecurityTest.php`, `tests/Unit/RateLimitTest.php`).

### SQL injection

- Every query is a real prepared statement (`PDO::ATTR_EMULATE_PREPARES =>
  false`) with bound values; multi-statements are off and errors raise
  exceptions.
- `Database::insert/update/delete` only accept table and column names that
  match `^[a-z_][a-z0-9_]*$`, quote them with backticks and refuse anything
  else with an exception. Updates use positional placeholders and a separate
  `:where_id`, so a key named `id` can never change the target row.
- The only SQL built in code goes through helpers: `Database::limit()` and
  `Database::offset()` (integers, clamped, no overflow on huge page numbers)
  and `Database::like()` (escapes `%`, `_` and `\`, so wildcards typed in a
  search box match literally). Filters (`status`, `instrument`...) are matched
  against whitelists or bound.
- SQL errors never reach the client, not even with `APP_DEBUG` on locally.

### Authentication and sessions

- bcrypt with an explicit cost (`PASSWORD_BCRYPT_COST`, 12 by default); older
  hashes are upgraded at the next sign-in. New passwords: 10 characters to
  72 bytes (bcrypt ignores anything longer).
- One message for unknown users, inactive accounts and wrong passwords, and
  the same hashing work in every case, so neither the answer nor its timing
  reveals which accounts exist.
- Session cookie: `HttpOnly`, `SameSite=Strict`, `Secure` over HTTPS and in
  production, with the `__Host-` prefix when secure. `use_strict_mode`,
  cookies only, no session ids in URLs.
- New session id at sign-in, at a role change and after a password change;
  the CSRF token rotates at sign-in.
- Idle timeout (`SESSION_LIFETIME`, 2 h) and absolute timeout
  (`SESSION_ABSOLUTE_LIFETIME`, 12 h).
- Each session is tied to the account's current password hash: changing the
  password ends every other session, and deactivating an account ends its
  sessions on their next request.

### CSRF

Every write route requires the `X-CSRF-Token` header (compared with
`hash_equals`); a test checks the whole route table. Writes that the browser
marks as cross-site (`Sec-Fetch-Site`) are refused even with a token. The
`_method` override only works from a real POST.

### Access control

- Middleware per route: staff, patient, administrator. A test checks that only
  the four public routes work without a session and that administrative routes
  require an administrator.
- Patients only reach their own consents, documents and questionnaires (the id
  in the URL is always checked against the patient in the session).
- Only the author of a session note, or an administrator, can edit or sign it.
- Route ids only match digits.
- No mass assignment: every insert and update lists its columns explicitly.

### Input

- Text is capped at its column size, dates must be real `YYYY-MM-DD` dates,
  numbers must be finite, amounts are clamped to their column range, lists are
  refused where text is expected, and every id that points to another record
  (patient, professional, appointment) is checked to exist and to belong to the
  right person. Invalid input answers 422 with the field, never 500.
- Incoming text is normalised to valid UTF-8 without NUL bytes.
- JSON bodies: 1 MB at most (`API_MAX_JSON_BYTES`), 16 levels deep at most;
  malformed JSON answers 400.

### Files

- Type detected from the content (`finfo`), never from the browser: PDF, PNG,
  JPG, plain text and Word only (no SVG or HTML). The file name's extension
  must agree with that type, and images must decode as images.
- Stored outside the web root (`backend/storage/uploads`) under a random name
  with an extension chosen by the server, permissions `0640`; Nginx only sends
  `/api/` to PHP and always runs `public/index.php`, so nothing uploaded can be
  executed.
- Downloads: `Content-Disposition: attachment`, `nosniff`, a sandboxed CSP,
  `no-store`, and only after checking that the document belongs to the
  patient in the session (or that the user is staff).

### Output and headers

- The API answers JSON with `application/json`, `Cache-Control: no-store`, and
  `<`, `>` and `&` escaped inside strings.
- Signatures are rebuilt on the server from coordinates; stored markup is
  sanitised again on the way out.
- Nginx: CSP without inline scripts or styles (`default-src 'none'` for the
  API), `frame-ancestors 'none'`, `X-Frame-Options`, `X-Content-Type-Options`,
  `Referrer-Policy: same-origin`, `Permissions-Policy`, `Cross-Origin-Opener-
  Policy` and `Cross-Origin-Resource-Policy: same-origin`, HSTS over HTTPS,
  `X-Robots-Tag: noindex` for the app, the portal and the API.
- `server_tokens off`, `expose_php = Off` and `X-Powered-By` removed.
- Dotfiles, backups, source maps and server-side files answer 404.

### Outbound calls (SSRF)

The RIPS validator address only accepts `http(s)` without credentials, and is
checked again right before every call: hosts that resolve to link-local,
cloud metadata (169.254.169.254, `metadata.google.internal`,
`fd00:ec2::254`...), multicast, broadcast or `0.0.0.0` are refused. Loopback
and private networks stay allowed because the Ministry's validator runs as a
Docker API inside the practice (`https://localhost:9443` by default).
SISPRO credentials are used for that request only and never stored.

### Errors and logs

`display_errors` is off; errors go to the container log only, without argument
values in stack traces. Every logged value is cleaned so text from a request
can never forge a log line. `allow_url_fopen` is off and shell functions are
disabled.

### Infrastructure

- PHP-FPM runs as an unprivileged user with a bounded pool, a request timeout
  and `security.limit_extensions = .php`.
- Containers run with `no-new-privileges`; production logs are capped in size.
- Audit log of access to and changes in clinical data; appointment-request IPs
  are stored as an HMAC.

## Reporting vulnerabilities

Open a private issue at https://github.com/stufonn-cell.
