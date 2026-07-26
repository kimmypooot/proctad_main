<?php

namespace App\Http\Controllers;

use App\Enums\PayeeType;
use App\Enums\Permission;
use App\Models\Designation;
use App\Models\DesignationCategory;
use App\Models\ExamAssignment;
use App\Models\FeeSchedule;
use App\Models\OtherExaminationPersonnel;
use App\Support\DesignationRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Examination designations and the committees they are filed under.
 *
 * Built-in designations and committees are seeded from ExamRole, PersonnelType
 * and their group enums, and cannot be deleted: the payroll workbook gives Room
 * Examiners and Proctors their own pages, the evaluation form covers four
 * designations by name, and the REC chairs are held ex officio. Deactivating is
 * the supported way to retire one.
 *
 * Custom designations are ordinary rows: assignable, payable on the payroll's
 * catch-all page, and — given a `rooms_per_slot` — staffed in the per-room grid
 * alongside the built-in three. What they stand outside is the payroll's
 * dedicated pages and the evaluation form, both of which name designations
 * directly.
 */
class DesignationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeManage($request);

        $rates = FeeSchedule::all()
            ->keyBy(fn (FeeSchedule $rate) => "{$rate->payee_type->value}:{$rate->payee_value}");

        $usage = $this->usageCounts();

        return Inertia::render('Settings/Designations/Index', [
            'sections' => collect(PayeeType::cases())->map(fn (PayeeType $type) => [
                'value' => $type->value,
                'name' => $type === PayeeType::ExamRole ? 'Examination Designations' : 'Other Examination Personnel',
                'description' => $type === PayeeType::ExamRole
                    ? 'Duties assigned to accredited PROCTAD members during an examination.'
                    : 'Support personnel engaged for an examination who are not PROCTAD members.',
                'categories' => DesignationCategory::where('section', $type->value)
                    ->with(['designations' => fn ($q) => $q->orderBy('sort_order')->orderBy('label')])
                    ->orderBy('sort_order')
                    ->get()
                    ->map(fn (DesignationCategory $category) => [
                        'id' => $category->id,
                        'key' => $category->key,
                        'label' => $category->label,
                        'is_builtin' => $category->is_builtin,
                        'designation_count' => $category->designations->count(),
                        'designations' => $category->designations->map(function (Designation $designation) use ($rates, $usage) {
                            $cents = $rates->get("{$designation->section}:{$designation->key}")?->amount_cents ?? 0;

                            return [
                                'id' => $designation->id,
                                'key' => $designation->key,
                                'label' => $designation->label,
                                'is_active' => $designation->is_active,
                                'is_builtin' => $designation->is_builtin,
                                'rooms_per_slot' => $designation->rooms_per_slot,
                                'category_id' => $designation->designation_category_id,
                                'amount' => $cents / 100,
                                'rate_configured' => $cents > 0,
                                'usage_count' => $usage["{$designation->section}|{$designation->key}"] ?? 0,
                            ];
                        })->values()->all(),
                    ])->values()->all(),
            ])->values()->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'section' => ['required', Rule::enum(PayeeType::class)],
            'designation_category_id' => ['required', 'integer'],
            'label' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'rooms_per_slot' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $category = DesignationCategory::findOrFail($validated['designation_category_id']);

        // A committee belongs to one list; filing an exam designation under a
        // personnel grouping would put it in the wrong dropdown entirely.
        abort_unless($category->section === $validated['section'], 422, 'That committee belongs to a different list.');

        $key = $this->uniqueKey($validated['section'], $validated['label']);

        $designation = Designation::create([
            'section' => $validated['section'],
            'key' => $key,
            'label' => trim($validated['label']),
            'designation_category_id' => $category->id,
            'is_active' => true,
            'rooms_per_slot' => $validated['rooms_per_slot'] ?? null,
            'sort_order' => (int) Designation::where('designation_category_id', $category->id)->max('sort_order') + 1,
        ]);

        $this->saveRate($request, $designation, (float) $validated['amount']);

        DesignationRegistry::flush();

        return back()->with('success', "\"{$designation->label}\" added.");
    }

    public function update(Request $request, Designation $designation): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'designation_category_id' => ['required', 'integer'],
            'is_active' => ['required', 'boolean'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'rooms_per_slot' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $category = DesignationCategory::findOrFail($validated['designation_category_id']);

        abort_unless($category->section === $designation->section, 422, 'That committee belongs to a different list.');

        // `key` is deliberately not updatable: it is the value already written
        // to every historical assignment.
        $designation->update([
            'label' => trim($validated['label']),
            'designation_category_id' => $category->id,
            'is_active' => $validated['is_active'],
            'rooms_per_slot' => $validated['rooms_per_slot'] ?? null,
        ]);

        $this->saveRate($request, $designation, (float) $validated['amount']);

        DesignationRegistry::flush();

        return back()->with('success', 'Designation updated.');
    }

    /**
     * Deleting is guarded three ways: built-ins are never deletable, a
     * designation in use by any assignment is refused, and the caller must
     * retype the exact name. The rate row goes with it, since nothing can
     * reference it any more.
     */
    public function destroy(Request $request, Designation $designation): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'confirm_label' => ['required', 'string'],
        ]);

        abort_if(
            $designation->is_builtin,
            422,
            'Built-in designations cannot be deleted — switch it off instead so historical records keep their meaning.',
        );

        if (trim($validated['confirm_label']) !== $designation->label) {
            return back()->withErrors([
                'confirm_label' => 'The name does not match. Type it exactly as shown to confirm.',
            ]);
        }

        $inUse = $this->usageCounts()["{$designation->section}|{$designation->key}"] ?? 0;

        abort_if(
            $inUse > 0,
            422,
            "This designation is used by {$inUse} assignment(s) and cannot be deleted. Switch it off instead.",
        );

        $label = $designation->label;

        DB::transaction(function () use ($designation) {
            FeeSchedule::where('payee_type', $designation->section)
                ->where('payee_value', $designation->key)
                ->delete();

            $designation->delete();
        });

        DesignationRegistry::flush();

        return back()->with('success', "\"{$label}\" deleted.");
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'section' => ['required', Rule::enum(PayeeType::class)],
            'label' => ['required', 'string', 'max:100'],
        ]);

        $category = DesignationCategory::create([
            'section' => $validated['section'],
            'key' => $this->uniqueCategoryKey($validated['section'], $validated['label']),
            'label' => trim($validated['label']),
            'sort_order' => (int) DesignationCategory::where('section', $validated['section'])->max('sort_order') + 1,
        ]);

        DesignationRegistry::flush();

        return back()->with('success', "\"{$category->label}\" added.");
    }

    public function updateCategory(Request $request, DesignationCategory $category): RedirectResponse
    {
        $this->authorizeManage($request);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
        ]);

        $category->update(['label' => trim($validated['label'])]);

        DesignationRegistry::flush();

        return back()->with('success', 'Committee renamed.');
    }

    /**
     * Built-in committees stay: ExamRole's coverage rule is decided by which
     * committee a designation sits in, so removing one would leave its
     * designations undefined. A custom committee must be emptied first —
     * designations are moved, not deleted, by re-filing them.
     */
    public function destroyCategory(Request $request, DesignationCategory $category): RedirectResponse
    {
        $this->authorizeManage($request);

        abort_if($category->is_builtin, 422, 'Built-in committees cannot be deleted.');

        $count = $category->designations()->count();

        abort_if(
            $count > 0,
            422,
            "Move the {$count} designation(s) in this committee elsewhere before deleting it.",
        );

        $label = $category->label;

        $category->delete();

        DesignationRegistry::flush();

        return back()->with('success', "\"{$label}\" deleted.");
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->hasPermission(Permission::DesignationsManage), 403);
    }

    /**
     * Honorarium rates are a separate permission from managing designations, so
     * holding one must not confer the other — otherwise this page would be a way
     * around it. A user without it edits names, committees and availability
     * here; the rate is simply left as it was.
     */
    private function saveRate(Request $request, Designation $designation, float $amount): void
    {
        if (! $request->user()->hasPermission(Permission::FeeSchedulesManage)) {
            return;
        }

        FeeSchedule::updateOrCreate(
            ['payee_type' => $designation->section, 'payee_value' => $designation->key],
            ['amount_cents' => (int) round($amount * 100), 'updated_by' => $request->user()->id],
        );
    }

    /**
     * How many records already carry each designation, so the page can show
     * what a deletion or deactivation would affect.
     *
     * @return array<string, int>
     */
    private function usageCounts(): array
    {
        $exam = ExamAssignment::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role')
            ->mapWithKeys(fn ($total, $role) => [PayeeType::ExamRole->value."|{$role}" => (int) $total]);

        $personnel = OtherExaminationPersonnel::query()
            ->selectRaw('personnel_type, count(*) as total')
            ->groupBy('personnel_type')
            ->pluck('total', 'personnel_type')
            ->mapWithKeys(fn ($total, $type) => [PayeeType::PersonnelType->value."|{$type}" => (int) $total]);

        return $exam->merge($personnel)->all();
    }

    /** Keys are slugs of the name, suffixed only if that slug is taken. */
    private function uniqueKey(string $section, string $label): string
    {
        $base = Str::of($label)->slug('_')->limit(34, '')->toString() ?: 'designation';
        $key = $base;
        $suffix = 2;

        while (Designation::where('section', $section)->where('key', $key)->exists()) {
            $key = "{$base}_{$suffix}";
            $suffix++;
        }

        return $key;
    }

    private function uniqueCategoryKey(string $section, string $label): string
    {
        $base = Str::of($label)->slug('_')->limit(34, '')->toString() ?: 'committee';
        $key = $base;
        $suffix = 2;

        while (DesignationCategory::where('section', $section)->where('key', $key)->exists()) {
            $key = "{$base}_{$suffix}";
            $suffix++;
        }

        return $key;
    }
}
