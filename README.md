# PROCTAD

Professional Conduct of Test Administration — the Civil Service Commission
Regional Office VIII system for accrediting, deploying and rating test
administrators for civil service examinations.

Replaces a legacy PHP/PDO application. Laravel 13 + Vue 3 + Inertia.

## Documentation map

Most of this project's operational knowledge already lives in dedicated
documents. Start here:

| Document | What it covers |
|---|---|
| [`CUTOVER_RUNBOOK.md`](CUTOVER_RUNBOOK.md) | **Read before deploying.** Pre-cutover blockers, cron and queue-worker setup, the ordered cutover sequence, smoke tests, rollback plan. |
| [`MIGRATION_CHECKPOINT.md`](MIGRATION_CHECKPOINT.md) | Phase-by-phase record of the migration from the legacy system. |
| [`DATABASE_AUDIT.md`](DATABASE_AUDIT.md) | Legacy schema audit and its mapping onto the new schema. |
| [`FRONTEND_AUDIT.md`](FRONTEND_AUDIT.md) | Frontend inventory and parity notes. |

## Requirements

- PHP 8.3+
- MySQL
- Composer
- Node.js (Vite 8)

## Local setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Then, in two terminals:

```bash
npm run dev
php artisan serve
```

Bulk assignment queues its confirmation emails, so if you are exercising that
flow you also need a worker — otherwise the jobs sit in the `jobs` table and no
mail is sent, with nothing reported in the UI:

```bash
php artisan queue:work
```

## Seeding

```bash
php artisan db:seed
```

> **Never run `db:seed` against production.** `UserSeeder` creates six accounts
> — including a Super Administrator — all with the password `password` and
> `@proctad.test` addresses. `MemberSeeder`, `ExaminationSeeder`,
> `CertificateSeeder` and `TrainingSeeder` create sample records.

The genuine reference data lives in `InitialDataSeeder` (field offices, testing
centers, schools, exam types, signatories), alongside `EmailTemplateSeeder`,
`SettingSeeder` and `FeeScheduleSeeder`. These are exactly what `db:seed` runs by
default, so a fresh database comes up with the real CSC RO VIII baseline and no
sample records:

```bash
php artisan migrate:fresh --seed
```

The sample-data seeders are kept out of that run. Invoke them by hand when you
need something to look at:

```bash
php artisan db:seed --class=MemberSeeder
```

Dashboard demo data is separate and opt-in:

```bash
php artisan db:seed --class=DashboardDemoDataSeeder
```

## Permissions

Authorization is decided in three parts, and only the first is configurable:

| Part | Where it lives | Configurable |
| --- | --- | --- |
| **Capability** — may this role do this kind of thing? | `App\Enums\Permission`, overridable per role at **Administration → Role Permissions** | Yes |
| **Scope** — is the record in the user's testing centers or field office? | The policy classes | No |
| **State** — is the record in a status that allows it? | The policy classes | No |

So granting a permission widens *who* may act, never *which records* they reach:
a Field Office role handed every members permission still cannot touch another
office's roster.

Roles themselves stay in code (`App\Enums\UserRole`). They can be **renamed** at
**Administration → Roles** (Super Admin only, gated on the role rather than a
permission so it cannot be granted away), but not added or deleted: several are
named directly by `CertificateType::approverRoles()` and
`ExamRole::reservedForRole()`, and each has its own hand-built sidebar. A rename
is display text only — `users.role` is unchanged, so no authorization decision
moves with it.

**Designations** and their **committees** are data, in the `designations` and
`designation_categories` tables, managed at **Administration → Designations**.
Both tables are seeded from `ExamRole`, `PersonnelType` and their group enums,
which remain the canonical list of *built-in* keys.

Built-in rows are flagged and cannot be deleted, because the structural rules
name them: the payroll workbook reserves pages for Room Examiners and Proctors,
the evaluation form covers four, and the REC chairs are held ex officio.

