<?php

namespace App\Enums;

/**
 * Canonical PROCTAD examination duty roles, ported from the legacy ProctadRoles class.
 * Grouped as REGIONAL (REC), TESTING_CENTER (LEC), SPECIAL, and SCHOOL.
 */
enum ExamRole: string
{
    // Regional Examination Committee
    case RecChair = 'rec_chair';
    case RecCoChair = 'rec_co_chair';
    case RecMember = 'rec_member';
    case RecStaffAssistant = 'rec_staff_assistant';

    // Local (Testing Center) Examination Committee
    case LecChair = 'lec_chair';
    case LecCoChair = 'lec_co_chair';
    case LecMember = 'lec_member';

    // Special roles
    case ChiefExaminer = 'chief_examiner';
    case CeForInvestigation = 'ce_for_investigation';
    case Driver = 'driver';

    // School-level roles
    case Coordinator = 'coordinator';
    case SupervisingExaminer = 'supervising_examiner';
    case RoomExaminer = 'room_examiner';
    case Proctor = 'proctor';
    case PnpOfficer = 'pnp_officer';
    case RoomSchoolInspector = 'room_school_inspector';
    case AlternateExaminer = 'alternate_examiner';

    public function label(): string
    {
        return match ($this) {
            self::RecChair => 'REC Chair',
            self::RecCoChair => 'REC Co-Chair',
            self::RecMember => 'REC Member',
            self::RecStaffAssistant => 'REC Staff Assistant',
            self::LecChair => 'LEC Chair',
            self::LecCoChair => 'LEC Co-Chair',
            self::LecMember => 'LEC Member',
            self::ChiefExaminer => 'Chief Examiner',
            self::CeForInvestigation => 'CE for Investigation',
            self::Driver => 'Driver',
            self::Coordinator => 'Coordinator',
            self::SupervisingExaminer => 'Supervising Examiner',
            self::RoomExaminer => 'Room Examiner',
            self::Proctor => 'Proctor',
            self::PnpOfficer => 'PNP Officer',
            self::RoomSchoolInspector => 'Room/School Inspector',
            self::AlternateExaminer => 'Alternate Examiner',
        };
    }

    public function group(): ExamRoleGroup
    {
        return match ($this) {
            self::RecChair, self::RecCoChair, self::RecMember, self::RecStaffAssistant => ExamRoleGroup::Regional,
            self::LecChair, self::LecCoChair, self::LecMember => ExamRoleGroup::TestingCenter,
            self::ChiefExaminer, self::CeForInvestigation, self::Driver => ExamRoleGroup::Special,
            default => ExamRoleGroup::School,
        };
    }

    /** @return array<self> */
    public static function inGroup(ExamRoleGroup $group): array
    {
        return array_values(array_filter(self::cases(), fn (self $role) => $role->group() === $group));
    }

    /**
     * REC (all), LEC (all), and Chief Examiner for Investigation are assigned
     * to exactly one testing center (their duty station) but reference
     * multiple schools they're responsible for monitoring — those covered
     * schools are pre-determined (no separate confirmation) but each still
     * needs its own attendance scan/manual entry, tracked independently of
     * the testing-center attendance that drives certificate issuance.
     */
    public function isCoverageRole(): bool
    {
        return match ($this->group()) {
            ExamRoleGroup::Regional, ExamRoleGroup::TestingCenter => true,
            ExamRoleGroup::Special => $this === self::CeForInvestigation,
            ExamRoleGroup::School => false,
        };
    }
}
