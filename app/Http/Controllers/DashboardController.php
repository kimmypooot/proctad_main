<?php

namespace App\Http\Controllers;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Enums\EligibilityRequirement;
use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $role = $user->role ?? UserRole::Member;
        $fieldOffice = $user->fieldOffice;
        $member = $role === UserRole::Member
            ? Member::with('requirements', 'user:id,google_avatar')->where('user_id', $user->id)->first()
            : null;

        return Inertia::render('Dashboard/Index', [
            'role' => $role->value,
            'roleLabel' => $role->label(),
            'fieldOffice' => $fieldOffice?->only('id', 'name', 'code'),
            'stats' => $this->statsFor($role, $user, $member),
            'memberSummary' => $role === UserRole::Member ? $this->memberSummary($member) : null,
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
        $foMembers = fn () => $activeMembers()->where('field_office_id', $user->field_office_id);

        return match ($role) {
            UserRole::SuperAdmin, UserRole::EsdAdmin => [
                $this->stat('Active PROCTAD Members', $activeMembers()->count(), 'users', 'Region-wide'),
                $this->stat('Examinations', Examination::count(), 'clipboard-check', 'All exam events'),
                $this->stat('Testing Centers', FieldOffice::count(), 'building-office', 'RO VIII'),
                $this->stat('System Users', User::where('role', '!=', UserRole::Member)->count(), 'user-circle', 'Staff accounts'),
            ],
            UserRole::Management => [
                $this->stat(
                    'Pending Appreciation Approvals',
                    Certificate::where('status', CertificateStatus::Pending)
                        ->where('type', CertificateType::Appreciation)->count(),
                    'clipboard-check',
                    'Awaiting your action',
                ),
                $this->stat('Active PROCTAD Members', $activeMembers()->count(), 'users', 'Region-wide'),
                $this->stat('Examinations', Examination::count(), 'calendar', 'All exam events'),
                $this->stat(
                    'Certificates Issued',
                    Certificate::where('status', CertificateStatus::Released)->count(),
                    'document-check',
                    'Region-wide, all types',
                ),
            ],
            UserRole::FieldDirector => [
                $this->stat(
                    'Pending Approvals',
                    Certificate::where('status', CertificateStatus::Pending)
                        ->whereIn('type', [CertificateType::Appearance, CertificateType::DesignationOrder])
                        ->where('field_office_id', $user->field_office_id)->count(),
                    'clipboard-check',
                    'Appearance & Designation Orders',
                ),
                $this->stat('Active Members in Testing Center', $foMembers()->count(), 'users', $user->fieldOffice?->name),
                $this->stat('Service Records', ExamAssignment::where('field_office_id', $user->field_office_id)->count(), 'document-text', 'Exam assignments in your FO'),
                $this->stat(
                    'Approved This Month',
                    Certificate::where('field_office_id', $user->field_office_id)
                        ->whereIn('type', [CertificateType::Appearance, CertificateType::DesignationOrder])
                        ->whereNotNull('approved_at')
                        ->whereMonth('approved_at', now()->month)
                        ->whereYear('approved_at', now()->year)
                        ->count(),
                    'check-circle',
                    'Appearance & Designation Orders',
                ),
            ],
            UserRole::FoAdmin => [
                $this->stat('Active Members in Testing Center', $foMembers()->count(), 'users', $user->fieldOffice?->name),
                $this->stat('Service Records', ExamAssignment::where('field_office_id', $user->field_office_id)->count(), 'document-text', 'Exam assignments in your FO'),
                $this->stat(
                    'Issuance Requests',
                    Certificate::where('status', CertificateStatus::Pending)
                        ->where('field_office_id', $user->field_office_id)->count(),
                    'paper-airplane',
                    'Awaiting approval',
                ),
                $this->stat(
                    'Upcoming Trainings',
                    Training::whereNull('completed_at')->whereDate('training_date', '>=', today())->count(),
                    'academic-cap',
                    null,
                ),
            ],
            UserRole::Member => [
                $this->stat(
                    'Examinations Served',
                    $member ? ExamAssignment::whereNotNull('attendance_confirmed_at')->where('member_id', $member->id)->count() : 0,
                    'clipboard-check',
                    'Attendance-confirmed assignments',
                ),
                $this->stat(
                    'Certificates',
                    $member ? Certificate::where('status', CertificateStatus::Released)->where('member_id', $member->id)->count() : 0,
                    'document-check',
                    'Available for download',
                ),
                $this->stat(
                    'Trainings Attended',
                    $member ? TrainingAssignment::whereNotNull('attendance_confirmed_at')->where('member_id', $member->id)->count() : 0,
                    'academic-cap',
                    null,
                ),
                $this->stat(
                    'Eligibility Requirements Complied',
                    $member?->requirements->where('complied', true)->count() ?? 0,
                    'shield-check',
                    $member?->status->label() ?? 'Not yet registered',
                ),
            ],
        };
    }

    private function stat(string $label, int $value, string $icon, ?string $hint): array
    {
        return ['label' => $label, 'value' => $value, 'icon' => $icon, 'hint' => $hint];
    }
}
