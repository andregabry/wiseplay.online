SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS public_notification_reads;
DROP TABLE IF EXISTS entitlements;
DROP TABLE IF EXISTS purchases;
DROP TABLE IF EXISTS offers;
DROP TABLE IF EXISTS public_notifications;
DROP TABLE IF EXISTS watch_history;
DROP TABLE IF EXISTS media_materials;
DROP TABLE IF EXISTS profile_preferences;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS payment_events;
DROP TABLE IF EXISTS payment_transactions;
DROP TABLE IF EXISTS payment_methods;
DROP TABLE IF EXISTS integration_credentials;
DROP TABLE IF EXISTS email_verification_codes;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS setting_profiles;
DROP TABLE IF EXISTS subscriptions;
DROP TABLE IF EXISTS billing_plans;
DROP TABLE IF EXISTS media_items;
DROP TABLE IF EXISTS titles;
DROP TABLE IF EXISTS producer_members;
DROP TABLE IF EXISTS producers;
DROP TABLE IF EXISTS profiles;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  account_avatar VARCHAR(500) NULL,
  role ENUM('SUPER_ADMIN','ADMIN','EDITOR','SUPPORT','BILLING_MANAGER','PRODUCER','CUSTOMER') NOT NULL DEFAULT 'CUSTOMER',
  status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
  email_verified_at DATETIME NULL,
  password_set_at DATETIME NULL,
  onboarding_completed_at DATETIME NULL,
  preferences_completed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  INDEX idx_users_role (role),
  INDEX idx_users_status (status),
  INDEX idx_users_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE email_verification_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  email VARCHAR(190) NOT NULL,
  code_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts_count INT UNSIGNED NOT NULL DEFAULT 0,
  max_attempts INT UNSIGNED NOT NULL DEFAULT 5,
  resend_count INT UNSIGNED NOT NULL DEFAULT 0,
  consumed_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_verification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_verification_email (email),
  INDEX idx_verification_active (email, consumed_at, expires_at),
  INDEX idx_verification_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE producers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_user_id BIGINT UNSIGNED NOT NULL,
  brand_name VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  status ENUM('PENDING','ACTIVE','DISABLED') NOT NULL DEFAULT 'PENDING',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_producers_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uk_producers_owner (owner_user_id),
  INDEX idx_producers_status (status),
  INDEX idx_producers_brand (brand_name)
) ENGINE=InnoDB;

CREATE TABLE producer_members (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  producer_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  member_role ENUM('OWNER','ADMIN','EDITOR','ANALYST') NOT NULL DEFAULT 'EDITOR',
  status ENUM('ACTIVE','DISABLED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_producer_members_producer FOREIGN KEY (producer_id) REFERENCES producers(id) ON DELETE CASCADE,
  CONSTRAINT fk_producer_members_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uk_producer_member (producer_id, user_id),
  INDEX idx_producer_members_user (user_id),
  INDEX idx_producer_members_status (status)
) ENGINE=InnoDB;

CREATE TABLE profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  profile_name VARCHAR(120) NOT NULL,
  avatar_path VARCHAR(500) NULL,
  is_kids TINYINT(1) NOT NULL DEFAULT 0,
  maturity_rating VARCHAR(20) NOT NULL DEFAULT '16',
  language VARCHAR(20) NOT NULL DEFAULT 'pt-BR',
  autoplay TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_profiles_user (user_id),
  INDEX idx_profiles_kids (is_kids)
) ENGINE=InnoDB;

CREATE TABLE profile_preferences (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  profile_id BIGINT UNSIGNED NOT NULL,
  preference_type ENUM('GENRE','TYPE','TOPIC') NOT NULL DEFAULT 'GENRE',
  preference_key VARCHAR(120) NOT NULL,
  weight SMALLINT UNSIGNED NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_profile_preferences_profile FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE,
  UNIQUE KEY uk_profile_preference (profile_id, preference_type, preference_key),
  INDEX idx_profile_preferences_profile (profile_id),
  INDEX idx_profile_preferences_type (preference_type),
  INDEX idx_profile_preferences_key (preference_key)
) ENGINE=InnoDB;

