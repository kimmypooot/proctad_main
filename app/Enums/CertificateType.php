<?php

namespace App\Enums;

enum CertificateType: string
{
    case Appearance = 'appearance';
    case Appreciation = 'appreciation';
    case Completion = 'completion';
    case DesignationOrder = 'designation_order';

    public function label(): string
    {
        return match ($this) {
            self::Appearance => 'Certificate of Appearance',
            self::Appreciation => 'Certificate of Appreciation',
            self::Completion => 'Certificate of Completion',
            self::DesignationOrder => 'Designation Order',
        };
    }

    /**
     * Who must approve release (spec 2.3 / 6): Appearance & Designation Orders
     * → Field Director of the concerned FO; Appreciation → Management;
     * Completion → auto-released, no approver.
     */
    public function approverRole(): ?UserRole
    {
        return match ($this) {
            self::Appearance, self::DesignationOrder => UserRole::FieldDirector,
            self::Appreciation => UserRole::Management,
            self::Completion => null,
        };
    }

    public function code(): string
    {
        return match ($this) {
            self::Appearance => 'COA',
            self::Appreciation => 'CAP',
            self::Completion => 'COC',
            self::DesignationOrder => 'DO',
        };
    }
}
