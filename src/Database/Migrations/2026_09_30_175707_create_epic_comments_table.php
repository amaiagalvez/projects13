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
        Schema::create('epic_comments', function (Blueprint $table) {
            $table->id();
            $table->longText('body');
            $table->foreignId('user_id')->constrained();
            $table->foreignId('epic_id')->constrained();

            addCommonColumns($table);
            addAuditColumns($table);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('epic_comments');
    }
};
