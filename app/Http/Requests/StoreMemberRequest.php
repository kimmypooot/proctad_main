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
            // Optional, and only for the minority of members who are also CSC
            // employees. A field office says who someone works for; an external
            // test administrator works for their own agency, recorded in
            // `agency` above.
            'field_office_id' => ['nullable', 'exists:field_offices,id', $this->fieldOfficeScopeRule()],
            // Required for everyone: the center is what decides who can see and
            // manage a member.
            'testing_center_id' => [
                'required',
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
            if ($value === null || $value === '') {
                return;
            }

            $user = $this->user();

            if ($user->role->isFieldOfficeScoped()
                && ! in_array((int) $value, $user->scopedFieldOfficeIds(), true)) {
                $fail('You can only manage members within your own jurisdiction.');
            }
        };
    }

    /**
     * FO-scoped staff can only place a member in a center they themselves
     * serve. Where an office is also given — the member is CSC staff — the two
     * must agree, or the record would be filed under an office that does not
     * serve that city.
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
