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

    // Local (Field Office) Examination Committee
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
     * to exactly one field office (their duty station) but reference
     * multiple schools they're responsible for monitoring — those covered
     * schools are pre-determined (no separate confirmation) but each still
     * needs its own attendance scan/manual entry, tracked independently of
     * the field-office attendance that drives certificate issuance.
     */
    public function isCoverageRole(): bool
    {
        return match ($this->group()) {
            ExamRoleGroup::Regional, ExamRoleGroup::TestingCenter => true,
            ExamRoleGroup::Special => $this === self::CeForInvestigation,
            ExamRoleGroup::School => false,
        };
    }

    /**
     * Seats held ex officio: the Regional Examination Committee is always
     * chaired by the Director IV and co-chaired by the Director III, so those
     * two designations belong to whoever holds the post rather than being
     * staffed from the pool.
     *
     * Returns the staff role entitled to the seat, or null for the sixteen
     * roles that are open to any accredited member.
     */
    public function reservedForRole(): ?UserRole
    {
        return match ($this) {
            self::RecChair => UserRole::DirectorIv,
            self::RecCoChair => UserRole::DirectorIii,
            default => null,
        };
    }

    /**
     * Designations the post-examination evaluation form covers.
     *
     * Lives here rather than on EvaluationController because three places now
     * need the same answer — the form itself, the member dashboard, and service
     * history — and they must agree, or a member is told an evaluation is due
     * on a page that then refuses to accept one.
     *
     * @return array<int, self>
     */
    public static function evaluableCases(): array
    {
        return [
            self::ChiefExaminer,
            self::SupervisingExaminer,
            self::Proctor,
            self::RoomExaminer,
        ];
    }

    public function isEvaluable(): bool
    {
        return in_array($this, self::evaluableCases(), true);
    }

    /**
     * Seats an Alternate Examiner from the venue's standby pool can be called
     * in to fill when the assignee does not report.
     *
     * Only the room-floor duties an alternate is physically able to take over —
     * Supervising Examiner, Room Examiner, Proctor. REC/LEC staff a testing
     * center or the region as a committee and are never covered from a single
     * venue's alternate pool, so they must not be offered a "mark absent / call
     * an alternate" prompt on the scanner.
     *
     * @return array<int, self>
     */
    public static function coverableCases(): array
    {
        return [
            self::SupervisingExaminer,
            self::RoomExaminer,
            self::Proctor,
        ];
    }

    public function isCoverable(): bool
    {
        return in_array($this, self::coverableCases(), true);
    }
}