CREATE TABLE titles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  producer_id BIGINT UNSIGNED NULL,
  title_name VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  type ENUM('MOVIE','SERIES','COURSE') NOT NULL,
  description TEXT NULL,
  genre VARCHAR(120) NOT NULL DEFAULT 'General',
  year SMALLINT UNSIGNED NULL,
  maturity_rating VARCHAR(20) NOT NULL DEFAULT '16',
  featured TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('DRAFT','PUBLISHED','ARCHIVED') NOT NULL DEFAULT 'DRAFT',
  cover_image VARCHAR(500) NULL,
  backdrop_image VARCHAR(500) NULL,
  created_by_user_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_titles_producer FOREIGN KEY (producer_id) REFERENCES producers(id) ON DELETE SET NULL,
  CONSTRAINT fk_titles_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_titles_producer (producer_id),
  INDEX idx_titles_type (type),
  INDEX idx_titles_status (status),
  INDEX idx_titles_featured (featured),
  INDEX idx_titles_genre (genre)
) ENGINE=InnoDB;

CREATE TABLE media_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  producer_id BIGINT UNSIGNED NULL,
  title_id BIGINT UNSIGNED NOT NULL,
  season_number INT UNSIGNED NULL,
  episode_number INT UNSIGNED NULL,
  item_label VARCHAR(255) NOT NULL,
  duration_minutes INT UNSIGNED NULL,
  source_type ENUM('ONEDRIVE','LOCAL','URL') NOT NULL DEFAULT 'ONEDRIVE',
  source_path VARCHAR(500) NULL,
  external_id VARCHAR(255) NULL,
  stream_ref VARCHAR(255) NULL,
  stream_url LONGTEXT NULL,
  stream_url_expires_at DATETIME NULL,
  download_url LONGTEXT NULL,
  drive_id VARCHAR(255) NULL,
  parent_item_id VARCHAR(255) NULL,
  mime_type VARCHAR(120) NULL,
  file_size_bytes BIGINT UNSIGNED NULL,
  upload_status ENUM('PENDING','UPLOADING','READY','FAILED') NOT NULL DEFAULT 'READY',
  thumbnail_url LONGTEXT NULL,
  poster_url LONGTEXT NULL,
  backdrop_url LONGTEXT NULL,
  subtitles_json LONGTEXT NULL,
  last_source_sync_at DATETIME NULL,
  subtitles VARCHAR(500) NULL,
  audio_lang VARCHAR(60) NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_media_producer FOREIGN KEY (producer_id) REFERENCES producers(id) ON DELETE SET NULL,
  CONSTRAINT fk_media_title FOREIGN KEY (title_id) REFERENCES titles(id) ON DELETE CASCADE,
  UNIQUE KEY uk_media_sequence (title_id, season_number, episode_number, item_label),
  INDEX idx_media_producer (producer_id),
  INDEX idx_media_title (title_id),
  INDEX idx_media_published (is_published),
  INDEX idx_media_sequence_lookup (season_number, episode_number),
  INDEX idx_media_external (external_id),
  INDEX idx_media_source_type (source_type),
  INDEX idx_media_upload_status (upload_status)
) ENGINE=InnoDB;

CREATE TABLE media_materials (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  producer_id BIGINT UNSIGNED NULL,
  media_item_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(190) NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  source_type ENUM('ONEDRIVE','URL','LOCAL') NOT NULL DEFAULT 'ONEDRIVE',
  one_drive_item_id VARCHAR(255) NULL,
  one_drive_path VARCHAR(500) NULL,
  download_url LONGTEXT NULL,
  mime_type VARCHAR(120) NULL,
  file_size_bytes BIGINT UNSIGNED NULL,
  sort_order INT NOT NULL DEFAULT 100,
  is_downloadable TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_media_materials_producer FOREIGN KEY (producer_id) REFERENCES producers(id) ON DELETE SET NULL,
  CONSTRAINT fk_media_materials_media FOREIGN KEY (media_item_id) REFERENCES media_items(id) ON DELETE CASCADE,
  INDEX idx_media_materials_producer (producer_id),
  INDEX idx_media_materials_media (media_item_id),
  INDEX idx_media_materials_sort (media_item_id, sort_order),
  INDEX idx_media_materials_downloadable (is_downloadable)
) ENGINE=InnoDB;

CREATE TABLE offers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  producer_id BIGINT UNSIGNED NOT NULL,
  title_id BIGINT UNSIGNED NOT NULL,
  offer_type ENUM('ONE_TIME','SUBSCRIPTION') NOT NULL DEFAULT 'ONE_TIME',
  billing_interval ENUM('MONTH','YEAR') NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'BRL',
  price DECIMAL(10,2) NOT NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  is_selectable TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_offers_producer FOREIGN KEY (producer_id) REFERENCES producers(id) ON DELETE CASCADE,
  CONSTRAINT fk_offers_title FOREIGN KEY (title_id) REFERENCES titles(id) ON DELETE CASCADE,
  INDEX idx_offers_producer (producer_id),
  INDEX idx_offers_title (title_id),
  INDEX idx_offers_status (status),
  INDEX idx_offers_type (offer_type),
  INDEX idx_offers_selectable (is_selectable)
) ENGINE=InnoDB;

