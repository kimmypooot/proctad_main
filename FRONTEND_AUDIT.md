# PROCTAD Frontend UI/UX Audit

> Written 2026-07-10. Scope: `resources/js/` (Laravel 13 + Inertia.js + Vue 3 `<script setup>` +
> Tailwind CSS 4). Reference comparison: `C:\xampp\htdocs\recruitment-system` (a prior CSC RO VIII
> system on the same stack — Laravel + Inertia + Vue 3 + Tailwind 4 — making it an unusually direct
> comparison rather than a cross-framework one).
>
> **This is an audit only. No components were modified while producing this document**, per the
> brief. See §12 for the proposed implementation roadmap and the question at the end of this
> document about where to start.

---

## 1. Executive Summary

PROCTAD's frontend is **further along than a typical "needs a redesign" codebase**. It already has
a real shared component library (23 components: `BaseButton`, `BaseModal`, `BaseBadge`, `BaseAlert`,
`BasePagination`, `EmptyState`, `TextInput`, `SelectInput`, `TextArea`, `CheckboxInput`, a central
`AppIcon` registry, etc.), a defined brand color system (Tailwind 4 `@theme` tokens: `brand-*`
indigo + `accent-*` red — a legitimate, restrained government palette, not generic admin-template
blue), and consistent use of Inertia's `useForm()` + shared validation-error markup across every
form in the app. The public marketing pages (`Home`, `About`, `Benefits`, `Faqs`, etc.) are
genuinely well-executed and internally consistent — no changes needed there.

The problems are not "this looks unprofessional" — they're **discipline/consistency gaps** where a
component that already exists gets bypassed in favor of hand-rolled markup, plus **one real
architectural outlier** (`Examinations/Show.vue`, 766 lines / 9 modals / ~15 forms in one file).
Concretely:

- The page header (title + subtitle + action button) is built **3 different ways** across ~26
  dashboard pages, with no visual logic explaining which page gets which shape.
- Flash/success/error banners are hand-rolled with raw Tailwind `red-*`/`emerald-*` classes in 6+
  places instead of reusing the existing `BaseAlert.vue`, which already correctly uses the brand
  `accent-*` palette.
- Checkboxes are hand-rolled in 12 files even though `CheckboxInput.vue` exists and is used in
  exactly 2 of them.
- "Manage a short reference list" (schools, signatories, exam types, email templates, letterheads,
  settings) is implemented **3 structurally different ways** with no stated rule for which entity
  gets which treatment.
- One page (`Examinations/Show.vue`) is 2.6× larger than the next-largest page and genuinely needs
  decomposition before any further visual work is done to it safely.
- A **verified WCAG AA contrast failure**: the required-field asterisk color (`accent-500` red on
  white) computes to **4.40:1**, just under the 4.5:1 AA threshold for normal text (§8).
- `DashboardLayout.vue` (the shell every authenticated user sees) has no skip-to-content link, while
  `PublicLayout.vue` does (§8).

None of this requires starting over. The recommended path is to **formalize the design system that
already implicitly exists**, retrofit the ~8 places that bypass it, decompose the one oversized page,
and fix the two concrete accessibility gaps — not to introduce a new visual language.

---

## 2. Overall Design Assessment

**Foundation: solid.** Tailwind 4's CSS-first `@theme` is used correctly (no legacy `tailwind.config.js`
sitting alongside it), with a real two-color brand system (`brand-*` indigo, `--color-brand-600:
#2A338F`; `accent-*` red, `--color-accent-500: #EC1C2D`) plus Poppins as the type family — this
reads as a considered, government-appropriate palette already, not something needing replacement.
Semantic colors (success = `emerald-*`, warning = `amber-*`) are stock Tailwind used consistently.

**Component layer: real, but under-adopted.** The component library isn't a thin wrapper — `BaseModal`
has proper focus/scroll handling context, `TextInput`/`SelectInput`/`TextArea`/`CheckboxInput` share
a genuinely consistent error/hint/required-indicator convention with `aria-invalid`/`aria-describedby`
wired correctly. The issue is **adoption**, not quality: several components (`BaseAlert`,
`CheckboxInput`) exist but are used in only 1–2 of the 10+ places that need them.

**Page layer: inconsistent chrome, consistent content patterns.** Table markup, pagination, and
empty-states are copy-paste consistent across ~15 index pages (good — this is *de facto* convention
even without a shared `Table` component). Page *headers*, however, fork into at least 3 shapes, and
list-management pages fork into 3 different architectures for what's conceptually the same task.

**One structural outlier.** `Examinations/Show.vue` at 766 lines is the only page in the app that
looks like it grew organically without a refactor checkpoint. Everything else is reasonably sized
(the next largest is `Members/Show.vue` at 293 lines).

**Net assessment: this is a "tighten and formalize" project, not a "rebuild" project.**

---

## 3. Strengths of the Current Implementation

1. **Government-appropriate, restrained color system** — two custom brand colors (`brand`/`accent`)
   composed via Tailwind's opacity modifiers (`bg-brand-50`, `text-brand-700`) rather than a sprawling
   custom palette. This is the same discipline the `recruitment-system` reference does well, and
   PROCTAD already matches it.
