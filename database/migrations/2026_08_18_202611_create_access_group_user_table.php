<?php

/*
Equivalent MySQL script for manual execution:

CREATE TABLE IF NOT EXISTS `access_group_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `access_group_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `access_group_user_user_id_access_group_id_unique` (`user_id`, `access_group_id`),
  KEY `access_group_user_user_id_foreign` (`user_id`),
  KEY `access_group_user_access_group_id_foreign` (`access_group_id`),
  CONSTRAINT `access_group_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `access_group_user_access_group_id_foreign` FOREIGN KEY (`access_group_id`) REFERENCES `access_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('access_group_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('access_group_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'access_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_group_user');
    }
};