CREATE TABLE purchases (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  producer_id BIGINT UNSIGNED NOT NULL,
  title_id BIGINT UNSIGNED NOT NULL,
  offer_id BIGINT UNSIGNED NOT NULL,
  status ENUM('PENDING','PAID','FAILED','REFUNDED','CANCELED') NOT NULL DEFAULT 'PENDING',
  provider VARCHAR(80) NOT NULL DEFAULT 'infinitepay',
  provider_reference VARCHAR(190) NULL,
  created_at DATETIME NOT NULL,
  paid_at DATETIME NULL,
  CONSTRAINT fk_purchases_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_purchases_producer FOREIGN KEY (producer_id) REFERENCES producers(id) ON DELETE CASCADE,
  CONSTRAINT fk_purchases_title FOREIGN KEY (title_id) REFERENCES titles(id) ON DELETE CASCADE,
  CONSTRAINT fk_purchases_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
  UNIQUE KEY uk_purchases_provider_ref (provider, provider_reference),
  INDEX idx_purchases_user (user_id),
  INDEX idx_purchases_producer (producer_id),
  INDEX idx_purchases_title (title_id),
  INDEX idx_purchases_status (status)
) ENGINE=InnoDB;

CREATE TABLE entitlements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  producer_id BIGINT UNSIGNED NOT NULL,
  title_id BIGINT UNSIGNED NOT NULL,
  source ENUM('ONE_TIME','SUBSCRIPTION') NOT NULL DEFAULT 'ONE_TIME',
  start_at DATETIME NOT NULL,
  end_at DATETIME NULL,
  status ENUM('ACTIVE','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_entitlements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_entitlements_producer FOREIGN KEY (producer_id) REFERENCES producers(id) ON DELETE CASCADE,
  CONSTRAINT fk_entitlements_title FOREIGN KEY (title_id) REFERENCES titles(id) ON DELETE CASCADE,
  INDEX idx_entitlements_user (user_id),
  INDEX idx_entitlements_title (title_id),
  INDEX idx_entitlements_status (status),
  INDEX idx_entitlements_window (end_at)
) ENGINE=InnoDB;

CREATE TABLE billing_plans (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  plan_name VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  price DECIMAL(10,2) NOT NULL,
  currency VARCHAR(10) NOT NULL DEFAULT 'BRL',
  billing_cycle ENUM('MONTHLY','YEARLY') NOT NULL DEFAULT 'MONTHLY',
  profile_limit INT UNSIGNED NOT NULL DEFAULT 1,
  max_streams INT UNSIGNED NOT NULL DEFAULT 1,
  quality_level VARCHAR(40) NOT NULL DEFAULT '1080p',
  active TINYINT(1) NOT NULL DEFAULT 1,
  is_selectable TINYINT(1) NOT NULL DEFAULT 1,
  requires_payment_method TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_plans_active (active),
  INDEX idx_plans_selectable (is_selectable),
  INDEX idx_plans_sort (sort_order)
) ENGINE=InnoDB;

CREATE TABLE subscriptions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  plan_id BIGINT UNSIGNED NOT NULL,
  status ENUM('PENDING','TRIALING','ACTIVE','PAUSED','CANCELED','EXPIRED','PAYMENT_FAILED') NOT NULL DEFAULT 'ACTIVE',
  start_at DATETIME NOT NULL,
  end_at DATETIME NULL,
  auto_renew TINYINT(1) NOT NULL DEFAULT 1,
  payment_required TINYINT(1) NOT NULL DEFAULT 0,
  payment_status ENUM('NOT_REQUIRED','PENDING','PAID','FAILED') NOT NULL DEFAULT 'NOT_REQUIRED',
  payment_provider VARCHAR(80) NULL,
  payment_reference VARCHAR(190) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES billing_plans(id) ON DELETE RESTRICT,
  UNIQUE KEY uk_subscriptions_user (user_id),
  INDEX idx_subscriptions_plan (plan_id),
  INDEX idx_subscriptions_status (status),
  INDEX idx_subscriptions_payment_status (payment_status)
) ENGINE=InnoDB;

CREATE TABLE payment_methods (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(80) NOT NULL,
  token_ref VARCHAR(255) NOT NULL,
  brand VARCHAR(40) NULL,
  last4 CHAR(4) NULL,
  exp_month TINYINT UNSIGNED NULL,
  exp_year SMALLINT UNSIGNED NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_payment_method_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_payment_method_user (user_id),
  INDEX idx_payment_method_provider (provider)
) ENGINE=InnoDB;

