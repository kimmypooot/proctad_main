# PROCTAD → Laravel 13 Migration Checkpoint

> **Purpose:** Living progress tracker. Every phase updates this file as work starts/finishes so any
> session (or a new one after an internet disconnection) can resume exactly where we left off.
> **Rule: update checkboxes + the Status Board row of the current phase before moving on.**
>
> **Reference material:**
> - `DATABASE_AUDIT.md` — audit of the legacy database (schema decisions; some superseded by the existing codebase, see "Reconciliation" below)
> - `legacy_proctad_db.sql` — legacy DB reference dump (contains NO member rows by design)
> - `C:\xampp\htdocs\proctad_legacy\` — full legacy PHP/PDO source code (its `CLAUDE.md` documents the architecture; `docs/` and audit reports inside)
> - `PROCTAD System Specification_new.docx` — official system spec (extracted text: scratchpad `spec/spec.txt`)
> - `CUTOVER_RUNBOOK.md` — Phase 9 deliverable: pre-cutover blockers (incl. an unresolved security finding), legacy parity sweep, and the step-by-step cutover sequence
>
> **Deployment target (per spec):** August 2026 CSE-PPT examination.

**Legend:** `[ ]` pending · `[x]` done · `[~]` in progress · `[!]` blocked/decision needed

---

## Status Board

| Phase | Title | Status | Last updated |
|---|---|---|---|
| R | Reconciliation & open decisions | **DONE — all 5 decided** | 2026-07-10 |
| 0 | Project scaffold & configuration | **DONE** (pre-existing) | 2026-07-10 |
| 1 | Database schema | **DONE** — 33 migrations, all ran clean | 2026-07-10 |
| 2 | Models, enums & relationships | **DONE** | 2026-07-11 |
| 3 | Staff/admin authentication | **DONE** — full traditional auth, 21 new tests | 2026-07-10 |
| 4 | Member authentication (Google OAuth) | **DONE** — member login page, hardened callback, isolation tests | 2026-07-10 |
| 5 | Seeders & legacy data ETL | **MOSTLY DONE** — command built, dry-run verified; awaits production member data | 2026-07-10 |
| 6 | Domain services (certificates, email, QR, approvals) | **DONE** — confirmation workflow + letterhead + QR/scanner + staffing tools + notifications + log pruning all done | 2026-07-10 |
| 7 | Inertia + Vue frontend | **DONE** — all planned items complete (REC/LEC grouping, settings editor, duplicate-members report) | 2026-07-10 |
| 8 | Reports & exports | **DONE** — Reports & Statistics page, Excel exports, per-member printable service history | 2026-07-10 |
| 9 | Verification, cutover prep & hardening | PARTIAL — everything doable from this dev environment is done; 4 items need production/owner access (see `CUTOVER_RUNBOOK.md`) | 2026-07-10 |

**Current phase:** 9 in progress — everything doable from this dev machine is done (see `CUTOVER_RUNBOOK.md`); 3 items remain blocked on production/owner access (ETL rehearsal with real data, securing exposed webroot files, cron setup on the real host). SMTP password rotation is owner-handled, no longer blocking. Nothing else to build in-repo; next session should check whether the owner has resolved those and update the runbook.
**Session notes (newest first):**
- 2026-07-11 (sample data seeders): Owner asked for sample data to explore the app. The existing seeders (field offices, users, members, signatories, 2 examinations, 2 trainings, email templates) predated most of this migration's later modules and left them empty. Added `ExamTypeSeeder`, `SchoolSeeder` (13 schools across 6 field offices), `NonExamPersonnelSeeder` (12 NEP across 8 personnel types incl. venue assignments + attendance), `CertificateSeeder` (uses the real `CertificateService` — genuine rendered PDFs via dompdf, not stubs — for Appreciation/Designation Order/Completion certs, split released vs. pending so the Approvals queue has something to show); rewrote `ExaminationSeeder` to attach venues+rooms per exam and vary assignment status (confirmed/pending/declined) so the confirmation workflow and staffing UI have real data too. Hit the same `WithoutModelEvents`-suppresses-ID-generator bug as the earlier Member fix, this time for `NonExamPersonnel::nep_id` — fixed the same way (explicit `generateNepId()` call in the seeder). Verified with `migrate:fresh --seed` end-to-end plus a live `php artisan serve` + curl walkthrough logged in as both `superadmin@proctad.test` and `member@proctad.test`, hitting every major page (dashboard, members, examinations incl. a specific exam's venue/room detail, certificates, NEP, schools, approvals, reports, trainings, signatories, exam types, users, email templates, letterheads, and all `/my/*` member pages) — all HTTP 200 with real seeded content. 210 tests / 1223 assertions still green; build clean. **Note:** cleanup used `taskkill /F /IM php.exe` to stop the test server, which kills *all* php.exe processes system-wide, not just this one — worth knowing on this shared machine if another PHP process was relying on staying up.
- 2026-07-11 (re-scan requested by owner — found & fixed 3 stale "coming soon" placeholders, closed Phase 2's last item): Owner asked for a fresh scan for any unimplemented phase or lingering "coming soon" UI. Sidebar nav had already been fully wired (no `#` placeholders left there — confirmed by grep). Found and fixed 3 genuinely stale dashboard placeholders that never got updated as their modules shipped: `DashboardController`'s Management-role "Certificates Issued" stat (hardcoded 0, "Module coming soon") and Field-Director "Approved This Month" stat (hardcoded 0) now query real `Certificate` counts; `Dashboard/Index.vue`'s admin quick-actions ("Generate Report", "Manage User Accounts", "Request Certificate Issuance") now link to `/reports`, `/users`, `/certificates` respectively instead of rendering a disabled "coming soon" tile. Also closed Phase 2's one remaining checklist item: audited every FK cascade in the schema and found a real risk the earlier "deferred, low priority" note had glossed over — deleting an Examination or Training cascades to silently wipe all linked service history — so added soft deletes to those two models (deliberately *not* to Certificate/ExamAssignment/TrainingAssignment/User, each of which has a unique constraint that would collide with a trashed row on re-creation; documented the reasoning in Phase 1's entry). Added the 4 remaining model factories. Verified `config/database.php` prefix wiring and the Fortify-vs-custom decision were already correctly resolved — ticked the stale checkboxes. 210 tests / 1223 assertions still green throughout (no regressions from any of this); Vite build clean.
- 2026-07-10 (later, SMTP rotation marked owner-handled): Owner confirmed they will rotate the Gmail SMTP app password themselves. Updated `CUTOVER_RUNBOOK.md` §1/§6/§7 and `MIGRATION_CHECKPOINT.md` Phase 9 to reflect this item is no longer a repo-side blocker.
- 2026-07-10 (Phase 9 — verification & cutover prep): **Found a real security exposure**: this dev machine's `C:\xampp\htdocs\` is a shared webroot serving several unrelated projects, and a full PROCTAD SQL dump (`proctad_db (11).sql`, containing real member PII and — per legacy CLAUDE.md — a plaintext SMTP password in `proctad_system_settings`) sits directly in it, alongside the entire `proctad_legacy/` source (which itself contains a dozen+ more historical dumps in `database_iterations/`). Flagged this to the owner via AskUserQuestion rather than acting unilaterally (shared machine, other unrelated projects present) — owner chose "tell me, don't touch yet," so nothing was moved or deleted; full detail and a recommended fix are in `CUTOVER_RUNBOOK.md` §2. Did a legacy feature-parity sweep against the Phase R inventory list — all 12 items confirmed implemented (re-verified "pending registrations queue" is genuinely N/A: `users.role` DB-defaults to `member` and self-registration never creates a staff account). Investigated legacy QR code format directly (`proctad_legacy/user/complete-profile.php`): confirmed old printed cards encode raw `{proctad_id}|attendance` text, never a URL, so there was never a "legacy verify link" to redirect — but added the same defensive `|attendance`-suffix stripping already used by `ScannerController::normalize()` to the public `VerifyController` route too, for defense-in-depth. Verified `php artisan schedule:list` shows all 3 scheduled commands correctly registered; confirmed no code currently implements `ShouldQueue` (queue worker not yet required, only cron for the scheduler). Started `php artisan serve` and curl-verified public pages actually render (catches Vite manifest issues the PHPUnit HTTP test client wouldn't). Wrote `CUTOVER_RUNBOOK.md` — pre-cutover blocker table, parity sweep table, and a step-by-step cutover sequence with rollback plan. Added `VerifyTest` case for the QR normalization; 210 tests / 1223 assertions total; build clean.
- 2026-07-10 (Phase 8 done — reports & exports): Researched first: legacy's `reports.php` files (per superadmin/management/field-director/testing-center panel) turned out to be almost entirely dashboard mockups with hardcoded chart data — no real filter/export backend to port, and no working PhpSpreadsheet export exists anywhere in legacy despite the checkpoint's earlier note. Extracted the actual spec docx (`PROCTAD System Specification_new.docx`, via a raw `ZipArchive`/`word/document.xml` read since no prior extraction survived) and confirmed §4.3/§5's real requirement: "Generate reports/statistics by FO, year, exam, gender" + "Exportable reports (PDF/Excel)" — no literal school-readiness or training-stats requirement, so those two were designed fresh rather than ported. Installed `maatwebsite/excel` (pulls in PhpSpreadsheet) — first new Composer dependency this migration. Built: `ReportController` (region-wide roles filter by any FO; FO-scoped roles hard-locked to their own regardless of query params) with a `Reports/Index.vue` page — summary cards, breakdowns by Field Office/Gender/Exam Type/Year (aggregated in PHP via Collection groupBy against the actual filtered assignment set, not raw SQL, for MySQL/SQLite portability), training attendance stats, and a venue "staffing readiness" view (rooms with ≥1 assignment vs total rooms per venue — new concept, not a legacy port). Three Excel export endpoints (`MembersExport`, `ServiceRecordsExport`, `TrainingAttendanceExport`, all `FromCollection`/`WithHeadings`/`WithMapping`) reuse the same filter resolution as the report page. Per-member printable service history: a plain Blade view (`members/service-history-print.blade.php`, not Inertia — opens in a new tab with a Print button) plus an Excel export reusing `ServiceRecordsExport` with a `memberId` filter. Nav wired to `/reports` for all 5 non-member roles (was a dead `#` placeholder everywhere). Tests: `ReportTest` (13 — FO-scope lockout is the one that matters most: a fo_admin passing a different `field_office_id` in the query string still only sees their own data); 209 tests / 1212 assertions total; build clean.
- 2026-07-10 (Phase 7, remaining items done — Phase 7 now fully complete): (1) REC/LEC committee grouping: `ExaminationController::show` now annotates each role and assignment with `group`/`group_label` (`ExamRoleGroup`); `SelectInput.vue` gained optgroup support (auto-detected when every option carries a `group`) so the assign/edit/reassign role dropdowns are now sectioned by REC/LEC/Special/School; `Examinations/Show.vue`'s assignment table gained a "Group by committee" toggle that sections the table into the same four groups with per-group counts — no new backend capability needed, this was purely presentation as the checkpoint predicted. (2) General settings editor: `SettingController` (super_admin/esd_admin, `SettingPolicy`) + `Settings/General/Index.vue` — full CRUD over the `settings` key-value table that already had `Setting::get()/set()` with cache invalidation on save (Phase 2) but no admin UI; validated key format (`[a-z0-9_.]+`, unique), typed values (string/number/boolean/json). (3) Duplicate-members report: ported `duplicate-members-report.php` verbatim in logic — `DuplicateMembersController` (super_admin only, matches legacy's access scope), flags members colliding on exact email or case/whitespace-normalized first+last name (`UPPER(TRIM(...))`, portable across MySQL/SQLite), read-only, no merge/delete action, exactly like the legacy page's explicit "review manually" stance. Tests: `ExaminationTest` (+1), `SettingTest` (8), `DuplicateMembersReportTest` (3); 199 tests / 1115 assertions total; build clean.
- 2026-07-10 (Phase 6, notifications + log pruning done — Phase 6 now fully complete): Built an in-app notification bell using Laravel's native `Notifiable`/database-notifications system (no new table needed — `notifications` was already scaffolded in Phase 1). Three real trigger events, chosen because they're genuine operational needs surfaced across Phases 3-6, not just a port of legacy's narrower "pending approvals" polling: `CertificatePendingApproval` (fires from `CertificateService::generatePending` to the certificate type's approver role, FO-scoped for Field Director, plus Super Admin as fallback — mirrors `CertificatePolicy::decide`'s existing logic so there's no drift between who *can* approve and who's *notified* to approve), `CertificateDecided` (fires from `release`/`disapprove` to `certificate.requestedBy`), `AssignmentDeclined` (fires from `AssignmentConfirmationController::store` on decline, to the assignment's own FO's `fo_admin`+`field_director` — the people who need to re-staff). `HandleInertiaRequests` shares `notifications.unread_count` + latest 8 items as a lazy prop (same pattern as existing `flash`); `NotificationController@read`/`@readAll` mark-as-read (ownership-scoped via `$user->notifications()`, so one user can't mark another's as read). Bell UI added to `DashboardLayout.vue` header (new `bell` icon in `AppIcon.vue`), dropdown with unread badge, mark-all-read, click-through navigation. Log pruning: `proctad:prune-logs` (scheduled monthly) deletes audit_logs/assignment_confirmations older than 2 years, email_logs older than 6 months, read notifications older than 90 days — retention windows are a judgment call (documented in the command's docblock), not a spec requirement, since the spec doesn't set one. 10 new tests (`NotificationTest`, `PruneOldLogsTest`), all green; 187 tests / 1039 assertions total; build clean.
- 2026-07-10 (Phase 6, staffing tools done): Read all four legacy AJAX endpoints (randomize-staffing.php, force-reassign.php, revoke-designation.php, bulk-resign-certificates.php) to port their exact business rules — especially the Supervising Examiner room-anchoring algorithm and force-reassign's "preserve pipeline state, only fix logistics" philosophy. Built StaffingRandomizer service + 4 controller actions + full UI wiring on Examinations/Show.vue and Certificates/Index.vue. 17 new tests, all green; 177 tests / 993 assertions total; build clean.
- 2026-07-10 (Phase 6, QR/scanner done): Found the training-attendance scanner path was dead code (controller supported it, Vue page never exposed it) and fixed it. Rebuilt QR normalization to handle NEP payloads and legacy pipe-suffixed codes without crashing. Added full NEP scanning (identity lookup + venue-scoped attendance, FO-scoped, idempotent). 8 new tests, 5 existing scanner tests still green (no regressions). 160 tests / 956 assertions total; build clean.
- 2026-07-10 (Phase 7, user accounts admin): Added `users.is_active` and enforced it at both login paths. Built full user management: admin-created accounts get a random password + emailed reset link (admin never handles a password directly), role/FO/active editing, admin-triggered reset, self-deactivation guard. 8 new tests including a real login-rejection check for deactivated accounts. 152 tests / 877 assertions total; build clean.
- 2026-07-10 (Phase 7, exam types + email templates admin): Built CRUD for exam types (blocks deleting a type in use) and a runtime email-template editor restricted to super_admin/esd_admin, closing the loop on the owner's explicit "templates must be editable" requirement from Phase 3. Confirmed self-registration only ever provisions member-role accounts, so there's no hidden "pending staff approval" gap — staff accounts are seeder/ETL-provisioned by design. 9 new tests, all green; 144 tests / 848 assertions total; build clean.
- 2026-07-10 (Phase 7, D3 done): Built the full Non-Exam Personnel module — registry CRUD with photo/ID-card/QR (mirrors Member module patterns), venue assignment, and a manual attendance toggle integrated into the examination venue cards. 14 new tests, all green; 135 tests / 807 assertions total; build clean.
- 2026-07-10 (Phase 7, D2 done): Built the full Schools + Venues + Rooms admin layer — schools CRUD, attach/detach schools as examination venues, per-venue room management, and wired venue/room selection into the assign/edit flows on Examinations/Show.vue. This was the actual blocker for the assignment-confirmation emails ever showing real venue/room data. 15 new tests, all green; 121 tests / 747 assertions total; build clean.
- 2026-07-10 (Phase 7, partial): Owner chose letterhead compositing (weigh-in) — implemented in Phase 6 first (see below), then swept Phase 7 and found 5 controller actions rendering Inertia components that didn't exist on disk (Trainings index/show, Certificates index, Approvals index, My/Certificates, My/Trainings) — these would have 500'd in production. Built all 5 pages plus sidebar nav wiring for every non-member role. 13 new tests, all passing on first run against the previously-broken routes. 106 tests / 701 assertions total; build clean.
- 2026-07-10 (Phase 6, partial): Built the full assignment confirmation workflow (D5) end-to-end — signed-URL emails, public confirm/decline page, reminder + expiry scheduled commands, admin-editable template rendering + email logging service, UI wiring. Verified approval routing (spec §2.3) and designation-order request flow were already correctly implemented — no changes needed. Found and fixed a real pre-existing bug: seeded `migrate:fresh --seed` failed against MySQL because `WithoutModelEvents` suppressed the member ID generator. 85 tests / 563 assertions green; Vite build clean; fresh migrate+seed verified end-to-end.
- 2026-07-10 (Phases 4+5): Phase 4 — `/member/login` Google-first page, callback hardening (locked check, audit, last_login), GuardIsolationTest; 72 tests green, build passes. Phase 5 — `proctad:migrate-legacy` ETL command + `legacy` connection; reference dump imported to local `proctad_legacy_db`; dry-run passes end-to-end (see Phase 5 section for counts). Two schema fixes from real data: audit_logs.action widened to 50; exam_rooms per-venue room-number unique dropped (legacy reuses numbers per designation). Awaiting full production copy (with members) for final ETL rehearsal.
- 2026-07-10 (Phase 3 done): Implemented full staff auth — username-or-email login, rate limiting + persistent lockout (5 fails → 15 min), forgot/reset password via Password broker (anti-enumeration, throttled), change-password with current-password check, `EnsurePasswordIsChanged` middleware on the auth group, `Password::defaults()` min8+letters+numbers, all auth events audited with IP, `last_login_at`. Vue: Login.vue reworked, ForgotPassword/ResetPassword/ChangePassword pages added; flash `status` added to Inertia shared props. Full suite green: **65 tests / 507 assertions** (21 new auth tests; DashboardTest login payload updated to `login` field). `npm run build` verified — Vite compiles all pages including the 3 new ones.
- 2026-07-10 (later): Owner decided D1–D5 (single guard / venues yes / NEP yes / all 17 roles / confirmation workflow yes). Implemented Phase 1 remainder: 15 new migrations (users auth columns, exam_types, schools, examination_school, exam_rooms, assignment venue+confirmation columns, assignment_confirmations, NEP ×3, settings, letterheads, email_templates, email_logs, notifications) — `php artisan migrate` clean. Phase 2: ExamRole expanded to 17 with ExamRoleGroup; new enums AssignmentStatus/ConfirmationAction/PersonnelType; 12 new models (School, ExaminationSchool, ExamRoom, AssignmentConfirmation, NonExamPersonnel, NepAssignment, NepAttendance, ExamType, Setting, Letterhead, EmailTemplate, EmailLog); Examination/ExamAssignment/Member/User updated (venues, status workflow, serviceHistory(), auth columns). Verified: DB smoke test (full exam→venue→room→assignment→confirmation graph + NEP + settings + letterhead + template render) passes; existing suite 43 tests / 437 assertions green.
- 2026-07-10: Discovered `C:\xampp\htdocs\proctad` is already a substantial Laravel 13 + Inertia + Vue 3 + Tailwind 4 rebuild (18 migrations, ~30 models/controllers/policies, 55 Vue components/pages). Checkpoint rebased onto actual state. Legacy source received and mapped; official spec extracted.

---

## Phase R — Reconciliation & open decisions

The existing codebase made design choices that differ from `DATABASE_AUDIT.md`. Reconciled verdicts:

### Already decided by the codebase (accept — do NOT rework)
- [x] **Single `users` table + role enum** (`super_admin, esd_admin, management, field_director, fo_admin, member`) instead of the audit's rebuilt-users plan. Matches the spec's 5 access roles better than the legacy 3-value enum. Members link via `members.user_id` (audit decision "1:1 column not pivot" — already implemented).
- [x] **Custom `audit_logs` + `Auditable` trait** instead of spatie/activitylog. Works; keep.
- [x] **`exam_assignments` carries `performance_rating`** — a spec requirement (§1.1, §2.2) the legacy DB never implemented. Keep.
- [x] **`training_assignments`** (participation + attendance confirmation in one row) replaces legacy `training_attendance` + logs + triggers. Simpler; keep, but see Phase 6 QR logging task.
- [x] Certificates, signatories, member_requirements, examinations tables exist in simplified spec-aligned form.

### Decisions — RESOLVED by owner 2026-07-10
- [x] **D1. Auth guards → single guard.** One `users` table + `role=member`, policies enforce isolation. Members get a distinct login page (Google-first); staff routes reject `member` role. (Member login page: Phase 4 task.)
- [x] **D2. Exam venues → yes.** Implemented: `schools`, `examination_school`, `exam_rooms` tables; `exam_assignments.examination_school_id`/`exam_room_id` nullable FKs.
- [x] **D3. NEP module → yes.** Implemented: `non_exam_personnel`, `nep_assignments`, `nep_attendances` tables + models/enums. UI in Phase 7.
- [x] **D4. Exam roles → all 17.** `ExamRole` enum expanded with `ExamRoleGroup` (Regional/TestingCenter/Special/School) and `inGroup()` helper. REC/LEC configuration UIs in Phase 7.
- [x] **D5. Confirmation workflow → yes, required.** Schema in place (`exam_assignments` status/confirmation columns + `assignment_confirmations` audit log). Signed-URL flow, mailables, reminders: Phase 6.

### Facts learned from legacy source (for whoever resumes)
- Legacy panels: `admin/superadmin/`, `admin/testing-center/` (field_office), `admin/management/`, `admin/field-director/`; member portal in `user/`; PWA QR scanner in `scanner/` (incl. live attendance map via SSE).
- Approval rules (spec §2.3): **Appreciation → Management approves; Appearance & Designation Orders → Field Director/Caretaker approves.** All approvals audited.
- 4 PDF builders (appearance/appreciation/completion/designation) using FPDF+FPDI letterhead overlay → new app uses dompdf; letterhead concept must carry over.
- Admin roles come from IMIS platform tables (`imis_system_access`, `imis_access_roles`) NOT in the proctad dump — ETL for users must read those too (or roles assigned manually post-import).
- Feature inventory to reach parity: staffing randomization, force-reassign, revoke designation, bulk resign certificates, duplicate-members report, pending registrations queue, member/attendance/service-history Excel exports, ID-card generation (member + NEP), letterhead management, exam types CRUD, schools CRUD, notifications, config editor.

---

## Phase 0 — Project scaffold & configuration — **DONE (pre-existing)**

- [x] Laravel 13 + Inertia + Vue 3 + Tailwind 4 + Vite (this repo)
- [x] `DB_PREFIX=proctad_` in `.env` (verify `config/database.php` honors it)
- [x] Packages: socialite, dompdf, chillerlan/php-qrcode, html5-qrcode (front), qrcode (front)
- [x] Database sessions/cache/queue configured
- [x] Verified 2026-07-11: `config/database.php`'s `mysql` connection (the one actually used, `DB_CONNECTION=mysql`) reads `'prefix' => env('DB_PREFIX', '')` correctly
- [x] Decided: extended the existing custom auth controllers rather than adopting Fortify (done throughout Phase 3) — no framework swap needed

---

## Phase 1 — Database schema — PARTIAL

Existing migrations (18): users(+profile/role/google_id/avatar), cache, jobs, field_offices, members, member_requirements, signatories, audit_logs, examinations, exam_assignments, trainings, training_assignments, certificates.

Completed 2026-07-10 (migrations `2026_07_10_1000xx`, all ran clean):
- [x] `schools` table (venue registry per field office)
- [x] `examination_school` (exam venues, unique exam+school) + `exam_rooms` (unique venue+room_number)
- [x] `exam_assignments`: `examination_school_id`, `exam_room_id` nullable FKs + confirmation workflow columns (`status`, `confirmation_sent_at`, `responded_at`, `decline_reason`)
- [x] NEP tables: `non_exam_personnel`, `nep_assignments`, `nep_attendances` (all FK-constrained — fixes legacy's missing FKs)
- [x] `exam_types` lookup + `examinations.exam_type_id` nullable FK (legacy `type` string kept during transition)
- [x] `assignment_confirmations` append-only audit log (action, ip, user_agent, metadata JSON)
- [x] `settings` key-value table
- [x] `letterheads` table
- [x] `email_templates` (runtime-editable) + `email_logs`
- [x] `notifications` (Laravel native)
- [x] Users: `username` (unique), `must_change_password`, `failed_login_attempts`, `locked_until`, `last_login_at`
- [x] Soft-deletes review (2026-07-11): audited every FK cascade in the schema. `exam_assignments.examination_id` and `training_assignments.training_id` both `cascadeOnDelete()` — an accidental Examination/Training delete silently wipes all linked service history and the certificate trail behind it. Added `deleted_at` to `examinations` and `trainings` (migration `2026_07_11_000001`) + `SoftDeletes` trait on both models; soft-delete means the cascade FK never actually fires (no real row delete), so this closes the risk with zero behavior change for intentional deletes. **Deliberately left `Certificate`, `ExamAssignment`, `TrainingAssignment`, and `User` without soft deletes** — each has a unique constraint tied to the same natural key used for re-creation (`certificates` on `[type, certifiable_type, certifiable_id]`, `exam_assignments`/`training_assignments` on `[..._id, member_id]`, `users` on `email`/`username`); soft-deleting those would let a trashed row block re-issuing/re-assigning the same combination without also reworking those constraints (MySQL has no partial-unique-index support to exclude trashed rows) — `User` already has the `is_active` deactivation flag from Phase 7, which covers the real operational need without that risk. Verified: 210 tests / 1223 assertions still green (no existing test depended on hard-delete semantics for either model).

---

## Phase 2 — Models, enums & relationships — PARTIAL

Done: User, FieldOffice, Member, MemberRequirement, Signatory, AuditLog(+Auditable), Examination, ExamAssignment, Training, TrainingAssignment, Certificate. Enums: UserRole, MemberStatus, EligibilityRequirement, ExamRole(3), PerformanceRating, TrainingType, CertificateType, CertificateStatus.

Completed 2026-07-10:
- [x] `ExamRole` expanded to all 17 legacy roles + `ExamRoleGroup` enum (Regional/TestingCenter/Special/School) with `group()`/`inGroup()`
- [x] New enums: `AssignmentStatus`, `ConfirmationAction`, `PersonnelType`
- [x] Models: School, ExaminationSchool, ExamRoom, AssignmentConfirmation (append-only), NonExamPersonnel, NepAssignment, NepAttendance, ExamType, Setting (cached get/set), Letterhead (activate()), EmailTemplate ({placeholder} render()), EmailLog
- [x] Examination: `examType()`, `venues()`, `schools()` relationships; ExamAssignment: venue/room/status/confirmations + model-default `status=pending`
- [x] `Member::serviceHistory()` relationship (attendance-confirmed assignments, newest exam first)
- [x] Verified: DB smoke test of full object graph passed; existing test suite green (43 tests / 437 assertions)
- [x] Factories (2026-07-11): 11 of the 12 Phase-2 models now have factories, added incrementally as each feature needing them was built (School/ExaminationSchool/ExamRoom/NonExamPersonnel/NepAssignment/ExamType/Letterhead from earlier phases; `AssignmentConfirmationFactory`, `NepAttendanceFactory`, `EmailTemplateFactory`, `EmailLogFactory` added this session to close the gap). `Setting` intentionally has none — it's a key-value model with no `HasFactory` trait by design; tests correctly use its own `Setting::set()`/`get()` helpers instead of a factory. Relationship feature tests exist throughout (every module's test suite exercises its relationships via real HTTP requests, not isolated unit tests) — no separate relationship-test file was ever needed.

Phase 2 is now fully complete.

---

## Phase 3 — Staff/admin authentication — PARTIAL ⚠️ owner-requested features missing

Done: `/login` (custom AuthenticatedSessionController), `/register` (self-registration → pending-registrations concept), logout, role middleware `EnsureUserHasRole`, policies.

Completed 2026-07-10:
- [x] **Forgot password / reset flow** — PasswordResetLinkController + NewPasswordController (Laravel Password broker, hashed tokens, throttle:5,1), Vue pages `Auth/ForgotPassword.vue` + `Auth/ResetPassword.vue`; anti-enumeration (same response for unknown emails); reset also clears `must_change_password`/lockout and audits `password_reset`
- [x] Login by **username OR email** — single `login` field, resolved by email-format detection; `Auth/Login.vue` updated
- [x] Login throttling (RateLimiter 5/min per identifier+IP) + persistent lockout (5 consecutive failures → `locked_until` +15 min; counters on users table)
- [x] `must_change_password` → `EnsurePasswordIsChanged` middleware (alias `password.changed`, applied to the auth route group; allows only password.edit/update + logout) + `Auth/ChangePassword.vue` with forced-mode banner
- [x] Change password (`/change-password`, current password required, must differ); `Password::defaults()` = min 8 + letters + numbers (AppServiceProvider)
- [x] Auth events audited to audit_logs: login, logout, login_failed, account_locked, password_reset, password_changed (with IP)
- [x] `last_login_at` tracked on successful login; flash `status` added to shared Inertia props
- [x] Feature tests: AuthenticationTest (8), PasswordResetTest (7), ForcePasswordChangeTest (6)

Remaining:
- [ ] Separate member login page (Google-first) — moved to Phase 4 per D1

---

## Phase 4 — Member authentication — PARTIAL

Done: Google OAuth redirect/callback (single guard), members.user_id link, member self-service pages (`/my/*`), dual-role support noted in routes.

Completed 2026-07-10:
- [x] `/member/login` Google-first member login page (`Auth/MemberLogin.vue`), cross-linked with staff login; route `member.login`
- [x] Callback hardening: pre-registered-only (already present, verified by test), locked-account check added, `last_login_at` updated, login audited with `method: google`, error redirects now land on member login page
- [x] Guard-isolation tests (`GuardIsolationTest`): member role 403s on all staff routes, self-service routes OK, fo_admin 403s on region approval routes; Google tests extended (locked account, audit)
- [x] Verified: 72 tests / 527 assertions green; Vite build passes

---

## Phase 5 — Seeders & legacy data ETL — MOSTLY DONE (2026-07-10)

Built: `php artisan proctad:migrate-legacy {--dry-run}` (`app/Console/Commands/MigrateLegacyData.php`), reading from a new `legacy` DB connection (`LEGACY_DB_*` env; local default `proctad_legacy_db`, imported from the reference dump with FK checks off). Whole import runs in one transaction; `--dry-run` rolls back and prints the reconciliation report. Auditable model events suppressed during import; legacy timestamps preserved.

- [x] Legacy connection in `config/database.php` (no prefix, non-strict for zero-dates); dump imported locally
- [x] Users: dedupe by email (rbuy/mmdelacruz merged, mapped to kept account so FKs resolve), bcrypt hashes carried over, shared-default-hash accounts flagged `must_change_password`, roles mapped (superadmin→super_admin, admin→esd_admin, FO-pivot users→fo_admin, else member), FO from legacy pivot
- [x] Reference data: field offices (8), exam types (2), schools (14), signatories (8), examinations (2, titled from exam type + date), venues/examination_school (12), exam rooms (571 — required relaxing the per-venue room-number unique; legacy reuses numbers per designation), trainings (14)
- [x] Members import (proctad_id codes preserved verbatim — QRs in the wild) + user link from user_member; skips logged for missing email/FO
- [x] Eligibility wide-table → member_requirements rows (7-way mapping to EligibilityRequirement enum values)
- [x] service_history (+school_assignments room join) → exam_assignments: role label→ExamRole mapping w/ Proctor fallback, confirmation status mapping, attendance_confirmed_at, unique member+exam dedupe
- [x] training_attendance → training_assignments; certificates (type map incl. designation→designation_order, certifiable morph to ExamAssignment/TrainingAssignment, status map, signatory snapshots, unique-dedupe)
- [x] NEP registry (36) + assignments (34) + attendances (13) via exam|school→venue pair map
- [x] settings (SMTP excluded by design), letterheads (+active flag from config), email templates (2), email logs (81), security_logs → audit_logs (656; action widened to 50 chars)
- [x] Dry-run verified on local legacy copy: 59 users imported/2 merged, all reference data 0-skip; member-dependent tables correctly skip-all (253 assignments / 611 training rows / 1055 certificates) because the reference dump has no member rows
- [ ] **Rehearsal on full production copy (WITH members) — the real acceptance test.** Point `LEGACY_DB_DATABASE` at the production copy and run `--dry-run`; member-dependent counts should flip from skipped to imported. Document results here.
- [ ] Optional: fresh-install seeders (exam types, requirement list, superadmin) — current seeders cover dev data

---

## Phase 6 — Domain services — PARTIAL

Done: CertificateService, CertificateReleased mailable, certificate approval controller (approve/disapprove), verify endpoints (member + certificate), ScannerController, MemberIdCard support class.

Verified already correct (no change needed): approval routing per spec §2.3 — `CertificateType::approverRole()` + `CertificatePolicy::decide()` already implement Appreciation→Management, Appearance/DesignationOrder→Field Director scoped to their own FO, Super Admin fallback; `assignments.designation-order` route + `requestDesignationOrder()` already queue a pending certificate correctly.

Completed 2026-07-10 — assignment confirmation workflow (D5):
- [x] `App\Mail\TemplatedMail` — generic Mailable rendering an `EmailTemplate` (admin-editable, owner requirement) with {placeholder} substitution
- [x] `App\Services\NotificationMailer::send()` — sends a templated email and always writes an `email_logs` row (sent or failed with error message)
- [x] `AssignmentConfirmationController`: `send()` (staff, policy-gated via existing `ExamAssignmentPolicy::update`) mints a `URL::temporarySignedRoute` good for 7 days (no stored plaintext token, per audit recommendation), emails via the `assignment_confirmation` template, sets status=pending + `confirmation_sent_at`, logs `assignment_confirmations` action=sent; `show()`/`store()` (public, `signed` middleware, no login) render `Assignments/Confirm.vue` and record confirm/decline + reason, guarded against double-response
- [x] `proctad:send-assignment-reminders` (daily 08:00) — reminds once, 3–7 days after send, via `assignment_reminder` template; `proctad:expire-pending-assignments` (daily 01:00) — flips stale (7+ day) pending assignments to `expired`; both scheduled in `routes/console.php`
- [x] `EmailTemplateSeeder` seeds the two templates (ported verbatim from the legacy dump's `assignment_confirmation`/`assignment_reminder` codes/variables) for fresh installs; the legacy ETL's real customized copies overwrite these when it runs
- [x] `Examinations/Show.vue`: assignment status badge + Send/Resend Confirmation action wired to the new endpoint
- [x] Tests: `AssignmentConfirmationTest` (13 cases — send/permission/no-email, signed-link show, tampered-signature 403, confirm, decline+reason, decline requires reason, double-response guard, reminder timing + no-duplicate, expiry, template rendering)
- [x] **Bug found & fixed while verifying:** `DatabaseSeeder`'s `WithoutModelEvents` trait silently suppressed `Member`'s `creating` hook that generates `proctad_id`, so `migrate:fresh --seed` against real MySQL failed (`proctad_id` has no DB default). Fixed by having `MemberFactory` set `proctad_id` explicitly rather than relying on the event. Verified: fresh migrate+seed now succeeds end-to-end; full suite still 85/85.

Completed 2026-07-10 — letterhead compositing (owner decision: composite onto uploaded image, matching legacy visual output):
- [x] `LetterheadPolicy` (super_admin/esd_admin only, region-wide asset per legacy `admin/superadmin/letter-head.php`) + `LetterheadController` (index/store/activate/destroy/preview, files on `local` disk under `letterheads/`, served through a Gate-checked route like `MemberController::photo`)
- [x] `Settings/Letterheads/Index.vue` admin page (upload with optional immediate-activate, thumbnail grid, activate/delete) + sidebar nav entry for esd_admin/super_admin
- [x] `CertificateService::letterheadDataUri()` reads `Letterhead::active()` and base64-embeds it (same data-URI technique already used for the QR code); `certificate.blade.php` renders it full-bleed behind the content and drops the self-drawn header/frame when a letterhead is active, **falling back to the original self-contained design when none is active or the file is missing** — certificate generation never breaks on a missing/unset letterhead
- [x] Tests: `LetterheadTest` (8 — upload+activate, single-active-enforced, fo_admin forbidden, delete removes file, preview streamed) + `CertificateLetterheadTest` (3 — renders with no letterhead, composites a real active one through an actual dompdf render, missing file falls back gracefully); `LetterheadFactory` added
- [x] Verified: 93 tests / 581 assertions green; Vite build clean

Completed 2026-07-10 — QR/scanner: NEP support + legacy payload compatibility:
- [x] Found and fixed a real pre-existing bug: `ScannerController` computed `trainingId` and returned training options, but `Scanner/Index.vue` never declared the prop or rendered a training picker — training attendance-by-QR was dead, unreachable code. Added an Examination/Training mode toggle so both flows are actually usable.
- [x] `ScannerController::normalize()` rewritten to a typed resolver: strips `/verify/{id}` URLs (unchanged), recognizes `NEP:{id}` payloads (case-insensitive) as non-exam personnel, strips legacy `|attendance`-style suffixes still on old printed QR stock before falling back to a member-code lookup, and never throws on unresolvable legacy formats (e.g. bare `'7|attendance'`) — reports "not found" instead of erroring, per the audit's compatibility goal
- [x] NEP scanning added end-to-end: scanning a `NEP:` code resolves the person (FO-scoped like members); when an Examination + Venue are selected, a scan calls the same present/idempotent attendance logic as the manual toggle (`confirmNepAttendance`, mirrors `NepAssignmentController::markAttendance`) and records `scan_method: 'qr'`; without a venue selected it prompts for one instead of silently doing nothing
- [x] `Scanner/Index.vue`: added the venue selector (populated from the selected examination's venues), a distinct NEP result panel, and an Examination/Training mode toggle
- [x] Tests: `ScannerNepAndCompatibilityTest` (8 — NEP resolves, attendance recorded once, venue-required prompt, not-assigned-to-venue, FO-scope rejection, legacy pipe-suffix stripped and resolved, bare numeric legacy code doesn't crash, lowercase `nep:` prefix recognized); existing `ScannerTest` (5) still green — no regressions
- [x] Verified: 160 tests / 956 assertions green; Vite build clean

Completed 2026-07-10 — staffing tools (randomize/force-reassign/revoke/bulk-resign), ported from legacy's four superadmin-only AJAX endpoints:
- [x] `StaffingRandomizer` service + `StaffingController` (`randomize`/`clear`): mirrors legacy's per-role independent shuffle + sequential room fill exactly, including the Supervising Examiner group-anchor logic (SE #1→room 1, SE #2→room 6, ... for the default group size 5); `scope=all` clears existing room links first, `scope=unfilled` only fills empty slots and never disturbs already-assigned rooms; FO-scoped, blocked when the venue has no rooms
- [x] `ExamAssignmentController::forceReassign`: admin override that changes role + venue while explicitly preserving confirmation status (unlike the general Edit form) — ported from force-reassign.php's "logistics only, pipeline state untouched" philosophy; clears the room link (must re-randomize) and blocked once the examination's date has passed (our schema has no legacy `status` enum, so exam_date-in-the-past is the equivalent guard); every override logs an `assignment_confirmations` row with action=admin_override and before/after metadata
- [x] `ExamAssignmentController::bulkRevoke`: super_admin-only bulk delete bypassing the normal per-assignment authorization (the "pipeline-lock bypass" from revoke-designation.php), audited with the full revoked list
- [x] `CertificateService::resign()` + `CertificateController::bulkResign`: super_admin-only, swaps the signatory on a batch of **released** certificates and regenerates each PDF; unlike legacy (which explicitly skipped `designation` type certificates due to per-type PDF-overlay geometry), every certificate type is supported here since the unified Blade template has no such geometry to account for; non-released certificates in the batch are skipped and reported, not silently mutated
- [x] UI: Examinations/Show.vue gained per-venue "Randomize All / Fill Unassigned Only / Clear Room Staffing" controls, a "Force Reassign" action per assignment (distinct modal explaining status preservation), and bulk-select checkboxes + a revoke bar (super_admin only); Certificates/Index.vue gained bulk-select checkboxes on released certificates + a re-sign modal with signatory picker (super_admin only)
- [x] Tests: `StaffingRandomizerTest` (7 — fills every room, `all` clears stale links, `unfilled` leaves filled rooms untouched, SE anchoring verified against exact room IDs, clear-all, FO-scope rejection, no-rooms error), `ExamAssignmentOverrideTest` (6 — reassign preserves status + logs override, blocked for past exams, FO-scope rejection, venue-must-match-examination validation, bulk revoke deletes + audits, non-super-admin forbidden), `CertificateBulkResignTest` (4 — resign updates signatory + regenerates PDF, pending certs skipped not mutated, inactive signatory rejected, non-super-admin forbidden)
- [x] Verified: 177 tests / 993 assertions green; Vite build clean

Completed 2026-07-10 — notifications + log pruning (Phase 6 now fully done):
- [x] In-app notification bell (`Illuminate\Notifications` database channel, `notifications` table from Phase 1) — `CertificatePendingApproval`, `CertificateDecided`, `AssignmentDeclined` notification classes; shared Inertia prop + `DashboardLayout.vue` bell dropdown; `NotificationController@read`/`@readAll`
- [x] `proctad:prune-logs` (scheduled monthly) — audit_logs/assignment_confirmations 2yr, email_logs 6mo, read notifications 90d
- [x] Tests: `NotificationTest` (9), `PruneOldLogsTest` (1); 187 tests / 1039 assertions green; build clean

---

## Phase 7 — Inertia + Vue frontend — **DONE**

Done: public site (Home/About/Benefits/Qualifications/ApplicationProcess/Faqs/News/Contact/Privacy/Terms/Maintenance/NotFound), auth pages (Login/Register), dashboard, Members module (Index/Show/Create/Edit + requirements + ID card + QR), Examinations (Index/Show + assignments), Signatories, AuditLogs, Scanner, My/* member portal (Profile/QrCode/ServiceHistory), Verify pages, component library (~20 base components).

Completed 2026-07-10 — **found 5 controller actions with no matching Vue page (would 500 in production)** and built all of them:
- [x] `Trainings/Index.vue` (list + create/edit modal, mirrors Examinations/Index pattern) + `Trainings/Show.vue` (participants, attendance edit, remove, "Mark Completed" confirmation flow that explains the auto-issued Certificates of Completion)
- [x] `Certificates/Index.vue` (paginated, status/type filters, download link gated by `downloadable` flag from the controller)
- [x] `Approvals/Index.vue` (pending queue with approve / disapprove+reason modal, used by Management, Field Director, Super Admin per spec §2.3 routing — verified via test that a Field Director only sees Appearance/Designation for their own FO and Management only sees Appreciation)
- [x] `My/Certificates.vue` + `My/Trainings.vue` (member self-service, same empty-state pattern as the existing `My/ServiceHistory.vue`)
- [x] Sidebar nav updated across all 5 non-member roles: dead `#` placeholders for Trainings/Certificates/Approvals replaced with real links; new Letterheads link added (esd_admin/super_admin)
- [x] Tests: `TrainingPagesTest` (4), `CertificatePagesTest` (5 incl. FO-scoping and type-routing checks), `MyCertificatesTrainingsTest` (4) — **all 13 passed on first real run**, confirming these routes were previously broken and are now fixed
- [x] Verified: 106 tests / 701 assertions green; Vite build clean

Completed 2026-07-10 — Schools/venues/rooms management UI (D2):
- [x] `SchoolPolicy` (super_admin/esd_admin any; fo_admin own FO only) + `SchoolController` (index/store/update/destroy) + `Schools/Index.vue` (mirrors Signatories/Index pattern) + nav links (fo_admin/esd_admin/super_admin)
- [x] `ExaminationSchoolPolicy` + `ExamVenueController` (attach/detach a school as an examination venue, unique-per-exam enforced, FO-scoped) + `ExamRoomController` (add/update/remove rooms per venue, FO-scoped via the venue's school)
- [x] `ExaminationController::show` now returns `venues` (with nested rooms) and `availableSchools` (unattached, FO-scoped); `Examinations/Show.vue` gained a full Venues & Rooms management section (attach/detach venues, add/remove rooms inline)
- [x] `ExamAssignmentController` (store + update) now accepts optional `examination_school_id`/`exam_room_id`, validated to belong to the given examination/venue respectively; assign form and edit modal in `Examinations/Show.vue` gained cascading Venue → Room selects; assignment table shows Venue/Room column — **this also means the assignment-confirmation email (Phase 6) can now actually display a real venue/room instead of always showing "—"**
- [x] Tests: `SchoolTest` (4), `ExamVenueTest` (6), `ExamAssignmentVenueTest` (5 incl. cross-venue room rejection, cross-examination venue rejection) — all passing; `SchoolFactory`, `ExaminationSchoolFactory`, `ExamRoomFactory` added
- [x] Verified: 121 tests / 747 assertions green; Vite build clean

Completed 2026-07-10 — Non-Exam Personnel module (D3):
- [x] `NonExamPersonnel` model: auto-generated `nep_id` (booted hook, `NEP-CSCRO8-` + 6-char unambiguous code, mirrors Member's generator) + `name` accessor
- [x] `NonExamPersonnelPolicy` (view: any staff or FO-scoped own-office; manage: super_admin/esd_admin any, fo_admin own office) + Store/UpdateNonExamPersonnelRequest (all fields optional except name/sex/type — matches legacy's sparse NEP data)
- [x] `NonExamPersonnelController` full resource (index w/ search+type+FO filters+pagination, create, store, show, edit, update, destroy, photo) — mirrors MemberController exactly
- [x] `NepIdCard` support class + `NepIdCard.vue` component (own "Non-Exam Personnel" branding distinct from the member card; QR value `NEP:{nep_id}` for scanner parsing, not a public verify URL since NEP has no self-service portal)
- [x] Pages: `NonExamPersonnel/Index.vue`, `Create.vue`, `Edit.vue`, `Show.vue` (with ID card + print), `Partials/NonExamPersonnelForm.vue`
- [x] `NepAssignmentPolicy` + `NepAssignmentController` (attach/detach NEP to an examination venue, unique-per-venue enforced, FO-scoped; `markAttendance` toggles a `NepAttendance` row, idempotent)
- [x] `ExaminationController::show` extended with `nep_assignments` per venue (name, id, present flag) + `availableNep`; `Examinations/Show.vue` venue cards gained an inline NEP roster with add/remove and a click-to-toggle attendance chip
- [x] Nav links added (fo_admin/esd_admin/super_admin)
- [x] Tests: `NonExamPersonnelTest` (7 — CRUD, FO-scoping both directions, photo upload/retrieval, ID card data, soft delete) + `NepAssignmentTest` (7 — assign/duplicate-reject/FO-scope-reject/remove/attendance-toggle-both-ways/no-duplicate-on-repeat/examination-page-integration)
- [x] Verified: 135 tests / 807 assertions green; Vite build clean

Completed 2026-07-10 — Exam Types + Email Templates admin:
- [x] `ExamTypePolicy` (viewAny: any staff; manage: super_admin/esd_admin) + `ExamTypeController` (CRUD, unique name, blocks deleting a type still referenced by an examination) + `Settings/ExamTypes/Index.vue`
- [x] `EmailTemplatePolicy` (super_admin/esd_admin only, matching the legacy admin/superadmin-only scope) + `EmailTemplateController` (index/update — no create/destroy since template `code`s are referenced directly by `NotificationMailer`/`AssignmentConfirmationController`, so ad-hoc new codes would be inert) + `Settings/EmailTemplates/Index.vue` with an edit modal showing available `{placeholder}` variables pulled from the template's `variables` JSON column
- [x] Confirms the owner's explicit requirement end-to-end: templates seeded in Phase 6 (`assignment_confirmation`, `assignment_reminder`) are now genuinely editable by an admin at runtime, and `EmailTemplate::render()` picks up the edit immediately (verified by test)
- [x] Nav links added (esd_admin/super_admin)
- [x] Tests: `ExamTypeTest` (5 — visibility, CRUD, permission, duplicate-name reject, in-use delete block) + `EmailTemplateTest` (4 — access restricted to super_admin/esd_admin only, edit flow, render-picks-up-edit, unauthorized role blocked); `ExamTypeFactory` added
- [x] Verified: 144 tests / 848 assertions green; Vite build clean

Completed 2026-07-10 — User Accounts admin:
- [x] Migration: `users.is_active` (boolean, default true) + enforced at both login paths — `AuthenticatedSessionController::store` and `GoogleAuthController::callback` now reject deactivated accounts with a clear message, same pattern as the existing `locked_until` check
- [x] `UserPolicy` (super_admin/esd_admin only) + `UserController`: index (search/role filter/paginate), store (creates the account with a random unguessable password, `must_change_password=true`, and immediately emails the standard password-reset link — **the admin never sees or sets a password**), update (role/field office/active), `sendPasswordReset` (admin-triggered reset link, audited as `password_reset_sent`)
- [x] Self-deactivation blocked at the controller (an admin cannot deactivate their own account — would risk locking out the only admin)
- [x] `Settings/Users/Index.vue`: search/filter/paginate, create modal, edit modal (role/FO/active — active checkbox disabled when editing yourself), one-click "Reset Password"; nav link wired for esd_admin/super_admin
- [x] Tests: `UserManagementTest` (8 — access restriction, create + reset-link-sent, duplicate email, permission, update role/FO/active, self-deactivation blocked, **deactivated user cannot log in**, admin-triggered reset is audited)
- [x] Verified: 152 tests / 877 assertions green; Vite build clean

Completed 2026-07-10 — REC/LEC committee grouping, settings editor, duplicate-members report:
- [x] REC/LEC/special-role grouped UX — `roles`/`assignments` payloads carry `group`/`group_label`; `SelectInput.vue` renders `<optgroup>`s when present; `Examinations/Show.vue` assignment table has a committee-grouping toggle
- [x] `SettingController` + `SettingPolicy` (super_admin/esd_admin) + `Settings/General/Index.vue` — CRUD admin UI over the Phase 2 `Setting` model
- [x] `DuplicateMembersController` (super_admin only) + `Reports/DuplicateMembers.vue` — ported from legacy's `duplicate-members-report.php` (email + normalized-name collision detection, read-only)
- [x] Tests: `ExaminationTest` (+1 committee-grouping check), `SettingTest` (8), `DuplicateMembersReportTest` (3); 199 tests / 1115 assertions green; build clean
- [x] NEP attendance is currently manual-toggle only; QR-scan capture for NEP is already covered by Phase 6's QR-compatibility item — not a gap

---

## Phase 8 — Reports & exports — **DONE**

Done: Dashboard with stats (DashboardController).

Completed 2026-07-10:
- [x] Service-history report per member — printable Blade view (`/members/{member}/service-history/print`, opens in new tab) + Excel export (`/members/{member}/service-history/export`); derived at request time, nothing new stored. Buttons added to `Members/Show.vue`.
- [x] Region-wide + per-FO reports — `ReportController@index` + `Reports/Index.vue`: filters by Field Office (region-wide roles only; FO-scoped roles are hard-locked server-side), year, exam type, gender; summary cards + By Field Office / By Gender / By Exam Type / By Year breakdowns. Nav wired for all 5 non-member roles at `/reports` (previously a dead `#` placeholder).
- [x] Excel exports — `maatwebsite/excel` installed (new Composer dependency). `MembersExport`, `ServiceRecordsExport` (also reused for the single-member export via a `memberId` filter), `TrainingAttendanceExport`, all filter-aware and downloadable from the Reports page.
- [x] Training attendance stats — participants/attended/attendance-rate, filtered same as the rest of the report. School/venue readiness — designed fresh (not a legacy concept; legacy's `reports.php` files turned out to be dashboard mockups with hardcoded data, no real backend to port): per-venue rooms-staffed vs total-rooms ratio.
- [x] Tests: `ReportTest` (13); 209 tests / 1212 assertions total; build clean.

---

## Phase 9 — Verification, cutover prep & hardening — PARTIAL

Full detail for everything in this phase lives in `CUTOVER_RUNBOOK.md` (written 2026-07-10).

- [x] Test suite green — 210 tests / 1223 assertions, all passing
- [x] Legacy feature-parity checklist sweep (Phase R inventory) — all 12 items confirmed implemented, no gaps found (`CUTOVER_RUNBOOK.md` §3)
- [x] Legacy QR codes resolve on new verify endpoints — confirmed legacy codes were always raw text (never a URL) so there's no legacy link to redirect; added defensive `|attendance`-suffix normalization to `VerifyController` to match `ScannerController` (`CUTOVER_RUNBOOK.md` §5)
- [x] Scheduler verified (`php artisan schedule:list` shows all 3 commands); confirmed no queued jobs exist yet so a queue worker isn't required until one does (`CUTOVER_RUNBOOK.md` §4)
- [x] Cutover runbook written: freeze legacy → final ETL → switch → smoke test → rollback plan (`CUTOVER_RUNBOOK.md` §6)
- [!] **Found and flagged, not fixed (owner said "tell me, don't touch yet"):** `C:\xampp\htdocs\proctad_db (11).sql` (real member PII + plaintext SMTP password per legacy CLAUDE.md) and `proctad_legacy/` (with a dozen+ more historical SQL dumps inside `database_iterations/`) sit directly in a **shared** XAMPP htdocs webroot alongside unrelated projects. See `CUTOVER_RUNBOOK.md` §2 for full detail and recommended fix.
- [ ] E2E browser walkthrough per module — not done (only code-level route/policy review + automated HTTP tests + a build/serve smoke check); recommended before go-live, see `CUTOVER_RUNBOOK.md` §7
- [ ] ETL re-rehearsal on fresh production copy; row-count reconciliation — blocked, needs production DB access (owner action)
- [x] **Rotate exposed Gmail SMTP app password** — owner-handled; owner will generate the new app password and update production `.env` directly
- [ ] Queue worker on host — not yet needed (no `ShouldQueue` jobs exist), but cron for the scheduler must be installed on the real host (owner action, `CUTOVER_RUNBOOK.md` §4)

Completed 2026-07-11 — Testing Center UX redesign (Examinations + Schools), guided workflow modeled on legacy:
- [x] Reviewed legacy `admin/testing-center/examinations.php`, `exam-rooms.php`, `assign-proctad.php`, `manage-schools.php`, `schools.php` in depth (not pixel-copied) to extract the reusable UX patterns: stat cards + filters before a listing, prerequisite-gating via disabled actions + explanatory subtext ("add rooms first"), confirm-before-destructive with exact scope shown, "explain the blocker then link to the fix" empty states, a numbered/free-navigation step indicator, and consistent async-button loading states
- [x] `Examinations/Index.vue` + `Schools/Index.vue` rebuilt: `StatCard` rows (totals/upcoming/fully-staffed; totals/active/inactive), client-side filter chips, per-exam staffing progress bar (confirmed/total, color-coded), Schools' "Used In" exam-count column, richer delete-impact warning on Schools when a school is attached to N examinations. Kept the app's table-based listing convention rather than switching to legacy's card grid, to stay consistent with every other index page in the app (an explicit trade-off against literal legacy fidelity, per the owner's "maintain consistency with the rest of the application's design language" objective)
- [x] `ExaminationController::index()` / `SchoolController::index()` extended with `stats` + per-row `venues_count`/`rooms_count`/`confirmed_count`/`staffing_ratio` (Examinations) and `venues_count` (Schools) via `withCount()`
- [x] New reusable `Components/StepTabs.vue` (v-model step indicator, free navigation, checkmark for completed steps) — used to restructure `Examinations/Show.vue` into a 3-step guided flow: **Venues & Rooms → Assign Members → Review Roster**, mirroring legacy's guided wizard sequence without legacy's hard page-per-step navigation (Inertia keeps it a single page)
- [x] New `Examinations/Partials/RosterReview.vue` (Step 3) — read-only roster summary: totals + confirmed/pending/declined by role group, "no venue yet" flag per group, attendance-outstanding count, Scan Attendance CTA
- [x] `VenuesRoomsPanel.vue`: added confirmation modals before "Randomize All" and "Fill Unassigned Only" (previously fired with zero confirmation — a gap vs. legacy, which always warns + shows scope before any bulk-overwrite); raised the room-staffing action buttons from tiny pills to normal `BaseButton`s; added a "Next: Assign Members →" nudge once venues exist
- [x] `AssignMemberForm.vue`: added a soft (non-blocking — our data model allows venue-less Regional-Committee-role assignments, unlike legacy) prerequisite banner when no venues are attached yet
- [x] Added `plus` icon to `AppIcon.vue` registry (was missing, needed for the new Add Venue/Room/Personnel buttons)
- [x] Tests: `ExaminationTest` (+1, index stats/counts), `SchoolTest` (+1, index stats/venue-usage count); 212 tests / 1263 assertions green; Vite build clean
- [ ] Not yet done: same guided-step treatment for `AssignmentsTable.vue`'s bulk-action bar visual prominence (functionality/confirmations already solid); no E2E browser walkthrough of the new flow yet (code-level + automated HTTP tests only)

---

## Resume instructions (fresh session)

1. Read `DATABASE_AUDIT.md`, this file, and `proctad_legacy\CLAUDE.md`.
2. Check Phase R open decisions (D1–D5) — if still `[!]`, ask the owner before building the affected areas.
3. Find the first non-done phase in the Status Board; continue from its first unchecked item.
4. Update checkboxes, Status Board, and Session notes **as you work**, not after.
