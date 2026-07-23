<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\EligibilityRequirement;
use App\Exports\ServiceRecordsExport;
use App\Http\Requests\UpdateOwnProfileRequest;
use App\Models\ExamAssignment;
use App\Models\Member;
use App\Services\AssignmentConfirmationSender;
use App\Services\AssignmentResponder;
use App\Services\IdCardPdfService;
use App\Services\PerformanceRatingCalculator;
use App\Support\MemberIdCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
            // Built from the enum rather than from existing rows, matching
            // MemberController::details(). Mapping only stored rows meant a
            // member without them saw an empty list — "nothing is required of
            // you" — while staff saw a full outstanding set. Members created
            // through the app always have rows, but ETL-imported ones need not.
            'requirements' => $member === null ? [] : collect(EligibilityRequirement::cases())
                ->map(function (EligibilityRequirement $requirement) use ($member) {
                    $record = $member->requirements->firstWhere('requirement', $requirement);

                    return [
                        'key' => $requirement->value,
                        'label' => $requirement->label(),
                        'complied' => (bool) $record?->complied,
                        // Submitted but not yet verified — the member has done
                        // their part and is waiting on the Testing Center.
                        'submitted' => (bool) $record?->file_path,
                        'remarks' => $record?->remarks,
                    ];
                })
                ->values(),
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
                ->with([
                    'examination:id,title,type,exam_date',
                    'examinationSchool.school:id,name',
                    'room:id,room_number,designation,examination_school_id',
                    'confirmedBy:id,name',
                    // Eager-loaded for the needs_evaluation flag below — a lazy
                    // read would be one query per row across a whole history.
                    // Not column-constrained: evaluation() uses latestOfMany(),
                    // whose self-join makes an unqualified column list ambiguous.
                    'evaluation',
                    // For the exam-day cover note below — a substitution names
                    // the person covered, so the relation is needed per row.
                    'coveringFor.member:id,first_name,middle_name,last_name,suffix',
                    'coveredBy.member:id,first_name,middle_name,last_name,suffix',
                ])
                ->get()
                ->sortByDesc(fn ($assignment) => $assignment->examination?->exam_date)
                ->values()
                ->map(function ($assignment) use ($ratingCalculator) {
                    $computed = $ratingCalculator->computeFor($assignment);
                    $rating = $computed['rating'] ?? $assignment->performance_rating;

                    // A member learns their room only after physically reporting in
                    // and being scanned at the venue — never in advance. Once
                    // revealed it stays on the record, so past service keeps showing
                    // the room actually worked. The exam-date guard is what stops a
                    // backfilled or corrected attendance stamp from leaking the room
                    // for an examination that hasn't happened yet.
                    $examDate = $assignment->examination?->exam_date;
                    $roomVisible = $assignment->attendance_confirmed_at !== null
                        && $examDate !== null
                        && ! $examDate->isFuture();

                    return [
                        'id' => $assignment->id,
                        'exam_title' => $assignment->examination?->title,
                        'exam_type' => $assignment->examination?->type,
                        'exam_date' => $assignment->examination?->exam_date?->format('M d, Y'),
                        'role_label' => $assignment->role->label(),
                        'status_label' => $assignment->status->label(),
                        'status_variant' => $assignment->status->badgeVariant(),
                        'testing_center' => $assignment->examinationSchool?->school?->name,
                        // Withheld rooms are omitted from the payload entirely, not
                        // just hidden in the template — this page is the member's
                        // own data, so the value must not be readable in devtools.
                        'room' => $roomVisible && $assignment->room
                            ? trim("{$assignment->room->designation} {$assignment->room->room_number}")
                            : null,
                        'room_withheld' => ! $roomVisible && $assignment->room !== null,
                        'attended' => (bool) $assignment->attendance_confirmed_at,
                        'attendance_confirmed_at' => $assignment->attendance_confirmed_at?->format('M d, Y g:i A'),
                        // Absence is stated plainly on the member's own record.
                        // Left implicit it reads as an administrative gap, and
                        // a member has a real interest in seeing what their
                        // service record says about them.
                        'attendance_outcome' => $assignment->attendanceOutcome(),
                        'service_note' => $assignment->serviceNote(),
                        'confirmed_by' => $assignment->confirmedBy?->name,
                        'decline_reason' => $assignment->decline_reason,
                        'remarks' => $assignment->remarks,
                        'rating_label' => $rating?->label(),
                        'rating_variant' => $rating?->badgeVariant(),
                        'rating_average' => $computed['average'] ?? null,
                        'rating_count' => $computed['ratings_count'] ?? null,
                        // Same conditions the evaluation form enforces, so a
                        // record is never flagged as needing one that the form
                        // would then refuse — see ExamAssignment::scopeAwaitingEvaluationFor.
                        'needs_evaluation' => $assignment->role->isEvaluable()
                            && $assignment->attendance_confirmed_at !== null
                            && $assignment->evaluation === null,
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

    /**
     * A member's own supporting document for one eligibility requirement.
     *
     * Submission only — `complied` stays staff-controlled. A member uploading a
     * file must not be able to mark themselves eligible; a Testing Center still
     * verifies the document and flips the flag. The member sees "submitted,
     * awaiting verification" in the meantime.
     */
    public function uploadRequirement(Request $request, string $requirement): RedirectResponse
    {
        $member = $this->ownMember($request);
        abort_if($member === null, 403);

        $key = EligibilityRequirement::tryFrom($requirement) ?? abort(404);

        $request->validate([
            'file' => ['required', 'file', 'max:5120', Rule::file()->types(['pdf', 'jpg', 'jpeg', 'png'])],
        ]);

        $record = $member->requirements()->firstOrCreate(['requirement' => $key]);

        // Already verified: re-submitting would replace the evidence a Testing
        // Center accepted, with no trace that it changed.
        if ($record->complied) {
            return back()->with('error', 'This requirement has already been verified. Contact your Testing Center if it needs to be updated.');
        }

        if ($record->file_path) {
            Storage::disk('local')->delete($record->file_path);
        }

        $record->update([
            'file_path' => $request->file('file')->store("member-requirements/{$member->id}", 'local'),
        ]);

        return back()->with('success', 'Document submitted. Your Testing Center will verify it.');
    }

    /**
     * Upcoming deployments, with the option to respond in place.
     *
     * The emailed signed link stays the primary route, but it expires after
     * seven days and a member cannot resend it to themselves — so a missed
     * email previously left them unable to see or answer an assignment at all,
     * short of telephoning their Testing Center.
     *
     * Deliberately no room in the payload: it is disclosed in person on exam
     * day, and every assignment listed here is by definition still upcoming
     * (see serviceHistory(), where the room appears only once attendance is
     * confirmed and the date has passed).
     */
    public function assignments(Request $request): Response
    {
        $member = $this->ownMember($request);

        return Inertia::render('My/Assignments', [
            'hasRecord' => $member !== null,
            'records' => $member?->assignments()
                ->whereHas('examination', fn ($q) => $q->whereDate('exam_date', '>=', today()))
                ->with('examination:id,title,type,exam_date', 'examinationSchool.school:id,name')
                ->get()
                ->sortBy(fn ($assignment) => $assignment->examination?->exam_date)
                ->values()
                ->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'exam_title' => $assignment->examination?->title,
                    'exam_type' => $assignment->examination?->type,
                    'exam_date' => $assignment->examination?->exam_date?->format('F j, Y (l)'),
                    'role_label' => $assignment->role->label(),
                    'venue' => $assignment->examinationSchool?->school?->name,
                    'status' => $assignment->status->value,
                    'status_label' => $assignment->status->label(),
                    'status_variant' => $assignment->status->badgeVariant(),
                    'awaiting_response' => $assignment->status === AssignmentStatus::Pending,
                    'decline_reason' => $assignment->decline_reason,
                    'responded_at' => $assignment->responded_at?->format('M d, Y'),
                    // The emailed link's deadline, restated so the member can see
                    // it without digging the email out.
                    'respond_by' => $assignment->confirmation_sent_at
                        ?->addDays(AssignmentConfirmationSender::LINK_LIFETIME_DAYS)
                        ->format('F j, Y'),
                ]) ?? [],
        ]);
    }

    /**
     * A signed-in member's response, mirroring the emailed signed link.
     * Both funnel through AssignmentResponder so the one-shot rule, the audit
     * row and the re-staffing notification cannot drift apart.
     */
    public function respondToAssignment(Request $request, ExamAssignment $assignment, AssignmentResponder $responder): RedirectResponse
    {
        $member = $this->ownMember($request);

        // Own-record only: a member may answer for themselves and nobody else.
        abort_unless($member !== null && $assignment->member_id === $member->id, 403);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['confirm', 'decline'])],
            'decline_reason' => ['required_if:action,decline', 'nullable', 'string', 'max:500'],
        ]);

        $confirmed = $validated['action'] === 'confirm';

        $recorded = $responder->respond(
            $assignment,
            $confirmed,
            $validated['decline_reason'] ?? null,
            $request->ip(),
            $request->userAgent(),
        );

        if (! $recorded) {
            return back()->with('error', 'You have already responded to this assignment. To change your response, please contact your Testing Center.');
        }

        return back()->with('success', $confirmed
            ? 'Thank you — your assignment is confirmed.'
            : 'Your response has been recorded. Thank you for letting us know.');
    }

    public function trainings(Request $request): Response
    {
        $member = $this->ownMember($request);

        return Inertia::render('My/Trainings', [
            'hasRecord' => $member !== null,
            'records' => $member?->trainingAssignments()
                ->with('training:id,title,type,training_date,end_date,venue,completed_at')
                ->get()
                ->sortByDesc(fn ($assignment) => $assignment->training?->training_date)
                ->values()
                ->map(fn ($assignment) => [
                    'id' => $assignment->id,
                    'title' => $assignment->training?->title,
                    'type_label' => $assignment->training?->type->label(),
                    'date' => $assignment->training?->training_date?->format('M d, Y'),
                    'end_date' => $assignment->training?->end_date?->format('M d, Y'),
                    'venue' => $assignment->training?->venue,
                    'attended' => (bool) $assignment->attendance_confirmed_at,
                    'attendance_confirmed_at' => $assignment->attendance_confirmed_at?->format('M d, Y g:i A'),
                    'completed' => $assignment->training?->completed_at !== null,
                    'completed_at' => $assignment->training?->completed_at?->format('M d, Y'),
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
