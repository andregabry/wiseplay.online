-- WisePlay OneDrive media expansion patch (idempotent)
SET @db := DATABASE();

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_items' AND COLUMN_NAME='stream_ref')=0,
  'ALTER TABLE media_items ADD COLUMN stream_ref VARCHAR(255) NULL AFTER external_id',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_items' AND COLUMN_NAME='stream_url')=0,
  'ALTER TABLE media_items ADD COLUMN stream_url LONGTEXT NULL AFTER stream_ref',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_items' AND COLUMN_NAME='stream_url_expires_at')=0,
  'ALTER TABLE media_items ADD COLUMN stream_url_expires_at DATETIME NULL AFTER stream_url',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_items' AND COLUMN_NAME='download_url')=0,
  'ALTER TABLE media_items ADD COLUMN download_url LONGTEXT NULL AFTER stream_url_expires_at',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_items' AND COLUMN_NAME='drive_id')=0,
  'ALTER TABLE media_items ADD COLUMN drive_id VARCHAR(255) NULL AFTER download_url',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_items' AND COLUMN_NAME='parent_item_id')=0,
  'ALTER TABLE media_items ADD COLUMN parent_item_id VARCHAR(255) NULL AFTER drive_id',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_items' AND COLUMN_NAME='mime_type')=0,
  'ALTER TABLE media_items ADD COLUMN mime_type VARCHAR(120) NULL AFTER parent_item_id',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_items' AND COLUMN_NAME='file_size_bytes')=0,
  'ALTER TABLE media_items ADD COLUMN file_size_bytes BIGINT UNSIGNED NULL AFTER mime_type',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_items' AND COLUMN_NAME='upload_status')=0,
  "ALTER TABLE media_items ADD COLUMN upload_status ENUM('PENDING','UPLOADING','READY','FAILED') NOT NULL DEFAULT 'READY' AFTER file_size_bytes",
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_items' AND COLUMN_NAME='thumbnail_url')=0,
  'ALTER TABLE media_items ADD COLUMN thumbnail_url LONGTEXT NULL AFTER upload_status',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_items' AND COLUMN_NAME='poster_url')=0,
  'ALTER TABLE media_items ADD COLUMN poster_url LONGTEXT NULL AFTER thumbnail_url',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_items' AND COLUMN_NAME='backdrop_url')=0,
  'ALTER TABLE media_items ADD COLUMN backdrop_url LONGTEXT NULL AFTER poster_url',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_items' AND COLUMN_NAME='subtitles_json')=0,
  'ALTER TABLE media_items ADD COLUMN subtitles_json LONGTEXT NULL AFTER subtitles',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_items' AND COLUMN_NAME='last_source_sync_at')=0,
  'ALTER TABLE media_items ADD COLUMN last_source_sync_at DATETIME NULL AFTER backdrop_url',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE media_items
  MODIFY source_type ENUM('ONEDRIVE','LOCAL','URL') NOT NULL DEFAULT 'ONEDRIVE';

UPDATE media_items SET upload_status = 'READY' WHERE upload_status IS NULL;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_items' AND INDEX_NAME='idx_media_external')=0,
  'ALTER TABLE media_items ADD INDEX idx_media_external (external_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_items' AND INDEX_NAME='idx_media_source_type')=0,
  'ALTER TABLE media_items ADD INDEX idx_media_source_type (source_type)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='media_items' AND INDEX_NAME='idx_media_upload_status')=0,
  'ALTER TABLE media_items ADD INDEX idx_media_upload_status (upload_status)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO settings (category, key_name, value_text, is_secret, created_at, updated_at)
VALUES
('onedrive', 'onedrive.root_folder', 'WisePlay', 0, NOW(), NOW()),
('onedrive', 'onedrive.last_upload_at', '', 0, NOW(), NOW()),
('onedrive', 'onedrive.access_token_expires_at', '', 0, NOW(), NOW()),
('onedrive', 'onedrive.quota_cache_json', '', 0, NOW(), NOW()),
('onedrive', 'onedrive.quota_cache_at', '', 0, NOW(), NOW()),
('onedrive', 'onedrive.upload_debug', '0', 0, NOW(), NOW()),
('onedrive', 'onedrive.upload_chunk_mb', '10', 0, NOW(), NOW()),
('onedrive', 'onedrive.upload_max_retries', '3', 0, NOW(), NOW()),
('onedrive', 'onedrive.upload_retry_backoff_ms', '500', 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);
