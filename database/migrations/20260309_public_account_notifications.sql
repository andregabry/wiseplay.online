SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS public_notifications (
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

CREATE TABLE IF NOT EXISTS public_notification_reads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notification_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  read_at DATETIME NOT NULL,
  UNIQUE KEY uk_public_notification_reads (notification_id, user_id),
  INDEX idx_public_notification_reads_user (user_id),
  CONSTRAINT fk_public_notification_reads_notification FOREIGN KEY (notification_id) REFERENCES public_notifications(id) ON DELETE CASCADE,
  CONSTRAINT fk_public_notification_reads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
