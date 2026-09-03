<?php

/*
Equivalent MySQL script for manual execution:

ALTER TABLE `users`
  ADD COLUMN `default_campaign_id` bigint unsigned DEFAULT NULL AFTER `collapseEmbed`,
  ADD CONSTRAINT `users_default_campaign_id_foreign` FOREIGN KEY (`default_campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE SET NULL;
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_campaign_id')->nullable()->after('collapseEmbed')->constrained('campaigns')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['default_campaign_id']);
            $table->dropColumn('default_campaign_id');
        });
    }
};