CREATE TABLE payment_transactions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  subscription_id BIGINT UNSIGNED NULL,
  plan_id BIGINT UNSIGNED NULL,
  provider VARCHAR(80) NOT NULL,
  provider_txn_id VARCHAR(190) NULL,
  status ENUM('PENDING','AUTHORIZED','PAID','FAILED','CANCELED') NOT NULL DEFAULT 'PENDING',
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  currency VARCHAR(10) NOT NULL DEFAULT 'BRL',
  metadata_json LONGTEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_payment_txn_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_txn_subscription FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL,
  CONSTRAINT fk_payment_txn_plan FOREIGN KEY (plan_id) REFERENCES billing_plans(id) ON DELETE SET NULL,
  INDEX idx_payment_txn_user (user_id),
  INDEX idx_payment_txn_status (status),
  INDEX idx_payment_txn_provider (provider)
) ENGINE=InnoDB;

CREATE TABLE payment_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaction_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(120) NOT NULL,
  provider VARCHAR(80) NOT NULL,
  payload_json LONGTEXT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_payment_event_transaction FOREIGN KEY (transaction_id) REFERENCES payment_transactions(id) ON DELETE SET NULL,
  INDEX idx_payment_events_type (event_type),
  INDEX idx_payment_events_provider (provider),
  INDEX idx_payment_events_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE public_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entity_type ENUM('TITLE','COURSE','SEASON','EPISODE') NOT NULL DEFAULT 'TITLE',
  entity_id BIGINT UNSIGNED NULL,
  title_text VARCHAR(190) NOT NULL,
  message_text VARCHAR(255) NOT NULL,
  link_url VARCHAR(500) NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_public_notifications_entity (entity_type, entity_id),
  INDEX idx_public_notifications_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE public_notification_reads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notification_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  read_at DATETIME NOT NULL,
  UNIQUE KEY uk_public_notification_reads (notification_id, user_id),
  INDEX idx_public_notification_reads_user (user_id),
  CONSTRAINT fk_public_notification_reads_notification FOREIGN KEY (notification_id) REFERENCES public_notifications(id) ON DELETE CASCADE,
  CONSTRAINT fk_public_notification_reads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(60) NOT NULL,
  key_name VARCHAR(190) NOT NULL UNIQUE,
  value_text LONGTEXT NULL,
  is_secret TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_settings_category (category)
) ENGINE=InnoDB;

CREATE TABLE setting_profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category VARCHAR(60) NOT NULL,
  config_name VARCHAR(120) NULL,
  description VARCHAR(255) NOT NULL,
  payload_json LONGTEXT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  INDEX idx_setting_profiles_category (category),
  INDEX idx_setting_profiles_default (category, is_default),
  INDEX idx_setting_profiles_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE integration_credentials (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider ENUM('ONEDRIVE','STRIPE','EMAIL') NOT NULL,
  key_name VARCHAR(120) NOT NULL,
  encrypted_value LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uk_integration_provider_key (provider, key_name)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(180) NOT NULL,
  entity_type VARCHAR(120) NOT NULL,
  entity_id VARCHAR(120) NULL,
  diff_summary LONGTEXT NULL,
  ip VARCHAR(80) NULL,
  user_agent VARCHAR(255) NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_action (action),
  INDEX idx_audit_entity (entity_type, entity_id),
  INDEX idx_audit_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE watch_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  profile_id BIGINT UNSIGNED NOT NULL,
  title_id BIGINT UNSIGNED NOT NULL,
  media_item_id BIGINT UNSIGNED NOT NULL,
  progress_percent INT UNSIGNED NOT NULL DEFAULT 0,
  duration_seconds INT UNSIGNED NULL,
  last_position_seconds INT UNSIGNED NULL,
  completed TINYINT(1) NOT NULL DEFAULT 0,
  completed_at DATETIME NULL,
  last_watched_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  CONSTRAINT fk_watch_profile FOREIGN KEY (profile_id) REFERENCES profiles(id) ON DELETE CASCADE,
  CONSTRAINT fk_watch_title FOREIGN KEY (title_id) REFERENCES titles(id) ON DELETE CASCADE,
  CONSTRAINT fk_watch_media FOREIGN KEY (media_item_id) REFERENCES media_items(id) ON DELETE CASCADE,
  UNIQUE KEY uk_watch_progress (profile_id, media_item_id),
  INDEX idx_watch_recent (profile_id, last_watched_at),
  INDEX idx_watch_completed (profile_id, completed)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
