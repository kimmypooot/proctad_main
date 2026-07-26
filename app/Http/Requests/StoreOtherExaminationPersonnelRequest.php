<?php

namespace App\Http\Requests;

use App\Enums\PersonnelType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreOtherExaminationPersonnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by OtherExaminationPersonnelPolicy in the controller.
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'sex' => ['required', Rule::in(['male', 'female'])],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:100'],
            'agency' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'personnel_type' => ['required', Rule::enum(PersonnelType::class)],
            // Required: the office is what decides who can see and manage this
            // person. Region-wide personnel are those of the regional office
            // (RO8), not those with the field left blank — see
            // OtherExaminationPersonnel::isRegionWide().
            'field_office_id' => ['required', 'exists:field_offices,id', $this->fieldOfficeScopeRule()],
            'testing_center_id' => [
                // Regional-office personnel serve region-wide and sit in no
                // center; everyone else must have one, since the center is what
                // decides who can see and manage them.
                Rule::requiredIf(fn () => ! $this->chosenOfficeIsRegional()),
                'nullable',
                'exists:testing_centers,id',
                $this->testingCenterScopeRule(),
            ],
            'is_active' => ['required', 'boolean'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    /**
     * Field Office Admins can only register other examination personnel in an
     * office sharing their jurisdiction. Deliberately not widened to the
     * regional office: FO staff may draw on region-wide personnel but may not
     * create them, which is management's call.
     */
    protected function fieldOfficeScopeRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $user = $this->user();
            if ($user->role->isFieldOfficeScoped() && $value !== null
                && ! in_array((int) $value, $user->scopedFieldOfficeIds(), true)) {
                $fail('You can only manage other examination personnel of your own Field Office.');
            }
        };
    }

    /**
     * Whether the office picked on this request is the regional one — the case
     * that makes a testing center inapplicable rather than merely missing.
     */
    protected function chosenOfficeIsRegional(): bool
    {
        $officeId = $this->input('field_office_id');

        if ($officeId === null || $officeId === '') {
            return false;
        }

        return (bool) DB::table('field_offices')
            ->where('id', (int) $officeId)
            ->value('is_regional');
    }

    /**
     * FO-scoped staff can only place someone in a center they themselves serve,
     * and the center must be one the chosen office actually handles — otherwise
     * the record is filed under an office that does not serve that city.
     *
     * Mirrors StoreMemberRequest::testingCenterScopeRule: the two kinds of
     * people are scoped by the same rule, so they are validated by the same one.
     */
    protected function testingCenterScopeRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if ($value === null || $value === '') {
                return;
            }

            $user = $this->user();
            $centerId = (int) $value;

            if ($user->role->isFieldOfficeScoped()
                && ! in_array($centerId, $user->scopedTestingCenterIds(), true)) {
                $fail('You can only assign personnel to a Testing Center you serve.');

                return;
            }

            $officeId = $this->input('field_office_id');

            if ($officeId === null || $officeId === '') {
                return;
            }

            $handled = DB::table('field_office_testing_center')
                ->where('field_office_id', (int) $officeId)
                ->where('testing_center_id', $centerId)
                ->exists();

            if (! $handled) {
                $fail('That Testing Center is not handled by the selected Field Office.');
            }
        };
    }
}
