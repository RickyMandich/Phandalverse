-- SQL for adding images column to user_reports table
ALTER TABLE `user_reports` ADD `images` TEXT NULL AFTER `page_urls`;
