<?php

/*
Equivalent MySQL script for manual execution:

CREATE TABLE IF NOT EXISTS `telegram_link_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('personal','group') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'personal',
  `telegram_user_id` bigint unsigned NOT NULL,
  `telegram_username` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `chat_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `thread_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `telegram_link_tokens_token_unique` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('telegram_link_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->enum('type', ['personal', 'group'])->default('personal');
            $table->unsignedBigInteger('telegram_user_id');
            $table->string('telegram_username')->nullable();
            $table->string('chat_id');
            $table->string('thread_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_link_tokens');
    }
};
