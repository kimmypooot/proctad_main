<?php

namespace App\Http\Requests;

class UpdateNonExamPersonnelRequest extends StoreNonExamPersonnelRequest
{
    // Same rules as store; nep_id and personnel_type changes don't require
    // extra fields the way Member's disqualification status does.
}
