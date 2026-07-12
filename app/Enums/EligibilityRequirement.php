<?php

namespace App\Enums;

enum EligibilityRequirement: string
{
    case PermanentEmployment = 'permanent_employment';
    case ExpressionOfIntent = 'expression_of_intent';
    case UpdatedPds = 'updated_pds';
    case GoodMoralCharacter = 'good_moral_character';
    case HeadOfOfficeRecommendation = 'head_of_office_recommendation';
    case NoPendingCase = 'no_pending_case';
    case ProctadTraining = 'proctad_training';

    public function label(): string
    {
        return match ($this) {
            self::PermanentEmployment => 'Proof of permanent government employment',
            self::ExpressionOfIntent => 'Written expression of intent to serve in the CSE',
            self::UpdatedPds => 'Updated Personal Data Sheet (PDS) certified by the Agency HRMO',
            self::GoodMoralCharacter => 'Certificate of Good Moral Character',
            self::HeadOfOfficeRecommendation => 'Recommendation from Head of Office',
            self::NoPendingCase => 'Certification of no final administrative or criminal case',
            self::ProctadTraining => 'Completion of PROCTAD training',
        };
    }
}
