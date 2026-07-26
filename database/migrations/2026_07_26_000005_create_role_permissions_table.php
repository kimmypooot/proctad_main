<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overrides to the built-in permission defaults, one row per role/permission
 * an administrator has explicitly changed.
 *
 * Only differences are stored. A permission with no row falls back to the
 * default in PermissionRegistry, so adding a new capability in code grants it
 * on the same terms everywhere without a data migration, and an untouched
 * install behaves exactly as it did before this table existed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30);
            $table->string('permission', 64);
            $table->boolean('granted');
            $table->timestamps();

            $table->unique(['role', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
