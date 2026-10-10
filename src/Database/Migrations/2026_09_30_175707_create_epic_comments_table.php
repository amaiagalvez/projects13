<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('PRO_epic_comments', function (Blueprint $table) {
            $table->id();
            $table->longText('body');
            $table->foreignId('user_id')->constrained();
            $table->foreignId('epic_id')->constrained('PRO_epics');

            addCommonColumns($table);
            addAuditColumns($table);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('PRO_epic_comments');
    }
};
