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
}
