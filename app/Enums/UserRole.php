<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case EsdAdmin = 'esd_admin';
    case Management = 'management';
    case FieldDirector = 'field_director';
    case FoAdmin = 'fo_admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Administrator',
            self::EsdAdmin => 'Administrator (ESD)',
            self::Management => 'Management (Director IV / III)',
            self::FieldDirector => 'Field Director / Caretaker',
            self::FoAdmin => 'Testing Center Staff',
            self::Member => 'PROCTAD Member',
        };
    }

    public function isRegionWide(): bool
    {
        return in_array($this, [self::SuperAdmin, self::EsdAdmin, self::Management], true);
    }

    public function isFieldOfficeScoped(): bool
    {
        return in_array($this, [self::FieldDirector, self::FoAdmin], true);
    }

    /**
     * Everyone who works for the Commission, as opposed to a PROCTAD member
     * using self-service. Written as "not a member" deliberately: a new staff
     * role added later should be staff by default, not silently locked out.
     */
    public function isStaff(): bool
    {
        return $this !== self::Member;
    }
}
