<?php

namespace App\Enums;

/**
 * The configurable capabilities behind the policy layer.
 *
 * These answer one question only — "may this role do this kind of thing at
 * all?" — and are deliberately coarse. Two other kinds of rule live in the
 * policies and are *not* configurable, because getting them wrong is silent and
 * serious:
 *
 *  - Scope. Whether a record falls inside the user's testing centers or field
 *    office. If this were a checkbox, one click would hand Leyte I staff the
 *    Northern Samar roster.
 *  - State. Whether the record is in a status that permits the action (only a
 *    Pending certificate can be decided, only a Released one downloaded).
 *
 * So a policy reads "has the capability AND is in scope AND is in a valid
 * state". Granting a permission widens *who* may act; it never widens *what*
 * they may reach.
 *
 * Most resources expose `view` and `manage` rather than separate create/update/
 * delete, because the policies already treat those three identically once
 * scoping is factored out. Where they genuinely differ (deleting a training is
 * admin-only while editing one is not) the odd one out gets its own case.
 */
enum Permission: string
{
    case MembersView = 'members.view';
    case MembersManage = 'members.manage';

    case ExaminationsView = 'examinations.view';
    case ExaminationsManage = 'examinations.manage';
    case ExaminationSchoolsManage = 'examination_schools.manage';
    case ExamAssignmentsManage = 'exam_assignments.manage';

    case TrainingsView = 'trainings.view';
    case TrainingsManage = 'trainings.manage';
    case TrainingsDelete = 'trainings.delete';
    case TrainingAssignmentsManage = 'training_assignments.manage';

    case CertificatesView = 'certificates.view';
    case CertificatesDecide = 'certificates.decide';
    case CertificatesDecideAnyType = 'certificates.decide_any_type';
    case CertificatesRegenerate = 'certificates.regenerate';

    case OepView = 'oep.view';
    case OepManage = 'oep.manage';
    case OepAssignmentsManage = 'oep_assignments.manage';

    case SchoolsView = 'schools.view';
    case SchoolsManage = 'schools.manage';

    case TestingCentersView = 'testing_centers.view';
    case TestingCentersManage = 'testing_centers.manage';
    case TestingCentersDesignate = 'testing_centers.designate';

    case FieldOfficesView = 'field_offices.view';
    case FieldOfficesManage = 'field_offices.manage';

    case SignatoriesView = 'signatories.view';
    case SignatoriesManage = 'signatories.manage';

    case ExamTypesView = 'exam_types.view';
    case ExamTypesManage = 'exam_types.manage';

    case BlacklistsView = 'blacklists.view';
    case BlacklistsManage = 'blacklists.manage';

    case EvaluationsView = 'evaluations.view';

    case ScannerSessionsCreate = 'scanner_sessions.create';
    case ScannerSessionsRevoke = 'scanner_sessions.revoke';

    case EmailTemplatesManage = 'email_templates.manage';
    case LetterheadsManage = 'letterheads.manage';
    case DesignationsManage = 'designations.manage';
    case FeeSchedulesManage = 'fee_schedules.manage';
    case SettingsManage = 'settings.manage';
    case UsersManage = 'users.manage';

