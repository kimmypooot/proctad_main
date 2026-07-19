# PROCTAD Cutover Runbook

> Companion to `MIGRATION_CHECKPOINT.md` Phase 9. Written 2026-07-10, targeting the
> August 2026 CSE-PPT examination as the deployment deadline.

## 1. Pre-cutover blockers (must resolve before go-live)

| # | Item | Status | Owner action needed |
|---|---|---|---|
| 1 | Exposed files in shared XAMPP `htdocs` webroot | **FLAGGED, not fixed** — see §2 | Decide: move out of webroot, restrict via server config, or accept risk if this host is never network-reachable |
| 2 | ETL rehearsal on full production copy (with real member rows) | **Blocked** — dev environment only has the reference dump (no member rows by design) | Point `LEGACY_DB_DATABASE` at a production copy and run `php artisan proctad:migrate-legacy --dry-run`; confirm member-dependent counts flip from skipped to imported; document results in `MIGRATION_CHECKPOINT.md` Phase 5 |
| 3 | Rotate the Gmail SMTP app password | **Owner-handled** — owner will generate and swap in the new app password directly | None from this repo's side; owner confirmed they will rotate it and update the production `.env` (`MAIL_PASSWORD`) themselves. |
| 4 | Queue worker / scheduler on the production host | **Not yet configured** — this is dev-only. As of 2026-07-20 the queue worker is no longer optional: bulk assignment queues its confirmation emails, and without a worker they are never sent and nothing reports an error | See §4 |

## 2. Exposed legacy files — finding (as of 2026-07-10)

