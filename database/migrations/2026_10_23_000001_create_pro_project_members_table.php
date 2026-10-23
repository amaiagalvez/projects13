<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('PRO_project_members', function (Blueprint $table) {
            $table->id();
            // A trashed project keeps its members, so restoring it gives the same people access; a
            // permanent delete takes them with it.
            $table->foreignId('project_id')->constrained('PRO_projects')->cascadeOnDelete();
            $table->foreignId('user_id')->index()->constrained('users')->cascadeOnDelete();
            $table->string('role', 10);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('PRO_project_members');
    }
};
