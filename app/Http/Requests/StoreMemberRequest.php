<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
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
     * Field Office Admins can only register members in their own Field Office.
     */
    protected function fieldOfficeScopeRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $user = $this->user();
            if ($user->role->isFieldOfficeScoped() && (int) $value !== $user->field_office_id) {
                $fail('You can only manage members of your own Field Office.');
            }
        };
    }
}
