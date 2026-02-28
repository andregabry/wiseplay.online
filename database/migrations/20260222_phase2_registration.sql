-- WisePlay Phase 2: registration + email verification (idempotent)
SET @db := DATABASE();

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='email_verified_at')=0,
  'ALTER TABLE users ADD COLUMN email_verified_at DATETIME NULL AFTER status',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='password_set_at')=0,
  'ALTER TABLE users ADD COLUMN password_set_at DATETIME NULL AFTER email_verified_at',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='users' AND COLUMN_NAME='onboarding_completed_at')=0,
  'ALTER TABLE users ADD COLUMN onboarding_completed_at DATETIME NULL AFTER password_set_at',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS email_verification_codes (
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
  INDEX idx_verification_email (email),
  INDEX idx_verification_active (email, consumed_at, expires_at),
  INDEX idx_verification_created (created_at),
  CONSTRAINT fk_verification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

UPDATE users
   SET email_verified_at = COALESCE(email_verified_at, created_at, NOW()),
       password_set_at = COALESCE(password_set_at, created_at, NOW())
 WHERE password_hash IS NOT NULL
   AND password_hash <> '';

INSERT INTO settings (category, key_name, value_text, is_secret, created_at, updated_at)
VALUES
('auth', 'auth.verification_code_ttl_minutes', '10', 0, NOW(), NOW()),
('auth', 'auth.verification_code_max_attempts', '5', 0, NOW(), NOW()),
('auth', 'auth.verification_resend_cooldown_seconds', '60', 0, NOW(), NOW()),
('auth', 'auth.password_min_length', '8', 0, NOW(), NOW()),
('auth', 'auth.verification_session_ttl_minutes', '30', 0, NOW(), NOW()),
('smtp', 'smtp.enabled', '1', 0, NOW(), NOW()),
('smtp', 'smtp.host', 'mailpit', 0, NOW(), NOW()),
('smtp', 'smtp.port', '1025', 0, NOW(), NOW()),
('smtp', 'smtp.username', '', 0, NOW(), NOW()),
('smtp', 'smtp.password', '', 1, NOW(), NOW()),
('smtp', 'smtp.encryption', 'none', 0, NOW(), NOW()),
('smtp', 'smtp.from_email', 'no-reply@wiseplay.local', 0, NOW(), NOW()),
('smtp', 'smtp.from_name', 'WisePlay', 0, NOW(), NOW()),
('email', 'email.verification_subject', 'WisePlay - Codigo de verificacao', 0, NOW(), NOW()),
('email', 'email.verification_template', 'Ola {{name}},\\n\\nSeu codigo de verificacao WisePlay: {{code}}\\nEste codigo expira em {{expires_minutes}} minutos.\\n\\nSe voce nao solicitou este cadastro, ignore este email.', 0, NOW(), NOW()),
('email', 'email.dev_log_delivery', '0', 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  value_text = VALUES(value_text),
  is_secret = VALUES(is_secret),
  updated_at = VALUES(updated_at);
