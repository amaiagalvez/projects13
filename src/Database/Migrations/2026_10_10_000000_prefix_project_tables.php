<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('epic_comments', 'PRO_epic_comments');
        Schema::rename('epics', 'PRO_epics');
        Schema::rename('projects', 'PRO_projects');
    }

    public function down(): void
    {
        Schema::rename('PRO_projects', 'projects');
        Schema::rename('PRO_epics', 'epics');
        Schema::rename('PRO_epic_comments', 'epic_comments');
    }
};
