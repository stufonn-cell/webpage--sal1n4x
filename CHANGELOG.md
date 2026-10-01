# Changelog

Format based on Keep a Changelog. Semantic versioning.

## [2.1.0] - 2026-10-01

### Added

- Terms and conditions page (`/terms`) and a Spanish version of the privacy
  page; both legal pages switch between English and Spanish (`?lang=es`).
- Security hardening (details in `SECURITY.md`): rate limits in the API
  (per IP and per user, stored in MySQL, configurable with `RATE_LIMIT_*`) and
  in Nginx, identifier whitelisting and escaped LIKE patterns in every query,
  stricter sessions (SameSite=Strict, idle and absolute timeout, other
  sessions closed after a password change), constant-time login, JSON size and
  depth limits, safer uploads, SSRF guard for the RIPS validator address,
  complete security headers and a hardened PHP configuration. Migration
  `005_security.sql` adds the `rate_limits` table.
- Documents in English or Spanish: informed consents (text, signature block
  and record of signature), printed session notes, invoices and assessment
  reports. New *Settings → Documents* default, a language choice when creating
  a consent and a language switch next to *Print*. The Spanish instrument
  catalog mirrors the English one item by item, so scores are identical.
  Migration `004_document_language.sql` stores each consent's language.
- ICD-11 coding: diagnoses use the WHO ICD-11 MMS 2025-01 catalog (adopted in
  Colombia by Resolution 1442 of 2024), searchable by code or by words. Each
  ICD-11 code stores its WHO ICD-10 equivalent for dual coding during the
  transition. DSM-5 and ICD-10 remain available. A diagnosis can be marked as
  primary and its status updated.
- RIPS reports (Colombia, Resolution 2275 of 2023): administrators generate
  "RIPS without invoice" JSON reports (`tipoNota` `RS`) for the completed
  appointments in a period, see which appointments are missing data before
  generating, download the JSON for the Ministry's local validator or send it
  to the Ministry's MUV Docker API (SISPRO credentials are never stored), and
  track each report's status (generated, validated with CUV, rejected). Each
  appointment is reported once; a report that was not validated can be deleted
  and generated again. New *Settings → RIPS* section.
- Professional document fields (document type and number) on staff accounts,
  used as the professional's identity in RIPS.
- RIPS fields on the patient form: biological sex, RIPS user type, country,
  municipality and zone of residence, and country of origin.
- Migration `003_icd11_rips.sql` and the ICD-11 data files in
  `backend/database/data`, loaded automatically by `php bin/console install`
  and `php bin/console migrate`.

### Changed

- New identity: a hand-drawn psi (Ψ) mark with a letterhead-style wordmark,
  Literata for headings and Atkinson Hyperlegible Next for text, and a
  two-row public header (help strip with the crisis line on top, quieter
  navigation and booking link below).
- Session notes can only be edited by their author or an administrator.
- The whole application — UI, API messages, routes, demo data and docs — is
  now English only (except the documents and legal pages that can be read in
  Spanish).
- Record numbers use the `MR-` prefix (for example `MR-2026-0001`), so portal
  usernames look like `mr-2026-0001`. Invoice numbers use `INV-`.
- The demo patient password is now `Patient2026`.
- Codes stored as Spanish words (document categories, consent template codes,
  note interventions) are English; migration 003 maps existing rows.
- The `rips_*` settings are only returned to administrators.
- Removed the Spanish i18n layer.

## [2.0.0] - 2026-10-01

Backend and frontend split. Full details in `docs/MIGRATION-2.0.md`.

### Architecture

- The PHP backend moves to `backend/` and exposes a JSON API under `/api`.
- New React 19 + TypeScript + Vite frontend in `frontend/`.
- Nginx serves the SPA and forwards `/api` to PHP-FPM (same origin).
- Dedicated production image for the frontend; CI with two suites.

### Added

- Public website with services, team, confidentiality, frequently asked
  questions, contact, privacy and structured data.
- Three-step appointment request and an appointment requests inbox for staff.
- Optional public profile per professional, configurable WhatsApp number and
  crisis line.
- Design system with tokens, dark mode, a purpose-built mobile layout and
  motion that respects `prefers-reduced-motion`.

### Security

- Fixed stored XSS in consent signatures.
- Fixed a patient's access to other patients' consents (IDOR).
- Fixed XSS in global search.
- Persistent sign-in attempts, inactivity timeout, CSRF rotation and a strict
  CSP.

### Changed

- Validation of catalog values, video call links and questionnaire answers;
  editing an appointment validates the same way as creating one.
- Signing a note is limited to its author or an administrator.
- Passwords must have at least 10 characters; changing one requires the
  current password.

## [1.0.0] - 2026-08-01

First complete working version.

### Clinical

- Patient file with personal details, contact details, reason for
  consultation, relevant history, medication, emergency contact and a risk
  level indicator.
- Weekly schedule with clash detection, three session types and five
  attendance statuses.
- Session notes in SOAP, DAP or free-form format, with an interventions
  catalog, mood scale, signing that locks the note and a printable version.
- ICD-10 and DSM-5 diagnoses.
- Unified timeline per patient.

### Psychometric instruments

- PHQ-9, GAD-7, DASS-21, PSS-10, RSES and WHO-5 with automatic scoring.
- Support for reverse-scored items, subscales, multipliers and severity bands.
- Critical item detection with an alert in the report.
- Progress chart per instrument and per patient.

### Patient portal

- Appointments list with a direct link to the video call.
- Assigned questionnaires with a progress bar.
- The patient's own progress chart.
- Consent signing with a vector signature.
- Document downloads.

### Administrative

- Five informed consent templates.
- Document repository with file type and size validation.
- Billing with line items, taxes, partial payments and automatic closing.
- Practice settings, user management and audit log.

### Interface

- Sign-in screen with a vector illustration and quick demo sign-in.
- Light and dark mode saved per user.
- Global search with `Ctrl+K`.
- Server-generated SVG charts: bar, line, donut and gauge.
- Inline vector icons. No raster images.
- Responsive design and a print stylesheet.

### Infrastructure

- Docker Compose with Nginx, PHP-FPM 8.3, MySQL 8.4 and Adminer.
- Multi-stage Dockerfile with `development` and `production` targets.
- Dedicated compose file for production.
- Idempotent migrations and the `bin/console` CLI.
- Test suite with no external dependencies and its own runner.
- Continuous integration on GitHub Actions.

### Security

- Passwords with `password_hash` and automatic rehash.
- CSRF token on every request that changes data.
- Temporary lockout after five failed attempts.
- Prepared statements and output escaping in every view.
- Role-based access control through middleware.
- Audit log of access and changes.
