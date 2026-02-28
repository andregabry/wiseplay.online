-- WisePlay Phase 3: onboarding + plan gating + payment abstraction (idempotent)
SET @db := DATABASE();

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='billing_plans' AND COLUMN_NAME='is_selectable')=0,
  'ALTER TABLE billing_plans ADD COLUMN is_selectable TINYINT(1) NOT NULL DEFAULT 1 AFTER active',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='billing_plans' AND COLUMN_NAME='requires_payment_method')=0,
  'ALTER TABLE billing_plans ADD COLUMN requires_payment_method TINYINT(1) NOT NULL DEFAULT 0 AFTER is_selectable',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='billing_plans' AND COLUMN_NAME='sort_order')=0,
  'ALTER TABLE billing_plans ADD COLUMN sort_order INT NOT NULL DEFAULT 100 AFTER requires_payment_method',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='billing_plans' AND INDEX_NAME='idx_plans_selectable')=0,
  'ALTER TABLE billing_plans ADD INDEX idx_plans_selectable (is_selectable)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='billing_plans' AND INDEX_NAME='idx_plans_sort')=0,
  'ALTER TABLE billing_plans ADD INDEX idx_plans_sort (sort_order)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE billing_plans
   SET requires_payment_method = 0
 WHERE slug = 'free';

UPDATE billing_plans
   SET requires_payment_method = 1
 WHERE slug IN ('standard', 'premium');

UPDATE billing_plans
   SET sort_order = CASE
       WHEN slug = 'free' THEN 10
       WHEN slug = 'standard' THEN 20
       WHEN slug = 'premium' THEN 30
       ELSE sort_order
   END;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='subscriptions' AND COLUMN_NAME='payment_required')=0,
  'ALTER TABLE subscriptions ADD COLUMN payment_required TINYINT(1) NOT NULL DEFAULT 0 AFTER auto_renew',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='subscriptions' AND COLUMN_NAME='payment_status')=0,
  'ALTER TABLE subscriptions ADD COLUMN payment_status ENUM(''NOT_REQUIRED'',''PENDING'',''PAID'',''FAILED'') NOT NULL DEFAULT ''NOT_REQUIRED'' AFTER payment_required',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='subscriptions' AND COLUMN_NAME='payment_provider')=0,
  'ALTER TABLE subscriptions ADD COLUMN payment_provider VARCHAR(80) NULL AFTER payment_status',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='subscriptions' AND COLUMN_NAME='payment_reference')=0,
  'ALTER TABLE subscriptions ADD COLUMN payment_reference VARCHAR(190) NULL AFTER payment_provider',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE subscriptions
  MODIFY COLUMN status ENUM('PENDING','TRIALING','ACTIVE','PAUSED','CANCELED','EXPIRED','PAYMENT_FAILED') NOT NULL DEFAULT 'ACTIVE',
  MODIFY COLUMN payment_status ENUM('NOT_REQUIRED','PENDING','PAID','FAILED') NOT NULL DEFAULT 'NOT_REQUIRED';

SET @sql := IF((SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='subscriptions' AND INDEX_NAME='idx_subscriptions_payment_status')=0,
  'ALTER TABLE subscriptions ADD INDEX idx_subscriptions_payment_status (payment_status)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE subscriptions s
INNER JOIN billing_plans bp ON bp.id = s.plan_id
   SET s.payment_required = CASE WHEN bp.requires_payment_method = 1 THEN 1 ELSE 0 END,
       s.payment_status = CASE
           WHEN bp.requires_payment_method = 0 THEN 'NOT_REQUIRED'
           WHEN s.status IN ('ACTIVE', 'TRIALING') THEN 'PAID'
           WHEN s.status = 'PAYMENT_FAILED' THEN 'FAILED'
           ELSE 'PENDING'
       END
 WHERE s.id > 0;

CREATE TABLE IF NOT EXISTS payment_methods (
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

CREATE TABLE IF NOT EXISTS payment_transactions (
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

CREATE TABLE IF NOT EXISTS payment_events (
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

INSERT INTO settings (category, key_name, value_text, is_secret, created_at, updated_at)
VALUES
('payment', 'payment.gateway_enabled', '0', 0, NOW(), NOW()),
('payment', 'payment.provider', 'mock', 0, NOW(), NOW()),
('payment', 'payment.mode', 'sandbox', 0, NOW(), NOW()),
('payment', 'payment.mock_auto_approve', '1', 0, NOW(), NOW()),
('payment', 'payment.public_paid_visible', '1', 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  value_text = VALUES(value_text),
  is_secret = VALUES(is_secret),
  updated_at = VALUES(updated_at);
