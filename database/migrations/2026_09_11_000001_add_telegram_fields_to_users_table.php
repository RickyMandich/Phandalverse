<?php

/*
Equivalent MySQL script for manual execution:

ALTER TABLE `users`
  ADD COLUMN `telegram_user_id` bigint unsigned DEFAULT NULL AFTER `default_campaign_id`,
  ADD COLUMN `telegram_username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `telegram_user_id`,
  ADD UNIQUE KEY `users_telegram_user_id_unique` (`telegram_user_id`);
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('telegram_user_id')->nullable()->unique()->after('default_campaign_id');
            $table->string('telegram_username')->nullable()->after('telegram_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['telegram_user_id']);
            $table->dropColumn(['telegram_user_id', 'telegram_username']);
        });
    }
};
