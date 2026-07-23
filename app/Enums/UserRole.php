<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case EsdAdmin = 'esd_admin';
    /*
     * Regional management, split by office rather than lumped together: the
     * Regional Examination Committee is always chaired by the Director IV and
     * co-chaired by the Director III, and a single 'management' role could not
     * express which is which. See ExamRole::reservedForRole().
     *
     * They are otherwise interchangeable — isManagement() covers everything
     * that means "either director", so authority stays where it was.
     */
    case DirectorIv = 'director_iv';
    case DirectorIii = 'director_iii';
    case FieldDirector = 'field_director';
    case FoAdmin = 'fo_admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Administrator',
            self::EsdAdmin => 'Administrator (ESD)',
            self::DirectorIv => 'Regional Director IV',
            self::DirectorIii => 'Assistant Regional Director III',
            self::FieldDirector => 'Field Director / Caretaker',
            self::FoAdmin => 'Testing Center Staff',
            self::Member => 'PROCTAD Member',
        };
    }

    /**
     * Regional management — the two directors, as one group. Use this wherever
     * the rule is about management authority rather than about which specific
     * director holds the post.
     */
    public function isManagement(): bool
    {
        return in_array($this, [self::DirectorIv, self::DirectorIii], true);
    }

    public function isRegionWide(): bool
    {
        return $this->isManagement()
            || in_array($this, [self::SuperAdmin, self::EsdAdmin], true);
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
