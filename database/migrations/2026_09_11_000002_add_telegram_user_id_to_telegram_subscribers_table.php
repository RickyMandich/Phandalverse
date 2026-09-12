<?php

/*
Equivalent MySQL script for manual execution:

ALTER TABLE `telegram_subscribers`
  ADD COLUMN `telegram_user_id` bigint unsigned DEFAULT NULL AFTER `username`,
  ADD KEY `telegram_subscribers_telegram_user_id_index` (`telegram_user_id`);
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('telegram_subscribers', function (Blueprint $table) {
            $table->unsignedBigInteger('telegram_user_id')->nullable()->after('username')->index();
        });
    }

    public function down(): void
    {
        Schema::table('telegram_subscribers', function (Blueprint $table) {
            $table->dropColumn('telegram_user_id');
        });
    }
};
