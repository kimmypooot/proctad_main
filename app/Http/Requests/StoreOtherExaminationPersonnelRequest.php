<?php

namespace App\Http\Requests;

use App\Enums\PersonnelType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
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
            'field_office_id' => ['nullable', 'exists:field_offices,id', $this->fieldOfficeScopeRule()],
            'is_active' => ['required', 'boolean'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ];
    }

    /**
     * Field Office Admins can only register other examination personnel in their own
     * Field Office (or region-wide/unassigned, matching legacy behavior).
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
}
