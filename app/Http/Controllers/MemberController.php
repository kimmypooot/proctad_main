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
use App\Models\User;
use App\Services\IdCardPdfService;
use App\Services\PerformanceRatingCalculator;
use App\Support\MemberIdCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
            ->with('fieldOffice:id,name,code')
            ->with(['assignments' => fn ($q) => $q
                ->whereNotNull('attendance_confirmed_at')
                ->with('examination:id,title,exam_date')])
            ->when(! $regionWide, fn ($q) => $q->where('field_office_id', $user->field_office_id))
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

    public function create(Request $request): Response
    {
        Gate::authorize('create', Member::class);

        return Inertia::render('Members/Create', [
            'fieldOffices' => $this->assignableFieldOffices($request->user()),
            'prefill' => $this->prefillFromUnlinkedUser($request),
        ]);
    }

    /**
     * Jumping here from the Users page's "unlinked member accounts" worklist
     * (?from_user=ID) prefills the form from that self-registered account —
     * submitting normally still goes through resolveAccount()'s email match,
     * which will find and link this exact account rather than creating a new one.
     */
    private function prefillFromUnlinkedUser(Request $request): ?array
    {
        if (! $request->filled('from_user')) {
            return null;
        }

        $user = User::where('id', $request->integer('from_user'))
            ->where('role', UserRole::Member)
            ->doesntHave('member')
            ->first();

        if (! $user) {
            return null;
        }

        return $user->only('first_name', 'middle_name', 'last_name', 'suffix', 'email', 'mobile_number', 'field_office_id');
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
            ->route('members.show', $member)
            ->with('success', "Member registered with PROCTAD ID {$member->proctad_id}.");
    }

    public function show(Member $member, PerformanceRatingCalculator $ratingCalculator): Response
    {
        Gate::authorize('view', $member);

        $member->load(
            'fieldOffice:id,name,code',
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

        return Inertia::render('Members/Show', [
            'member' => $this->presentForList($member) + [
                'middle_name' => $member->middle_name,
                'suffix' => $member->suffix,
                'sex' => $member->sex,
                'position' => $member->position,
                'disqualification_remarks' => $member->disqualification_remarks,
                'created_at' => $member->created_at->toDateString(),
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

        $member->load('fieldOffice:id,name,code', 'assignments.examination:id,title,exam_type_id,exam_date');

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

    public function edit(Request $request, Member $member): Response
    {
        Gate::authorize('update', $member);

        return Inertia::render('Members/Edit', [
            'member' => $member->only([
                'id', 'proctad_id', 'first_name', 'middle_name', 'last_name', 'suffix',
                'sex', 'email', 'mobile_number', 'agency', 'position', 'field_office_id',
                'status', 'disqualification_remarks',
            ]),
            'fieldOffices' => $this->assignableFieldOffices($request->user(), $member->field_office_id),
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
            ->route('members.show', $member)
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

    public function downloadIdCardBulk(Request $request, IdCardPdfService $service): HttpResponse
    {
        Gate::authorize('viewAny', Member::class);

        $ids = $request->input('ids', []);
        $members = Member::query()
            ->when($ids !== [], fn ($q) => $q->whereIn('id', $ids))
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
            ->when($user->role === UserRole::FoAdmin, fn ($q) => $q->whereKey($user->field_office_id))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
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
            'status' => $member->status->value,
            'status_label' => $member->status->label(),
            'status_variant' => $member->status->badgeVariant(),
        ];
    }
}
