<?php

/*
Equivalent MySQL script for manual execution:

CREATE TABLE IF NOT EXISTS `campaigns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `folder_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaigns_folder_name_unique` (`folder_name`),
  UNIQUE KEY `campaigns_order_unique` (`order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Popolamento iniziale campagna corrente (es. newCampaign):
INSERT INTO `campaigns` (`id`, `folder_name`, `display_name`, `order`, `created_at`, `updated_at`)
VALUES (1, 'newCampaign', 'Phandalin', 10, NOW(), NOW())
ON DUPLICATE KEY UPDATE `display_name` = VALUES(`display_name`);
*/

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('folder_name')->unique();
            $table->string('display_name');
            $table->integer('order')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
