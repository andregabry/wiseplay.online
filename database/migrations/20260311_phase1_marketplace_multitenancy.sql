SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE users
  MODIFY role ENUM('SUPER_ADMIN','ADMIN','EDITOR','SUPPORT','BILLING_MANAGER','PRODUCER','CUSTOMER') NOT NULL DEFAULT 'CUSTOMER';

UPDATE users
   SET role = 'CUSTOMER'
 WHERE role = 'VIEWER';

CREATE TABLE IF NOT EXISTS producers (
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

CREATE TABLE IF NOT EXISTS producer_members (
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

ALTER TABLE titles
  ADD COLUMN producer_id BIGINT UNSIGNED NULL AFTER id,
  ADD INDEX idx_titles_producer (producer_id),
  ADD CONSTRAINT fk_titles_producer FOREIGN KEY (producer_id) REFERENCES producers(id) ON DELETE SET NULL;

ALTER TABLE media_items
  ADD COLUMN producer_id BIGINT UNSIGNED NULL AFTER id,
  ADD INDEX idx_media_producer (producer_id),
  ADD CONSTRAINT fk_media_producer FOREIGN KEY (producer_id) REFERENCES producers(id) ON DELETE SET NULL;

SET @media_materials_exists := (
  SELECT COUNT(*)
  FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_name = 'media_materials'
);

SET @media_materials_alter_sql := IF(
  @media_materials_exists = 1,
  'ALTER TABLE media_materials ADD COLUMN producer_id BIGINT UNSIGNED NULL AFTER id, ADD INDEX idx_media_materials_producer (producer_id), ADD CONSTRAINT fk_media_materials_producer FOREIGN KEY (producer_id) REFERENCES producers(id) ON DELETE SET NULL',
  'SELECT 1'
);

PREPARE stmt_media_materials_alter FROM @media_materials_alter_sql;
EXECUTE stmt_media_materials_alter;
DEALLOCATE PREPARE stmt_media_materials_alter;

INSERT INTO producers (owner_user_id, brand_name, slug, status, created_at, updated_at)
SELECT src.owner_user_id,
       src.brand_name,
       CONCAT('producer-', src.owner_user_id),
       'ACTIVE',
       NOW(),
       NOW()
  FROM (
    SELECT DISTINCT t.created_by_user_id AS owner_user_id,
           COALESCE(NULLIF(TRIM(u.name), ''), CONCAT('Producer ', t.created_by_user_id)) AS brand_name
      FROM titles t
      INNER JOIN users u ON u.id = t.created_by_user_id
     WHERE t.created_by_user_id IS NOT NULL
  ) AS src
  LEFT JOIN producers p ON p.owner_user_id = src.owner_user_id
 WHERE p.id IS NULL;

UPDATE titles t
INNER JOIN producers p ON p.owner_user_id = t.created_by_user_id
   SET t.producer_id = p.id
 WHERE t.producer_id IS NULL
   AND t.created_by_user_id IS NOT NULL;

UPDATE media_items m
INNER JOIN titles t ON t.id = m.title_id
   SET m.producer_id = t.producer_id
 WHERE m.producer_id IS NULL;

SET @media_materials_update_sql := IF(
  @media_materials_exists = 1,
  'UPDATE media_materials mm INNER JOIN media_items m ON m.id = mm.media_item_id SET mm.producer_id = m.producer_id WHERE mm.producer_id IS NULL',
  'SELECT 1'
);

PREPARE stmt_media_materials_update FROM @media_materials_update_sql;
EXECUTE stmt_media_materials_update;
DEALLOCATE PREPARE stmt_media_materials_update;

SET FOREIGN_KEY_CHECKS = 1;
