-- LoveyNest: seluruh struktur database (tanpa data).
-- Cara pakai: buat database kosong di phpMyAdmin (utf8mb4_unicode_ci), pilih database itu, tab Import, pilih berkas ini.
-- Setelah impor TIDAK perlu menjalankan "php artisan migrate": tabel migrations sudah terisi.
-- Dibuat dari 29 migrasi, sampai 2026_11_04_000100_add_profile_setup.
-- Diuji impor bersih di MariaDB 10.11 (MySQL 8.0 diuji sampai versi sebelumnya). Tanpa tipe uuid. Di .env hosting MySQL isi DB_CONNECTION=mysql.

SET NAMES utf8mb4;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `action` varchar(40) NOT NULL,
  `subject_type` varchar(40) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `subject_title` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `activity_logs_actor_id_foreign` (`actor_id`),
  KEY `activity_logs_couple_space_id_id_index` (`couple_space_id`,`id`),
  CONSTRAINT `activity_logs_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `activity_logs_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `agreements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `title` text NOT NULL,
  `note` text DEFAULT NULL,
  `status` enum('active','paused','done') NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `agreements_created_by_foreign` (`created_by`),
  KEY `agreements_couple_space_id_status_index` (`couple_space_id`,`status`),
  CONSTRAINT `agreements_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `agreements_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_quiz_questions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `topic` varchar(80) DEFAULT NULL,
  `question` varchar(200) NOT NULL,
  `choices` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ai_quiz_questions_created_by_foreign` (`created_by`),
  KEY `ai_quiz_questions_couple_space_id_index` (`couple_space_id`),
  CONSTRAINT `ai_quiz_questions_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ai_quiz_questions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `albums` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scope` enum('personal','shared') NOT NULL,
  `owner_user_id` bigint(20) unsigned DEFAULT NULL,
  `couple_space_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `title` text NOT NULL,
  `description` text DEFAULT NULL,
  `cover_photo_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `albums_created_by_foreign` (`created_by`),
  KEY `albums_owner_user_id_updated_at_index` (`owner_user_id`,`updated_at`),
  KEY `albums_couple_space_id_updated_at_index` (`couple_space_id`,`updated_at`),
  CONSTRAINT `albums_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `albums_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `albums_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `albums_scope_check` CHECK (`scope` = 'personal' and `owner_user_id` is not null and `couple_space_id` is null or `scope` = 'shared' and `couple_space_id` is not null and `owner_user_id` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `challenge_completions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `challenge_key` varchar(40) NOT NULL,
  `done_on` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `challenge_once_a_day` (`couple_space_id`,`user_id`,`challenge_key`,`done_on`),
  KEY `challenge_completions_user_id_foreign` (`user_id`),
  CONSTRAINT `challenge_completions_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `challenge_completions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_conversations_couple_space_id_unique` (`couple_space_id`),
  CONSTRAINT `chat_conversations_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chat_conversation_id` bigint(20) unsigned NOT NULL,
  `sender_id` bigint(20) unsigned DEFAULT NULL,
  `client_uuid` char(36) NOT NULL,
  `body` text NOT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `photo_thumb_path` varchar(255) DEFAULT NULL,
  `photo_width` smallint(5) unsigned DEFAULT NULL,
  `photo_height` smallint(5) unsigned DEFAULT NULL,
  `sticker` varchar(32) DEFAULT NULL,
  `audio_path` varchar(255) DEFAULT NULL,
  `audio_mime` varchar(40) DEFAULT NULL,
  `audio_seconds` smallint(5) unsigned DEFAULT NULL,
  `edited_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `pinned_at` timestamp NULL DEFAULT NULL,
  `pinned_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp(3) NOT NULL DEFAULT current_timestamp(3),
  `reply_to_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_messages_chat_conversation_id_sender_id_client_uuid_unique` (`chat_conversation_id`,`sender_id`,`client_uuid`),
  KEY `chat_messages_sender_id_foreign` (`sender_id`),
  KEY `chat_messages_chat_conversation_id_id_index` (`chat_conversation_id`,`id`),
  KEY `chat_messages_reply_to_id_foreign` (`reply_to_id`),
  KEY `chat_messages_pinned_by_foreign` (`pinned_by`),
  CONSTRAINT `chat_messages_chat_conversation_id_foreign` FOREIGN KEY (`chat_conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_messages_pinned_by_foreign` FOREIGN KEY (`pinned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chat_messages_reply_to_id_foreign` FOREIGN KEY (`reply_to_id`) REFERENCES `chat_messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chat_messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_reactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chat_message_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `emoji` varchar(16) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_reactions_chat_message_id_user_id_unique` (`chat_message_id`,`user_id`),
  KEY `chat_reactions_user_id_foreign` (`user_id`),
  CONSTRAINT `chat_reactions_chat_message_id_foreign` FOREIGN KEY (`chat_message_id`) REFERENCES `chat_messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_reactions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_read_states` (
  `chat_conversation_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `last_read_message_id` bigint(20) unsigned DEFAULT NULL,
  `muted` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`chat_conversation_id`,`user_id`),
  KEY `chat_read_states_user_id_foreign` (`user_id`),
  CONSTRAINT `chat_read_states_chat_conversation_id_foreign` FOREIGN KEY (`chat_conversation_id`) REFERENCES `chat_conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_read_states_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `checkins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `week_start` date NOT NULL,
  `feeling` tinyint(3) unsigned NOT NULL,
  `appreciation` text DEFAULT NULL,
  `improve` text DEFAULT NULL,
  `shared` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `checkins_couple_space_id_user_id_week_start_unique` (`couple_space_id`,`user_id`,`week_start`),
  KEY `checkins_user_id_foreign` (`user_id`),
  CONSTRAINT `checkins_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `checkins_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `couple_invitations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `code_hash` char(64) NOT NULL,
  `expires_at` timestamp NOT NULL,
  `used_at` timestamp NULL DEFAULT NULL,
  `used_by` bigint(20) unsigned DEFAULT NULL,
  `revoked_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `couple_invitations_code_hash_unique` (`code_hash`),
  KEY `couple_invitations_created_by_foreign` (`created_by`),
  KEY `couple_invitations_used_by_foreign` (`used_by`),
  KEY `couple_invitations_couple_space_id_used_at_revoked_at_index` (`couple_space_id`,`used_at`,`revoked_at`),
  CONSTRAINT `couple_invitations_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `couple_invitations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `couple_invitations_used_by_foreign` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `couple_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `slot` tinyint(3) unsigned NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `left_at` timestamp NULL DEFAULT NULL,
  `active_user_id` bigint(20) unsigned GENERATED ALWAYS AS (if(`left_at` is null,`user_id`,NULL)) STORED,
  `active_slot` tinyint(3) unsigned GENERATED ALWAYS AS (if(`left_at` is null,`slot`,NULL)) STORED,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `couple_members_active_user_id_unique` (`active_user_id`),
  UNIQUE KEY `couple_members_couple_space_id_active_slot_unique` (`couple_space_id`,`active_slot`),
  KEY `couple_members_user_id_foreign` (`user_id`),
  KEY `couple_members_couple_space_id_left_at_index` (`couple_space_id`,`left_at`),
  CONSTRAINT `couple_members_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `couple_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `couple_members_slot_check` CHECK (`slot` in (1,2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `couple_spaces` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `status` enum('active','dissolved') NOT NULL DEFAULT 'active',
  `dissolved_at` timestamp NULL DEFAULT NULL,
  `dissolved_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `anniversary_date` date DEFAULT NULL,
  `met_date` date DEFAULT NULL,
  `date_budget` int(10) unsigned DEFAULT NULL,
  `theme` enum('rose','lavender','sage') NOT NULL DEFAULT 'rose',
  PRIMARY KEY (`id`),
  KEY `couple_spaces_created_by_foreign` (`created_by`),
  KEY `couple_spaces_dissolved_by_foreign` (`dissolved_by`),
  KEY `couple_spaces_status_index` (`status`),
  CONSTRAINT `couple_spaces_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `couple_spaces_dissolved_by_foreign` FOREIGN KEY (`dissolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `daily_answers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `answer_date` date NOT NULL,
  `question_index` smallint(5) unsigned NOT NULL,
  `answer` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `daily_answers_user_id_answer_date_unique` (`user_id`,`answer_date`),
  KEY `daily_answers_couple_space_id_answer_date_index` (`couple_space_id`,`answer_date`),
  CONSTRAINT `daily_answers_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `daily_answers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `date_expenses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `paid_by` bigint(20) unsigned DEFAULT NULL,
  `title` text NOT NULL,
  `note` text DEFAULT NULL,
  `amount` int(10) unsigned NOT NULL,
  `payer_share` tinyint(3) unsigned NOT NULL DEFAULT 50,
  `category` enum('makan','jalan','hadiah','nonton','lainnya') NOT NULL DEFAULT 'lainnya',
  `spent_on` date NOT NULL,
  `settled_at` datetime DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `date_expenses_paid_by_foreign` (`paid_by`),
  KEY `date_expenses_couple_space_id_spent_on_index` (`couple_space_id`,`spent_on`),
  CONSTRAINT `date_expenses_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `date_expenses_paid_by_foreign` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `dreams` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `title` text NOT NULL,
  `note` text DEFAULT NULL,
  `category` enum('tempat','makanan','film','pengalaman','liburan','belajar','lainnya') NOT NULL DEFAULT 'pengalaman',
  `status` enum('wish','planned','active','done','cancelled') NOT NULL DEFAULT 'wish',
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `target_date` date DEFAULT NULL,
  `progress` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL,
  `image_thumb_path` varchar(255) DEFAULT NULL,
  `done_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dreams_created_by_foreign` (`created_by`),
  KEY `dreams_couple_space_id_status_index` (`couple_space_id`,`status`),
  CONSTRAINT `dreams_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `dreams_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `e2e_devices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `public_key` text NOT NULL,
  `label` varchar(80) DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `e2e_devices_user_id_foreign` (`user_id`),
  CONSTRAINT `e2e_devices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `e2e_key_wraps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `e2e_space_key_id` bigint(20) unsigned NOT NULL,
  `e2e_device_id` bigint(20) unsigned NOT NULL,
  `wrapped` text NOT NULL,
  `wrapped_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `e2e_key_wraps_e2e_space_key_id_e2e_device_id_unique` (`e2e_space_key_id`,`e2e_device_id`),
  KEY `e2e_key_wraps_e2e_device_id_foreign` (`e2e_device_id`),
  KEY `e2e_key_wraps_wrapped_by_foreign` (`wrapped_by`),
  CONSTRAINT `e2e_key_wraps_e2e_device_id_foreign` FOREIGN KEY (`e2e_device_id`) REFERENCES `e2e_devices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `e2e_key_wraps_e2e_space_key_id_foreign` FOREIGN KEY (`e2e_space_key_id`) REFERENCES `e2e_space_keys` (`id`) ON DELETE CASCADE,
  CONSTRAINT `e2e_key_wraps_wrapped_by_foreign` FOREIGN KEY (`wrapped_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `e2e_space_keys` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `key_id` char(8) NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `e2e_space_keys_key_id_unique` (`key_id`),
  KEY `e2e_space_keys_created_by_foreign` (`created_by`),
  KEY `e2e_space_keys_couple_space_id_id_index` (`couple_space_id`,`id`),
  CONSTRAINT `e2e_space_keys_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `e2e_space_keys_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_rsvps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `status` enum('yes','maybe','no') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_rsvps_event_id_user_id_unique` (`event_id`,`user_id`),
  KEY `event_rsvps_user_id_foreign` (`user_id`),
  CONSTRAINT `event_rsvps_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `event_rsvps_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scope` enum('personal','shared') NOT NULL,
  `owner_user_id` bigint(20) unsigned DEFAULT NULL,
  `couple_space_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `title` text NOT NULL,
  `description` text DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `all_day` tinyint(1) NOT NULL DEFAULT 0,
  `timezone` varchar(64) NOT NULL,
  `reminder_minutes` smallint(5) unsigned DEFAULT NULL,
  `repeat` enum('none','weekly','monthly','yearly') NOT NULL DEFAULT 'none',
  `repeat_until` date DEFAULT NULL,
  `category` enum('kencan','keluarga','kerja','lainnya') DEFAULT NULL,
  `rating` tinyint(3) unsigned DEFAULT NULL,
  `rating_note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `events_created_by_foreign` (`created_by`),
  KEY `events_owner_user_id_starts_at_index` (`owner_user_id`,`starts_at`),
  KEY `events_couple_space_id_starts_at_index` (`couple_space_id`,`starts_at`),
  KEY `events_repeat_starts_at_index` (`repeat`,`starts_at`),
  CONSTRAINT `events_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `events_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `events_scope_check` CHECK (`scope` = 'personal' and `owner_user_id` is not null and `couple_space_id` is null or `scope` = 'shared' and `couple_space_id` is not null and `owner_user_id` is null),
  CONSTRAINT `events_range_check` CHECK (`ends_at` is null or `ends_at` >= `starts_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `goal_events` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `goal_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `from_status` varchar(12) DEFAULT NULL,
  `to_status` varchar(12) NOT NULL,
  `progress` tinyint(3) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `goal_events_goal_id_foreign` (`goal_id`),
  KEY `goal_events_user_id_foreign` (`user_id`),
  CONSTRAINT `goal_events_goal_id_foreign` FOREIGN KEY (`goal_id`) REFERENCES `goals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goal_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `goals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scope` enum('personal','shared') NOT NULL,
  `owner_user_id` bigint(20) unsigned DEFAULT NULL,
  `couple_space_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `title` text NOT NULL,
  `note` text DEFAULT NULL,
  `category` varchar(30) DEFAULT NULL,
  `status` enum('planned','active','done','cancelled') NOT NULL DEFAULT 'planned',
  `target_date` date DEFAULT NULL,
  `progress` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `goals_created_by_foreign` (`created_by`),
  KEY `goals_owner_user_id_status_index` (`owner_user_id`,`status`),
  KEY `goals_couple_space_id_status_index` (`couple_space_id`,`status`),
  CONSTRAINT `goals_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goals_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `goals_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `goals_scope_check` CHECK (`scope` = 'personal' and `owner_user_id` is not null and `couple_space_id` is null or `scope` = 'shared' and `couple_space_id` is not null and `owner_user_id` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `habit_checkins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `habit_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `done_on` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `habit_checkins_habit_id_user_id_done_on_unique` (`habit_id`,`user_id`,`done_on`),
  KEY `habit_checkins_user_id_foreign` (`user_id`),
  CONSTRAINT `habit_checkins_habit_id_foreign` FOREIGN KEY (`habit_id`) REFERENCES `habits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `habit_checkins_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `habits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scope` enum('personal','shared') NOT NULL,
  `owner_user_id` bigint(20) unsigned DEFAULT NULL,
  `couple_space_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `title` text NOT NULL,
  `period` enum('daily','weekly','monthly') NOT NULL DEFAULT 'daily',
  `target` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `archived_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `habits_created_by_foreign` (`created_by`),
  KEY `habits_owner_user_id_archived_at_index` (`owner_user_id`,`archived_at`),
  KEY `habits_couple_space_id_archived_at_index` (`couple_space_id`,`archived_at`),
  CONSTRAINT `habits_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `habits_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `habits_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `habits_scope_check` CHECK (`scope` = 'personal' and `owner_user_id` is not null and `couple_space_id` is null or `scope` = 'shared' and `couple_space_id` is not null and `owner_user_id` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `journal_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scope` enum('personal','shared') NOT NULL,
  `owner_user_id` bigint(20) unsigned DEFAULT NULL,
  `couple_space_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `kind` enum('journal','letter') NOT NULL DEFAULT 'journal',
  `title` text NOT NULL,
  `body` text NOT NULL,
  `entry_date` date NOT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `journal_entries_created_by_foreign` (`created_by`),
  KEY `journal_entries_owner_user_id_kind_entry_date_index` (`owner_user_id`,`kind`,`entry_date`),
  KEY `journal_entries_couple_space_id_kind_entry_date_index` (`couple_space_id`,`kind`,`entry_date`),
  CONSTRAINT `journal_entries_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `journal_entries_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `journal_entries_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `journal_entries_scope_check` CHECK (`scope` = 'personal' and `owner_user_id` is not null and `couple_space_id` is null or `scope` = 'shared' and `couple_space_id` is not null and `owner_user_id` is null),
  CONSTRAINT `journal_letter_check` CHECK (`kind` = 'journal' or `scope` = 'shared')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `list_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scope` enum('personal','shared') NOT NULL,
  `owner_user_id` bigint(20) unsigned DEFAULT NULL,
  `couple_space_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `kind` enum('wishlist','bucket') NOT NULL,
  `title` text NOT NULL,
  `note` text DEFAULT NULL,
  `price` int(10) unsigned DEFAULT NULL,
  `done_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `list_items_created_by_foreign` (`created_by`),
  KEY `list_items_owner_user_id_kind_done_at_index` (`owner_user_id`,`kind`,`done_at`),
  KEY `list_items_couple_space_id_kind_done_at_index` (`couple_space_id`,`kind`,`done_at`),
  CONSTRAINT `list_items_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `list_items_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `list_items_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `list_items_scope_check` CHECK (`scope` = 'personal' and `owner_user_id` is not null and `couple_space_id` is null or `scope` = 'shared' and `couple_space_id` is not null and `owner_user_id` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `location_shares` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `lat` decimal(8,5) DEFAULT NULL,
  `lng` decimal(8,5) DEFAULT NULL,
  `accuracy` int(10) unsigned DEFAULT NULL,
  `sealed` text DEFAULT NULL,
  `located_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `location_shares_user_id_unique` (`user_id`),
  KEY `location_shares_couple_space_id_foreign` (`couple_space_id`),
  CONSTRAINT `location_shares_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `location_shares_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `love_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `sender_id` bigint(20) unsigned DEFAULT NULL,
  `body` text NOT NULL,
  `deliver_at` datetime NOT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `love_notes_couple_space_id_foreign` (`couple_space_id`),
  KEY `love_notes_sender_id_foreign` (`sender_id`),
  KEY `love_notes_delivered_at_deliver_at_index` (`delivered_at`,`deliver_at`),
  CONSTRAINT `love_notes_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `love_notes_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `milestones` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `title` text NOT NULL,
  `event_date` date NOT NULL,
  `repeat_yearly` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `milestones_created_by_foreign` (`created_by`),
  KEY `milestones_couple_space_id_event_date_index` (`couple_space_id`,`event_date`),
  CONSTRAINT `milestones_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `milestones_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `moods` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `mood_date` date NOT NULL,
  `level` tinyint(3) unsigned NOT NULL,
  `note` text DEFAULT NULL,
  `share` enum('full','level','private') NOT NULL DEFAULT 'full',
  `wants_company` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `moods_user_id_mood_date_unique` (`user_id`,`mood_date`),
  CONSTRAINT `moods_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `moods_level_check` CHECK (`level` between 1 and 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notification_preferences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `type` enum('chat_message','event_reminder','task_reminder','partner_activity','couple_update') NOT NULL,
  `in_app` tinyint(1) NOT NULL DEFAULT 1,
  `push` tinyint(1) NOT NULL DEFAULT 0,
  `show_preview` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notification_preferences_user_id_type_unique` (`user_id`,`type`),
  CONSTRAINT `notification_preferences_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp(3) NULL DEFAULT NULL,
  `updated_at` timestamp(3) NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`),
  KEY `notifications_notifiable_created_index` (`notifiable_type`,`notifiable_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `nudges` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `sender_id` bigint(20) unsigned NOT NULL,
  `kind` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `nudges_sender_id_foreign` (`sender_id`),
  KEY `nudges_couple_space_id_created_at_index` (`couple_space_id`,`created_at`),
  CONSTRAINT `nudges_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nudges_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `photobooth_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `mode` varchar(10) NOT NULL DEFAULT 'turns',
  `layout` varchar(10) NOT NULL DEFAULT 'strip',
  `frame` varchar(10) NOT NULL DEFAULT 'blush',
  `theme` varchar(24) NOT NULL DEFAULT 'pink-romance',
  `photo_id` bigint(20) unsigned DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `saved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `photobooth_sessions_created_by_foreign` (`created_by`),
  KEY `photobooth_sessions_photo_id_foreign` (`photo_id`),
  KEY `photobooth_sessions_couple_space_id_saved_at_index` (`couple_space_id`,`saved_at`),
  CONSTRAINT `photobooth_sessions_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `photobooth_sessions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `photobooth_sessions_photo_id_foreign` FOREIGN KEY (`photo_id`) REFERENCES `photos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `photobooth_shots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `photobooth_session_id` bigint(20) unsigned NOT NULL,
  `position` tinyint(3) unsigned NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `photobooth_shots_photobooth_session_id_position_unique` (`photobooth_session_id`,`position`),
  KEY `photobooth_shots_user_id_foreign` (`user_id`),
  CONSTRAINT `photobooth_shots_photobooth_session_id_foreign` FOREIGN KEY (`photobooth_session_id`) REFERENCES `photobooth_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `photobooth_shots_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `photos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `album_id` bigint(20) unsigned NOT NULL,
  `uploaded_by` bigint(20) unsigned DEFAULT NULL,
  `path` varchar(120) NOT NULL,
  `thumb_path` varchar(120) NOT NULL,
  `width` smallint(5) unsigned NOT NULL,
  `height` smallint(5) unsigned NOT NULL,
  `caption` text DEFAULT NULL,
  `location` text DEFAULT NULL,
  `taken_on` date DEFAULT NULL,
  `is_favorite` tinyint(1) NOT NULL DEFAULT 0,
  `event_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `photos_uploaded_by_foreign` (`uploaded_by`),
  KEY `photos_album_id_id_index` (`album_id`,`id`),
  KEY `photos_event_id_foreign` (`event_id`),
  CONSTRAINT `photos_album_id_foreign` FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE CASCADE,
  CONSTRAINT `photos_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE SET NULL,
  CONSTRAINT `photos_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `playlist_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `added_by` bigint(20) unsigned DEFAULT NULL,
  `title` text NOT NULL,
  `artist` text DEFAULT NULL,
  `url` text DEFAULT NULL,
  `note` text DEFAULT NULL,
  `is_ours` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `playlist_items_added_by_foreign` (`added_by`),
  KEY `playlist_items_couple_space_id_id_index` (`couple_space_id`,`id`),
  CONSTRAINT `playlist_items_added_by_foreign` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `playlist_items_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poll_options` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `poll_id` bigint(20) unsigned NOT NULL,
  `label` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `poll_options_poll_id_foreign` (`poll_id`),
  CONSTRAINT `poll_options_poll_id_foreign` FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `poll_votes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `poll_id` bigint(20) unsigned NOT NULL,
  `poll_option_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `poll_votes_poll_id_user_id_unique` (`poll_id`,`user_id`),
  KEY `poll_votes_poll_option_id_foreign` (`poll_option_id`),
  KEY `poll_votes_user_id_foreign` (`user_id`),
  CONSTRAINT `poll_votes_poll_id_foreign` FOREIGN KEY (`poll_id`) REFERENCES `polls` (`id`) ON DELETE CASCADE,
  CONSTRAINT `poll_votes_poll_option_id_foreign` FOREIGN KEY (`poll_option_id`) REFERENCES `poll_options` (`id`) ON DELETE CASCADE,
  CONSTRAINT `poll_votes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `polls` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `question` text NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `polls_created_by_foreign` (`created_by`),
  KEY `polls_couple_space_id_created_at_index` (`couple_space_id`,`created_at`),
  CONSTRAINT `polls_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `polls_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `push_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `endpoint` text NOT NULL,
  `endpoint_hash` char(64) NOT NULL,
  `public_key` varchar(255) NOT NULL,
  `auth_token` varchar(255) NOT NULL,
  `content_encoding` varchar(16) NOT NULL DEFAULT 'aes128gcm',
  `device_label` varchar(100) DEFAULT NULL,
  `last_success_at` datetime DEFAULT NULL,
  `failure_count` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `expired_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_subscriptions_endpoint_hash_unique` (`endpoint_hash`),
  KEY `push_subscriptions_user_id_expired_at_index` (`user_id`,`expired_at`),
  CONSTRAINT `push_subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_answers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `question_index` smallint(5) unsigned NOT NULL,
  `self_choice` tinyint(3) unsigned NOT NULL,
  `guess_choice` tinyint(3) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `quiz_answers_couple_space_id_user_id_question_index_unique` (`couple_space_id`,`user_id`,`question_index`),
  KEY `quiz_answers_user_id_foreign` (`user_id`),
  CONSTRAINT `quiz_answers_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quiz_answers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reflections` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `month_start` date NOT NULL,
  `went_well` text DEFAULT NULL,
  `to_improve` text DEFAULT NULL,
  `next_month` text DEFAULT NULL,
  `shared` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reflections_couple_space_id_user_id_month_start_unique` (`couple_space_id`,`user_id`,`month_start`),
  KEY `reflections_user_id_foreign` (`user_id`),
  CONSTRAINT `reflections_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reflections_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reminders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `event_id` bigint(20) unsigned DEFAULT NULL,
  `task_id` bigint(20) unsigned DEFAULT NULL,
  `remind_at` datetime NOT NULL,
  `status` enum('pending','processing','sent','failed','skipped','cancelled') NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `claimed_at` datetime DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `reminders_user_id_event_id_remind_at_unique` (`user_id`,`event_id`,`remind_at`),
  UNIQUE KEY `reminders_user_id_task_id_remind_at_unique` (`user_id`,`task_id`,`remind_at`),
  KEY `reminders_event_id_foreign` (`event_id`),
  KEY `reminders_task_id_foreign` (`task_id`),
  KEY `reminders_status_remind_at_index` (`status`,`remind_at`),
  CONSTRAINT `reminders_event_id_foreign` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reminders_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reminders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reminders_source_check` CHECK (`event_id` is null <> (`task_id` is null))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `rewards` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `title` text NOT NULL,
  `given_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `rewards_created_by_foreign` (`created_by`),
  KEY `rewards_couple_space_id_given_at_index` (`couple_space_id`,`given_at`),
  CONSTRAINT `rewards_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `rewards_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `savings_goals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `title` text NOT NULL,
  `kind` enum('tabungan','liburan','lainnya') NOT NULL DEFAULT 'tabungan',
  `target_amount` bigint(20) unsigned NOT NULL,
  `saved_amount` bigint(20) unsigned NOT NULL DEFAULT 0,
  `deadline` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `savings_goals_created_by_foreign` (`created_by`),
  KEY `savings_goals_couple_space_id_index` (`couple_space_id`),
  CONSTRAINT `savings_goals_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `savings_goals_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `task_subtasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `task_id` bigint(20) unsigned NOT NULL,
  `title` text NOT NULL,
  `done` tinyint(1) NOT NULL DEFAULT 0,
  `position` smallint(5) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `task_subtasks_task_id_position_index` (`task_id`,`position`),
  CONSTRAINT `task_subtasks_task_id_foreign` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scope` enum('personal','shared') NOT NULL,
  `owner_user_id` bigint(20) unsigned DEFAULT NULL,
  `couple_space_id` bigint(20) unsigned DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `title` text NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('todo','in_progress','done') NOT NULL DEFAULT 'todo',
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `category` varchar(30) DEFAULT NULL,
  `repeat` enum('none','daily','weekly','monthly') NOT NULL DEFAULT 'none',
  `assignee_id` bigint(20) unsigned DEFAULT NULL,
  `due_at` datetime DEFAULT NULL,
  `due_has_time` tinyint(1) NOT NULL DEFAULT 0,
  `due_timezone` varchar(64) DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tasks_created_by_foreign` (`created_by`),
  KEY `tasks_completed_by_foreign` (`completed_by`),
  KEY `tasks_owner_user_id_status_due_at_index` (`owner_user_id`,`status`,`due_at`),
  KEY `tasks_couple_space_id_status_due_at_index` (`couple_space_id`,`status`,`due_at`),
  KEY `tasks_assignee_id_foreign` (`assignee_id`),
  CONSTRAINT `tasks_assignee_id_foreign` FOREIGN KEY (`assignee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_completed_by_foreign` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tasks_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_scope_check` CHECK (`scope` = 'personal' and `owner_user_id` is not null and `couple_space_id` is null or `scope` = 'shared' and `couple_space_id` is not null and `owner_user_id` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `time_capsules` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `couple_space_id` bigint(20) unsigned NOT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `title` text NOT NULL,
  `body` text NOT NULL,
  `open_on` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `time_capsules_created_by_foreign` (`created_by`),
  KEY `time_capsules_couple_space_id_open_on_index` (`couple_space_id`,`open_on`),
  CONSTRAINT `time_capsules_couple_space_id_foreign` FOREIGN KEY (`couple_space_id`) REFERENCES `couple_spaces` (`id`) ON DELETE CASCADE,
  CONSTRAINT `time_capsules_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL,
  `nickname` varchar(40) DEFAULT NULL,
  `gender` varchar(16) DEFAULT NULL,
  `profile_completed_at` timestamp NULL DEFAULT NULL,
  `birthday` date DEFAULT NULL,
  `quiet_start` time DEFAULT NULL,
  `quiet_end` time DEFAULT NULL,
  `digest` enum('off','daily','weekly') NOT NULL DEFAULT 'off',
  `digest_sent_on` date DEFAULT NULL,
  `email` varchar(191) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Jakarta',
  `locale` varchar(8) NOT NULL DEFAULT 'id',
  `avatar_path` varchar(255) DEFAULT NULL,
  `background_preset` varchar(20) DEFAULT NULL,
  `background_path` varchar(255) DEFAULT NULL,
  `background_veil` tinyint(4) DEFAULT NULL,
  `dashboard_prefs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`dashboard_prefs`)),
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;


SET FOREIGN_KEY_CHECKS=0;
INSERT INTO `migrations` (`migration`,`batch`) VALUES
('0001_01_01_000000_create_users_table',1),
('0001_01_01_000001_create_cache_table',1),
('0001_01_01_000002_create_jobs_table',1),
('2026_10_25_000100_create_couple_tables',1),
('2026_10_25_000200_create_tasks_and_events_tables',1),
('2026_10_25_000300_create_reminders_table',1),
('2026_10_25_000400_create_chat_tables',1),
('2026_10_25_000500_create_notification_tables',1),
('2026_10_25_000600_add_millisecond_precision_to_notifications',1),
('2026_10_26_000100_create_memory_tables',1),
('2026_10_27_000100_create_couple_fun_tables',1),
('2026_10_27_000200_add_chat_replies_and_reactions',1),
('2026_10_28_000100_add_chat_photos_and_task_assignee',1),
('2026_10_29_000100_add_fun_features',1),
('2026_10_29_000200_add_growth_features',1),
('2026_10_29_000300_add_quiet_hours',1),
('2026_10_29_000400_add_event_repeat_category_rating',1),
('2026_10_29_000500_add_photo_details',1),
('2026_10_29_000600_add_mood_sharing',1),
('2026_10_29_000700_add_reflections_and_agreements',1),
('2026_10_29_000800_add_digest_setting',1),
('2026_10_29_000900_add_rewards_rsvp_theme_price',1),
('2026_10_30_000100_add_chat_voice_notes',1),
('2026_10_30_000200_add_ai_quiz_questions',1),
('2026_10_31_000100_add_location_shares',1),
('2026_11_01_000100_add_photobooth',1),
('2026_11_02_000100_add_photobooth_theme',1),
('2026_11_03_000100_add_end_to_end_encryption',1),
('2026_11_04_000100_add_profile_setup',1);
SET FOREIGN_KEY_CHECKS=1;
