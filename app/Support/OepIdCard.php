<?php

namespace App\Support;

use App\Models\OtherExaminationPersonnel;
use App\Models\Signatory;

class OepIdCard
{
    /**
     * Everything the OepIdCard.vue component needs to render/print an ID.
     */
    public static function data(OtherExaminationPersonnel $oep): array
    {
        $oep->loadMissing('fieldOffice:id,name,code');
        $signatory = Signatory::currentFor($oep->field_office_id);

        return [
            'oep_id' => $oep->oep_id,
            'name' => $oep->name,
            'personnel_type_label' => $oep->personnel_type->label(),
            'agency' => $oep->agency,
            'position' => $oep->position,
            'field_office' => $oep->fieldOffice?->name,
            'is_active' => $oep->is_active,
            'photo_url' => $oep->photo_path ? route('other-examination-personnel.photo', $oep) : null,
            'qr_value' => "OEP:{$oep->oep_id}",
            'signatory' => $signatory?->only('name', 'position'),
        ];
    }
}
