<?php

namespace App\Enums;

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

    public function label(): string
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

    public function group(): NepRoleGroup
    {
        return match ($this) {
            self::Coordinator, self::Inspector, self::Paymaster => NepRoleGroup::Committee,
            self::PnpOfficer, self::SecurityOfficer, self::Janitor, self::Helper, self::Driver => NepRoleGroup::Support,
        };
    }
}