2. **A real, consistent form-field convention.** `TextInput`/`TextArea`/`SelectInput`/`CheckboxInput`
   share identical error/hint/required-marker patterns, and every field is wired for screen readers
   (`aria-invalid`, `aria-describedby`, `role="alert"` on error text). This is *better* than the
   reference system, which has no visible per-field error styling (errors surface via toast there).
3. **A single central icon registry** (`AppIcon.vue`, 56 icons, consistent `viewBox`/stroke-width)
   used everywhere — no page reaches for a raw inline SVG or a second icon source.
4. **Consistent table/empty-state/pagination conventions** across ~15 index pages, even without a
   shared `Table` component — this is disciplined copy-paste, not chaos.
5. **`BaseModal`'s footer button convention is genuinely uniform**: 32 modal usages across 15 files,
   32/32 use `Cancel (outline) → Action (primary/accent)` left-to-right, `size="sm"`, wired to
   `form.processing`. This is a real strength worth explicitly preserving during any redesign.
6. **The public marketing module is fully realized and consistent** — `PageHeader`, `SectionHeader`,
   `.reveal` scroll animations, card grids all used correctly across 10 pages. No changes needed here.
7. **Accessibility is already a design consideration, not an afterthought**: global `:focus-visible`
   ring token in `app.css`, a `prefers-reduced-motion` override that disables all animation durations,
   a skip-link in `PublicLayout`, and `aria-label`s already present on every icon-only button in
   `DashboardLayout.vue` (menu toggle, notifications bell, sidebar close — verified by direct grep).
8. **Auth module (`Pages/Auth/*`) is the most internally consistent module in the dashboard app** —
   same layout, same spacing, same flash-banner shape (even though that shape itself should be
   `BaseAlert`, see §5).

---

## 4. Weaknesses and Usability Issues

Ranked by how many screens they touch, not by visual severity:

1. **Page header fragmentation (touches ~26 files).** Three shapes exist:
   - Shape A (wrapped, with action button): Members, NonExamPersonnel, Examinations, Trainings,
     Schools, Signatories, Settings/Users, Settings/ExamTypes, Settings/General, Dashboard.
   - Shape B (bare `<h1>`+`<p>`, no wrapper, no action slot): Certificates, Approvals, Scanner,
     AuditLogs, Settings/Letterheads, Settings/EmailTemplates, Reports/DuplicateMembers, all 4 of
     `My/Profile|ServiceHistory|Trainings|Certificates`.
   - Shape C: Dashboard substitutes role badges where the action button would go.
   - The split isn't justified by whether the page *has* an action — Certificates and Scanner both
     have a contextual action but place it outside the header, which breaks the pattern's own logic.
2. **Flash/alert banners hand-rolled in 6+ places** with raw `red-*`/`emerald-*` Tailwind classes
   (`DashboardLayout.vue`, `Auth/Login.vue`, `Auth/MemberLogin.vue`, `Auth/ForgotPassword.vue`,
   `Auth/ChangePassword.vue`, `Assignments/Confirm.vue`) instead of `BaseAlert.vue`, which already
   exists, is already used correctly elsewhere (`PublicLayout.vue`, `Contact.vue`), and already uses
   the correct `accent-*` brand palette for errors instead of raw `red-*`.
3. **Checkboxes hand-rolled in 12 files** (`Certificates/Index`, `Examinations/Show`, `Members/Show`,
   `NonExamPersonnelForm`, `Schools/Index`, 4× `Settings/*`, `Signatories/Index`, `Trainings/Show`)
   repeating the exact same class string, even though `CheckboxInput.vue` exists and is used in only
   `Auth/Login.vue` and `Auth/Register.vue`.
4. **"Manage a short list" implemented 3 different architectural ways** with no documented rule:
   (a) table + inline modal, unpaginated (Schools, Signatories, ExamTypes, Examinations, Trainings,
   Settings/General); (b) paginated table with dedicated Create/Edit routes (Members, NonExamPersonnel,
   Settings/Users, Certificates, AuditLogs); (c) card-grid (Letterheads) or row-list (EmailTemplates).
   A developer adding a new "manage a short list" feature today has no signal for which pattern to
   follow.
