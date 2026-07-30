<?php

namespace App\Http\Requests;

use App\Enums\MemberStatus;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends StoreMemberRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['required', Rule::enum(MemberStatus::class)],
            'disqualification_remarks' => [
                'nullable', 'string', 'max:1000',
                Rule::requiredIf($this->input('status') === MemberStatus::Disqualified->value),
            ],
        ];
    }

    protected function emailUniqueRule(): Rule|string
    {
        return Rule::unique('members', 'email')->ignore($this->route('member'));
    }

    /**
     * The record's own account settles it here, ahead of the email lookup the
     * parent does: the address on the form is editable, and an employee who
     * corrects theirs must not have the testing center become required again
     * mid-edit just because the new address has no login yet.
     */
    protected function isEmployeeRecord(): bool
    {
        return $this->route('member')?->user?->role->isStaff() === true
            || parent::isEmployeeRecord();
    }
}
