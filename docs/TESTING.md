# Testing

The project has two suites: the backend one (PHP) and the frontend one
(TypeScript). CI runs both on every push.

## Backend

The suite does not depend on PHPUnit or Composer. The runner lives in
`backend/tests/Runner.php` and is called with `bin/test`.

---

## Running

```bash
docker compose exec app php bin/test           # everything
docker compose exec app php bin/test unit      # unit tests only
docker compose exec app php bin/test feature   # integration tests only
```

Without Docker, with PHP installed on your computer:

```bash
php bin/test
```

Output:

```
  PsiClinic - test suite

  InstrumentsTest
    [ok]   catalog exposes the six instruments
    [ok]   phq9 scores minimum and maximum
    ...

  <n> passed, 0 failed, 0 skipped | <n> assertions | <t> s
```

The process exits with code `1` if anything fails, so it can be used in CI.

---

## What each file covers

### Unit tests (no database)

| File | Covers |
|---|---|
| `Unit/InstrumentsTest.php` | Scoring of the six instruments, reverse-scored items, subscales, multipliers, critical items, full coverage of the bands |
| `Unit/ValidatorTest.php` | Each validation rule and combinations of several |
| `Unit/RouterTest.php` | Route matching, parameters, HTTP verb, method spoofing, middlewares and 404/405 errors |
| `Unit/RequestTest.php` | Input normalization, casts, files and route attributes |
| `Unit/HelpersTest.php` | Escaping, initials, age, dates, currency and UUID generation |
| `Unit/EnvTest.php` | Reading `.env`: quotes, inline comments, booleans and default values |

### Integration tests (require MySQL)

| File | Covers |
|---|---|
| `Feature/SchemaTest.php` | Tables, engine, collation, foreign keys and transactions |
| `Feature/AuthTest.php` | Sign-in by username or email, inactive accounts, roles, lockout after failed attempts (persistent across sessions), audit log, CSRF and its rotation at sign-in |
| `Feature/PatientTest.php` | Record numbering (`MR-` prefix), search, filters, pagination and timeline |
| `Feature/AppointmentTest.php` | Schedule clash detection, weekly view and upcoming appointments |
| `Feature/ClinicalNoteTest.php` | Session numbering, search in the note body, mood curve and signing |
| `Feature/AssessmentTest.php` | Scoring and storage of each instrument, progress series and filters |
| `Feature/BillingTest.php` | Totals, partial payments, balance, automatic closing and unique invoice numbers |
| `Feature/InstallerTest.php` | Demo data, idempotency, settings and dashboard metrics |
| `Feature/ApiTest.php` | The real API (routes, middlewares and controllers): session, sign-in, CSRF, role permissions, per-field validation, catalog values, schedule clashes, video call links, note signing, IDOR and XSS in consents, portal questionnaires, public website, appointment requests with rate limit and honeypot field, and search |

If the database is not available, integration tests are marked as skipped
instead of failing.

---

## Test database

Commands run from `backend/` (or inside the `app` container, where `backend/`
is the root).

Tests use `psiclinic_test`, created automatically by
`docker/mysql/init/01-create-test-database.sql` the first time the MySQL
container starts. They never touch the working database.

To point to another database, set it in `.env`:

```dotenv
DB_TEST_DATABASE=psiclinic_test
```

Each integration test empties the tables before it runs, so the order does not
affect the result.

---

## Adding a test

```php
<?php

declare(strict_types=1);

namespace PsiClinic\Tests\Unit;

use PsiClinic\Tests\TestCase;

final class MyFeatureTest extends TestCase
{
    public function testDescribesWhatShouldHappen(): void
    {
        $this->assertSame(2, 1 + 1);
    }
}
```

Runner rules:

- The file name ends in `Test.php` and lives in `tests/Unit` or `tests/Feature`.
- The class extends `TestCase` (or `FeatureTestCase` if it needs the database).
- Test methods start with `test`.
- `setUp()` and `tearDown()` run around each method.

Available assertions: `assertTrue`, `assertFalse`, `assertSame`,
`assertEquals`, `assertNull`, `assertNotNull`, `assertCount`, `assertContains`,
`assertGreaterThan`, `assertArrayHasKey` and `assertThrows`.

---

## Frontend

```bash
cd frontend
npm run typecheck   # strict TypeScript over all of src/
npm test            # Vitest: formatting, catalogs and utilities
npm run build       # production build
```

With Docker, without Node on your computer: `bin/psiclinic test-front`.

| File | Covers |
|---|---|
| `src/lib/format.test.ts` | MySQL dates, age, greeting by time of day, relative day names, initials, full names, plurals, file sizes, `en-US` currency formatting (COP), catalog labels and severity tones |

## Recommended manual check

Before publishing, go through these in the browser (desktop and mobile):

1. Public website: section navigation, mobile menu, services, questions, and a
   full appointment request at `/request-appointment` (including the errors on
   each step).
2. Sign in at `/login` with each role, and sign out.
3. Staff: accept the request and create the patient file, book an appointment
   (and cause a clash), write and sign a note, administer an instrument, add an
   ICD-11 diagnosis, generate a consent, upload a document, create an invoice
   and record a payment.
4. RIPS (as administrator): fill in *Settings → RIPS*, preview a period at
   `/app/rips`, check the missing data, generate the report and download the
   JSON.
5. Portal: answer the assigned questionnaire at `/portal/questionnaires`, sign
   the consent, download a document.
6. Keyboard: go through each screen with `Tab`, open search with `Ctrl+K`,
   move between tabs with the arrow keys and close dialogs with `Escape`.
7. Turn on *reduce motion* in the system and check that there are no
   animations.

---

Made by Salinas — [github.com/stufonn-cell](https://github.com/stufonn-cell)
