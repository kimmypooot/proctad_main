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
     * → Field Director of the concerned FO; Appreciation → either regional
     * director; Completion → auto-released, no approver.
     *
     * @return array<int, UserRole> empty when no approval is required
     */
    public function approverRoles(): array
    {
        return match ($this) {
            self::Appearance, self::DesignationOrder => [UserRole::FieldDirector],
            // Either director signs an Appreciation. Returning both rather than
            // naming the Director IV keeps the pre-split behaviour: management
            // was one role here, and making the head of office the sole
            // approver would strand the queue whenever they are unavailable.
            self::Appreciation => [UserRole::DirectorIv, UserRole::DirectorIii],
            self::Completion => [],
        };
    }

    /**
     * Whether this type reaches an approver at all (Completion auto-releases).
     */
    public function needsApproval(): bool
    {
        return $this->approverRoles() !== [];
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
