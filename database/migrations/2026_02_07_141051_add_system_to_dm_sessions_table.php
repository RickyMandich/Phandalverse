<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dm_sessions', function (Blueprint $table) {
            // RAW SQL: ALTER TABLE dm_sessions ADD COLUMN `system` VARCHAR(255) DEFAULT 'dnd5e' NOT NULL;
            $table->string('system')->default('dnd5e')->after('data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dm_sessions', function (Blueprint $table) {
            $table->dropColumn('system');
        });
    }
};
