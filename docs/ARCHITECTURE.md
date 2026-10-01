# Architecture

Reference document for understanding and extending the code.

## Overview

```
Browser
   │  same origin (HttpOnly session cookie + X-CSRF-Token header)
   ▼
Nginx ──────────────► frontend/dist        (SPA: website, app, portal)
   │
   └── /api/* ──────► PHP-FPM → backend/public/index.php
                         Router → Middlewares → Controller → Domain → JSON
                                                                │
                                                              MySQL
```

### Why this split

- **The PHP backend is kept.** The clinical logic (instrument scoring, schedule
  clashes, note signing, audit log) was already tested. Rewriting it in another
  language would have added risk with no benefit for users.
- **The frontend moves to React.** The interface holds a lot of state
  (multi-step forms, tabs, search, signatures, questionnaires) and benefits from
  reusable components and strict typing.
- **Same origin.** Nginx serves both parts, so there is no need for CORS or for
  tokens in `localStorage`: the session lives in an `HttpOnly` cookie.

## Backend

### Principles

1. **No magic.** No dependency container and no ORM.
2. **Explicit queries.** SQL lives in `Domain`.
3. **JSON only.** There are no views: every controller returns data.
4. **Expected errors as exceptions.** `HttpException` carries the HTTP status,
   a message written for people and per-field errors.
5. **Nothing sensitive leaves through the API.** `Support/Present` filters
   columns (for example, it never returns `password_hash`).

### Layers

