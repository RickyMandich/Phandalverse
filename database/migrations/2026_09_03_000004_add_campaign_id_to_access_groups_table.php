<?php

/*
Equivalent MySQL script for manual execution:

-- Aggiunta campaign_id e aggiornamento indice unique per scoping per campagna:
ALTER TABLE `access_groups`
  ADD COLUMN `campaign_id` bigint unsigned NOT NULL DEFAULT 1 AFTER `id`,
  DROP INDEX `access_groups_slug_unique`,
  ADD UNIQUE KEY `access_groups_campaign_id_slug_unique` (`campaign_id`, `slug`),
  ADD CONSTRAINT `access_groups_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE;

-- Aggiorna eventuali gruppi esistenti associandoli alla campagna iniziale (id: 1):
UPDATE `access_groups` SET `campaign_id` = 1 WHERE `campaign_id` = 0 OR `campaign_id` IS NULL;
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('access_groups', function (Blueprint $table) {
            $table->foreignId('campaign_id')->default(1)->after('id')->constrained('campaigns')->cascadeOnDelete();
            $table->dropUnique(['slug']);
            $table->unique(['campaign_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('access_groups', function (Blueprint $table) {
            $table->dropForeign(['campaign_id']);
            $table->dropUnique(['campaign_id', 'slug']);
            $table->dropColumn('campaign_id');
            $table->unique('slug');
        });
    }
};
