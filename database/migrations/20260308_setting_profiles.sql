CREATE TABLE IF NOT EXISTS setting_profiles (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO setting_profiles (category, config_name, description, payload_json, is_default, is_active, created_at, updated_at)
SELECT 'general', 'Production', 'Configuracao geral padrao da aplicacao',
       JSON_OBJECT('app_name','WisePlay','app_url','https://wiseplay.online','default_language','pt-PT','timezone','Europe/Lisbon','maintenance_mode','0'),
       1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM setting_profiles WHERE category = 'general' AND is_default = 1);

INSERT INTO setting_profiles (category, config_name, description, payload_json, is_default, is_active, created_at, updated_at)
SELECT 'email', 'Hostinger SMTP', 'Canal de email principal para notificacoes e autenticacao',
       JSON_OBJECT('smtp_host','smtp.hostinger.com','smtp_port','465','smtp_encryption','ssl','smtp_username','noreply@wiseplay.online','smtp_password','','sender_email','noreply@wiseplay.online','sender_name','WisePlay'),
       1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM setting_profiles WHERE category = 'email' AND is_default = 1);