5. **Stat-card markup duplicated 3×** (`Dashboard/Index.vue`, `Reports/Index.vue` twice) with
   *inconsistent number sizing* between the duplicates (`text-3xl` vs `text-2xl` vs `text-xl` for
   what's conceptually the same "big number" element).
6. **Two competing badge/pill implementations**: `BaseBadge.vue` (`rounded-full px-3 py-1 text-xs
   font-semibold ring-1 ring-inset`) vs. hand-rolled status pills in `MemberIdCard.vue`/`NepIdCard.vue`
   (`rounded-full px-2 py-0.5 text-[0.6rem] font-bold uppercase tracking-wide`, no ring). These may
   need to stay visually distinct (ID cards are printed physical documents with tighter space
   constraints than a table badge) but should be the *same component* with a `size` variant, not two
   independent implementations that will drift.
7. **`Examinations/Show.vue` is a 766-line outlier** (next largest page is 293 lines) with 9 separate
   `BaseModal` instances and ~15 `useForm()` calls in one file. This is a maintainability and
   code-review risk independent of any visual redesign, and it should be decomposed *before* it is
   restyled, not after — restyling a 766-line file in place multiplies review difficulty.
8. **The "action link" button** (`text-sm font-medium text-brand-700 hover:underline`) is the single
   most copy-pasted string in the codebase (30+ occurrences across Schools, Signatories, ExamTypes,
   Settings/Users, Examinations, Trainings) — it's functionally a button variant that doesn't exist
   yet on `BaseButton`.
9. **No skeleton/loading state for tables.** Filter/search changes rely entirely on Inertia's global
   progress bar; there's no per-table "this is refreshing" affordance, so a slow filter request can
   read as an unresponsive page rather than a loading one.
10. **No shared `FileInput` component.** `MemberForm.vue` and `NonExamPersonnelForm.vue` both
    hand-roll an identical native file input with matching `file:` pseudo-class styling — the only
    form-field type not abstracted into `Components/`.

---

## 5. Component-by-Component Analysis

| Component | Verdict | Notes |
|---|---|---|
| `BaseButton.vue` | **Keep, extend** | 6 variants, 3 sizes, handles `<button>`/`<a>`/Inertia `<Link>` via props — solid. Add a `variant="link"` to absorb the 30+ hand-rolled action-link buttons (§4.8). |
| `BaseModal.vue` | **Keep as-is** | Genuinely uniform adoption (32/32 usages follow the same footer convention). Only issue: every modal uses the default `md` width even for dense forms — worth spot-checking whether any form modal (e.g. Member create/edit if it were modal-based, or the denser `Examinations/Show.vue` modals) would read better at `lg`. |
| `BaseBadge.vue` | **Keep, consolidate** | Correct implementation; needs the ID-card status pills merged into it (§4.6) rather than left as a parallel implementation. |
| `BaseAlert.vue` | **Keep, adopt everywhere** | Correctly uses `accent-*` for errors already. The fix is retrofitting the 6 hand-rolled flash banners to use it (§4.2), not changing the component itself. |
| `TextInput` / `TextArea` / `SelectInput` / `CheckboxInput` | **Keep, extend adoption** | Best-executed family in the app. `CheckboxInput` needs wider adoption (§4.3). Consider extracting the shared label/required-marker markup (currently duplicated verbatim across all 4) into a small internal `FormLabel` to prevent drift, but this is a nice-to-have, not urgent since the 4 copies are currently identical. |
| `EmptyState.vue` | **Keep as-is** | Used in 25 files, consistent icon+title+description+action pattern. No issues found. |
| `BasePagination.vue` | **Keep as-is** | Used everywhere Laravel's paginator is passed; no competing implementation exists. |
| `AppIcon.vue` | **Keep, harden** | 56-icon registry, consistent stroke convention, correctly used everywhere sampled. One risk: an unknown icon name silently renders an empty path with no dev warning — add a `console.warn` in dev mode so a typo'd icon name is caught immediately instead of shipping as an invisible icon. |
| `MemberIdCard.vue` / `NepIdCard.vue` | **Keep, fix pill duplication** | Otherwise well-built (correct brand-color differentiation between member vs. NEP card headers is intentional and fine). Only issue is the status-pill duplication noted in §4.6. |
| `AppLogo.vue`, `Breadcrumbs.vue`, `PageHeader.vue` (public), `SectionHeader.vue`, `StatCounter.vue`, `HeroSection.vue`, `QrCode.vue`, `LoadingSpinner.vue`, `BaseAccordion.vue` | **Keep as-is** | All scoped correctly to their one use-case (public marketing pages), no cross-cutting issues found. |
| *(missing)* Dashboard page header | **Add** | No shared component exists for the authenticated app's page header — this is the direct cause of §4.1's 3-way fork. Proposed: `DashboardPageHeader.vue` with `title`, `subtitle`, and an `#actions` slot, adopted by all ~26 dashboard pages. |
| *(missing)* `StatCard.vue` | **Add** | Absorbs the 3 duplicated stat-card implementations (§4.5) into one component with consistent number typography. |
| *(missing)* `FileInput.vue` | **Add** | Absorbs the 2 duplicated file-input implementations (§4.10). |
| *(missing)* Table shell | **Consider, lower priority** | Given how consistent the hand-rolled table markup already is (identical class strings across ~15 files), a full generic `DataTable` component risks over-engineering (forcing a rigid column-config API onto pages with genuinely different needs). A lighter option — a `TableShell.vue` that only wraps the container/`<thead>` styling — would remove duplication without constraining column layout. Recommend this only after the higher-impact items land. |

---

## 6. Page-by-Page Recommendations

Grouped by module; only modules needing changes are listed (public marketing pages and Auth need no
structural changes — see §3).

- **Members / NonExamPersonnel** — reference-quality already; no changes beyond adopting the new
  `DashboardPageHeader` for consistency with the rest of the app.
- **Examinations** — `Index.vue`: fine as-is (table + modal is appropriate for exam-event count).
  `Show.vue`: **decompose first, restyle second** (§4.7). Proposed split: `VenuesRoomsPanel.vue`
  (venue/room/NEP roster management), `AssignmentsTable.vue` (the assignment list + its own edit/
  reassign modals), `StaffingToolsPanel.vue` (randomize/clear/bulk-revoke), leaving `Show.vue` as an
  orchestrator that's under ~150 lines. Also evaluate whether a tabbed layout (Overview / Venues &
  Rooms / Assignments / Staffing Tools) reduces the page's cognitive load versus one long scroll,
  given how many distinct concerns it now serves.
