<?php

/*
Equivalent MySQL script for manual execution:

-- Step 1: Aggiunta colonna campaign_id senza FK
ALTER TABLE `telegram_subscribers`
  ADD COLUMN `campaign_id` bigint unsigned NOT NULL DEFAULT 1 AFTER `id`;

-- Step 2: Assicura che le righe esistenti abbiano un valore valido
UPDATE `telegram_subscribers` SET `campaign_id` = 1 WHERE `campaign_id` = 0 OR `campaign_id` IS NULL;

-- Step 3: Aggiunta FK
ALTER TABLE `telegram_subscribers`
  ADD KEY `telegram_subscribers_campaign_id_foreign` (`campaign_id`),
  ADD CONSTRAINT `telegram_subscribers_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE;
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Step 1: Aggiungi la colonna senza FK, così le righe esistenti ricevono il DEFAULT 1
        Schema::table('telegram_subscribers', function (Blueprint $table) {
            $table->unsignedBigInteger('campaign_id')->default(1)->after('id');
        });

        // Step 2: Assicura che tutte le righe esistenti abbiano un valore valido
        DB::table('telegram_subscribers')->whereNull('campaign_id')->orWhere('campaign_id', 0)->update(['campaign_id' => 1]);

        // Step 3: Aggiunge la FK
        Schema::table('telegram_subscribers', function (Blueprint $table) {
            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
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