`C:\xampp\htdocs\` is a **shared XAMPP htdocs root** serving many unrelated projects
(`csc-ors`, `hris`, `ipcrf`, `ors`, several other `proctad_*` snapshots, etc.) alongside
this app. If Apache on this host is reachable over any network (not strictly
`localhost`-only), every file under `htdocs` is downloadable by URL. Confirmed present:

- **`C:\xampp\htdocs\proctad_db (11).sql`** (90KB, dated 2026-02-09) — a full dump
  including `proctad_members` (real member PII: names, contact info) and
  `proctad_system_settings` (which per the legacy CLAUDE.md holds the SMTP
  password in plaintext).
- **`C:\xampp\htdocs\proctad_legacy\`** — the full legacy PHP source, which itself
  contains a `database_iterations\` folder with over a dozen additional historical
  SQL dumps (`proctad_db (03-11).sql`, `proctad_db(03-17).sql`, etc.), each a
  potential PII/credential exposure in its own right.
- Several other `proctad_*` snapshot directories at the htdocs root
  (`proctad1`, `proctad_02042026`, `proctad_02062025`, `proctad_02162026`,
  `proctad_ro8`) were **not inspected** — they may be unrelated to this migration
  and were left untouched.

**I did not move or delete anything** — the owner asked to be told first rather
than have this acted on automatically, since it's a shared machine and other
processes/people may reference these paths. Recommended fix: move the PROCTAD-
specific files (the dump and `proctad_legacy/`) to a location outside any
web-served directory (e.g. `C:\xampp\proctad-archive\`) before the production
cutover, or confirm this host's Apache is genuinely localhost-only and accept
the risk with that caveat recorded here.

## 3. Legacy feature-parity sweep (Phase R inventory)

Cross-checked against the inventory list in `MIGRATION_CHECKPOINT.md` §Phase R
("Facts learned from legacy source"). All items are implemented and tested:

| Legacy feature | New implementation |
|---|---|
| Staffing randomization | `StaffingRandomizer` + `StaffingController` (Phase 6) |
| Force-reassign | `ExamAssignmentController::forceReassign` (Phase 6) |
| Revoke designation (bulk) | `ExamAssignmentController::bulkRevoke` (Phase 6) |
| Bulk resign certificates | `CertificateService::resign` + `CertificateController::bulkResign` (Phase 6) |
| Duplicate-members report | `DuplicateMembersController` (Phase 7) |
| Pending registrations queue | **N/A by design** — confirmed `users.role` defaults to `member` and self-registration never creates a staff account, so there is no "pending staff approval" gap to replicate (verified again this session by re-reading `RegisteredUserController` and the `role` column's DB default) |
| Member/attendance/service-history Excel exports | `MembersExport`, `ServiceRecordsExport`, `TrainingAttendanceExport` (Phase 8) |
| ID-card generation (member + NEP) | `MemberIdCard` support class + `NepIdCard` (Phases 2/7) |
| Letterhead management | `LetterheadController` + certificate compositing (Phase 6) |
| Exam types CRUD | `ExamTypeController` (Phase 7) |
| Schools CRUD | `SchoolController` (Phase 7) |
| Notifications | In-app bell, `Illuminate\Notifications` (Phase 6) |
| Config/settings editor | `SettingController` (Phase 7) |

No open parity gaps found.

## 4. Queue worker and scheduler

- **Scheduler**: three commands are registered in `routes/console.php`
  (`proctad:send-assignment-reminders` daily 08:00, `proctad:expire-pending-assignments`
  daily 01:00, `proctad:prune-logs` monthly). Verified via `php artisan schedule:list`.
  **Production requires a single cron entry**:
  ```
  * * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1
  ```
- **Queue worker**: `QUEUE_CONNECTION=database` is set and the `jobs` table exists.
  **A worker is now REQUIRED** — this changed on 2026-07-20 and supersedes the
  earlier note that it was optional.

  `App\Jobs\SendAssignmentConfirmation` implements `ShouldQueue` and is dispatched
  by **bulk assignment** (`ExamAssignmentController::bulkStore`) — staffing a whole
  venue would otherwise make one SMTP round-trip per member inside a single request
  and time out mid-batch. Without a running worker, those jobs sit in the `jobs`
  table indefinitely and **members are never sent their confirmation, with no error
  shown to the staff member who deployed them**. This is a silent failure: the
  assignments are created and the page reports success.

  Add a supervisor-managed process before go-live:
  ```
  php artisan queue:work --tries=3 --sleep=3
  ```
  Verify after deploying: run a bulk assign against a test venue, then confirm the
  `jobs` table drains and `email_logs` gains a `sent` row per member.

  Everything else still sends synchronously and does **not** depend on the worker —
  the "Send Confirmation" button, single assign, assignment update, certificate
  release, and the daily reminder command. That split is deliberate: single sends
  report a bounce back to the user inline, which a queued send cannot do.

## 5. QR code compatibility

- Legacy printed member ID QR codes encode raw text (`{proctad_id}|attendance`),
  never a URL — confirmed by reading `proctad_legacy/user/complete-profile.php`.
  They were never "links" to a legacy verify page, so there is no legacy URL to
  redirect from.
- The internal QR scanner (`ScannerController::normalize()`) already strips this
  legacy suffix and resolves old codes correctly (Phase 6, tested).
- This session added the same defensive normalization to the new public
  `/verify/{proctadId}` endpoint (`VerifyController`), so a legacy-format code
  fed into that route directly also resolves rather than 404ing. Tested.

## 6. Cutover sequence (execute in order, on the target production window)

1. **Freeze legacy**: put `proctad_legacy` into maintenance mode / disable writes.
   No new members, exams, certificates, or attendance should be recorded after this point.
2. **Final ETL**: run `php artisan proctad:migrate-legacy --dry-run` against the
   frozen production database first; review the reconciliation report; then run
   without `--dry-run` to commit.
3. **Verify row counts**: compare source table counts (members, exam_assignments,
   certificates, training_assignments) against the new schema's imported counts;
   investigate any skips reported by the command.
4. **Switch**: point the production domain/DNS or web server config at the new
   Laravel app's `public/` directory.
5. **Smoke test** (do this before opening access to real users):
   - Staff login (username or email) succeeds; forced password change works for
     ETL'd accounts flagged `must_change_password`.
   - Member Google login succeeds for an already-registered member.
   - A known member's `/verify/{proctad_id}` page resolves correctly.
   - A known certificate's `/verify-certificate/{certificate_no}` page resolves.
   - Scanner correctly reads a physical legacy-printed member QR card.
   - `php artisan schedule:list` shows all three commands; cron is installed.
6. **Rollback plan**: keep the legacy app and its database untouched (read-only)
   for at least one full examination cycle after cutover. If a blocking issue is
   found, revert DNS/web server config back to legacy and re-open it read-write;
   any records created in the new system during the failed window must be
   manually reconciled back into legacy before reopening it for writes.
7. **Post-cutover**: confirm the SMTP password has been rotated (§1.3 — owner-handled);
   confirm the exposed-files finding (§2) has been resolved; remove or restrict
   `proctad_legacy/` and any SQL dumps from the production web server's document root
   entirely (not just moved within the same host, if that host is directly
   internet-facing).

## 7. What's still open after this session

- §1 items 1, 2, and 4 above (all require actions outside this repository:
  production DB access, host/webroot changes, cron setup). Item 3 (SMTP
  rotation) is owner-handled and no longer blocking.
- Manual UI walkthrough / browser-based E2E pass — this session verified all
  210 automated feature tests pass and did a code-level review of every route
  group, but did not drive the app in a real browser. Recommended before
  go-live: log in as each of the 6 roles and click through their full nav menu.

**Update 2026-07-20.** The suite is now 336 tests (325 feature, 11 unit). The
manual walkthrough is **still outstanding** — attempted this date and blocked on
the browser tooling, not on the app. It remains the single largest gap between
"the tests pass" and "someone has looked at it", and §1's four open blockers are
unchanged apart from item 4, which got stricter (see §4: a queue worker is now
required rather than optional).

## 8. Member-facing enhancements (post-cutover backlog)

Identified 2026-07-20 by reading the member self-service surface. **None of these
are cutover blockers** — the member area is functional without them, and each is
new feature work rather than a fix. They are recorded here so they are not lost
between the migration and whatever comes next.

Members can already: view a dashboard summary, edit their contact details and
photo, view their QR code, download their ID card, view/print/export service
history, view and download certificates, view trainings, and receive in-app
notifications.

### 8.1 No in-app view of assignments — highest value

A member learns they have been deployed **only by email**. There is no
`/my/assignments` route and no nav entry; the dashboard's `latest_service`
(`DashboardController::memberSummary`) shows only *past* assignments with
confirmed attendance.

This matters because the confirmation link is a signed URL that expires after
7 days and, per §2 of `CheckMaintenanceMode`'s exempt-route list, **cannot be
re-sent by the member**. A missed, spam-filtered or deleted email leaves the
member with no way to see the assignment and no way to confirm it — their only
recourse is telephoning a Testing Center admin to press Resend.

Proposed: a "My Assignments" page listing upcoming deployments (examination,
date, venue, role, status) with a Confirm action, so the operational loop does
not depend on one email arriving.

### 8.2 Requirements are visible but not actionable

`MyProctadController::profile()` returns each requirement's label, complied flag
and remarks, so members can see what is outstanding. Only staff can attach the
document, via the member details modal. A member who sees "not complied" has no
way to submit anything and must deliver it in person or by email.

The benefit is as much to Testing Center staff — who currently receive every
document by hand — as to members.

### 8.3 Requirement list asymmetry between the member and staff views

`MemberController::store` creates one row per `EligibilityRequirement` case at
member creation, so app-created members are complete. But the member profile
maps only *existing* rows, while the staff modal iterates
`EligibilityRequirement::cases()` and renders missing ones as outstanding.

A member with no requirement rows therefore sees an **empty list** while staff
see a full set of outstanding requirements. As of 2026-07-20 this affects 1 of
20 members in the development database — presumably seeded or ETL-imported.
**Re-check this after the production ETL**, which may import many members
without requirement rows.

Small and self-contained: have the member view iterate the enum the same way the
staff view does. This is the only item in §8 worth considering before cutover.

### 8.4 Evaluations are not linked from the signed-in area

The post-examination evaluation flow is public: search by name or PROCTAD ID at
`/evaluation`, resolve the assignment, then fill the form. A signed-in member
goes through that same anonymous search even though the system already knows who
they are and which assignments they attended. Prefilling for authenticated
members would remove a step and the chance of selecting the wrong record.
