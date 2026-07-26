<?php

namespace App\Enums;

use App\Support\DesignationRegistry;

enum PersonnelType: string
{
    case Coordinator = 'coordinator';
    case Inspector = 'inspector';
    case Paymaster = 'paymaster';
    case PnpOfficer = 'pnp_officer';
    case SecurityOfficer = 'security_officer';
    case Janitor = 'janitor';
    case Helper = 'helper';
    case Driver = 'driver';

    /** Renameable at Administration → Designations; see DesignationRegistry. */
    public function label(): string
    {
        return DesignationRegistry::label(PayeeType::PersonnelType, $this->value);
    }

    /** The built-in name, used when the designation has not been renamed. */
    public function defaultLabel(): string
    {
        return match ($this) {
            self::Coordinator => 'Coordinator',
            self::Inspector => 'Inspector',
            self::Paymaster => 'Paymaster',
            self::PnpOfficer => 'PNP Officer',
            self::SecurityOfficer => 'Security Officer',
            self::Janitor => 'Janitor',
            self::Helper => 'Helper',
            self::Driver => 'Driver',
        };
    }

    public function group(): OepRoleGroup
    {
        return match ($this) {
            self::Coordinator, self::Inspector, self::Paymaster => OepRoleGroup::Committee,
            self::PnpOfficer, self::SecurityOfficer, self::Janitor, self::Helper, self::Driver => OepRoleGroup::Support,
        };
    }
}
