<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renamed role labels, one row per role an administrator has relabelled.
 *
 * Display text only. The stored `users.role` value never changes, so renaming
 * "Field Office Staff" cannot affect a single authorization decision — every
 * policy, scope rule and permission still matches on the underlying value.
 * Roles with no row here keep the built-in label from UserRole::defaultLabel().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_labels', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30)->unique();
            $table->string('label', 100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_labels');
    }
};
