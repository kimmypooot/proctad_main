<?php

use App\Enums\ExamRole;
use App\Enums\ExamRoleGroup;
use App\Enums\OepRoleGroup;
use App\Enums\PayeeType;
use App\Enums\PersonnelType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Promotes designations and their committees from enums to data, so they can be
 * added, moved and removed without a code change.
 *
 * The enums stay: ExamRole and PersonnelType remain the canonical list of
 * *built-in* keys, and the structural rules keep naming them — the payroll
 * workbook's Room Examiner and Proctor pages, the room grid, the evaluation
 * form, the ex officio REC seats. Built-in rows are flagged and cannot be
 * deleted, which is what keeps those rules honest. Anything added afterwards is
 * a custom designation: assignable and payable, but outside those rules.
 *
 * Rows are seeded from the enums so the tables open as an exact description of
 * how the system already behaves, and any overrides recorded in the previous
 * designation_settings table are carried across before it is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('designation_categories', function (Blueprint $table) {
            $table->id();
            // 'exam_role' or 'personnel_type' — which of the two lists this
            // committee belongs to. Mirrors PayeeType.
            $table->string('section', 20);
            $table->string('key', 40);
            $table->string('label', 100);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_builtin')->default(false);
            $table->timestamps();

            $table->unique(['section', 'key']);
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('section', 20);
            // The value stored on exam_assignments.role and
            // other_examination_personnel.personnel_type. Immutable once created,
            // so renaming or re-filing a designation never rewrites history.
            $table->string('key', 40);
            $table->string('label', 100);
            $table->foreignId('designation_category_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_builtin')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['section', 'key']);
        });

        $this->seedBuiltins();
        $this->carryOverPreviousSettings();

        Schema::dropIfExists('designation_settings');
    }

    public function down(): void
    {
        Schema::create('designation_settings', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('value', 64);
            $table->string('label', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['type', 'value']);
        });

        Schema::dropIfExists('designations');
        Schema::dropIfExists('designation_categories');
    }

    private function seedBuiltins(): void
    {
        $now = now();
        $categoryIds = [];
        $sort = 0;

        foreach (ExamRoleGroup::cases() as $group) {
            $categoryIds["exam_role|{$group->value}"] = DB::table('designation_categories')->insertGetId([
                'section' => PayeeType::ExamRole->value,
                'key' => $group->value,
                'label' => $group->label(),
                'sort_order' => $sort++,
                'is_builtin' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $sort = 0;

        foreach (OepRoleGroup::cases() as $group) {
            $categoryIds["personnel_type|{$group->value}"] = DB::table('designation_categories')->insertGetId([
                'section' => PayeeType::PersonnelType->value,
                'key' => $group->value,
                'label' => $group->label(),
                'sort_order' => $sort++,
                'is_builtin' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $sort = 0;

        foreach (ExamRole::cases() as $role) {
            DB::table('designations')->insert([
                'section' => PayeeType::ExamRole->value,
                'key' => $role->value,
                'label' => $role->defaultLabel(),
                'designation_category_id' => $categoryIds["exam_role|{$role->group()->value}"],
                'is_active' => true,
                'is_builtin' => true,
                'sort_order' => $sort++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $sort = 0;

        foreach (PersonnelType::cases() as $type) {
            DB::table('designations')->insert([
                'section' => PayeeType::PersonnelType->value,
                'key' => $type->value,
                'label' => $type->defaultLabel(),
                'designation_category_id' => $categoryIds["personnel_type|{$type->group()->value}"],
                'is_active' => true,
                'is_builtin' => true,
                'sort_order' => $sort++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** Renames and deactivations made on the previous page must survive. */
    private function carryOverPreviousSettings(): void
    {
        if (! Schema::hasTable('designation_settings')) {
            return;
        }

        foreach (DB::table('designation_settings')->get() as $setting) {
            DB::table('designations')
                ->where('section', $setting->type)
                ->where('key', $setting->value)
                ->update(array_filter([
                    'label' => $setting->label,
                    'is_active' => (bool) $setting->is_active,
                ], fn ($value) => $value !== null) + ['is_active' => (bool) $setting->is_active]);
        }
    }
};
