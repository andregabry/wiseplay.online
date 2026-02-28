SET NAMES utf8mb4;

SET @db_name = DATABASE();

SET @col_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'users' AND COLUMN_NAME = 'preferences_completed_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE users ADD COLUMN preferences_completed_at DATETIME NULL AFTER onboarding_completed_at',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS profile_preferences (
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

CREATE TABLE IF NOT EXISTS media_materials (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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
  CONSTRAINT fk_media_materials_media FOREIGN KEY (media_item_id) REFERENCES media_items(id) ON DELETE CASCADE,
  INDEX idx_media_materials_media (media_item_id),
  INDEX idx_media_materials_sort (media_item_id, sort_order),
  INDEX idx_media_materials_downloadable (is_downloadable)
) ENGINE=InnoDB;

SET @col_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'watch_history' AND COLUMN_NAME = 'duration_seconds'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE watch_history ADD COLUMN duration_seconds INT UNSIGNED NULL AFTER progress_percent',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'watch_history' AND COLUMN_NAME = 'completed'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE watch_history ADD COLUMN completed TINYINT(1) NOT NULL DEFAULT 0 AFTER last_position_seconds',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'watch_history' AND COLUMN_NAME = 'completed_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE watch_history ADD COLUMN completed_at DATETIME NULL AFTER completed',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'watch_history' AND INDEX_NAME = 'idx_watch_completed'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE watch_history ADD INDEX idx_watch_completed (profile_id, completed)',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO settings (category, key_name, value_text, is_secret, created_at, updated_at)
SELECT 'onboarding', 'onboarding.preferences_allow_skip', '1', 0, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = 'onboarding.preferences_allow_skip');

INSERT INTO settings (category, key_name, value_text, is_secret, created_at, updated_at)
SELECT 'playback', 'playback.autosave_interval_seconds', '15', 0, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = 'playback.autosave_interval_seconds');

INSERT INTO settings (category, key_name, value_text, is_secret, created_at, updated_at)
SELECT 'playback', 'playback.completion_threshold_percent', '92', 0, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = 'playback.completion_threshold_percent');

INSERT INTO settings (category, key_name, value_text, is_secret, created_at, updated_at)
SELECT 'playback', 'playback.show_next_countdown_seconds', '8', 0, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = 'playback.show_next_countdown_seconds');
