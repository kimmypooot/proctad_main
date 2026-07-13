<?php

namespace App\Http\Controllers;

use App\Enums\BlacklistStatus;
use App\Enums\UserRole;
use App\Http\Requests\StoreBlacklistRequest;
use App\Models\Blacklist;
use App\Models\FieldOffice;
use App\Models\Member;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BlacklistController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Blacklist::class);

        $user = $request->user();
        $regionWide = $user->role->isRegionWide();

        $blacklists = Blacklist::query()
            ->with(['member:id,proctad_id,first_name,middle_name,last_name,suffix', 'fieldOffice:id,name,code', 'blacklistedBy:id,name', 'liftedBy:id,name'])
            ->when(! $regionWide, fn ($q) => $q->where('field_office_id', $user->field_office_id))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = $request->string('search')->trim();
                $q->whereHas('member', fn ($q) => $q
                    ->where('proctad_id', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%"));
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($regionWide && $request->filled('field_office_id'),
                fn ($q) => $q->where('field_office_id', $request->integer('field_office_id')))
            ->latest('blacklisted_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Blacklist $blacklist) => [
                'id' => $blacklist->id,
                'member' => [
                    'id' => $blacklist->member->id,
                    'name' => $blacklist->member->name,
                    'proctad_id' => $blacklist->member->proctad_id,
                ],
                'field_office' => $blacklist->fieldOffice?->name,
                'reason' => $blacklist->reason,
                'status' => $blacklist->status->value,
                'status_label' => $blacklist->status->label(),
                'status_variant' => $blacklist->status->badgeVariant(),
                'blacklisted_by' => $blacklist->blacklistedBy?->name,
                'blacklisted_at' => $blacklist->blacklisted_at->format('M d, Y g:i A'),
                'lifted_by' => $blacklist->liftedBy?->name,
                'lifted_at' => $blacklist->lifted_at?->format('M d, Y g:i A'),
                'lift_reason' => $blacklist->lift_reason,
                'can_lift' => $user->can('lift', $blacklist),
            ]);

        return Inertia::render('Blacklists/Index', [
            'blacklists' => $blacklists,
            'filters' => $request->only('search', 'status', 'field_office_id'),
            'fieldOffices' => $regionWide ? FieldOffice::orderBy('name')->get(['id', 'name', 'code']) : null,
            'can' => ['create' => $this->canCreateAny($user)],
        ]);
    }

    /**
     * Typeahead for the "Blacklist a Member" modal — avoids preloading the
     * whole (potentially thousands-strong) member roster into the page.
     */
    public function searchMembers(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Blacklist::class);

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $term = $validated['q'];

        $members = Member::query()
            ->select('id', 'proctad_id', 'first_name', 'middle_name', 'last_name', 'suffix')
            ->when(! $user->role->isRegionWide(), fn ($q) => $q->where('field_office_id', $user->field_office_id))
            ->whereDoesntHave('blacklists', fn ($q) => $q->where('status', BlacklistStatus::Active))
            ->where(fn ($q) => $q
                ->where('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%"))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(10)
            ->get();

        return response()->json([
            'results' => $members->map(fn (Member $member) => [
                'id' => $member->id,
                'label' => "{$member->name} ({$member->proctad_id})",
            ]),
        ]);
    }

    public function store(StoreBlacklistRequest $request): RedirectResponse
    {
        $member = Member::findOrFail($request->validated('member_id'));

        Gate::authorize('create', [Blacklist::class, $member]);

        if ($member->blacklists()->where('status', BlacklistStatus::Active)->exists()) {
            throw ValidationException::withMessages([
                'member_id' => 'This member is already blacklisted.',
            ]);
        }

        Blacklist::create([
            'member_id' => $member->id,
            'field_office_id' => $member->field_office_id,
            'reason' => $request->validated('reason'),
            'status' => BlacklistStatus::Active,
            'blacklisted_by' => $request->user()->id,
            'blacklisted_at' => now(),
        ]);

        return back()->with('success', "{$member->name} has been blacklisted.");
    }

    public function lift(Request $request, Blacklist $blacklist): RedirectResponse
    {
        Gate::authorize('lift', $blacklist);

        abort_if($blacklist->status === BlacklistStatus::Lifted, 404);

        $validated = $request->validate([
            'lift_reason' => ['required', 'string', 'max:1000'],
        ]);

        $blacklist->update([
            'status' => BlacklistStatus::Lifted,
            'lifted_by' => $request->user()->id,
            'lifted_at' => now(),
            'lift_reason' => $validated['lift_reason'],
        ]);

        return back()->with('success', 'Blacklist entry lifted.');
    }

    /**
     * Whether this user may blacklist a member anywhere within their own scope
     * (region-wide roles: anywhere; FO-scoped roles: their own Testing Center) —
     * mirrors BlacklistPolicy::manage() without needing a concrete Member.
     */
    private function canCreateAny(User $user): bool
    {
        return $user->hasRole(UserRole::SuperAdmin, UserRole::EsdAdmin) || $user->role->isFieldOfficeScoped();
    }
}
