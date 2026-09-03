<?php

/*
Equivalent MySQL script for manual execution:

ALTER TABLE `telegram_subscribers`
  ADD COLUMN `campaign_id` bigint unsigned NOT NULL DEFAULT 1 AFTER `id`,
  ADD KEY `telegram_subscribers_campaign_id_foreign` (`campaign_id`),
  ADD CONSTRAINT `telegram_subscribers_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE;

-- Assegna le iscrizioni esistenti alla campagna iniziale:
UPDATE `telegram_subscribers` SET `campaign_id` = 1 WHERE `campaign_id` = 0 OR `campaign_id` IS NULL;
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('telegram_subscribers', function (Blueprint $table) {
            $table->foreignId('campaign_id')->default(1)->after('id')->constrained('campaigns')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_subscribers', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropColumn('campaign_id');
        });
    }
};
