<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A field office says which office a member of CSC staff works for. It
        // was never meant to describe an external test administrator, who works
        // for their own agency and simply serves a testing center — asking them
        // to pick one produced associations that were at best arbitrary.
        //
        // Members are now anchored by testing_center_id alone. The column stays
        // for the minority of members who are also CSC employees, and becomes
        // nullable for everyone else.
        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('field_office_id')->nullable()->change();
        });

        // External = no login account, or an account that is not staff. Members
        // who are CSC employees keep their office; it is what makes a regional
        // office employee assignable region-wide.
        DB::table('members')
            ->whereNotNull('field_office_id')
            ->where(fn ($q) => $q
                ->whereNull('user_id')
                ->orWhereExists(fn ($sub) => $sub
                    ->select(DB::raw(1))
                    ->from('users')
                    ->whereColumn('users.id', 'members.user_id')
                    ->where('users.role', 'member')))
            ->update(['field_office_id' => null]);
    }

    public function down(): void
    {
        // The cleared associations are not recoverable; only the constraint is.
        DB::table('members')->whereNull('field_office_id')->update(['field_office_id' => 1]);

        Schema::table('members', function (Blueprint $table) {
            $table->foreignId('field_office_id')->nullable(false)->change();
        });
    }
};
