<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlacklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Gated by BlacklistPolicy in the controller.
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'exists:members,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
