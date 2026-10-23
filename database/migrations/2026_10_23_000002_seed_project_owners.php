<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

/**
 * Whoever created each existing project becomes its owner, so nobody loses the projects they
 * started once visibility follows membership. Trashed projects too: restoring one keeps its owner.
 * Running it again adds nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('PRO_projects')
            ->whereNotNull('created_by')
            ->select(['id', 'created_by'])
            ->chunkById(500, static function ($projects) use ($now): void {
                DB::table('PRO_project_members')->insertOrIgnore($projects->map(static fn (object $project): array => [
                    'project_id' => $project->id,
                    'user_id' => $project->created_by,
                    'role' => 'owner',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }

    public function down(): void
    {
        // The rows go with the table the previous migration drops.
    }
};
