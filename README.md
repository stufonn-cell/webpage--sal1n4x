# PsiClinic

Clinical records system for psychology practices, with a public website, a
patient portal and a psychometric instruments module with automatic scoring.

Since version 2.0 the project is split into two parts:

| Part | Technology | Responsibility |
|---|---|---|
| `backend/` | PHP 8.3 with no frameworks, MySQL 8.4 | JSON API under `/api`, clinical rules, security, audit log |
| `frontend/` | React 19, TypeScript, Vite | Public website, staff application and patient portal |

Nginx serves the compiled frontend and forwards `/api/*` to PHP-FPM: the browser
sees a single origin, so the session cookie and the CSRF token work without CORS.

The whole application is in English.

Made by Salinas — [github.com/stufonn-cell](https://github.com/stufonn-cell)

---

## What's included

**Public website** (`/`)

- Home page with the practice's introduction, browsable services, how to get
  started, team (only professionals who turn on their public profile),
  confidentiality, frequently asked questions and contact details.
- Three-step appointment request (`/request-appointment`) with per-step
  validation, a honeypot field for bots and a submission limit per connection.
  Requests land in a staff inbox.
- Always-visible "get help now" notice with a configurable crisis line.
- Privacy page (`/privacy`) based on the consents the practice already uses,
  and terms and conditions (`/terms`). Both can be read in English or Spanish
  (`?lang=es`); have a lawyer review them before going live.
- Metadata, Open Graph, canonical URL and structured data (schema.org).

**Staff** (`/app`)

- Today's dashboard, appointment requests inbox, weekly schedule with clash
  detection, patients, SOAP/DAP/free-form session notes with signing that locks
  the note, assessments with severity bands and critical items, informed
  consents signed by hand, documents, billing with partial payments, settings,
  users and audit log.
- **Documents in English or Spanish.** The interface is English, but informed
  consents, printed session notes, invoices and assessment reports can be
  produced in English or Spanish. *Settings → Documents* sets the default; each
  consent is created in a chosen language (and signed in it, also from the
  portal), and the other documents have a language switch next to *Print*.
- **ICD-11 coding.** Diagnoses are coded with the WHO ICD-11 MMS 2025-01 catalog
  (adopted in Colombia by Resolution 1442 of 2024), searchable by code or by
  words. Each ICD-11 code stores its WHO ICD-10 equivalent, so every diagnosis
  is dual coded during the transition. DSM-5 and ICD-10 remain available.
- **RIPS reports** (Colombia, Resolution 2275 of 2023). Administrators generate
  "RIPS without invoice" JSON reports (`tipoNota` `RS`) for the completed
  appointments in a period. Before generating, you can see which appointments
  are missing data. Download the JSON for the Ministry's local validator, or send
  it to the Ministry's MUV Docker API (SISPRO credentials are used for that
  request only and are never stored), and track each report's status:
  generated, validated (with its CUV) or rejected. Each appointment is reported
  once; a report that was not validated can be deleted to generate it again.
  *Settings → RIPS* holds the reporter NIT, REPS provider code, service code
  (default `344` Psychology), default purpose and cause codes, first note
  number, environment and validator URL.
- Global search with `Ctrl+K`, light and dark mode, printable notes and
  invoices.

**Patient portal** (`/portal`)

- Next session with a link to the video call, questionnaires with progress,
  progress chart, consent signing and document downloads.

**Instruments**: PHQ-9, GAD-7, DASS-21, PSS-10, RSES and WHO-5.

---

## Quick start

Requirements: Docker Desktop with WSL2 (Windows) or Docker Engine (Linux, macOS).

```bash
git clone https://github.com/stufonn-cell/psiclinic.git
cd psiclinic
cp .env.example .env
make install          # builds the frontend, starts everything and loads demo data
```

Without `make`:

```bash
docker compose run --rm --no-deps frontend sh -c "npm install && npm run build"
docker compose up -d
```

