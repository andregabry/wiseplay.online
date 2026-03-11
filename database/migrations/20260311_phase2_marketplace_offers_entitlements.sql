ALTER TABLE titles
  ADD INDEX idx_titles_producer_marketplace (producer_id, status, type);

CREATE TABLE IF NOT EXISTS offers (
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

CREATE TABLE IF NOT EXISTS purchases (
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

CREATE TABLE IF NOT EXISTS entitlements (
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
