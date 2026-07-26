<?php

namespace App\Http\Controllers;

use App\Enums\EligibilityRequirement;
use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Exports\ServiceRecordsExport;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\TestingCenter;
use App\Models\User;
use App\Services\IdCardPdfService;
use App\Services\PerformanceRatingCalculator;
use App\Support\MemberIdCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Member::class);

        $user = $request->user();
        $regionWide = $user->role->isRegionWide();

        $members = Member::query()
            ->with('fieldOffice:id,name,code', 'testingCenter:id,name', 'user.fieldOffice:id,is_regional')
            ->with(['assignments' => fn ($q) => $q
                ->whereNotNull('attendance_confirmed_at')
                ->with('examination:id,title,exam_date')])
            // Jurisdiction is the testing center, so Leyte I and Leyte II staff
            // share the Tacloban City roster. Regional-office members appear for
            // every office too: they serve region-wide and any office may need
            // to assign them, though MemberPolicy keeps them read-only here.
            ->when(! $regionWide, fn ($q) => $q
                ->where(fn ($q) => $q
                    ->whereIn('testing_center_id', $user->scopedTestingCenterIds())
                    ->orWhereHas('fieldOffice', fn ($o) => $o->where('is_regional', true))))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search')->trim();
                $q->where(fn ($q) => $q
                    ->where('proctad_id', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('agency', 'like', "%{$term}%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($regionWide && $request->filled('field_office_id'),
                fn ($q) => $q->where('field_office_id', $request->integer('field_office_id')))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Member $member) => $this->presentForList($member));

        return Inertia::render('Members/Index', [
            'members' => $members,
            'filters' => $request->only('search', 'status', 'field_office_id'),
            'fieldOffices' => $regionWide ? FieldOffice::orderBy('name')->get(['id', 'name', 'code']) : null,
            'statuses' => $this->statusOptions(),
            'can' => ['create' => $user->can('create', Member::class)],
        ]);
    }

    public function store(StoreMemberRequest $request): RedirectResponse
    {
        Gate::authorize('create', Member::class);

        $member = DB::transaction(function () use ($request) {
            $validated = $this->withPhoto($request, $request->validated());

            $member = Member::create([
                ...$validated,
                'user_id' => $this->resolveAccount($validated)->id,
            ]);

            foreach (EligibilityRequirement::cases() as $requirement) {
                $member->requirements()->create(['requirement' => $requirement]);
            }

            return $member;
        });

        return redirect()
            ->route('members.index')
            ->with('success', "Member registered with PROCTAD ID {$member->proctad_id}.");
    }

    public function show(Member $member): RedirectResponse
    {
        Gate::authorize('view', $member);

        return redirect()->route('members.index');
    }

    public function details(Member $member, PerformanceRatingCalculator $ratingCalculator): JsonResponse
    {
        Gate::authorize('view', $member);

        $member->load(
            'fieldOffice:id,name,code',
            'testingCenter:id,name',
            'requirements',
            'assignments.examination:id,title,type,exam_date',
        );

        $requirements = collect(EligibilityRequirement::cases())->map(function ($requirement) use ($member) {
            $record = $member->requirements->firstWhere('requirement', $requirement);

            return [
                'id' => $record?->id,
                'key' => $requirement->value,
                'label' => $requirement->label(),
                'complied' => (bool) $record?->complied,
                'has_file' => (bool) $record?->file_path,
                'remarks' => $record?->remarks,
            ];
        });

        return response()->json([
            'member' => $this->presentForList($member) + [
                'middle_name' => $member->middle_name,
                'suffix' => $member->suffix,
                'sex' => $member->sex,
                'date_of_birth' => $member->date_of_birth,
                'position' => $member->position,
                'disqualification_remarks' => $member->disqualification_remarks,
                'created_at' => $member->created_at->toDateString(),
                'photo_url' => $member->user?->google_avatar
                    ?? ($member->photo_path ? route('members.photo', $member) : null),
            ],
            'requirements' => $requirements,
            'compliedCount' => $requirements->where('complied', true)->count(),
            'serviceHistory' => $member->assignments
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
                }),
            'idCard' => MemberIdCard::data($member),
            'can' => ['update' => request()->user()->can('update', $member)],
        ]);
    }

    /**
     * Printable service-history report for one member (Phase 8 — derived,
     * not stored). A plain print-friendly Blade view rather than an Inertia
     * page, opened in a new tab with the browser's print dialog.
     */
    public function printServiceHistory(Member $member): View
    {
        Gate::authorize('view', $member);

        $member->load('fieldOffice:id,name,code', 'testingCenter:id,name', 'assignments.examination:id,title,exam_type_id,exam_date');

        return view('members.service-history-print', [
            'member' => $member,
            'serviceHistory' => $member->assignments->sortByDesc(fn ($a) => $a->examination?->exam_date)->values(),
        ]);
    }

    public function exportServiceHistory(Member $member): BinaryFileResponse
    {
        Gate::authorize('view', $member);

        return Excel::download(
            new ServiceRecordsExport(memberId: $member->id),
            "service-history-{$member->proctad_id}.xlsx",
        );
    }

    public function edit(Request $request, Member $member): RedirectResponse
    {
        Gate::authorize('update', $member);

        return redirect()->route('members.index');
    }

    public function editData(Request $request, Member $member): JsonResponse
    {
        Gate::authorize('update', $member);

        return response()->json([
            'member' => $member->only([
                'id', 'proctad_id', 'first_name', 'middle_name', 'last_name', 'suffix',
                'sex', 'date_of_birth', 'email', 'mobile_number', 'agency', 'position', 'field_office_id',
                'testing_center_id', 'status', 'disqualification_remarks',
            ]),
            'fieldOffices' => $this->assignableFieldOffices($request->user(), $member->field_office_id),
            'testingCenters' => $this->assignableTestingCenters($request->user(), $member->testing_center_id),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function update(UpdateMemberRequest $request, Member $member): RedirectResponse
    {
        Gate::authorize('update', $member);

        $validated = $this->withPhoto($request, $request->validated(), $member);

        if ($validated['status'] !== MemberStatus::Disqualified->value) {
            $validated['disqualification_remarks'] = null;
        }

        $member->update($validated);

        return redirect()
            ->route('members.index')
            ->with('success', 'Member record updated.');
    }

    public function destroy(Member $member): RedirectResponse
    {
        Gate::authorize('delete', $member);

        $member->delete();

        return redirect()
            ->route('members.index')
            ->with('success', "Member {$member->proctad_id} removed. The PROCTAD ID remains reserved.");
    }

    /**
     * Link an existing account by email, or create a member account.
     * The generated password is unusable until a reset flow ships;
     * members sign in with Google using the same email.
     */
    private function resolveAccount(array $validated): User
    {
        return User::firstOrCreate(['email' => $validated['email']], [
            'name' => trim(collect([
                $validated['first_name'],
                $validated['middle_name'] ?? null,
                $validated['last_name'],
                $validated['suffix'] ?? null,
            ])->filter()->implode(' ')),
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'suffix' => $validated['suffix'] ?? null,
            'mobile_number' => $validated['mobile_number'],
            'password' => Str::password(32),
            'role' => UserRole::Member,
            'field_office_id' => $validated['field_office_id'],
        ]);
    }

    /**
     * Store a newly uploaded photo (replacing any previous file) and swap the
     * validated 'photo' file for its stored path.
     */
    private function withPhoto($request, array $validated, ?Member $member = null): array
    {
        unset($validated['photo']);

        if ($request->hasFile('photo')) {
            if ($member?->photo_path) {
                Storage::disk('local')->delete($member->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store('member-photos', 'local');
        }

        return $validated;
    }

    public function photo(Member $member)
    {
        Gate::authorize('view', $member);

        abort_unless($member->photo_path && Storage::disk('local')->exists($member->photo_path), 404);

        return Storage::disk('local')->response($member->photo_path);
    }

    public function downloadIdCard(Member $member, IdCardPdfService $service): HttpResponse
    {
        Gate::authorize('view', $member);

        return $this->pdfResponse($service->renderMember($member), "proctad-id-{$member->proctad_id}.pdf");
    }

    public function downloadIdCardBulk(Request $request, IdCardPdfService $service): HttpResponse|JsonResponse
    {
        Gate::authorize('viewAny', Member::class);

        // 'ids' must be required and bounded: an absent/empty list previously fell
        // through to "no whereIn", rendering an ID card for every member in the
        // registry in one synchronous request.
        //
        // Validated by hand rather than via $request->validate(): this endpoint is
        // called by fetch() expecting a PDF, not by Inertia. shouldRenderJsonWhen()
        // in bootstrap/app.php limits JSON error rendering to 'api/*', so a thrown
        // ValidationException would redirect — and fetch() follows the redirect to a
        // 200 HTML page, which the caller would happily save as a .pdf.
        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first('ids'),
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        // Authorized per row rather than via a scoped query: requesting a member
        // outside your field office should 403, not silently drop them from the PDF.
        $members = Member::query()
            ->whereIn('id', $validator->validated()['ids'])
            ->get()
            ->each(fn (Member $member) => Gate::authorize('view', $member));

        abort_if($members->isEmpty(), 404);

        return $this->pdfResponse($service->renderMembersBulk($members), 'proctad-ids-bulk.pdf');
    }

    private function pdfResponse(string $pdf, string $filename): HttpResponse
    {
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function assignableFieldOffices(User $user, ?int $currentId = null)
    {
        return FieldOffice::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($currentId, fn ($q2) => $q2->orWhere('id', $currentId)))
            ->when($user->role->isFieldOfficeScoped(), fn ($q) => $q->whereIn('id', $user->scopedFieldOfficeIds()))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }

    /**
     * Testing centers this user may file a member under, tagged with the offices
     * that handle each so the form can narrow the list once an office is chosen.
     * The member's current center is always included, even if since deactivated,
     * so editing an existing record never silently moves them.
     */
    private function assignableTestingCenters(User $user, ?int $currentId = null)
    {
        return TestingCenter::query()
            ->where(fn ($q) => $q->where('is_active', true)->when($currentId, fn ($q2) => $q2->orWhere('id', $currentId)))
            ->when($user->role->isFieldOfficeScoped(), fn ($q) => $q->whereIn('id', $user->scopedTestingCenterIds()))
            ->with('fieldOffices:id')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (TestingCenter $center) => [
                'id' => $center->id,
                'name' => $center->name,
                'field_office_ids' => $center->fieldOffices->pluck('id'),
            ]);
    }

    private function statusOptions(): array
    {
        return collect(MemberStatus::cases())
            ->map(fn ($status) => ['value' => $status->value, 'label' => $status->label()])
            ->all();
    }

    private function presentForList(Member $member): array
    {
        $lastServed = $member->relationLoaded('assignments') ? $member->lastServed() : null;

        return [
            'last_served' => $lastServed ? [
                'title' => $lastServed->examination?->title,
                'date' => $lastServed->examination?->exam_date?->format('M d, Y'),
            ] : null,
            'id' => $member->id,
            'proctad_id' => $member->proctad_id,
            'name' => $member->name,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'email' => $member->email,
            'mobile_number' => $member->mobile_number,
            'agency' => $member->agency,
            'field_office' => $member->fieldOffice?->only('id', 'name', 'code'),
            'testing_center' => $member->testingCenter?->only('id', 'name'),
            // Surfaces the members left unplaced by the testing-center backfill:
            // their office handles several centers, so no center could be
            // derived and staff have to choose one. Regional-office members
            // legitimately have none, hence the exclusion.
            'needs_testing_center' => $member->testing_center_id === null && ! $member->isRegionWide(),
            'status' => $member->status->value,
            'status_label' => $member->status->label(),
            'status_variant' => $member->status->badgeVariant(),
        ];
    }
}