| Layer | Responsibility |
|---|---|
| `public/index.php` | Bootstrap, headers, catching `HttpException` (JSON) and any other error (generic 500, details only in the log) |
| `src/Core` | `Router` (404/405 as JSON), `Request` (JSON body), `Session` (inactivity timeout), `Auth` (failed attempts in the database), `Csrf` (rotation at sign-in), `Validator`, `HttpException` |
| `src/Core/Middleware` | `VerifyCsrf` (`X-CSRF-Token` header), `Authenticate` (401), `RequireStaff`, `RequireAdmin`, `RequirePatient` (403) |
| `src/Controllers` | Validate input, restrict catalog values (`oneOf`) and call the domain |
| `src/Domain` | Rules and queries. Catalog constants are the source of the labels the interface shows (`/api/meta`) |
| `src/Support` | `Present` (serialization), `Signature` (builds the signature SVG from coordinates), `Installer`, `MuvClient` (HTTP client for the Ministry's MUV Docker API) |

### Response format

```json
{ "data": { ... }, "message": "Optional text to show" }
{ "error": { "message": "Text for the person", "fields": { "email": "..." } } }
```

Status codes in use: `200`, `201`, `401`, `403`, `404`, `405`, `409` (conflict:
schedule clash, note already signed…), `419` (CSRF), `422` (validation), `429`
(too many attempts), `500`.

### ICD-11 coding

- `Domain/Icd11` holds the WHO ICD-11 MMS 2025-01 catalog (adopted in Colombia
  by Resolution 1442 of 2024) in the `icd11_codes` table. The installer loads it
  from `backend/database/data/icd11-2025-01.tsv` and the ICD-10 equivalences
  from `backend/database/data/icd11-to-icd10.tsv` when you run
  `php bin/console install` or `php bin/console migrate`.
- `GET /api/icd11` searches by code or by words. The diagnosis form uses it to
  pick a code.
- Each ICD-11 code stores its WHO ICD-10 equivalent. A diagnosis saves both
  (`code` and `icd10_code`): dual coding during the transition, because RIPS
  still asks for ICD-10. DSM-5 and ICD-10 diagnoses remain available, and one
  diagnosis per patient can be marked as primary (`is_primary`).
- The WHO data is licensed under CC BY-ND 3.0 IGO (see `NOTICE`).

### RIPS reports

RIPS (Colombia, Resolution 2275 of 2023) is the individual record of health
services that providers report to the Ministry of Health.

- `Domain/Rips` builds "RIPS without invoice" reports (`tipoNota` `RS`) from the
  completed appointments in a period. The JSON field names (`codSexo`,
  `numDocumentoIdentificacion`, …) are fixed by the Ministry and are not
  translated.
- A preview lists every appointment in the period and the data each one is
  still missing (the patient's document and RIPS fields, an active diagnosis
  with its ICD-10 equivalent, the professional's ID document), plus anything
  missing in *Settings → RIPS*. Only complete appointments go into a report.
- `rips_reports` keeps each report's JSON exactly as generated, its status
  (`generated`, `validated` with its CUV, `rejected`) and the validator's
  response. `rips_report_items` links each appointment to a single report, so
  an appointment is never reported twice. A report that was not validated can
  be deleted to generate it again.
- Administrators can download the JSON for the Ministry's local validator, or
  send it to the Ministry's MUV Docker API through `Support/MuvClient`. The
  SISPRO credentials are used for that request only and are never stored.
- *Settings → RIPS* holds the reporter NIT, REPS provider code, service code
  (default `344` Psychology), default purpose and cause codes, first note
  number, environment and validator URL.

### Database migrations

| File | Content |
|---|---|
| `001_schema.sql` | Base schema |
| `002_public_site.sql` | Appointment requests, sign-in attempts and public profiles |
| `003_icd11_rips.sql` | `icd11_codes`, `rips_reports`, `rips_report_items`; diagnosis columns `icd10_code` and `is_primary` (and `icd11` in the `system` enum); patient RIPS columns `biological_sex`, `rips_user_type`, `residence_country`, `residence_municipality`, `residence_zone` and `origin_country`; staff `document_type` and `document_number`. It also maps values stored in Spanish by earlier versions (document categories, consent template codes, note interventions) to their English codes |

Every migration is idempotent.

## Frontend

### Structure

| Folder | Content |
|---|---|
| `styles/tokens.css` | Single source of colors, typography, spacing, radii, shadows, motion and breakpoints, in light and dark |
| `styles/base.css` | Reset, visible focus, skip link to the main content, layout utilities, fade-in animation, print |
| `components/ui` | Button, Field, Badge, Panel, PageHeader, EmptyState, Tabs, Toast, confirmation dialog, loading states, pagination, icons |
| `components/charts` | SVG line, bar and distribution charts, with an accessible summary |
| `components/forms` | Signature (Pointer Events), Likert questionnaire, consent document |
| `site/` | Public website. Editorial copy lives in `site/content.ts` |
| `app/` | Staff application; one folder per module in `app/pages` |
| `portal/` | Patient portal |
| `session/` | Session (user, CSRF, sign-out on 401) and theme |
| `lib/` | API client, `en-US` formatting (COP currency), catalogs, types |

### Routes

| Area | Routes |
|---|---|
| Public website | `/`, `/request-appointment`, `/privacy`, `/login` |
| Staff | `/app`, `/app/requests`, `/app/patients`, `/app/schedule`, `/app/notes`, `/app/assessments`, `/app/consents`, `/app/documents`, `/app/billing`, `/app/rips`, `/app/settings`, `/app/settings/users`, `/app/settings/audit`, `/app/profile` |
| Patient portal | `/portal`, `/portal/appointments`, `/portal/questionnaires`, `/portal/documents`, `/portal/consents/:id`, `/portal/profile` |

### Decisions

- **Three bundles.** The public website does not download the clinical code,
  and a patient does not download the staff code (`React.lazy` per area).
- **Data with TanStack Query.** Consistent caching, retries and loading states.
  A 4xx error is not retried.
- **Forms without a library.** `hooks/useForm` handles values, client-side
  errors and the API's `422` responses, and focuses the first field with an
  error.
- **No animation libraries.** CSS transitions (`opacity` + `transform`) and an
  `IntersectionObserver` for fade-in on scroll. All of it turns off with
  `prefers-reduced-motion`.
- **Self-hosted fonts.** Newsreader and Source Sans 3 are served from the same
  domain (`@fontsource`): no third-party requests.
- **Nothing personal in `localStorage`.** Only the theme preference is stored.
  Appointment request data never travels in the URL (router state is used).

## Security

| Risk | Control |
|---|---|
| CSRF | Per-session token in a header; rotates at sign-in; automatic retry after a 419 |
| XSS | React escapes all output; the signature is rebuilt on the server and shown as an `<img>`; CSP `script-src 'self'` without `unsafe-inline` |
| IDOR | Consents, documents and questionnaires are filtered by the patient in the session |
| Brute force | Failed attempts stored in the database, per user and per IP |
| Forgotten session | Sign-out after `SESSION_LIFETIME` seconds without activity |
| Cached data | `Cache-Control: no-store` on every API response |
| Exposure | `Present` filters columns; staff rates and emails are not public |
| Third-party credentials | SISPRO credentials for RIPS are sent to the validator and never stored |
| Errors | Technical details are never shown; they go to the server log |

## Extension points

**Add an instrument**: edit `backend/src/Domain/instrument_catalog.php`. The API
(`/api/instruments`), the form, the portal and the charts pick it up
automatically.

**Add a module**

1. Migration in `backend/database/migrations/00N_*.sql` (idempotent).
2. Domain in `backend/src/Domain`, controller in `backend/src/Controllers`.
3. Routes in `backend/src/routes.php` with the right middleware.
4. Test in `backend/tests/Feature/ApiTest.php`.
5. Page in `frontend/src/app/pages/<module>/`, route in `app/StaffApp.tsx` and
   link in `app/layout/StaffLayout.tsx`.

**Change the website copy**: `frontend/src/site/content.ts`. Contact details,
the introduction and the WhatsApp number are edited from *Settings* in the
application.

## Conventions

- Backend: `strict_types`, English names and English interface text.
- Frontend: strict TypeScript, small components, no loose styles (always
  tokens), catalog labels from `/api/meta`.
- Text for people: plain, warm US English, second person, no jargon.

---

Made by Salinas — [github.com/stufonn-cell](https://github.com/stufonn-cell)
