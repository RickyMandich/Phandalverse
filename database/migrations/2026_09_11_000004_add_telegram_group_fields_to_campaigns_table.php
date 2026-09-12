<?php

/*
Equivalent MySQL script for manual execution:

ALTER TABLE `campaigns`
  ADD COLUMN `telegram_chat_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `order`,
  ADD COLUMN `telegram_thread_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `telegram_chat_id`,
  ADD UNIQUE KEY `campaigns_telegram_chat_id_telegram_thread_id_unique` (`telegram_chat_id`, `telegram_thread_id`);
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->after('order');
            $table->string('telegram_thread_id')->nullable()->after('telegram_chat_id');
            $table->unique(['telegram_chat_id', 'telegram_thread_id']);
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropUnique(['telegram_chat_id', 'telegram_thread_id']);
            $table->dropColumn(['telegram_chat_id', 'telegram_thread_id']);
        });
    }
};
