<?php

namespace App\Http\Requests;

use App\Enums\PersonnelType;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNonExamPersonnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by NonExamPersonnelPolicy in the controller.
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
     * Testing Center Admins can only register non-exam personnel in their own
     * Testing Center (or region-wide/unassigned, matching legacy behavior).
     */
    protected function fieldOfficeScopeRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            $user = $this->user();
            if ($user->role === UserRole::FoAdmin && $value !== null && (int) $value !== $user->field_office_id) {
                $fail('You can only manage non-exam personnel of your own Testing Center.');
            }
        };
    }
}