    public function label(): string
    {
        return match ($this) {
            self::MembersView => 'View members',
            self::MembersManage => 'Add, edit and remove members',
            self::ExaminationsView => 'View examinations',
            self::ExaminationsManage => 'Add, edit and remove examinations',
            self::ExaminationSchoolsManage => 'Assign and remove examination venues',
            self::ExamAssignmentsManage => 'Assign personnel to examinations',
            self::TrainingsView => 'View trainings',
            self::TrainingsManage => 'Add, edit and complete trainings',
            self::TrainingsDelete => 'Delete trainings',
            self::TrainingAssignmentsManage => 'Assign personnel to trainings',
            self::CertificatesView => 'View certificates',
            self::CertificatesDecide => 'Approve or disapprove certificates',
            self::CertificatesDecideAnyType => 'Approve any certificate type, not just designated ones',
            self::CertificatesRegenerate => 'Re-render issued certificates',
            self::OepView => 'View other examination personnel',
            self::OepManage => 'Add, edit and remove other examination personnel',
            self::OepAssignmentsManage => 'Assign other examination personnel',
            self::SchoolsView => 'View schools',
            self::SchoolsManage => 'Add, edit and remove schools',
            self::TestingCentersView => 'View testing centers',
            self::TestingCentersManage => 'Add, edit and remove testing centers',
            self::TestingCentersDesignate => 'Designate the administering field office',
            self::FieldOfficesView => 'View field offices',
            self::FieldOfficesManage => 'Add, edit and remove field offices',
            self::SignatoriesView => 'View signatories',
            self::SignatoriesManage => 'Add, edit and remove signatories',
            self::ExamTypesView => 'View examination types',
            self::ExamTypesManage => 'Add, edit and remove examination types',
            self::BlacklistsView => 'View the blacklist',
            self::BlacklistsManage => 'Blacklist and reinstate members',
            self::EvaluationsView => 'View evaluations',
            self::ScannerSessionsCreate => 'Open scanner sessions',
            self::ScannerSessionsRevoke => 'Revoke scanner sessions',
            self::EmailTemplatesManage => 'Edit email templates',
            self::LetterheadsManage => 'Manage letterheads',
            self::DesignationsManage => 'Rename examination designations and set which are in use',
            self::FeeSchedulesManage => 'Set honorarium rates',
            self::SettingsManage => 'Change system settings',
            self::UsersManage => 'Manage user accounts',
        };
    }

    /** The heading this permission is listed under in the matrix. */
    public function group(): string
    {
        return match ($this) {
            self::MembersView, self::MembersManage,
            self::BlacklistsView, self::BlacklistsManage => 'Members',

            self::ExaminationsView, self::ExaminationsManage,
            self::ExaminationSchoolsManage, self::ExamAssignmentsManage,
            self::ScannerSessionsCreate, self::ScannerSessionsRevoke => 'Examinations',

            self::TrainingsView, self::TrainingsManage,
            self::TrainingsDelete, self::TrainingAssignmentsManage => 'Trainings',

            self::CertificatesView, self::CertificatesDecide,
            self::CertificatesDecideAnyType,
            self::CertificatesRegenerate => 'Certificates',

            self::OepView, self::OepManage,
            self::OepAssignmentsManage => 'Other Examination Personnel',

            self::EvaluationsView => 'Evaluations',

            self::SchoolsView, self::SchoolsManage,
            self::TestingCentersView, self::TestingCentersManage,
            self::TestingCentersDesignate,
            self::FieldOfficesView, self::FieldOfficesManage => 'Offices & Venues',

            self::SignatoriesView, self::SignatoriesManage,
            self::ExamTypesView, self::ExamTypesManage,
            self::EmailTemplatesManage, self::LetterheadsManage,
            self::DesignationsManage, self::FeeSchedulesManage,
            self::SettingsManage, self::UsersManage => 'Administration',
        };
    }

    /**
     * A short note on what stays fixed no matter how the permission is set —
     * shown in the matrix so an administrator granting something region-wide
     * does not expect it to reach outside the role's jurisdiction.
     */
    public function scopeNote(): ?string
    {
        return match ($this) {
            self::MembersManage, self::ExamAssignmentsManage,
            self::TrainingAssignmentsManage, self::OepManage,
            self::OepAssignmentsManage, self::SchoolsManage,
            self::TestingCentersManage, self::SignatoriesManage,
            self::BlacklistsManage, self::ExaminationSchoolsManage,
            self::ScannerSessionsRevoke, self::CertificatesRegenerate => 'Field Office roles stay limited to their own testing centers.',

            self::CertificatesDecide => 'Region-wide roles are still limited to their designated certificate types; the rest to their own testing centers.',

            self::CertificatesDecideAnyType => 'Only affects region-wide roles — it is the standing fallback for when the designated approver is unavailable.',

            default => null,
        };
    }
}
