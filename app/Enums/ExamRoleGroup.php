<?php

namespace App\Enums;

enum ExamRoleGroup: string
{
    case Regional = 'regional';
    case TestingCenter = 'testing_center';
    case Special = 'special';
    case School = 'school';

    public function label(): string
    {
        return match ($this) {
            self::Regional => 'Regional Examination Committee (REC)',
            self::TestingCenter => 'Local Examination Committee (LEC)',
            self::Special => 'Special Roles',
            self::School => 'School-Level Roles',
        };
    }
}
