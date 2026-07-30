<?php

namespace App\Http\Requests;

use App\Models\User;
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
            /*
             * Optional when staff enter a record, unlike self-registration
             * (RegisteredUserController), which still requires it: someone
             * filling in their own form knows their birth date, while staff
             * registering a colleague often do not have it to hand and should
             * not be blocked from creating the record over one field. The
             * 18-year floor still applies to whatever is supplied.
             */
            'date_of_birth' => ['nullable', 'date', 'before:-18 years'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', $this->emailUniqueRule()],
            'mobile_number' => ['required', 'string', 'regex:/^(\+639|09)\d{9}$/'],
            'agency' => ['required', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            // Optional, and only for the minority of members who are also CSC
            // employees. A field office says who someone works for; an external
            // test administrator works for their own agency, recorded in
            // `agency` above.
            'field_office_id' => ['nullable', 'exists:field_offices,id', $this->fieldOfficeScopeRule()],
            // Required for external test administrators: the center is what
            // decides who can see and manage them, and where they may serve.
            // Optional for Commission employees, whose reach comes from their
            // employment record instead — a regional office employee serves
            // region-wide and a field office employee serves the centers their
            // office covers, neither of which any one center can express.
            'testing_center_id' => [
                Rule::requiredIf(fn () => ! $this->isEmployeeRecord()),
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
     * Whether this record belongs to a Commission employee rather than an
     * external test administrator.
     *
     * Decided by the login the record will attach to, not by anything on the
     * form: MemberController::resolveAccount() links by email, so the account
     * that already holds this address is the same one the record will end up
     * under. A member-role account, or no account at all, means external — and
     * a new account minted by resolveAccount() is always member-role.
     */
    protected function isEmployeeRecord(): bool
    {
        $email = $this->input('email');

        return is_string($email)
            && User::where('email', $email)->first()?->role->isStaff() === true;
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
