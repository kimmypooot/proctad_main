<?php

namespace App\Http\Controllers;

use App\Enums\EligibilityRequirement;
use App\Exports\ServiceRecordsExport;
use App\Http\Requests\UpdateOwnProfileRequest;
use App\Models\Member;
use App\Services\IdCardPdfService;
use App\Services\PerformanceRatingCalculator;
use App\Support\MemberIdCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MyProctadController extends Controller
{
    public function profile(Request $request): Response
    {
        $member = $this->ownMember($request);

        return Inertia::render('My/Profile', [
            'member' => $member ? [
                'proctad_id' => $member->proctad_id,
                'name' => $member->name,
                'first_name' => $member->first_name,
                'middle_name' => $member->middle_name,
                'last_name' => $member->last_name,
                'suffix' => $member->suffix,
                'sex' => $member->sex,
                'email' => $member->email,
                'mobile_number' => $member->mobile_number,
                'agency' => $member->agency,
                'position' => $member->position,
                'field_office' => $member->fieldOffice?->name,
                'status_label' => $member->status->label(),
                'status_variant' => $member->status->badgeVariant(),
                'photo_url' => $member->user?->google_avatar
                    ?? ($member->photo_path ? route('members.photo', $member) : null),
            ] : null,
            'requirements' => $member?->requirements
                ->map(fn ($record) => [
                    'label' => $record->requirement->label(),
                    'complied' => $record->complied,
                    'remarks' => $record->remarks,
                ])
                ->values() ?? [],
            'requirementsTotal' => count(EligibilityRequirement::cases()),
        ]);
    }

    /**
     * Self-service edit — intentionally limited to contact details and photo.
     * Identity fields (name, sex), agency, and Testing Center stay
     * staff-controlled so the accreditation record can't drift from what a
     * Testing Center verified.
     */
    public function updateProfile(UpdateOwnProfileRequest $request): RedirectResponse
    {
        $member = $this->ownMember($request);

        abort_unless($member, 404);

        $validated = $this->withPhoto($request, $request->validated(), $member);

        $member->update($validated);

        return redirect()->route('my.profile')->with('success', 'Profile updated.');
    }

    public function qrCode(Request $request): Response
    {
        $member = $this->ownMember($request);

        return Inertia::render('My/QrCode', [
            'idCard' => $member ? MemberIdCard::data($member) : null,
        ]);
    }

    public function idCardDownload(Request $request, IdCardPdfService $service): HttpResponse
    {
        $member = $this->ownMember($request);

        abort_unless($member, 404);

        return response($service->renderMember($member), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"proctad-id-{$member->proctad_id}.pdf\"",
        ]);
    }

    public function serviceHistory(Request $request, PerformanceRatingCalculator $ratingCalculator): Response
    {
        $member = $this->ownMember($request);

        return Inertia::render('My/ServiceHistory', [
            'hasRecord' => $member !== null,
            'records' => $member?->assignments()
                ->with('examination:id,title,type,exam_date')
                ->get()
                ->sortByDesc(fn ($assignment) => $assignment->examination?->exam_date)
                ->values()
                ->map(function ($assignment) use ($ratingCalculator) {
                    $computed = $ratingCalculator->computeFor($assignment);
                    $rating = $computed['rating'] ?? $assignment->performance_rating;

                    return [
                        'id' => $assignment->id,
                        'exam_title' => $assignment->examination?->title,
                        'exam_type' => $assignment->examination?->type,
                        'exam_date' => $assignment->examination?->exam_date?->format('M d, Y'),
                        'role_label' => $assignment->role->label(),
                        'attended' => (bool) $assignment->attendance_confirmed_at,
                        'rating_label' => $rating?->label(),
                        'rating_variant' => $rating?->badgeVariant(),
                    ];
                }) ?? [],
        ]);
    }

    /**
     * Printable service-history report for the signed-in member — the
     * same print view MemberController uses for staff, just scoped to
     * the caller's own record instead of an arbitrary {member}.
     */
    public function printServiceHistory(Request $request): View
    {
        $member = $this->ownMember($request);

        abort_unless($member, 404);

        $member->load('fieldOffice:id,name,code', 'assignments.examination:id,title,exam_type_id,exam_date');

        return view('members.service-history-print', [
            'member' => $member,
            'serviceHistory' => $member->assignments->sortByDesc(fn ($a) => $a->examination?->exam_date)->values(),
        ]);
    }

    public function exportServiceHistory(Request $request): BinaryFileResponse
    {
        $member = $this->ownMember($request);

        abort_unless($member, 404);

        return Excel::download(
            new ServiceRecordsExport(memberId: $member->id),
            "service-history-{$member->proctad_id}.xlsx",
        );
    }

    public function certificates(Request $request): Response
    {
        $member = $this->ownMember($request);

        return Inertia::render('My/Certificates', [
            'hasRecord' => $member !== null,
            'certificates' => $member?->certificates()
                ->with('certifiable')
                ->latest('id')
                ->get()
                ->map(fn ($certificate) => [
                    'id' => $certificate->id,
                    'certificate_no' => $certificate->certificate_no,
                    'type' => $certificate->type->value,
                    'type_label' => $certificate->type->label(),
                    'source' => $certificate->sourceDescription(),
                    'source_date' => $certificate->sourceDate(),
                    'status' => $certificate->status->value,
                    'status_label' => $certificate->status->label(),
                    'status_variant' => $certificate->status->badgeVariant(),
                    'disapproval_remarks' => $certificate->disapproval_remarks,
                    'released_at' => $certificate->released_at?->format('M d, Y'),
                ]) ?? [],
        ]);
    }

    public function trainings(Request $request): Response
    {
        $member = $this->ownMember($request);

        return Inertia::render('My/Trainings', [
            'hasRecord' => $member !== null,
            'records' => $member?->trainingAssignments()
                ->with('training:id,title,type,training_date,completed_at')
                ->get()
                ->sortByDesc(fn ($assignment) => $assignment->training?->training_date)
                ->values()
                ->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'title' => $assignment->training?->title,
                    'type_label' => $assignment->training?->type->label(),
                    'date' => $assignment->training?->training_date?->format('M d, Y'),
                    'attended' => (bool) $assignment->attendance_confirmed_at,
                    'completed' => $assignment->training?->completed_at !== null,
                ]) ?? [],
        ]);
    }

    private function ownMember(Request $request): ?Member
    {
        return Member::with('fieldOffice:id,name,code', 'requirements', 'user:id,google_avatar')
            ->where('user_id', $request->user()->id)
            ->first();
    }

    /**
     * Store a newly uploaded photo (replacing any previous file) and swap the
     * validated 'photo' file for its stored path.
     */
    private function withPhoto(Request $request, array $validated, Member $member): array
    {
        unset($validated['photo']);

        if ($request->hasFile('photo')) {
            if ($member->photo_path) {
                Storage::disk('local')->delete($member->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store('member-photos', 'local');
        }

        return $validated;
    }
}
