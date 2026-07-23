<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Splits the old catch-all 'management' role into 'director_iv' and
 * 'director_iii' so the REC chairmanship can be modelled (the committee is
 * always headed by the Director IV, co-chaired by the Director III).
 *
 * Existing rows cannot be told apart — the old role carried no such
 * distinction — so they all become Director IV, the senior post. That is the
 * safe direction: it preserves every approval right the account already had
 * (isManagement() covers both, and isRegionWide() is unchanged), and a
 * superadmin can demote whichever account is really the Director III from
 * /users. Guessing the other way would silently strip the head of office.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'management')->update(['role' => 'director_iv']);
    }

    public function down(): void
    {
        DB::table('users')
            ->whereIn('role', ['director_iv', 'director_iii'])
            ->update(['role' => 'management']);
    }
};
