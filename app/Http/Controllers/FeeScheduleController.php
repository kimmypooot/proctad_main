<?php

namespace App\Http\Controllers;

use App\Enums\ExamRole;
use App\Enums\PayeeType;
use App\Enums\PersonnelType;
use App\Models\FeeSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FeeScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', FeeSchedule::class);

        $rates = FeeSchedule::all()->keyBy(fn (FeeSchedule $rate) => "{$rate->payee_type->value}:{$rate->payee_value}");

        return Inertia::render('Settings/FeeSchedules/Index', [
            'examRoleRates' => collect(ExamRole::cases())->map(fn (ExamRole $role) => [
                'payee_type' => PayeeType::ExamRole->value,
                'payee_value' => $role->value,
                'label' => $role->label(),
                'group' => $role->group()->value,
                'group_label' => $role->group()->label(),
                'amount' => ($rates->get("exam_role:{$role->value}")?->amount_cents ?? 0) / 100,
                'configured' => $rates->has("exam_role:{$role->value}") && $rates->get("exam_role:{$role->value}")->amount_cents > 0,
            ])->values(),
            'personnelTypeRates' => collect(PersonnelType::cases())->map(fn (PersonnelType $type) => [
                'payee_type' => PayeeType::PersonnelType->value,
                'payee_value' => $type->value,
                'label' => $type->label(),
                'group' => $type->group()->value,
                'group_label' => $type->group()->label(),
                'amount' => ($rates->get("personnel_type:{$type->value}")?->amount_cents ?? 0) / 100,
                'configured' => $rates->has("personnel_type:{$type->value}") && $rates->get("personnel_type:{$type->value}")->amount_cents > 0,
            ])->values(),
            'can' => ['manage' => $request->user()->can('manage', FeeSchedule::class)],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage', FeeSchedule::class);

        $validated = $request->validate([
            'payee_type' => ['required', Rule::in([PayeeType::ExamRole->value, PayeeType::PersonnelType->value])],
            'payee_value' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $isValidPayeeValue = $validated['payee_type'] === PayeeType::ExamRole->value
            ? ExamRole::tryFrom($validated['payee_value']) !== null
            : PersonnelType::tryFrom($validated['payee_value']) !== null;

        if (! $isValidPayeeValue) {
            abort(422, 'Invalid payee value for the given payee type.');
        }

        FeeSchedule::updateOrCreate(
            ['payee_type' => $validated['payee_type'], 'payee_value' => $validated['payee_value']],
            ['amount_cents' => (int) round($validated['amount'] * 100), 'updated_by' => $request->user()->id],
        );

        return back()->with('success', 'Fee rate updated.');
    }
}
