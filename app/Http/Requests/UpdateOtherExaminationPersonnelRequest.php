<?php

namespace App\Http\Requests;

class UpdateOtherExaminationPersonnelRequest extends StoreOtherExaminationPersonnelRequest
{
    // Same rules as store; oep_id and personnel_type changes don't require
    // extra fields the way Member's disqualification status does.
}
