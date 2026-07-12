<?php

namespace App\Console\Commands;

use App\Enums\CertificateStatus;
use App\Enums\CertificateType;
use App\Enums\ExamRole;
use App\Enums\PersonnelType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\ExaminationSchool;
use App\Models\Letterhead;
use App\Models\TrainingAssignment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * One-off import of the legacy PHP/PDO database (see DATABASE_AUDIT.md §8 and
 * MIGRATION_CHECKPOINT.md Phase 5) into the new schema.
 *
 * Legacy source is read from the `legacy` connection (LEGACY_DB_* env vars).
 * Run with --dry-run to execute the full import inside a rolled-back
 * transaction and get the reconciliation report without writing anything.
 */
class MigrateLegacyData extends Command
{
    protected $signature = 'proctad:migrate-legacy {--dry-run : Roll back everything, report only}';

    protected $description = 'Import data from the legacy PROCTAD database into the new schema';

    private ConnectionInterface $legacy;

    /** @var array<string, array{imported: int, skipped: int}> */
    private array $stats = [];

    /** @var list<string> */
    private array $warnings = [];

    // Legacy PK → new PK maps.
    private array $foMap = [];

    private array $examTypeMap = [];

    private array $userMap = [];

    private array $schoolMap = [];

    private array $examMap = [];

    private array $venueMap = [];        // legacy exam_school_id → examination_school.id

    private array $venueByPair = [];     // "legacyExamId|legacySchoolId" → examination_school.id

    private array $roomMap = [];

    private array $trainingMap = [];

    private array $memberMap = [];       // legacy proctad_id (string) → members.id

    private array $serviceMap = [];      // legacy service_id → exam_assignments.id

    private array $oepMap = [];          // legacy nep_id (string) → other_examination_personnel.id

