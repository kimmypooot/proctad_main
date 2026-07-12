<?php

namespace App\Support;

use App\Models\NonExamPersonnel;
use App\Models\Signatory;

class NepIdCard
{
    /**
     * Everything the NepIdCard.vue component needs to render/print an ID.
     */
    public static function data(NonExamPersonnel $nep): array
    {
        $nep->loadMissing('fieldOffice:id,name,code');
        $signatory = Signatory::currentFor($nep->field_office_id);

        return [
            'nep_id' => $nep->nep_id,
            'name' => $nep->name,
            'personnel_type_label' => $nep->personnel_type->label(),
            'agency' => $nep->agency,
            'position' => $nep->position,
            'field_office' => $nep->fieldOffice?->name,
            'is_active' => $nep->is_active,
            'photo_url' => $nep->photo_path ? route('non-exam-personnel.photo', $nep) : null,
            'qr_value' => "NEP:{$nep->nep_id}",
            'signatory' => $signatory?->only('name', 'position'),
        ];
    }
}
