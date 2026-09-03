<?php

/*
Equivalent MySQL script for manual execution:

CREATE TABLE IF NOT EXISTS `campaign_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `campaign_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaign_user_campaign_id_user_id_unique` (`campaign_id`, `user_id`),
  KEY `campaign_user_campaign_id_foreign` (`campaign_id`),
  KEY `campaign_user_user_id_foreign` (`user_id`),
  CONSTRAINT `campaign_user_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `campaigns` (`id`) ON DELETE CASCADE,
  CONSTRAINT `campaign_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Associazione di tutti gli utenti esistenti alla prima campagna:
INSERT IGNORE INTO `campaign_user` (`campaign_id`, `user_id`, `created_at`, `updated_at`)
SELECT 1, `id`, NOW(), NOW() FROM `users`;
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaign_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['campaign_id', 'user_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_user');
    }
};
