# Migrating to version 2.0

What changed when the backend was split from the frontend, what was kept, what
was fixed and what is still pending.

## Summary

| Before (1.x) | Now (2.0) |
|---|---|
| PHP rendered HTML with templates (`views/`) | PHP exposes a JSON API under `/api` |
| Loose CSS and JS in `public/assets` | React + TypeScript SPA (`frontend/`) built with Vite |
| No public website: `/` redirected to sign-in | Public website with appointment requests |
| Forms with page reloads and flash messages | Forms with live validation, per-field errors and notices |
| PHP code at the repository root | PHP code in `backend/` |

## What was kept from the backend

- The whole `Domain` layer: patients, schedule and clash detection, notes,
  instruments and their scoring, consents, documents, billing, metrics and the
  audit log. The SQL queries did not change.
- The `001_schema.sql` schema and the installer with demo data.
- The router, validation, the cookie-based session and the per-session CSRF
  token.
- The roles and the per-route permission matrix.
- The custom test suite (no PHPUnit), now extended.

## What changed in the backend (and why)

| Change | Reason |
|---|---|
| Controllers return JSON; `View`, `views/`, `Icons` and `Chart` were removed | Presentation now lives in the frontend |
| `HttpException` and a uniform JSON error response | Human messages and per-field errors for the forms |
| CSRF in the `X-CSRF-Token` header, rotated at sign-in | Standard pattern for SPAs; prevents token fixation |
| Sign-in attempts in the `login_attempts` table, per user and per IP | They used to live in the session: deleting the cookie reset the counter |
| Sign-out after inactivity (`SESSION_LIFETIME`) | Clinical records left open on shared computers |
| Catalog values restricted with `oneOf` | A value outside the `ENUM` caused a 500 error |
| Editing an appointment now validates the same way as creating one | It did not validate before |
| `meeting_url` only accepts `http(s)` | Prevents `javascript:` links shown to the patient |
| Signing a note: author or admin only, and only once | The signature certifies authorship |
| Questionnaire answers validated against the scale | Any integer used to be accepted |
| Passwords: at least 10 characters, and the current one is required to change it | Account hardening |
| SVG removed from the allowed document types | An SVG can contain code |
| Validation messages rewritten in a warm, plain tone | Quality of the text people read |

## Vulnerabilities fixed

1. **Stored XSS in consent signatures.** The SVG sent by the browser was stored
   and printed unescaped in the staff view. Now the browser sends coordinates,
   the server builds the SVG and also sanitizes it when returning it, and the
   frontend shows it as an `<img>`.
2. **IDOR in consents.** A patient could see and sign another person's consent
   by changing the number in the URL.
3. **XSS in global search.** Results were inserted with `innerHTML`.
4. **Questionnaires could be resubmitted.** An answered questionnaire could be
   sent again and overwrite the result.
5. **Demo accounts visible in production.** They are now only listed with
   `APP_ENV=local`.
6. **File name in the download header.** It is now encoded to prevent header
   injection.

All of them have a regression test in `backend/tests/Feature/ApiTest.php`.

## Database

`002_public_site.sql` (idempotent, applied with `php bin/console migrate`):

- `appointment_requests`: requests from the public website. The IP is stored as
  an HMAC with `APP_KEY`, not in plain text.
- `login_attempts`: failed sign-in attempts.
- `users.show_on_site` and `users.public_bio`: optional public profile.
- Normalizes intervention names in existing notes to the catalog spelling.

New settings keys: `clinic_about`, `whatsapp_number`, `crisis_line`.

Severity labels are stored with each result, so results saved earlier keep the
label they had when they were scored. Consents already generated keep their
original text.

Version 2.1 adds `003_icd11_rips.sql` (ICD-11 catalog, RIPS reports and the
related patient, diagnosis and staff columns). See
[ARCHITECTURE.md](ARCHITECTURE.md) and the [CHANGELOG](../CHANGELOG.md).

## New features

- Full public website and a three-step appointment request, with an inbox for
  staff and conversion into a patient file.
- Optional public profile for each professional.
- "Get help now" notice with a configurable crisis line.
- Optional WhatsApp link.
- Privacy page.
- Password change from the profile.
- Audit log filter and readable descriptions of each action.
- Shortcuts: write the note from a completed appointment, create the patient
  file from a request.

## Pending or recommended next

| Topic | Details |
|---|---|
| Prerendering the public website | Today it is an SPA: modern search engines run JS, but a static HTML home page would improve SEO and first paint. Options: `vite-plugin-ssr`/`react-router` in framework mode, or generating `index.html` with the public data at build time |
| Open Graph image | `og:image` points to the SVG logo; several networks require a 1200×630 PNG/JPG |
| Real photos | The design uses a line illustration; if the practice has its own photos of the office or the team, add a photo field to the public profile |
| Notifications | Email staff when a request arrives and remind patients of appointments. Requires SMTP (the `MAIL_*` variables already exist) and a queue or cron |
| Calendar | Export appointments as `.ics` or sync with Google/Outlook. Suggested endpoint: `GET /api/appointments/{id}.ics` |
| Password recovery | Today the practice resets it. Requires outgoing email and a table of single-use tokens |
| Two-factor authentication | Recommended for staff accounts |
| End-to-end tests | Playwright on the critical flows (sign-in, note, signature, request) |
| Dependency updates | React Router 7, Vite 7 and TypeScript 5.9 were pinned for stability; evaluate the next major versions on a separate branch |
| Portal catalog labels | The portal has its own labels because `/api/meta` is staff only; a public subset could be exposed |
