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

The seeders carrying genuine reference data are `FieldOfficeSeeder`,
`ExamTypeSeeder`, `SchoolSeeder`, `EmailTemplateSeeder` and `SettingSeeder`.
Run those individually if a fresh production database needs baseline rows:

```bash
php artisan db:seed --class=EmailTemplateSeeder
```

Dashboard demo data is separate and opt-in:

```bash
php artisan db:seed --class=DashboardDemoDataSeeder
```

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
