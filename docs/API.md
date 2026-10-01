# API

Every route lives under `/api` and responds with JSON. Writes (`POST`, `PUT`,
`PATCH`, `DELETE`) require the `X-CSRF-Token` header with the token returned by
`GET /api/session`. The session travels in an `HttpOnly` cookie.

Access legend: **Public** · **Session** (any signed-in user) · **Staff**
(administrator, psychologist, assistant) · **Admin** · **Patient**.

## Session and public website

| Method | Route | Access | Description |
|---|---|---|---|
| GET | `/api/session` | Public | Current user, CSRF token, practice name and demo accounts (local only) |
| POST | `/api/auth/login` | Public | `{identifier, password}`. 422 if they do not match, 429 after too many attempts |
| POST | `/api/auth/logout` | Session | Ends the session and returns a new token |
| GET | `/api/public/site` | Public | Public practice details, professionals with an active profile, approaches, instruments and form options |
| POST | `/api/public/appointment-requests` | Public | Appointment request. At most 3 per hour per connection (429) |
| GET/PUT | `/api/profile` | Session | Your own profile. Changing the password requires `current_password`. Staff can also set `document_type` and `document_number`, the professional's identity document reported in RIPS |
| PUT | `/api/profile/theme` | Session | `{theme: "light" \| "dark"}` |

## Staff

| Method | Route | Access | Description |
|---|---|---|---|
| GET | `/api/meta` | Staff | Catalogs (statuses, session types, roles…), patient and professional lists, schedule settings |
| GET | `/api/dashboard` | Staff | Dashboard metrics and lists |
| GET | `/api/search?q=` | Staff | Search patients and notes |
| GET | `/api/appointment-requests?status=&page=` | Staff | Appointment requests inbox |
| PATCH | `/api/appointment-requests/{id}` | Staff | `{status}` |
| GET/POST | `/api/patients` | Staff | Paginated list (`q`, `status`, `page`) and create. Includes the RIPS fields `biological_sex`, `rips_user_type`, `residence_country`, `residence_municipality`, `residence_zone` and `origin_country` |
| GET/PUT | `/api/patients/{id}` | Staff | Full patient file / edit |
| DELETE | `/api/patients/{id}` | Admin | Deletes the whole clinical record |
| POST | `/api/patients/{id}/diagnoses` | Staff | New diagnosis: `system` (`icd11` \| `icd10` \| `dsm5`), `code`, `title`, `icd10_code`, `status`, `is_primary`, `onset_date`, `notes`. For `icd11` the code must exist in the catalog: the title and the ICD-10 equivalent are taken from it (422 if not found) |
| PATCH | `/api/patients/{id}/diagnoses/{diagnosisId}` | Staff | `status`, `icd10_code`, `is_primary` (marking one as primary clears the others) |
| DELETE | `/api/patients/{id}/diagnoses/{diagnosisId}` | Staff | Deletes a diagnosis |
| POST | `/api/patients/{id}/portal-access` | Staff | Creates the portal account and returns the temporary password only once |
| POST | `/api/patients/{id}/assessments` | Staff | Assigns a questionnaire in the portal |
| GET | `/api/patients/{id}/note-context` | Staff | Next session number and the patient's appointments |
| GET | `/api/icd11?q=&limit=` | Staff | Searches the ICD-11 MMS 2025-01 catalog by code or by words. Returns the release and each code's title and ICD-10 equivalent |
| GET/POST | `/api/appointments` | Staff | Week (`week`, `psychologist_id`) and create. 409 on a clash |
| GET/PUT/DELETE | `/api/appointments/{id}` | Staff | Detail, edit, delete |
| POST | `/api/appointments/{id}/status` | Staff | `{status}` |
| GET/POST | `/api/notes` | Staff | List (`q`, `page`) and create |
| GET/PUT | `/api/notes/{id}` | Staff | Detail / edit (409 if signed) |
| POST | `/api/notes/{id}/sign` | Staff | Signs and locks. Author or admin only (403), only once (409) |
| GET | `/api/instruments`, `/api/instruments/{code}` | Staff | Catalog and the definition of each instrument |
| GET/POST | `/api/assessments` | Staff | List (`instrument`, `status`, `page`) and in-session administration |
| GET/DELETE | `/api/assessments/{id}` | Staff | Report / delete. `?language=en` or `?language=es` returns the instrument text, band labels and interpretation in that language (recalculated from the answers; the score never changes). Defaults to the practice document language |
| GET/POST | `/api/consents` | Staff | List and create from a template: `{patient_id, template_code, language}` with `language` `en` or `es`. Without `language` the practice default applies. The text is copied into the consent, so it never changes after signing |
| GET | `/api/consents/{id}` | Session | A patient only sees their own (404 otherwise) |
| POST | `/api/consents/{id}/sign` | Session | `{signed_name, strokes: [[[x, y], …], …]}` |
| GET/POST | `/api/documents` | Staff | Repository and upload (`multipart/form-data`) |
| GET | `/api/documents/{id}/download` | Session | Download; a patient only gets their own |
| DELETE | `/api/documents/{id}` | Staff | Deletes the file and its record |
| GET/POST | `/api/invoices` | Staff | List (`status`, `page`) and create with `items[]` |
| GET | `/api/invoices/{id}` | Staff | Invoice, line items, payments and balance |
| POST | `/api/invoices/{id}/payments` | Staff | Records a payment |
| GET | `/api/settings` | Staff | Settings, including `document_language` (`en` or `es`). The `rips_*` keys are only returned to administrators |
| PUT | `/api/settings` | Admin | Saves the settings |
| GET | `/api/users` | Staff | Accounts (no hashes) |
| POST | `/api/users` | Admin | New staff account. Optional `document_type` and `document_number` (identity document reported in RIPS) |
| PATCH | `/api/users/{id}` | Admin | Professional profile: `show_on_site`, `specialty`, `license_number`, `public_bio`, and the RIPS identity document `document_type` / `document_number` |
| POST | `/api/users/{id}/toggle` | Admin | Turns an account on or off (not your own) |
| GET | `/api/audit` | Staff | Last 150 actions |

