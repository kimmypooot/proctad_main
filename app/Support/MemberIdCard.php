<?php

namespace App\Support;

use App\Models\Member;
use App\Models\Signatory;

class MemberIdCard
{
    /**
     * Everything the MemberIdCard.vue component needs to render/print an ID.
     * Photo priority: Google avatar → uploaded photo → null (silhouette).
     */
    public static function data(Member $member): array
    {
        $member->loadMissing('fieldOffice:id,name,code', 'user:id,google_avatar');
        $signatory = Signatory::currentFor($member->field_office_id);

        return [
            'proctad_id' => $member->proctad_id,
            'name' => $member->nameFirstLast(),
            'agency' => $member->agency,
            'position' => $member->position,
            'field_office' => $member->fieldOffice?->name,
            'status' => $member->status->value,
            'status_label' => $member->status->label(),
            'photo_url' => $member->user?->google_avatar
                ?? ($member->photo_path ? route('members.photo', $member) : null),
            'qr_value' => route('verify', $member->proctad_id),
            'signatory' => $signatory?->only('name', 'position'),
        ];
    }
}
