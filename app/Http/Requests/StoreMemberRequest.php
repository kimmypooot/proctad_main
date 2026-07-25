<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by MemberPolicy in the controller.
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'sex' => ['required', Rule::in(['male', 'female'])],
            'date_of_birth' => ['required', 'date', 'before:-18 years'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', $this->emailUniqueRule()],
            'mobile_number' => ['required', 'string', 'regex:/^(\+639|09)\d{9}$/'],
            'agency' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'field_office_id' => ['required', 'exists:field_offices,id', $this->fieldOfficeScopeRule()],
            'testing_center_id' => [
                // Regional-office members serve region-wide and sit in no
                // center; everyone else must have one, since the center is what
                // decides who can see and manage them.
                Rule::requiredIf(fn () => ! $this->chosenOfficeIsRegional()),
                'nullable',
                'exists:testing_centers,id',
                $this->testingCenterScopeRule(),
            ],
            'photo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'mobile_number.regex' => 'Enter a valid Philippine mobile number (e.g. 09171234567).',
            'date_of_birth.before' => 'Member must be at least 18 years old.',
        ];
    }

    protected function emailUniqueRule(): Rule|string
    {
        return Rule::unique('members', 'email');
    }

    /**
     * FO-scoped staff can only register members under an office that shares
     * their jurisdiction — their own, or one they share a testing center with
     * (Leyte I and Leyte II both serve Tacloban City).
     */
    protected function fieldOfficeScopeRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $user = $this->user();

            if ($user->role->isFieldOfficeScoped()
                && ! in_array((int) $value, $user->scopedFieldOfficeIds(), true)) {
                $fail('You can only manage members within your own jurisdiction.');
            }
        };
    }

    /**
     * The center must be one the chosen office actually handles — otherwise the
     * member would be filed under staff who do not serve them — and, for
     * FO-scoped staff, one inside their own jurisdiction.
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
                $fail('You can only assign members to a Testing Center you serve.');

                return;
            }

            $officeId = (int) $this->input('field_office_id');

            $handled = DB::table('field_office_testing_center')
                ->where('field_office_id', $officeId)
                ->where('testing_center_id', $centerId)
                ->exists();

            if (! $handled) {
                $fail('That Testing Center is not handled by the selected Field Office.');
            }
        };
    }

    /** Whether the submitted field office is the regional office. */
    protected function chosenOfficeIsRegional(): bool
    {
        $officeId = $this->input('field_office_id');

        return $officeId !== null
            && DB::table('field_offices')->where('id', $officeId)->value('is_regional') == true;
    }
}