    public function handle(): int
    {
        $this->legacy = DB::connection('legacy');

        try {
            $this->legacy->getPdo();
        } catch (\Throwable $e) {
            $this->error('Cannot connect to the legacy database: '.$e->getMessage());

            return self::FAILURE;
        }

        DB::beginTransaction();

        try {
            // Suppress Auditable model events; imported rows are historical,
            // not edits performed today.
            Model::withoutEvents(function () {
                $this->importFieldOffices();
                $this->importExamTypes();
                $this->importUsers();
                $this->importSchools();
                $this->importSignatories();
                $this->importExaminations();
                $this->importExamVenues();
                $this->importExamRooms();
                $this->importTrainings();
                $this->importMembers();
                $this->importMemberRequirements();
                $this->importExamAssignments();
                $this->importTrainingAssignments();
                $this->importCertificates();
                $this->importOtherExaminationPersonnel();
                $this->importOepAssignments();
                $this->importOepAttendances();
                $this->importSettings();
                $this->importLetterheads();
                $this->importEmailTemplates();
                $this->importEmailLogs();
                $this->importSecurityLogs();
            });

            if ($this->option('dry-run')) {
                DB::rollBack();
                $this->warn('DRY RUN — all changes rolled back.');
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Import failed and was rolled back: {$e->getMessage()}");
            $this->line($e->getTraceAsString());

            return self::FAILURE;
        }

        $this->report();

        return self::SUCCESS;
    }

    // ─── Reference data ────────────────────────────────────────────────

    private function importFieldOffices(): void
    {
        foreach ($this->legacy->table('proctad_field_offices')->orderBy('field_office_id')->get() as $row) {
            $id = DB::table('field_offices')->insertGetId([
                'name' => $row->office_name,
                'code' => $row->office_code,
                'address' => $row->address,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
            $this->foMap[$row->field_office_id] = $id;
            $this->tally('field_offices');
        }
    }

    private function importExamTypes(): void
    {
        foreach ($this->legacy->table('proctad_exam_types')->orderBy('exam_type_id')->get() as $row) {
            $id = DB::table('exam_types')->insertGetId([
                'name' => $row->exam_name,
                'is_active' => (bool) $row->is_active,
                'created_at' => $row->created_at,
                'updated_at' => $row->created_at,
            ]);
            $this->examTypeMap[$row->exam_type_id] = $id;
            $this->tally('exam_types');
        }
    }

    // ─── Users ─────────────────────────────────────────────────────────

    private function importUsers(): void
    {
        $rows = $this->legacy->table('users_cscro8')->orderBy('id')->get();

        // A password hash shared across several accounts is the legacy
        // default password — those users must change it on first login.
        $hashCounts = $rows->countBy('password');

        // FO staff are identified by the legacy user↔field-office pivot.
        $foByUser = $this->legacy->table('proctad_user_field_office')->pluck('field_office_id', 'user_id');

        $emailToNewId = [];

        foreach ($rows as $row) {
            $email = mb_strtolower(trim($row->email));

            if ($email !== '' && isset($emailToNewId[$email])) {
                // Duplicate person (e.g. one account per field office in the
                // legacy system). Map to the kept account so FKs still resolve.
                $this->userMap[$row->id] = $emailToNewId[$email];
                $this->tally('users', skipped: true);
                $this->warnings[] = "users: '{$row->username}' merged into existing account for {$email}";

                continue;
            }

            $role = match (true) {
                $row->role === 'superadmin' => UserRole::SuperAdmin,
                $row->role === 'admin' => UserRole::EsdAdmin,
                isset($foByUser[$row->id]) => UserRole::FoAdmin,
                default => UserRole::Member,
            };

            $user = new User;
            $user->forceFill([
                'name' => trim("{$row->fname} {$row->lname}"),
                'first_name' => $row->fname,
                'middle_name' => $row->mname ?: null,
                'last_name' => $row->lname,
                'email' => $email,
                'username' => $row->username,
                'password' => $row->password, // already bcrypt; hashed cast keeps it
                'must_change_password' => $hashCounts[$row->password] > 2,
                'role' => $role,
                'field_office_id' => isset($foByUser[$row->id])
                    ? ($this->foMap[$foByUser[$row->id]] ?? null)
                    : null,
                'last_login_at' => $this->date($row->last_login),
            ])->save();

            $this->userMap[$row->id] = $user->id;
            $emailToNewId[$email] = $user->id;
            $this->tally('users');
        }
    }

    // ─── Venues ────────────────────────────────────────────────────────

    private function importSchools(): void
    {
        foreach ($this->legacy->table('proctad_schools')->orderBy('school_id')->get() as $row) {
            if (! isset($this->foMap[$row->field_office_id])) {
                $this->tally('schools', skipped: true);

                continue;
            }

            $id = DB::table('schools')->insertGetId([
                'field_office_id' => $this->foMap[$row->field_office_id],
                'name' => $row->school_name,
                'municipality' => $row->municipality,
                'contact_person' => $row->contact_person ?: null,
                'contact_number' => $row->contact_number ?: null,
                'contact_email' => $row->contact_email ?: null,
                'is_active' => $row->status === 'active',
                'created_at' => $row->created_at,
                'updated_at' => $row->created_at,
            ]);
            $this->schoolMap[$row->school_id] = $id;
            $this->tally('schools');
        }
    }

    private function importSignatories(): void
    {
        foreach ($this->legacy->table('proctad_signatories')->orderBy('signatory_id')->get() as $row) {
            DB::table('signatories')->insert([
                'field_office_id' => $row->field_office_id !== null
                    ? ($this->foMap[$row->field_office_id] ?? null)
                    : null,
                'name' => $row->name,
                'position' => $row->position,
                'active' => (bool) $row->active_status,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
            $this->tally('signatories');
        }
    }

    private function importExaminations(): void
    {
        $typeNames = $this->legacy->table('proctad_exam_types')->pluck('exam_name', 'exam_type_id');

        foreach ($this->legacy->table('proctad_examinations')->orderBy('exam_id')->get() as $row) {
            $name = $typeNames[$row->exam_type_id] ?? 'Examination';

            $id = DB::table('examinations')->insertGetId([
                'title' => $name.' — '.date('F j, Y', strtotime($row->exam_date)),
                'type' => mb_substr($name, 0, 100),
                'exam_type_id' => $this->examTypeMap[$row->exam_type_id] ?? null,
                'exam_date' => $row->exam_date,
                'created_at' => $row->created_at,
                'updated_at' => $row->created_at,
            ]);
            $this->examMap[$row->exam_id] = $id;
            $this->tally('examinations');
        }
    }

    private function importExamVenues(): void
    {
        foreach ($this->legacy->table('proctad_exam_schools')->orderBy('exam_school_id')->get() as $row) {
            if (! isset($this->examMap[$row->exam_id], $this->schoolMap[$row->school_id])) {
                $this->tally('examination_school', skipped: true);

                continue;
            }

            $id = DB::table('examination_school')->insertGetId([
                'examination_id' => $this->examMap[$row->exam_id],
                'school_id' => $this->schoolMap[$row->school_id],
                'assigned_by' => $this->userMap[$row->assigned_by] ?? null,
                'is_active' => $row->status === 'active',
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
            $this->venueMap[$row->exam_school_id] = $id;
            $this->venueByPair["{$row->exam_id}|{$row->school_id}"] = $id;
            $this->tally('examination_school');
        }
    }

    private function importExamRooms(): void
    {
        // Legacy exam_rooms.school_id actually references exam_schools.exam_school_id.
        foreach ($this->legacy->table('proctad_exam_rooms')->orderBy('exam_room_id')->get() as $row) {
            if (! isset($this->venueMap[$row->school_id])) {
                $this->tally('exam_rooms', skipped: true);

                continue;
            }

            $id = DB::table('exam_rooms')->insertGetId([
                'examination_school_id' => $this->venueMap[$row->school_id],
                'room_number' => $row->room_number,
                'capacity' => $row->capacity,
                'designation' => $row->designation,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
            $this->roomMap[$row->exam_room_id] = $id;
            $this->tally('exam_rooms');
        }
    }

    private function importTrainings(): void
    {
        foreach ($this->legacy->table('proctad_training_records')->orderBy('training_id')->get() as $row) {
            $id = DB::table('trainings')->insertGetId([
                'title' => $row->training_title,
                'type' => mb_substr($row->training_type ?? 'TEA', 0, 20),
                'training_date' => $row->training_date,
                'end_date' => null,
                'venue' => $row->venue,
                'completed_at' => $row->training_status === 'completed' ? $row->updated_at : null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
            $this->trainingMap[$row->training_id] = $id;
            $this->tally('trainings');
        }
    }

    // ─── Members ───────────────────────────────────────────────────────

    private function importMembers(): void
    {
        $userByMember = $this->legacy->table('proctad_user_member')->pluck('user_id', 'proctad_id');

        foreach ($this->legacy->table('proctad_members')->orderBy('proctad_id')->get() as $row) {
            $email = mb_strtolower(trim((string) $row->email));

            if ($email === '') {
                $this->tally('members', skipped: true);
                $this->warnings[] = "members: {$row->proctad_id} has no email — cannot import (email is required)";

                continue;
            }

            if (! isset($this->foMap[$row->field_office_id])) {
                $this->tally('members', skipped: true);
                $this->warnings[] = "members: {$row->proctad_id} has no valid field office";

                continue;
            }

            $status = in_array($row->accreditation_status, ['active', 'inactive', 'disqualified'], true)
                ? $row->accreditation_status
                : 'active';

            $id = DB::table('members')->insertGetId([
                'proctad_id' => $row->proctad_id, // permanent, non-reusable — QRs in the wild
                'user_id' => isset($userByMember[$row->proctad_id])
                    ? ($this->userMap[$userByMember[$row->proctad_id]] ?? null)
                    : null,
                'first_name' => $row->first_name,
                'middle_name' => $row->middle_name,
                'last_name' => $row->last_name,
                'suffix' => $row->suffix,
                'sex' => $row->gender,
                'email' => $email,
                'mobile_number' => $row->contact_number ?: 'N/A',
                'agency' => $row->agency,
                'position' => $row->position,
                'field_office_id' => $this->foMap[$row->field_office_id],
                'status' => $status,
                'disqualification_remarks' => $row->disqualification_reason,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
            $this->memberMap[$row->proctad_id] = $id;
            $this->tally('members');
        }
    }

    private function importMemberRequirements(): void
    {
        $map = [
            'permanent_employment' => 'permanent_employment',
            'intent_to_serve' => 'expression_of_intent',
            'pds_document' => 'updated_pds',
            'good_moral' => 'good_moral_character',
            'recommendation' => 'head_of_office_recommendation',
            'no_case' => 'no_pending_case',
            'training_completed' => 'proctad_training',
        ];

        foreach ($this->legacy->table('proctad_eligibility_requirements')->get() as $row) {
            if (! isset($this->memberMap[$row->proctad_id])) {
                $this->tally('member_requirements', skipped: true);

                continue;
            }

            foreach ($map as $legacyKey => $requirement) {
                DB::table('member_requirements')->insert([
                    'member_id' => $this->memberMap[$row->proctad_id],
                    'requirement' => $requirement,
                    'complied' => $row->{$legacyKey} === 'complied',
                    'file_path' => $row->{$legacyKey.'_file'},
                    'created_at' => $row->uploaded_at,
                    'updated_at' => $row->updated_at,
                ]);
                $this->tally('member_requirements');
            }
        }
    }

    // ─── Assignments & service history ─────────────────────────────────

    private function importExamAssignments(): void
    {
        $roleByLabel = [];
        foreach (ExamRole::cases() as $role) {
            $roleByLabel[$role->label()] = $role->value;
        }

        // Room assignments come from the legacy school_assignments twin table.
        // NOTE: its school_id also references exam_schools.exam_school_id.
        $rooms = [];
        foreach ($this->legacy->table('proctad_school_assignments')->whereNull('deleted_at')->get() as $sa) {
            $rooms["{$sa->proctad_id}|{$sa->exam_id}|{$sa->school_id}"] = $sa->assigned_room_id;
        }

        $seen = [];

        foreach ($this->legacy->table('proctad_service_history')->whereNull('deleted_at')->orderBy('service_id')->get() as $row) {
            if (! isset($this->memberMap[$row->proctad_id])) {
                $this->tally('exam_assignments', skipped: true);

                continue;
            }

            if ($row->exam_id === null || ! isset($this->examMap[$row->exam_id])) {
                $this->tally('exam_assignments', skipped: true);
                $this->warnings[] = "exam_assignments: service {$row->service_id} has no linked examination";

                continue;
            }

            $uniqueKey = $this->examMap[$row->exam_id].'|'.$this->memberMap[$row->proctad_id];
            if (isset($seen[$uniqueKey])) {
                $this->tally('exam_assignments', skipped: true);
                $this->warnings[] = "exam_assignments: duplicate member/exam pair for service {$row->service_id}";

                continue;
            }
            $seen[$uniqueKey] = true;

            $role = $roleByLabel[$row->role_performed] ?? null;
            if ($role === null) {
                $role = ExamRole::Proctor->value;
                $this->warnings[] = "exam_assignments: unknown role '{$row->role_performed}' on service {$row->service_id} — defaulted to Proctor";
            }

            $status = match ($row->confirmation_status) {
                'confirmed' => 'confirmed',
                'declined' => 'declined',
                'expired' => 'expired',
                default => 'pending',
            };

            $legacyRoomId = $rooms["{$row->proctad_id}|{$row->exam_id}|{$row->school_id}"] ?? null;

            $id = DB::table('exam_assignments')->insertGetId([
                'examination_id' => $this->examMap[$row->exam_id],
                'examination_school_id' => $row->school_id !== null ? ($this->venueMap[$row->school_id] ?? null) : null,
                'exam_room_id' => $legacyRoomId !== null ? ($this->roomMap[$legacyRoomId] ?? null) : null,
                'member_id' => $this->memberMap[$row->proctad_id],
                'role' => $role,
                'field_office_id' => $this->foMap[$row->field_office_id],
                'status' => $status,
                'confirmation_sent_at' => $row->confirmation_sent_at,
                'responded_at' => $row->confirmed_at,
                'decline_reason' => $row->decline_reason ? mb_substr($row->decline_reason, 0, 255) : null,
                'attendance_confirmed_at' => $row->attendance_confirmed === 'yes'
                    ? ($row->confirmed_at ?? $row->exam_date)
                    : null,
                'remarks' => $row->remarks ? mb_substr($row->remarks, 0, 255) : null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
            $this->serviceMap[$row->service_id] = $id;
            $this->tally('exam_assignments');
        }
    }

    private function importTrainingAssignments(): void
    {
        $memberFo = DB::table('members')->pluck('field_office_id', 'id');

        foreach ($this->legacy->table('proctad_training_attendance')->orderBy('attendance_id')->get() as $row) {
            if (! isset($this->trainingMap[$row->training_id], $this->memberMap[$row->proctad_id])) {
                $this->tally('training_assignments', skipped: true);

                continue;
            }

            $memberId = $this->memberMap[$row->proctad_id];

            DB::table('training_assignments')->insert([
                'training_id' => $this->trainingMap[$row->training_id],
                'member_id' => $memberId,
                'field_office_id' => $memberFo[$memberId],
                'attendance_confirmed_at' => $row->attendance_status === 'present'
                    ? ($row->qr_scan_timestamp ?? $row->created_at)
                    : null,
                'attendance_confirmed_by' => $this->userMap[$row->scanned_by ?? $row->recorded_by] ?? null,
                'created_at' => $row->created_at,
                'updated_at' => $row->created_at,
            ]);
            $this->tally('training_assignments');
        }
    }

    private function importCertificates(): void
    {
        $memberFo = DB::table('members')->pluck('field_office_id', 'id');
        $seen = [];

        foreach ($this->legacy->table('proctad_certificates')->orderBy('certificate_id')->get() as $row) {
            if (! isset($this->memberMap[$row->proctad_id])) {
                $this->tally('certificates', skipped: true);

                continue;
            }

            $memberId = $this->memberMap[$row->proctad_id];

            $type = match ($row->certificate_type) {
                'appearance' => CertificateType::Appearance,
                'appreciation' => CertificateType::Appreciation,
                'completion' => CertificateType::Completion,
                'designation' => CertificateType::DesignationOrder,
                default => null,
            };

            if ($type === null) {
                $this->tally('certificates', skipped: true);

                continue;
            }

            // Resolve the source event (morph target).
            [$certifiableType, $certifiableId] = $this->resolveCertifiable($row, $memberId);

            if ($certifiableId === null) {
                $this->tally('certificates', skipped: true);
                $this->warnings[] = "certificates: {$row->certificate_number} has no resolvable source assignment";

                continue;
            }

            $uniqueKey = "{$type->value}|{$certifiableType}|{$certifiableId}";
            if (isset($seen[$uniqueKey])) {
                $this->tally('certificates', skipped: true);

                continue;
            }
            $seen[$uniqueKey] = true;

            DB::table('certificates')->insert([
                'certificate_no' => mb_substr($row->certificate_number, 0, 40),
                'type' => $type->value,
                'member_id' => $memberId,
                'field_office_id' => $memberFo[$memberId],
                'certifiable_type' => $certifiableType,
                'certifiable_id' => $certifiableId,
                'status' => match ($row->approval_status) {
                    'approved' => CertificateStatus::Released->value,
                    'disapproved' => CertificateStatus::Disapproved->value,
                    default => CertificateStatus::Pending->value,
                },
                'requested_by' => $this->userMap[$row->generated_by] ?? null,
                'approved_by' => $this->userMap[$row->approved_by] ?? null,
                'approved_at' => $row->approved_at,
                'disapproval_remarks' => $row->disapproval_reason ? mb_substr($row->disapproval_reason, 0, 255) : null,
                'signatory_name' => $row->signatory_name,
                'signatory_position' => $row->signatory_position,
                'pdf_path' => $row->certificate_file,
                'released_at' => $row->email_sent ? $row->email_sent_at : null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
            $this->tally('certificates');
        }
    }

    /** @return array{0: ?string, 1: ?int} */
    private function resolveCertifiable(object $row, int $memberId): array
    {
        if ($row->service_id !== null && isset($this->serviceMap[$row->service_id])) {
            return [\App\Models\ExamAssignment::class, $this->serviceMap[$row->service_id]];
        }

        if ($row->training_id !== null && isset($this->trainingMap[$row->training_id])) {
            $assignment = TrainingAssignment::query()
                ->where('training_id', $this->trainingMap[$row->training_id])
                ->where('member_id', $memberId)
                ->first();

            if ($assignment !== null) {
                return [TrainingAssignment::class, $assignment->id];
            }
        }

        return [null, null];
    }

    // ─── Other examination personnel ───────────────────────────────────

    private function importOtherExaminationPersonnel(): void
    {
        $typeMap = [
            'Coordinator' => PersonnelType::Coordinator,
            'Inspector' => PersonnelType::Inspector,
            'Paymaster' => PersonnelType::Paymaster,
            'PNP Officer' => PersonnelType::PnpOfficer,
            'Security Officer' => PersonnelType::SecurityOfficer,
            'Janitor' => PersonnelType::Janitor,
            'Helper' => PersonnelType::Helper,
            'Driver' => PersonnelType::Driver,
        ];

        foreach ($this->legacy->table('proctad_non_exam_personnel')->orderBy('nep_id')->get() as $row) {
            $id = DB::table('other_examination_personnel')->insertGetId([
                'oep_id' => $row->nep_id,
                'first_name' => $row->first_name,
                'middle_name' => $row->middle_name,
                'last_name' => $row->last_name,
                'suffix' => $row->suffix,
                'sex' => $row->gender,
                'contact_number' => $row->contact_number,
                'email' => $row->email,
                'agency' => $row->agency,
                'position' => $row->position,
                'personnel_type' => ($typeMap[$row->personnel_type] ?? PersonnelType::Helper)->value,
                'field_office_id' => $row->field_office_id !== null
                    ? ($this->foMap[$row->field_office_id] ?? null)
                    : null,
                'is_active' => $row->status === 'active',
                'created_by' => $this->userMap[$row->created_by] ?? null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
            $this->oepMap[$row->nep_id] = $id;
            $this->tally('other_examination_personnel');
        }
    }

    private function importOepAssignments(): void
    {
        foreach ($this->legacy->table('proctad_nep_school_assignments')->orderBy('assignment_id')->get() as $row) {
            $venueId = $this->venueByPair["{$row->exam_id}|{$row->school_id}"] ?? null;

            if ($venueId === null || ! isset($this->oepMap[$row->nep_id])) {
                $this->tally('oep_assignments', skipped: true);

                continue;
            }

            DB::table('oep_assignments')->insert([
                'other_examination_personnel_id' => $this->oepMap[$row->nep_id],
                'examination_school_id' => $venueId,
                'status' => $row->assignment_status === 'cancelled' ? 'cancelled' : 'confirmed',
                'assigned_by' => $this->userMap[$row->assigned_by] ?? null,
                'created_at' => $row->assigned_at,
                'updated_at' => $row->updated_at,
            ]);
            $this->tally('oep_assignments');
        }
    }

    private function importOepAttendances(): void
    {
        foreach ($this->legacy->table('proctad_nep_attendance')->orderBy('attendance_id')->get() as $row) {
            $venueId = $this->venueByPair["{$row->exam_id}|{$row->school_id}"] ?? null;

            if ($venueId === null || ! isset($this->oepMap[$row->nep_id])) {
                $this->tally('oep_attendances', skipped: true);

                continue;
            }

            DB::table('oep_attendances')->insert([
                'other_examination_personnel_id' => $this->oepMap[$row->nep_id],
                'examination_school_id' => $venueId,
                'status' => $row->attendance_status,
                'scan_method' => $row->scan_method,
                'scanned_at' => $row->scanned_at,
                'scanned_by' => $this->userMap[$row->scanned_by] ?? null,
            ]);
            $this->tally('oep_attendances');
        }
    }

    // ─── Settings, letterheads, email ──────────────────────────────────

    private function importSettings(): void
    {
        // SMTP credentials deliberately excluded — mail config lives in .env now.
        $rows = $this->legacy->table('proctad_system_settings')
            ->where('setting_key', 'not like', 'smtp%')
            ->get();

        foreach ($rows as $row) {
            DB::table('settings')->insert([
                'key' => $row->setting_key,
                'value' => $row->setting_value,
                'type' => $row->setting_type ?? 'string',
                'description' => $row->description,
                'is_public' => (bool) $row->is_public,
                'updated_by' => $this->userMap[$row->updated_by] ?? null,
                'created_at' => $row->updated_at,
                'updated_at' => $row->updated_at,
            ]);
            $this->tally('settings');
        }

        foreach ($this->legacy->table('proctad_config')->get() as $row) {
            if ($row->key === 'active_letter_head') {
                continue; // becomes letterheads.is_active below
            }

            DB::table('settings')->insert([
                'key' => $row->key,
                'value' => $row->value,
                'type' => 'string',
                'description' => 'Imported from legacy proctad_config',
                'is_public' => false,
                'updated_by' => null,
                'created_at' => $row->updated_at,
                'updated_at' => $row->updated_at,
            ]);
            $this->tally('settings');
        }
    }

    private function importLetterheads(): void
    {
        $activeFile = $this->legacy->table('proctad_config')->where('key', 'active_letter_head')->value('value');

        foreach ($this->legacy->table('proctad_letter_head')->orderBy('id')->get() as $row) {
            Letterhead::query()->insert([
                'name' => $row->filename,
                'file_path' => 'letterheads/'.$row->filename,
                'is_active' => $row->filename === $activeFile,
                'uploaded_by' => $this->userMap[$row->user_id] ?? null,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
            $this->tally('letterheads');
        }
    }

    private function importEmailTemplates(): void
    {
        foreach ($this->legacy->table('proctad_email_templates')->get() as $row) {
            EmailTemplate::query()->insert([
                'code' => $row->template_code,
                'name' => $row->template_name,
                'subject' => $row->subject,
                'body_html' => $row->body_html,
                'body_plain' => $row->body_plain,
                'variables' => $row->variables,
                'is_active' => (bool) $row->is_active,
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ]);
            $this->tally('email_templates');
        }
    }

    private function importEmailLogs(): void
    {
        foreach ($this->legacy->table('proctad_email_logs')->orderBy('log_id')->get() as $row) {
            EmailLog::query()->insert([
                'recipient_email' => $row->recipient_email,
                'recipient_name' => $row->recipient_name,
                'subject' => $row->subject,
                'email_type' => $row->email_type,
                'status' => $row->status,
                'error_message' => $row->error_message,
                'sent_by' => $this->userMap[$row->sent_by] ?? null,
                'sent_at' => $row->sent_at,
                'created_at' => $row->created_at,
            ]);
            $this->tally('email_logs');
        }
    }

    private function importSecurityLogs(): void
    {
        foreach ($this->legacy->table('proctad_security_logs')->orderBy('id')->get() as $row) {
            $userId = $this->userMap[$row->user_id] ?? null;

            if ($userId === null) {
                $this->tally('audit_logs', skipped: true);

                continue;
            }

            AuditLog::query()->insert([
                'user_id' => $userId,
                'action' => mb_substr($row->event_type, 0, 50),
                'auditable_type' => User::class,
                'auditable_id' => $userId,
                'field_office_id' => null,
                'changes' => json_encode([
                    'details' => json_decode($row->details ?? 'null', true),
                    'ip' => $row->ip_address,
                ]),
                'created_at' => $row->created_at,
                'updated_at' => $row->created_at,
            ]);
            $this->tally('audit_logs');
        }
    }

    // ─── Helpers ───────────────────────────────────────────────────────

    private function date(?string $value): ?string
    {
        return ($value === null || str_starts_with($value, '0000-00-00')) ? null : $value;
    }

    private function tally(string $table, bool $skipped = false): void
    {
        $this->stats[$table] ??= ['imported' => 0, 'skipped' => 0];
        $this->stats[$table][$skipped ? 'skipped' : 'imported']++;
    }

    private function report(): void
    {
        $this->newLine();
        $this->table(
            ['Table', 'Imported', 'Skipped'],
            collect($this->stats)->map(fn (array $s, string $t) => [$t, $s['imported'], $s['skipped']])->values(),
        );

        if ($this->warnings !== []) {
            $this->newLine();
            $this->warn(count($this->warnings).' warning(s):');
            foreach (array_slice($this->warnings, 0, 40) as $warning) {
                $this->line("  • {$warning}");
            }
            if (count($this->warnings) > 40) {
                $this->line('  … and '.(count($this->warnings) - 40).' more');
            }
        }

        $this->newLine();
        $this->info('Skipped by design: rate-limit/login-attempt/oauth-state/password-reset tables (Laravel natives), '
            .'qr_scans (verification log, superseded), approval_requests (state lives on certificates now), '
            .'view-snapshot tables.');
    }
}
