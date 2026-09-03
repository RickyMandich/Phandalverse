<?php

/*
Equivalent MySQL script for manual execution:

-- Step 1: Aggiunta colonna campaign_id senza FK
ALTER TABLE `access_groups`
  ADD COLUMN `campaign_id` bigint unsigned NOT NULL DEFAULT 1 AFTER `id`;

-- Step 2: Assicura che le righe esistenti abbiano un valore valido
UPDATE `access_groups` SET `campaign_id` = 1 WHERE `campaign_id` = 0 OR `campaign_id` IS NULL;

-- Step 3: Aggiunta FK e aggiornamento indice unique
ALTER TABLE `access_groups`
  DROP INDEX `access_groups_slug_unique`,
  ADD UNIQUE KEY `access_groups_campaign_id_slug_unique` (`campaign_id`, `slug`),
  ADD CONSTRAINT `access_groups_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE;
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Step 1: Aggiungi la colonna senza FK, così le righe esistenti ricevono il DEFAULT 1
        Schema::table('access_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('campaign_id')->default(1)->after('id');
        });

        // Step 2: Assicura che tutte le righe esistenti abbiano un valore valido
        DB::table('access_groups')->whereNull('campaign_id')->orWhere('campaign_id', 0)->update(['campaign_id' => 1]);

        // Step 3: Ora aggiunge FK e aggiorna l'indice univoco
        Schema::table('access_groups', function (Blueprint $table) {
            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
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