The per-room staffing grid is **not** one of those rules — it is driven by
`designations.rooms_per_slot`, which is how many rooms one person covers (1 per
room, or a group of N anchored at the group's first room). Any designation given
a value takes part, so a custom one is staffed alongside the built-in three.
`RoomStaffingCalculator` accepts the list via its constructor so the arithmetic
stays unit-testable without the database.

For anchored designations that number is only a **default**. The group size is
per venue, in `examination_school.rooms_per_supervisor`, chosen by the field
office when room staffing is generated and constrained to 3–8 (see
`ExaminationSchool::MIN/MAX_ROOMS_PER_SUPERVISOR`). It is persisted before the
randomizer runs, because `StaffingRandomizer` and `RoomStaffingCalculator` must
agree on it: if they disagree, supervisors appear against the wrong rooms and
correctly staffed rooms read as Incomplete. Pass the venue's value to
`stats()`/`breakdown()` — never assume the designation default. Deactivating is how a built-in is
retired — it leaves every historical assignment intact and keeps its rate for
when it returns. Custom designations are assignable and appear on payroll's
catch-all page, but stand outside those rules and never reach the room grid;
they are deletable only while unused, behind a retype-the-name confirmation.

A designation's `key` is what lands in `exam_assignments.role`, and is immutable
— renaming or re-filing never rewrites history. The column is cast through
`App\Casts\AsDesignation` to a `DesignationValue` rather than an enum, since a
custom key is not an `ExamRole` case; compare with `->is()` / `->isAnyOf()`
rather than `===`. Coverage follows the committee, so moving a designation into
the REC or an LEC makes it a coverage duty. Rates live in the same
`fee_schedules` rows the payroll reports read. There is no separate Fee
Management page — a designation's rate is set where the designation is, and
editing it needs `fee_schedules.manage` on top of `designations.manage`, so
naming duties and setting what they pay stay separate permissions.

Note that the administration routes sit behind `role:super_admin,esd_admin`
middleware (`routes/web.php`). For those pages a permission can *narrow* access
but not widen it.

Defaults live in `App\Support\PermissionRegistry` and reproduce the role tiers
the policies were originally written against, so an untouched install behaves as
it always did. Only differences are stored, in `role_permissions`. Super
Administrator always holds every permission and cannot be edited — that is the
guarantee there is always a way back into the page.

## Tests

```bash
php artisan test
```

## Production configuration

`.env.example` is a **local development** template. These values must differ in
production:

| Key | Local | Production |
|---|---|---|
| `APP_ENV` | `local` | `production` |
| `APP_DEBUG` | `true` | **`false`** — while `true`, any error page exposes stack traces, file paths and config values to whoever triggered it |
| `LOG_LEVEL` | `debug` | `error` |
| `MAIL_MAILER` | `log` | `smtp` — while `log`, mail is written to the log file and never sent |
| `APP_URL` | `http://127.0.0.1:8001` | The real host; Google OAuth redirect URIs derive from this |
| `SESSION_ENCRYPT` | `false` | Consider `true` |

Credentials — `GOOGLE_CLIENT_SECRET`, `MAIL_PASSWORD`, database password — must
be set on the production host only.

> **Do not put real credentials in `.env.example`.** It is tracked in git, so
> anything committed there is permanently in history and must be treated as
> compromised and rotated.

Deployment steps proper — the required cron entry, the queue-worker question,
and the ordered cutover sequence — are in
[`CUTOVER_RUNBOOK.md`](CUTOVER_RUNBOOK.md) rather than duplicated here, so
there is one authoritative copy.

## Scheduled commands

Registered in `routes/console.php`. All three require the `schedule:run` cron
entry documented in the runbook (§4); without it they never run and nothing
reports an error:

| Command | Schedule |
|---|---|
| `proctad:send-assignment-reminders` | Daily 08:00 |
| `proctad:expire-pending-assignments` | Daily 01:00 |
| `proctad:prune-logs` | Monthly |

Verify with `php artisan schedule:list`.

Other commands are operational one-offs: `proctad:migrate-legacy` (the legacy
ETL), `proctad:regenerate-certificate-pdfs`, `proctad:normalize-name-casing`.
