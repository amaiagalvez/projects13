<?php

use Illuminate\Database\Migrations\Migration;

/**
 * The permissions the project and epic policies check; planning and timeline read through
 * `projects.view`. `admin` gets full access to both modules, so the accounts that held it before
 * keep working with projects and epics.
 */
return new class extends Migration
{
    public function up(): void
    {
        seedPermissions(['projects', 'epics'], ['projects.manage', 'epics.manage']);
    }

    public function down(): void
    {
        // The permission tables are dropped by the basics13 migrations that created them.
    }
};