| Address | What it is |
|---|---|
| <http://localhost:8080> | Full application (compiled frontend + API) |
| <http://localhost:5173> | Frontend with hot reload, for development |
| <http://localhost:8081> | Adminer (server `mysql`) |

Demo accounts (only with `APP_ENV=local`; the sign-in screen at `/login` lists
them so you can fill them in with one click):

| Role | Username | Password |
|---|---|---|
| Administrator | `admin` | `Psiclinic2026` |
| Psychologist | `l.moreno` | `Psiclinic2026` |
| Patient | `mr-2026-0001` | `Patient2026` |

Patient portal usernames are the lowercase record number (record numbers look
like `MR-2026-0001`).

The detailed guide for Windows is in [docs/INSTALL-WSL.md](docs/INSTALL-WSL.md).

---

## Commands

```bash
bin/psiclinic up          # starts everything (builds the frontend if missing)
bin/psiclinic build       # rebuilds the frontend
bin/psiclinic test        # backend tests
bin/psiclinic test-front  # frontend type check and tests
bin/psiclinic logs        # PHP, Nginx and Vite logs
bin/psiclinic down        # stops everything and keeps the data
```

Inside the backend container:

```bash
php bin/console install    # migrations + demo data
php bin/console migrate    # schema only (idempotent)
php bin/console fresh      # wipes everything and reinstalls
php bin/console key        # generates an APP_KEY
```

`install` and `migrate` also load the ICD-11 catalog from
`backend/database/data/icd11-2025-01.tsv` and its ICD-10 equivalences from
`backend/database/data/icd11-to-icd10.tsv`.

`make` equivalents: `make up`, `make build`, `make test`, `make test-front`,
`make fresh`, `make logs`, `make down`.

---

## Tests

- **Backend**: a test suite with no PHPUnit or Composer (`php bin/test`). It
  includes tests against the real API with routes, middlewares, CSRF, role
  permissions, IDOR, signatures, schedule clashes, validation and submission
  limits.
- **Frontend**: strict type check (`npm run typecheck`) and Vitest tests
  (`npm test`).

Details in [docs/TESTING.md](docs/TESTING.md). CI runs both suites and builds
the two production images on every push.

---

## Structure

```
psiclinic/
├── backend/
│   ├── bin/                 console and test
│   ├── database/migrations  idempotent SQL schema
│   ├── database/data        ICD-11 catalog and ICD-10 equivalences (TSV)
│   ├── public/index.php     API front controller
│   ├── src/Core             router, request, session, auth, CSRF, HTTP errors
│   ├── src/Controllers      one controller per module (JSON)
│   ├── src/Domain           business rules and queries
│   ├── src/Support          serialization, signature, installer, MUV client
│   └── tests/               unit and integration tests
├── frontend/
│   └── src/
│       ├── styles/          design system tokens and base styles
│       ├── components/      shared UI, charts and forms
│       ├── site/            public website
│       ├── app/             staff application
│       ├── portal/          patient portal
│       ├── session/         session and theme
│       ├── hooks/ lib/      utilities, API client, types
│       └── pages/           sign-in, profile, errors
├── docker/                  Dockerfiles and Nginx
├── bin/psiclinic            day-to-day shortcuts
└── docs/
```

Architecture in [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md), API reference in
[docs/API.md](docs/API.md) and version 2 changes in
[docs/MIGRATION-2.0.md](docs/MIGRATION-2.0.md).

---

## Deployment

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

This builds a backend image with the code inside and an Nginx image with the
frontend already compiled. See [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

---

## Clinical and legal notice

Using psychometric instruments requires professional training. Scores are a
guide: they do not replace clinical judgment and are not a diagnosis. Before
using the system with real data, review your local rules on health data
protection and read [SECURITY.md](SECURITY.md).

---

## License

Proprietary software. See [LICENSE](LICENSE) and [NOTICE](NOTICE).
The authorship notice must be kept in every copy or derivative work.
