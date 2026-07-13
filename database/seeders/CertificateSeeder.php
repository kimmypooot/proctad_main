<?php

namespace Database\Seeders;

use App\Enums\CertificateType;
use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\ExamAssignment;
use App\Models\Examination;
use App\Models\Training;
use App\Models\TrainingAssignment;
use App\Models\User;
use App\Services\CertificateService;
use Illuminate\Database\Seeder;

class CertificateSeeder extends Seeder
{
    public function run(CertificateService $certificates): void
    {
        if (Certificate::exists()) {
            return;
        }

        $superAdmin = User::where('role', UserRole::SuperAdmin)->first();
        $fieldDirector = User::where('role', UserRole::FieldDirector)->first();

        if (! $superAdmin) {
            return;
        }

        $this->seedAppearanceAndAppreciation($certificates, $superAdmin, $fieldDirector);
        $this->seedCompletion($certificates, $superAdmin);
    }

    private function seedAppearanceAndAppreciation(CertificateService $certificates, User $requestedBy, ?User $approver): void
    {
        $past = Examination::where('title', 'March 2026 CSE-PPT')->first();

        if (! $past) {
            return;
        }

        $assignments = ExamAssignment::where('examination_id', $past->id)
            ->whereNotNull('attendance_confirmed_at')
            ->get();

        foreach ($assignments as $index => $assignment) {
            $certificate = $certificates->generatePending(CertificateType::Appreciation, $assignment, $requestedBy);

            // Release most, leave a couple pending so the Approvals queue has something to show.
            if ($index % 3 !== 2) {
                $certificates->release($certificate, $approver ?? $requestedBy);
            }
        }

        // One Designation Order, still pending Field Director approval.
        if ($first = $assignments->first()) {
            $certificates->generatePending(CertificateType::DesignationOrder, $first, $requestedBy);
        }
    }

    private function seedCompletion(CertificateService $certificates, User $requestedBy): void
    {
        $tea = Training::where('title', 'TEA Batch 1 — 2026')->first();

        if (! $tea) {
            return;
        }

        $assignments = TrainingAssignment::where('training_id', $tea->id)
            ->whereNotNull('attendance_confirmed_at')
            ->get();

        foreach ($assignments as $assignment) {
            // generatePending() auto-releases Completion certificates itself —
            // there's no approver role for this type, so none can ever be left
            // pending (a prior version of this seeder did that "for demo
            // purposes," which produced a certificate nobody could approve).
            $certificates->generatePending(CertificateType::Completion, $assignment, $requestedBy);
        }

        if ($assignments->isNotEmpty()) {
            $tea->update(['completed_at' => '2026-02-12 17:00:00']);
        }
    }
}
