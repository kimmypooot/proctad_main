<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\BlacklistStatus;
use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Enums\EligibilityRequirement;
use App\Enums\ExamRole;
use App\Enums\MemberStatus;
use App\Enums\PerformanceRating;
use App\Enums\UserRole;
use App\Models\Blacklist;
use App\Models\Certificate;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\TestingCenter;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $fieldOffice = $user->fieldOffice;

        // Staff who are also accredited members get the member dashboard while
        // switched into their PROCTAD workspace. Resolving that to an effective
        // role — rather than threading a workspace flag through every helper —
        // keeps the panels below with a single notion of "who is this for", so
        // stats, analytics and approvals can't disagree about which hat is on.
        // Safe because nothing here authorizes: the panels a staff role would
        // see are simply not built, and the pages themselves stay policy-gated.
        $role = Workspace::current($request) === Workspace::MEMBER
            ? UserRole::Member
            : ($user->role ?? UserRole::Member);

        $member = $role === UserRole::Member
            ? Member::with('requirements', 'user:id,google_avatar', 'testingCenter:id,name')->where('user_id', $user->id)->first()
            : null;

        return Inertia::render('Dashboard/Index', [
            'role' => $role->value,
            'roleLabel' => $role->label(),
            'scopeBadge' => $this->scopeBadge($role, $member, $fieldOffice),
            'stats' => $this->statsFor($role, $user, $member),
            'memberSummary' => $role === UserRole::Member ? $this->memberSummary($member) : null,
            'analytics' => $this->adminAnalytics($role, $user, $request),
            'pendingApprovals' => $this->pendingApprovals($role, $user),
        ]);
    }

    /**
     * The jurisdiction the workspace covers, named on the header badge. Staff
     * work for a field office; a test administrator works for their own agency
     * and is based at a testing center instead, so labelling their dashboard
     * with a field office would name an office they don't belong to.
     *
     * @return array{label: string, icon: string}|null
     */
    private function scopeBadge(UserRole $role, ?Member $member, ?FieldOffice $fieldOffice): ?array
    {
        if ($role === UserRole::Member) {
            // Null for regional-office members, who serve region-wide, and for
            // members not yet placed in a center — better no badge than a wrong one.
            return $member?->testingCenter
                ? ['label' => $member->testingCenter->name, 'icon' => 'map-pin']
                : null;
        }

        return $fieldOffice
            ? ['label' => $fieldOffice->name, 'icon' => 'building-office']
            : null;
    }

    /**
     * Real pending-approval queue preview for the dashboard's Approvals
     * panel (Management/Field Director) — mirrors CertificateApprovalController's
     * type-scoping so the count and preview match what /approvals actually shows.
     * Previously this panel was a hardcoded empty state regardless of the
     * real backlog.
     */
    private function pendingApprovals(UserRole $role, User $user): ?array
    {
        if (! $role->isManagement() && $role !== UserRole::FieldDirector) {
            return null;
        }

        $types = $role->isManagement()
            ? [CertificateType::Appreciation]
            : [CertificateType::Appearance, CertificateType::DesignationOrder, CertificateType::Appreciation];

        $base = Certificate::where('status', CertificateStatus::Pending)
            ->whereIn('type', $types)
            ->when(! $role->isRegionWide(), fn ($q) => $q->inJurisdictionOf($user));

        return [
            'total' => (clone $base)->count(),
            'items' => (clone $base)
                ->with('member:id,proctad_id,first_name,middle_name,last_name,suffix', 'fieldOffice:id,name,code')
                ->oldest('id')
                ->limit(5)
                ->get()
                ->map(fn (Certificate $certificate) => [
                    'id' => $certificate->id,
                    'type_label' => $certificate->type->label(),
                    'member_name' => $certificate->member->name,
                    'field_office' => $certificate->fieldOffice?->name,
                    'requested_at' => $certificate->created_at->diffForHumans(),
                ]),
        ];
    }

    /**
     * SaaS-style analytics for the admin dashboard: a 6-month registration
     * trend, member status breakdown, and a recent-registrations feed.
     * Region-wide roles (Super Admin/ESD Admin) see the whole region plus a
     * per-testing-center breakdown and can filter the recent-registrations
     * feed to one office; Field Office Staff (FO-scoped) see their own
     * office only, with a region-wide total alongside for context.
     */
    private function adminAnalytics(UserRole $role, User $user, Request $request): ?array
    {
        // Field Directors run their Field Office's operations alongside FO
        // Admin staff, so they get the same operational analytics.
        if (! in_array($role, [UserRole::SuperAdmin, UserRole::EsdAdmin, UserRole::FoAdmin, UserRole::FieldDirector], true)) {
            return null;
        }

        $regionWide = $role->isRegionWide();
        $ownFieldOfficeId = $user->field_office_id;

        // Region-wide roles may filter the recent-registrations feed to one
        // office; FO-scoped roles always see their own office.
        $filterFieldOfficeId = $regionWide
            ? ($request->filled('field_office_id') ? $request->integer('field_office_id') : null)
            : $ownFieldOfficeId;

        // The trend/status/secondary-stat scope: null (whole region) for
        // region-wide roles, own office for FO-scoped roles. Deliberately NOT
        // tied to $filterFieldOfficeId — a region-wide admin filtering the
        // feed to one office still sees region-wide trend/status context.
        $scopeFieldOfficeId = $regionWide ? null : $ownFieldOfficeId;

        return [
            'scope' => $regionWide ? 'region' : 'field_office',
            'fieldOffices' => $regionWide ? FieldOffice::orderBy('name')->get(['id', 'name', 'code']) : null,
            'selectedFieldOfficeId' => $filterFieldOfficeId,
            'registrationTrend' => $this->registrationTrend($scopeFieldOfficeId),
            'registrationsByTestingCenter' => $regionWide ? $this->registrationsByTestingCenter() : null,
            'statusBreakdown' => $this->memberStatusBreakdown($scopeFieldOfficeId),
            'recentRegistrations' => $this->recentRegistrations($filterFieldOfficeId),
            'regionTotalThisMonth' => $regionWide ? null : Member::query()
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->count(),
            'secondaryStats' => [
                $this->stat(
                    'Upcoming Examinations',
                    Examination::where('exam_date', '>=', today())
                        ->when($scopeFieldOfficeId, fn ($q) => $q->whereHas('assignments', fn ($q2) => $q2->where('field_office_id', $scopeFieldOfficeId)))
                        ->count(),
                    'calendar',
                    null,
                    '/examinations',
                ),
                $this->stat(
                    'Certificates Issued (Month)',
                    Certificate::where('status', CertificateStatus::Released)
                        ->whereYear('released_at', now()->year)
                        ->whereMonth('released_at', now()->month)
                        ->when($scopeFieldOfficeId, fn ($q) => $q->where('field_office_id', $scopeFieldOfficeId))
                        ->count(),
                    'document-check',
                    null,
                    '/certificates',
                ),
                $this->stat(
                    'Upcoming Trainings',
                    Training::whereNull('completed_at')
                        ->whereDate('training_date', '>=', today())
                        ->when($scopeFieldOfficeId, fn ($q) => $q->whereHas('assignments', fn ($q2) => $q2->where('field_office_id', $scopeFieldOfficeId)))
                        ->count(),
                    'academic-cap',
                    null,
                    '/trainings',
                ),
                $this->stat(
                    'Active Blacklist Entries',
                    Blacklist::where('status', BlacklistStatus::Active)
                        ->when($scopeFieldOfficeId, fn ($q) => $q->where('field_office_id', $scopeFieldOfficeId))
                        ->count(),
                    'exclamation-triangle',
                    null,
                    '/blacklists',
                ),
            ],
            'upcomingExaminations' => $this->upcomingExaminations($scopeFieldOfficeId),
            'performanceRatingBreakdown' => $this->performanceRatingBreakdown($scopeFieldOfficeId),
            'evaluationCompliance' => $this->evaluationCompliance($scopeFieldOfficeId),
        ];
    }

    /**
     * Next 5 upcoming exams with their assignment-confirmation progress —
     * the dashboard previously only showed a bare count with no way to see
     * which exams need attention or drill into them.
     */
    private function upcomingExaminations(?int $fieldOfficeId): array
    {
        return Examination::where('exam_date', '>=', today())
            ->when($fieldOfficeId, fn ($q) => $q->whereHas('assignments', fn ($q2) => $q2->where('field_office_id', $fieldOfficeId)))
            ->orderBy('exam_date')
            ->limit(5)
            ->get()
            ->map(function (Examination $exam) use ($fieldOfficeId) {
                $assignments = ExamAssignment::where('examination_id', $exam->id)
                    ->when($fieldOfficeId, fn ($q) => $q->where('field_office_id', $fieldOfficeId));

                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'exam_date' => $exam->exam_date->format('M d, Y'),
                    'assignments_total' => (clone $assignments)->count(),
                    'assignments_confirmed' => (clone $assignments)->where('status', AssignmentStatus::Confirmed)->count(),
                ];
            })
            ->all();
    }

    /**
     * Distribution of post-exam performance ratings across assignments —
     * surfaces administrator-quality trends the dashboard didn't show at all.
     */
    private function performanceRatingBreakdown(?int $fieldOfficeId): array
    {
        $scoped = fn () => ExamAssignment::query()
            ->whereNotNull('performance_rating')
            ->when($fieldOfficeId, fn ($q) => $q->where('field_office_id', $fieldOfficeId));

        return collect(PerformanceRating::cases())
            ->map(fn (PerformanceRating $rating) => [
                'key' => $rating->value,
                'label' => $rating->label(),
                'value' => (clone $scoped())->where('performance_rating', $rating)->count(),
                'color' => match ($rating) {
                    PerformanceRating::Outstanding, PerformanceRating::VerySatisfactory => 'good',
                    PerformanceRating::Satisfactory => 'neutral',
                    PerformanceRating::Unsatisfactory, PerformanceRating::Poor => 'critical',
                },
            ])
            ->all();
    }

    /**
     * Post-Examination Evaluation submission compliance for the most
     * recently dated exam (mirrors EvaluationMonitoringController's default
     * exam selection) — the Evaluation Monitoring module already computes
     * this, but the dashboard never surfaced it.
     */
    private function evaluationCompliance(?int $fieldOfficeId): ?array
    {
        $examination = Examination::query()->orderByDesc('exam_date')->first(['id', 'title']);

        if (! $examination) {
            return null;
        }

        $eligibleRoles = [
            ExamRole::ChiefExaminer, ExamRole::SupervisingExaminer, ExamRole::Proctor, ExamRole::RoomExaminer,
        ];

        $base = ExamAssignment::query()
            ->whereIn('role', array_column($eligibleRoles, 'value'))
            ->where('examination_id', $examination->id)
            ->when($fieldOfficeId, fn ($q) => $q->where('field_office_id', $fieldOfficeId));

        $total = (clone $base)->count();
        $submitted = (clone $base)->whereHas('evaluation')->count();

        return [
            'examination_title' => $examination->title,
            'total' => $total,
            'submitted' => $submitted,
            'percentage' => $total > 0 ? round($submitted / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Monthly registration counts for the last 6 months (this month inclusive).
     */
    private function registrationTrend(?int $fieldOfficeId): array
    {
        return collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($fieldOfficeId) {
                $month = now()->subMonths($monthsAgo);

                $count = Member::query()
                    ->whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->when($fieldOfficeId, fn ($q) => $q->where('field_office_id', $fieldOfficeId))
                    ->count();

                return ['label' => $month->format('M'), 'value' => $count];
            })
            ->values()
            ->all();
    }

    /**
     * Members are filed under a testing center, not an office (a center is
     * handled by several offices in turn — see TestingCenter), so the
     * all-time breakdown is per center.
     */
    private function registrationsByTestingCenter(): array
    {
        return TestingCenter::withCount('members')
            ->orderByDesc('members_count')
            ->orderBy('name')
            ->get()
            ->map(fn (TestingCenter $center) => ['label' => $center->name, 'value' => $center->members_count])
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, value: int, color: string}>
     */
    private function memberStatusBreakdown(?int $fieldOfficeId): array
    {
        $scoped = fn () => Member::query()->when($fieldOfficeId, fn ($q) => $q->where('field_office_id', $fieldOfficeId));

        return [
            ['key' => 'active', 'label' => 'Active', 'value' => (clone $scoped())->where('status', MemberStatus::Active)->count(), 'color' => 'good'],
            ['key' => 'inactive', 'label' => 'Inactive', 'value' => (clone $scoped())->where('status', MemberStatus::Inactive)->count(), 'color' => 'neutral'],
            ['key' => 'disqualified', 'label' => 'Disqualified', 'value' => (clone $scoped())->where('status', MemberStatus::Disqualified)->count(), 'color' => 'warning'],
            [
                'key' => 'blacklisted',
                'label' => 'Blacklisted',
                'value' => (clone $scoped())->whereHas('blacklists', fn ($q) => $q->where('status', BlacklistStatus::Active))->count(),
                'color' => 'critical',
            ],
        ];
    }

    private function recentRegistrations(?int $fieldOfficeId): Collection
    {
        return Member::query()
            ->with('fieldOffice:id,name,code')
            ->when($fieldOfficeId, fn ($q) => $q->where('field_office_id', $fieldOfficeId))
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Member $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'proctad_id' => $member->proctad_id,
                'field_office' => $member->fieldOffice?->name,
                'status_label' => $member->status->label(),
                'status_variant' => $member->status->badgeVariant(),
                'registered_at' => $member->created_at->diffForHumans(),
            ]);
    }

    /**
     * "At a glance" panel for the member dashboard — identity, eligibility
     * progress, and latest activity, so it surfaces something the sidebar
     * nav doesn't rather than just duplicating those links.
     */
    private function memberSummary(?Member $member): ?array
    {
        if (! $member) {
            return null;
        }

        $latestCertificate = Certificate::where('member_id', $member->id)->latest('id')->first();

        $latestService = ExamAssignment::where('member_id', $member->id)
            ->whereNotNull('attendance_confirmed_at')
            ->with('examination:id,title,exam_date')
            ->get()
            ->sortByDesc(fn ($assignment) => $assignment->examination?->exam_date)
            ->first();

        return [
            'proctad_id' => $member->proctad_id,
            'name' => $member->name,
            'status_label' => $member->status->label(),
            'status_variant' => $member->status->badgeVariant(),
            'photo_url' => $member->user?->google_avatar
                ?? ($member->photo_path ? route('members.photo', $member) : null),
            'requirements_complied' => $member->requirements->where('complied', true)->count(),
            'requirements_total' => count(EligibilityRequirement::cases()),
            'latest_certificate' => $latestCertificate ? [
                'type_label' => $latestCertificate->type->label(),
                'status_label' => $latestCertificate->status->label(),
                'status_variant' => $latestCertificate->status->badgeVariant(),
            ] : null,
            'latest_service' => $latestService ? [
                'title' => $latestService->examination?->title,
                'date' => $latestService->examination?->exam_date?->format('M d, Y'),
            ] : null,
        ];
    }

    /**
     * Stat cards per role, all backed by live counts.
     *
     * @return array<int, array{label: string, value: int, icon: string, hint: string|null}>
     */
    private function statsFor(UserRole $role, User $user, ?Member $member): array
    {
        $activeMembers = fn () => Member::where('status', MemberStatus::Active);
        $foMembers = fn () => $activeMembers()->withinJurisdictionOf($user);

        return match ($role) {
            UserRole::SuperAdmin, UserRole::EsdAdmin => [
                $this->stat('Active PROCTAD Members', $activeMembers()->count(), 'users', 'Region-wide', '/members'),
                $this->stat('Examinations', Examination::count(), 'clipboard-check', 'All exam events', '/examinations'),
                $this->stat('Field Offices', FieldOffice::count(), 'building-office', 'RO VIII', '/locations'),
                $this->stat('System Users', User::where('role', '!=', UserRole::Member)->count(), 'user-circle', 'Staff accounts', '/users'),
            ],
            UserRole::DirectorIv, UserRole::DirectorIii => [
                $this->stat(
                    'Pending Appreciation Approvals',
                    Certificate::where('status', CertificateStatus::Pending)
                        ->where('type', CertificateType::Appreciation)->count(),
                    'clipboard-check',
                    'Awaiting your action',
                    '/approvals',
                ),
                $this->stat('Active PROCTAD Members', $activeMembers()->count(), 'users', 'Region-wide', '/members'),
                $this->stat('Examinations', Examination::count(), 'calendar', 'All exam events', '/examinations'),
                $this->stat(
                    'Certificates Issued',
                    Certificate::where('status', CertificateStatus::Released)->count(),
                    'document-check',
                    'Region-wide, all types',
                    '/certificates',
                ),
            ],
            UserRole::FieldDirector => [
                $this->stat(
                    'Pending Approvals',
                    Certificate::where('status', CertificateStatus::Pending)
                        ->whereIn('type', [CertificateType::Appearance, CertificateType::DesignationOrder])
                        ->inJurisdictionOf($user)->count(),
                    'clipboard-check',
                    'Appearance & Designation Orders',
                    '/approvals',
                ),
                $this->stat('Active Members in Field Office', $foMembers()->count(), 'users', $user->fieldOffice?->name, '/members'),
                $this->stat('Service Records', ExamAssignment::inJurisdictionOf($user)->count(), 'document-text', 'Exam assignments in your FO'),
                $this->stat(
                    'Approved This Month',
                    Certificate::inJurisdictionOf($user)
                        ->whereIn('type', [CertificateType::Appearance, CertificateType::DesignationOrder])
                        ->whereNotNull('approved_at')
                        ->whereMonth('approved_at', now()->month)
                        ->whereYear('approved_at', now()->year)
                        ->count(),
                    'check-circle',
                    'Appearance & Designation Orders',
                    '/approvals',
                ),
            ],
            UserRole::FoAdmin => [
                $this->stat('Active Members in Field Office', $foMembers()->count(), 'users', $user->fieldOffice?->name, '/members'),
                $this->stat('Service Records', ExamAssignment::inJurisdictionOf($user)->count(), 'document-text', 'Exam assignments in your FO'),
                $this->stat(
                    'Issuance Requests',
                    Certificate::where('status', CertificateStatus::Pending)
                        ->inJurisdictionOf($user)->count(),
                    'paper-airplane',
                    'Awaiting approval',
                    '/certificates',
                ),
                $this->stat(
                    'Upcoming Trainings',
                    Training::whereNull('completed_at')
                        ->whereDate('training_date', '>=', today())
                        ->whereIn('field_office_id', $user->scopedFieldOfficeIds())
                        ->count(),
                    'academic-cap',
                    null,
                    '/trainings',
                ),
            ],
            UserRole::Member => [
                $this->stat(
                    'Examinations Served',
                    $member ? ExamAssignment::whereNotNull('attendance_confirmed_at')->where('member_id', $member->id)->count() : 0,
                    'clipboard-check',
                    'Attendance-confirmed assignments',
                    '/my/service-history',
                ),
                $this->stat(
                    'Certificates',
                    $member ? Certificate::where('status', CertificateStatus::Released)->where('member_id', $member->id)->count() : 0,
                    'document-check',
                    'Available for download',
                    '/my/certificates',
                ),
                $this->stat(
                    'Trainings Attended',
                    $member ? TrainingAssignment::whereNotNull('attendance_confirmed_at')->where('member_id', $member->id)->count() : 0,
                    'academic-cap',
                    null,
                    '/my/trainings',
                ),
                // Members are asked to evaluate an examination they served, but
                // nothing told them one was outstanding — they had to think to
                // visit the public evaluation page and search for themselves.
                $this->stat(
                    'Evaluations to Complete',
                    $member ? ExamAssignment::query()->awaitingEvaluationFor($member->id)->count() : 0,
                    'clipboard-check',
                    'Examinations you served that still need your evaluation',
                    '/evaluation',
                ),
            ],
        };
    }

    private function stat(string $label, int $value, string $icon, ?string $hint, ?string $href = null): array
    {
        return ['label' => $label, 'value' => $value, 'icon' => $icon, 'hint' => $hint, 'href' => $href];
    }
}