## RIPS (Colombia, Resolution 2275 of 2023)

"RIPS without invoice" reports (`tipoNota` `RS`) for completed appointments.
Every route is admin only. Each appointment can be in only one report.

| Method | Route | Access | Description |
|---|---|---|---|
| GET | `/api/rips` | Admin | Reports with their status (`generated`, `validated`, `rejected`), configured environment and the RIPS catalogs |
| POST | `/api/rips/preview` | Admin | `{from, to}`. Appointments in the period, which ones are ready and what data is missing for the rest, without saving anything |
| POST | `/api/rips` | Admin | `{from, to}`. Generates and saves the report with the ready appointments. 422 if there is nothing to report or the RIPS settings are incomplete |
| GET | `/api/rips/{id}` | Admin | Report, JSON payload and the last validation result |
| GET | `/api/rips/{id}/download` | Admin | Downloads the JSON for the Ministry's local validator |
| POST | `/api/rips/{id}/send` | Admin | `{document_type, document_number, password}`. Sends the report to the Ministry's MUV Docker API with the SISPRO credentials, which are used for this request only and never stored. Saves the CUV when validated. 409 if it was already validated |
| DELETE | `/api/rips/{id}` | Admin | Deletes a report that was not validated, so its appointments can be reported again. 409 if validated |

## Patient portal

Every route requires a patient session and filters by the patient in the
session: the patient id is never taken from the request.

| Method | Route | Description |
|---|---|---|
| GET | `/api/portal` | Upcoming appointments, pending items, consents and completed questionnaires |
| GET | `/api/portal/appointments` | All appointments |
| GET | `/api/portal/questionnaires` | Questionnaires with date and score (no clinical interpretation) |
| GET/POST | `/api/portal/questionnaires/{id}` | Definition for answering / submitting answers (409 if already answered) |
| GET | `/api/portal/documents` | Shared documents |