- **Trainings** — `Show.vue` is the right *size* reference for "assignment management" pages (231
  lines doing a structurally similar job to `Examinations/Show.vue`'s 766) — no changes needed beyond
  header consolidation.
- **Certificates / Approvals** — adopt `DashboardPageHeader` with the bulk-action / approve-disapprove
  controls moved into the header's `#actions` slot instead of floating separately below the filter bar.
- **Scanner** — adopt `DashboardPageHeader`; extract the duplicated outcome-badge coloring logic
  (member-result vs. NEP-result branches render nearly identical badge markup) into a small
  `<AttendanceOutcome>` component.
- **Schools / Signatories / Settings/ExamTypes / Settings/General** — keep the table+modal pattern
  (it's appropriate for genuinely small reference lists) but document it as the *intentional* default
  for "small reference list" entities, so future additions follow it deliberately.
- **Settings/EmailTemplates** — convert its unique row-list shape to the same table+modal pattern as
  its 4 Settings siblings; there's no content reason (template name/subject/status fit a table fine)
  for it to be the only row-list in the app.
- **Settings/Letterheads** — keep the card-grid (genuinely justified: image-preview content doesn't
  suit a table), but note it explicitly as the one intentional exception to the table+modal default.
- **Reports** — replace the 2 inline stat-card blocks with the new `StatCard` component; consider
  whether the Apply/Clear-button filter pattern (the only one in the app that isn't auto-apply) should
  be standardized either direction — recommend keeping explicit Apply/Clear here specifically, since
  Reports queries are heavier (multiple aggregate queries) than a simple list filter, so avoiding
  live-refresh-per-keystroke is the right call; just note it's an intentional exception, not an
  oversight.
- **AuditLogs** — remove the artificial `sm:max-w-xl` cap on its filter panel so it matches every
  other full-width filter panel in the app.
- **My/* (member self-service)** — adopt `DashboardPageHeader` on the 3 pages currently using the bare
  shape (`Profile`, `ServiceHistory`, `Trainings`, `Certificates`); extract the 4×-duplicated "No
  PROCTAD record linked" empty-state copy into a shared constant or a small dedicated component.

---

## 7. Responsive Design Issues

- **Single-breakpoint layouts.** `DashboardLayout.vue`, `NavBar.vue`, and `AuthLayout.vue` each use
  only the `lg:` (1024px) breakpoint for their major structural collapse (sidebar, nav menu,
  split-screen). There is no `md:` intermediate treatment, meaning a tablet in the 640–1023px range
  gets the full mobile treatment (off-canvas sidebar, hamburger menu) even though it usually has room
  for more. This isn't broken, but it's a missed opportunity for the "tablet" breakpoint the brief
  specifically calls out — worth testing whether a `md:` icon-only collapsed sidebar (rather than fully
  hidden) reads better on tablet viewports.
- **`Examinations/Show.vue`'s density** (§4.7) is a responsive risk independent of its size: with 9
  modals and dense inline tables, small-viewport testing on this specific page should be prioritized
  once it's decomposed, since it's the most likely page in the app to overflow or clip on mobile today.
- **Table overflow handling is already correct**: every index table's container uses
  `overflow-x-auto`, so horizontal scroll (rather than layout breakage) is the fallback on narrow
  viewports — this is acceptable but not ideal for a "mobile-first" bar; consider whether the
  highest-traffic tables (Members, Examinations assignment list) would benefit from a card-based
  responsive alternative below `sm:`, matching how `Approvals/Index.vue` already uses a card-list
  instead of a table (proving the pattern is already in the codebase, just not applied to tables that
  currently only get horizontal scroll).
- No ultra-wide (`2xl`/`3xl`+) container constraints were flagged as broken, since `PublicLayout` and
  most dashboard pages cap at `max-w-7xl`; the dashboard's `main` content area, however, has no
  max-width constraint (`flex-1 p-4 sm:p-6` with no `max-w-*`), meaning on an ultra-wide monitor,
  content will stretch edge-to-edge inside the `lg:pl-64` column — worth adding a `max-w-screen-2xl
  mx-auto` constraint there for large-monitor readability.

---

## 8. Accessibility Findings (WCAG 2.2 AA)

**Verified with computed contrast ratios** (WCAG relative luminance formula, not estimated):

| Pair | Ratio | AA normal text (4.5:1) | AA large text (3:1) |
|---|---|---|---|
| `accent-500` (#EC1C2D) required-asterisk on white | **4.40:1** | ❌ **Fails** | ✅ Passes |
| `accent-600` (#c8121f) error text on white | 5.90:1 | ✅ Passes | ✅ Passes |
| `slate-500` subtitle text on white | 4.76:1 | ✅ Passes (barely) | ✅ Passes |
| `brand-300` on `brand-900` (sidebar section labels, full opacity) | 6.16:1 | ✅ Passes | ✅ Passes |
| White on `brand-600` (primary button) | 10.67:1 | ✅ Passes | ✅ Passes |
| `emerald-800` on `emerald-50` (success flash) | 7.29:1 | ✅ Passes | ✅ Passes |

**Findings:**

1. **Concrete AA failure**: the required-field asterisk (`text-accent-500`, used in `TextInput`,
   `TextArea`, `SelectInput`) computes to 4.40:1 against white — just under the 4.5:1 threshold. Fix:
   switch the asterisk to `accent-600` (5.90:1), matching the color already used for error text in the
   same components, which also improves internal consistency (asterisk and error text currently use
   two different accent shades for what are both "attention" signals).
2. **Sidebar section labels use `brand-300/70`** (70% opacity applied on top of an already-passing
   6.16:1 full-opacity ratio). Opacity blending against the `brand-900` background will land the
   *effective* contrast somewhere below 6.16:1 — likely still passing for the label's actual size, but
   this should be spot-checked with a real contrast tool against the rendered pixel color rather than
   assumed, since it's currently the only place in the app applying a contrast-affecting opacity
   modifier to text color rather than to a background/border.
3. **Missing skip-link in `DashboardLayout.vue`** (verified by direct grep — no `sr-only`/`skip`
   pattern found). `PublicLayout.vue` has one (`sr-only focus:not-sr-only`, jumps to `#main-content`).
   Since every authenticated user of the app lands in `DashboardLayout`, this is the higher-traffic of
   the two layouts to be missing it.
4. **Icon-only buttons in `DashboardLayout.vue` already have `aria-label`s** (verified: menu toggle,
   sidebar close, notifications bell all labeled) — this is a genuine strength, not a gap, and should
   be held up as the standard the rest of the app should match. Recommend auditing icon-only buttons
   elsewhere (table row actions that might use an icon instead of the text "action link" pattern,
   modal close buttons — `BaseModal`'s close button should be checked for a label) against this same bar.
5. **No tooltips exist anywhere in the app** — the brief asks that "every icon-only button should
   include an accessible tooltip." Currently the app relies on `aria-label` alone (good for screen
   readers) but has no visual tooltip for sighted mouse users to discover what an icon-only control
   does. Given there's no tooltip component in the codebase at all (confirmed — `recruitment-system`
   also has none, using only native `title=` attributes), this is a genuine net-new component to add,
   not a fix to an existing one. Scope it to icon-only controls specifically (the topbar notification
   bell, sidebar toggle, modal close button) rather than every icon in the app, to avoid tooltip
   fatigue on icons that already have adjacent text labels (e.g. nav items, which pair every icon with
   a visible label already).
6. **Forms are already the strongest accessibility area** — `aria-invalid`, `aria-describedby`,
   `role="alert"` on error text, and a global `:focus-visible` ring are all correctly implemented
   already. No changes needed here beyond the color fix in finding 1.
7. **`prefers-reduced-motion` is already respected globally** (`app.css` disables all animation/
   transition durations under the media query) — a strength worth preserving exactly as-is through any
   redesign.

---

## 9. Tailwind CSS Improvements

1. **Formalize the radius scale** (currently implicit/inconsistent): recommend documenting —
   `rounded-lg` for controls (buttons, inputs, pagination items) and banners (`BaseAlert`);
   `rounded-xl` for cards/panels/modal surfaces; `rounded-full` for pills/avatars. Fix the one
   concrete drift: `DashboardLayout.vue`'s topbar icon-chrome buttons use `rounded-md` while every
   other interactive control in the app uses `rounded-lg`.
2. **Document the shadow/elevation scale** as a comment block in `app.css` rather than leaving it
   implicit: `shadow-sm` (buttons, scrolled nav), `shadow-md` (ID cards), `shadow-lg` (dropdowns,
   mobile nav panel), `shadow-xl` (modal surface). The actual usage already follows a coherent
   elevation logic — it just isn't written down anywhere, so a new page is likely to guess wrong.
3. **Fix the raw-color-vs-token drift** (§4.2): everywhere a flash/alert banner currently hardcodes
   `red-50/red-200/red-800` or `emerald-50/emerald-200/emerald-800` directly, replace with `BaseAlert`
   (which already uses `accent-*` for error, and can keep `emerald-*` for success as `BaseAlert`
   already does — `emerald` isn't a custom brand token and doesn't need to become one).
4. **`@theme` has no custom spacing scale** — this is fine; stock Tailwind spacing is used
   consistently. No change recommended here, noting it only because the brief asks about "spacing
   scales" — the finding is "already consistent," not "needs a fix."
5. **Long class strings on repeated patterns** (the 30+ instance action-link button, the 12-instance
   checkbox, the flash banner) are a Tailwind-best-practices smell primarily because they're
   *duplicated*, not because any single instance is unreadable — the fix is component extraction
   (§5), not a CSS-layer `@apply` shortcut, since the underlying Vue components already exist or are
   proposed for exactly this purpose.

---

## 10. Vue Component Improvements

1. **`Examinations/Show.vue` decomposition is the single highest-value Vue-architecture change**
   (§4.7, §6) — 766 lines / 9 modals / ~15 `useForm()` instances in one file is a genuine
   maintainability risk (hard to code-review, hard to reason about which of ~15 reactive form states
   is affecting a given render) independent of any visual changes.
2. **Extract the duplicated Scanner outcome-badge logic** (`Scanner/Index.vue`) into a small
   `<AttendanceOutcome>` component — the member-result and NEP-result branches currently duplicate
   near-identical badge-coloring logic.
3. **Consider extracting a shared `FormLabel` sub-pattern** (required asterisk + optional marker) used
   identically across `TextInput`/`TextArea`/`SelectInput`/`CheckboxInput` — currently 4 copies of the
   same 3-line template block. Low urgency since they're currently in sync, but worth doing at the same
   time as the accent-500→600 asterisk color fix (§8 finding 1), since that fix has to touch all 4
   files anyway — good opportunity to consolidate while already in there.
4. **Props/events are already reasonably consistent** across the reviewed components (`defineModel`
   used correctly for two-way-bound inputs, `error`/`required`/`optional` prop names consistent across
   the form-field family) — no broad prop-naming cleanup needed.
5. **No lazy-loading/code-splitting opportunities were identified as urgent** — Inertia's per-page
   component resolution already code-splits by route via Vite, which is the primary lazy-loading lever
   for this architecture; the app doesn't appear to have any single non-page component large enough
   (e.g. a heavy chart library) to warrant a manual `defineAsyncComponent` split.

---

## 11. Inertia Optimization Opportunities

1. **Audit filter-triggered `router.get()` calls for `only:`** — Index pages with search/filter
   (Members, Certificates, AuditLogs, Settings/Users, Reports) should confirm they pass an `only:`
   array scoped to just the data/pagination props, not the full page props, on every filter-triggered
   partial reload. This wasn't confirmed file-by-file in this audit pass and should be checked before/
   during the table-loading-state work (§4.9), since adding a loading skeleton is the natural moment
   to also verify the request itself is minimal.
2. **`preserveState`/`preserveScroll`** are already used correctly in the majority of form
   submissions reviewed during earlier migration phases (confirmed via this session's own prior work
   on `NotificationController`, `AssignmentConfirmationController`, etc.) — no broad gap found, but
   worth including in the same audit pass as item 1 above for the handful of pages not touched this
   session.
3. **Consider Inertia's deferred/lazy props** (`defer()`) for `Reports/Index.vue`'s heavier aggregate
   sections (the "by field office / by exam type / by year" breakdown tables and venue-readiness
   calculation) so the page's primary filter UI and summary cards render before the more expensive
   aggregate queries resolve, rather than blocking the whole page on all of them together.
4. **Shared props** (`auth.user`, `flash`, `notifications`) are already used correctly via
   `HandleInertiaRequests` middleware with lazy `fn()` closures where appropriate (confirmed this
   session while building the notification bell) — no changes needed here.

---

## 12. Prioritized Implementation Roadmap

Grouped by impact; effort noted per item. "High impact" = touches many screens or fixes a real
correctness/compliance gap. Recommend working top-to-bottom, one item at a time, each as its own
reviewable change — consistent with the "one module at a time" instruction in the brief.

### High impact

| # | Item | Why high impact | Rough effort | Status |
|---|---|---|---|---|
| H1 | Decompose `Examinations/Show.vue` into sub-components | Structural risk; blocks safely restyling the app's most complex page | Medium-large | **Done 2026-07-10** — split into `Partials/VenuesRoomsPanel.vue` (venues/rooms/NEP roster + 6 modals, 304 lines), `Partials/AssignMemberForm.vue` (99 lines), `Partials/AssignmentsTable.vue` (grouped table + 4 modals, 363 lines), orchestrated by a 78-line `Show.vue` that also finally adopts `DashboardPageHeader` (deferred from H2). Extracted a shared `Composables/useVenueOptions.js` (venue→room `<select>` option derivation, needed in 3 places) to avoid re-duplicating that logic across the split. Pure refactor — no behavior or markup changes; the original 766-line file's own JS chunk dropped from 25.77kB to 2.42kB post-split. Verified: 210/210 tests green, clean build, live-server smoke check. |
| H2 | New `DashboardPageHeader.vue`, adopt across all ~26 dashboard pages | Single highest-visibility consistency fix; touches every screen | Medium | **Done 2026-07-10** — new component with `title`/`subtitle` props + `#back`/`#eyebrow`/`#badges`/`#subtitle`/`#actions` slots, adopted across 29 pages (all identified Shape A + Shape B pages, plus Members/NonExamPersonnel Create+Edit which used the same markup but weren't in the original audit list). Also fixed the `AuditLogs` filter-panel width cap (M8) as a drive-by while in that file. **Deliberately deferred**: `Examinations/Show.vue`'s header — bundled with its H1 decomposition instead of edited twice. Not done as part of this pass (left for their own roadmap items): moving Certificates'/Approvals' contextual actions into the header `#actions` slot, and the `AttendanceOutcome` extraction on Scanner. |
| H3 | Retrofit 6 hand-rolled flash banners onto `BaseAlert` | Fixes color-token drift (§9.3) + removes duplication; every screen shows flash banners | Small | **Done 2026-07-10** — `DashboardLayout.vue`, `AuthLayout.vue` (centralized for all 6 Auth pages: Login, MemberLogin, Register, ForgotPassword, ResetPassword, ChangePassword), `Assignments/Confirm.vue` all now use `BaseAlert`. `PublicLayout.vue` already did. |
| H4 | Fix `accent-500` → `accent-600` required-asterisk (WCAG AA fix) | Concrete, verified compliance failure | Trivial | **Done 2026-07-10** — `TextInput.vue`, `TextArea.vue`, `SelectInput.vue`, `Settings/Letterheads/Index.vue`. New ratio: 5.90:1 (was 4.40:1). |
| H5 | Add skip-link to `DashboardLayout.vue` | Concrete, verified compliance gap on the higher-traffic layout | Trivial | **Done 2026-07-10** — matches `PublicLayout.vue`'s pattern exactly; `<main>` now has `id="main-content"`. |

### Medium impact

| # | Item | Why | Rough effort | Status |
|---|---|---|---|---|
| M1 | Retrofit 12 hand-rolled checkboxes onto `CheckboxInput` | Consistency + a11y (label wiring) | Small-medium | **Done 2026-07-11** — 10 of 12 converted (`Members/Show`, `Examinations/Partials/AssignmentsTable`, `Schools`, `NonExamPersonnelForm`, `Signatories`, `Settings/EmailTemplates`, `Trainings/Show`, `Settings/ExamTypes`, `Settings/General` ×2, `Settings/Users`, `Settings/Letterheads`). Added a `disabled` prop to `CheckboxInput` for the Users "can't deactivate yourself" case. **Deliberately left as-is**: the 3 table row-selection checkboxes (`Certificates/Index` ×2, `AssignmentsTable` ×1) — a genuinely different UX pattern (no label, compact `h-4 w-4`, dense table cell) that `CheckboxInput`'s labeled-field design doesn't fit. |
| M2 | Add `BaseButton variant="link"`, replace 30+ hand-rolled action-links | Removes the single most-duplicated string in the codebase | Medium | **Done 2026-07-11** — added `link` (brand) and `link-accent` (destructive) variants to `BaseButton` with a conditional class branch (link variants skip the padding/rounded/shadow button treatment entirely). Replaced all 27 occurrences found across 14 files, including 2 download `<a>` links and 2 Inertia `<Link>` back-links that were the same visual pattern. |
| M3 | New `StatCard.vue`, replace 3 duplicated stat-card blocks | Fixes an actual visual inconsistency (mismatched number sizes) | Small | **Done 2026-07-11** — new component, standardized the number size to `text-2xl` (was 3xl/2xl/xl across the 3 duplicates) and the label style to the uppercase-tracked variant (majority pattern). Used in `Dashboard/Index.vue` (with icon) and `Reports/Index.vue` (summary cards + `:bordered="false"` training-stats row). |
| M4 | Consolidate `BaseBadge` + ID-card status pills (add a size variant) | Removes a parallel implementation before it drifts further | Small | **Done 2026-07-11** — added an `xs` size to `BaseBadge` (solid-fill, no ring, tighter padding — preserves the ID cards' bolder "printed card" look rather than forcing the subtler ring-bordered table-badge style onto them). `MemberIdCard.vue`/`NepIdCard.vue` now use `<BaseBadge size="xs">` instead of their own hand-rolled pill markup. |
| M5 | Convert `Settings/EmailTemplates` row-list to table+modal | Removes the 3rd "manage a short list" pattern down to 2 (table+modal, and the one justified card-grid exception) | Small | **Done 2026-07-11** — converted to the standard table pattern (Name/Code/Subject/Status/Actions columns) matching its 4 Settings siblings. |
| M6 | New `FileInput.vue`, replace 2 hand-rolled file inputs | Removes the one un-abstracted field type | Small | **Done 2026-07-11** — new component matching the `TextInput`/`SelectInput` label/error/hint convention. Applied to `MemberForm.vue` and `NonExamPersonnelForm.vue`. `Settings/Letterheads/Index.vue`'s file input was deliberately left alone — it has extra input-ref-reset logic `FileInput` doesn't support yet, and was outside M6's stated "2 hand-rolled file inputs" scope. |
| M7 | Add tooltip component for icon-only controls (bell, sidebar toggle, modal close) | Genuine net-new a11y/discoverability gap (§8 finding 5) | Small-medium | **Done 2026-07-11** — new `Tooltip.vue` (CSS-only, `group-hover`/`group-focus-within`, so it works for keyboard focus too, not just mouse hover). Applied to `BaseModal`'s close button (every modal in the app), and `DashboardLayout`'s sidebar toggle, mobile sidebar-close, and notifications bell. |
| M8 | Remove `AuditLogs`'s artificial filter-panel width cap | One-line fix, but a real inconsistency | Trivial | **Done 2026-07-10** — done as a drive-by during H2 (see H2 notes). |

### Low impact

| # | Item | Why | Rough effort | Status |
|---|---|---|---|---|
| L1 | Add dev-mode warning for unknown `AppIcon` names | Catches future typos; not a current visible bug | Trivial | **Done 2026-07-11** — `console.warn` in dev builds (`import.meta.env.DEV`) when a requested icon name resolves to neither the stroke nor fill registry. |
| L2 | Table loading/skeleton states for filter-triggered reloads | Polish; current global progress bar is functional | Medium | **Done 2026-07-11** — new `TableSkeleton.vue` (pulsing placeholder `<tr>`s, `columns` prop). Applied to all 5 filter-triggered list pages: `Members`, `NonExamPersonnel`, `Certificates`, `AuditLogs`, `Settings/Users`. Each page tracks a local `loading` ref set via the `router.get()` visit's `onStart`/`onFinish` callbacks; the table container stays mounted (`v-if="loading || data.length"`) so headers don't flash away, and the row list swaps to `TableSkeleton` while `loading` (`v-for="x in loading ? [] : data.data"` avoids mixing `v-if`/`v-for` on the same element). `Reports/Index.vue`'s small inline summary tables were left as-is — lower payoff, not the same "user waiting on a big paginated fetch" case this targets. |
| L3 | Document radius/shadow elevation scale in `app.css` comments | Documentation only, no visual change | Trivial | **Done 2026-07-11** — comment block added above `@theme` in `app.css` documenting the radius scale (`rounded-lg` controls/banners, `rounded-xl` surfaces, `rounded-full` pills) and shadow scale (sm/md/lg/xl by elevation role), matching what the codebase already does in practice. |
| L4 | Fix `rounded-md` → `rounded-lg` drift on `DashboardLayout` topbar icon buttons | Minor, low-visibility | Trivial | **Done 2026-07-11** — sidebar mobile-close button and the two topbar icon buttons (sidebar toggle, notifications bell). |
| L5 | Audit Inertia `only:` usage on filter-triggered requests | Performance polish, no user-visible bug identified | Small-medium | **Done 2026-07-11** — added `only:` to all 6 filter-triggered `router.get()` calls, each list verified against its controller's actual `Inertia::render` prop keys (not guessed): `Members` → `['members','filters']`, `NonExamPersonnel` → `['personnel','filters']`, `Certificates` → `['certificates','filters']`, `AuditLogs` → `['logs','filters']`, `Settings/Users` → `['users','filters']`, `Reports` → `['filters','summary','byFieldOffice','byGender','byExamType','byYear','trainingStats','venueReadiness']` (excludes the static `filterOptions`). `Scanner/Index.vue`'s `router.get()` was left out of scope — it's a QR-lookup action, not a filtered-list reload, a different pattern than the rest of this item targets. Note: since these controllers build props eagerly (not via `Inertia::lazy()`), `only:` still trims the JSON response payload sent to the client, but doesn't skip server-side computation of the excluded props — a further optimization if those computations ever become expensive, out of this item's scope. |
| L6 | Add `max-w-screen-2xl` constraint to dashboard main content area | Only affects ultra-wide monitors | Trivial | **Done 2026-07-11** — wrapped `<main>`'s slot content in `DashboardLayout.vue`. |
| L7 | Evaluate `md:` intermediate breakpoint for sidebar/nav collapse | Tablet-specific polish; current behavior isn't broken | Medium | **Done 2026-07-11** — decision: icon-only persistent rail (`md:w-16`, not collapsible) between `md:`/`lg:` (768–1023px), expanding to the full labeled sidebar at `lg:`+. Below `md:` unchanged (off-canvas overlay drawer). Nav items/section labels/footer text hidden only in the rail range (`md:hidden lg:inline`/`lg:block`), each item gets a native `title` tooltip since the rail has no room for labels; `AppLogo`'s existing `compact` prop is used for the rail's icon-only mark. Topbar hamburger hidden in the rail range too (nothing to toggle there — the rail is always visible, not collapsible; collapse remains an `lg:`+-only feature). Main content padding adds `md:pl-16`. |

---

## Out-of-roadmap polish (done 2026-07-10, driven by direct comparison against `recruitment-system`)

Not part of the numbered roadmap above — these came from a follow-up request to compare
`DashboardLayout.vue`'s topbar/sidebar directly against `recruitment-system/AdminLayout.vue` after
an unrelated background task moved logout into a topbar dropdown. Logged here for completeness:

- **Logout confirmation modal** — added (was previously an unconfirmed direct POST). Styled to match
  recruitment-system's weight for a destructive action: centered layout, icon in a tinted circle
  (using PROCTAD's `accent-*` tokens), two equal-width Cancel/Log out buttons.
- **Dropdown de-duplication** — the account dropdown no longer repeats name/email/role (which is
  already visible in the trigger button); role label moved into the trigger itself (two-line: name +
  role), dropdown is now action-only (just "Log out"), matching recruitment-system's split between
  "trigger shows identity, panel shows actions."
- **Desktop sidebar collapse** — the hamburger button was `lg:hidden` (mobile-drawer-only) with no
  desktop replacement, silently losing a collapse affordance entirely on desktop. Added
  recruitment-system's pattern: one unified `toggleSidebar()` (viewport-width branch: mobile drawer
  vs. desktop full-collapse), state persisted to `localStorage.sidebar_collapsed`.
- **Sidebar scrollbar theming** — the nav list's scroll thumb used to fall back to the browser default
  (light), which reads poorly against the dark `brand-900` sidebar. Added the same thin,
  semi-transparent-white `::-webkit-scrollbar`/`scrollbar-color` treatment recruitment-system uses.

All four verified: 210/210 backend tests green, clean build after each change.

---

## What I need from you before implementing

Per the brief, I haven't changed any components yet. The roadmap above is ordered by impact, but
"where to start" is a real decision — the high-impact items touch very different parts of the app
(one is a single risky file, the rest are app-wide consistency sweeps). I'll ask which entry point
you'd like via the question that follows this document.
