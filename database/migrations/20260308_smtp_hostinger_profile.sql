-- WisePlay SMTP profile for Hostinger (idempotent)

UPDATE settings
   SET value_text = '1',
       updated_at = NOW()
 WHERE key_name = 'smtp.enabled'
   AND value_text IN ('', '0', '1');

UPDATE settings
   SET value_text = 'smtp.hostinger.com',
       updated_at = NOW()
 WHERE key_name = 'smtp.host'
   AND value_text IN ('', 'mailpit', 'localhost', '127.0.0.1');

UPDATE settings
   SET value_text = '465',
       updated_at = NOW()
 WHERE key_name = 'smtp.port'
   AND value_text IN ('', '1025', '587', '2525');

UPDATE settings
   SET value_text = 'noreply@wiseplay.online',
       updated_at = NOW()
 WHERE key_name = 'smtp.username'
   AND value_text IN ('', 'no-reply@wiseplay.local');

UPDATE settings
   SET value_text = 'ssl',
       updated_at = NOW()
 WHERE key_name = 'smtp.encryption'
   AND value_text IN ('', 'none', 'tls');

UPDATE settings
   SET value_text = 'noreply@wiseplay.online',
       updated_at = NOW()
 WHERE key_name = 'smtp.from_email'
   AND value_text IN ('', 'no-reply@wiseplay.local');

UPDATE settings
   SET value_text = 'WisePlay',
       updated_at = NOW()
 WHERE key_name = 'smtp.from_name'
   AND value_text IN ('', 'WisePlay');

INSERT INTO settings (category, key_name, value_text, is_secret, created_at, updated_at)
SELECT 'smtp', 'smtp.incoming_imap_host', 'imap.hostinger.com', 0, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = 'smtp.incoming_imap_host');

INSERT INTO settings (category, key_name, value_text, is_secret, created_at, updated_at)
SELECT 'smtp', 'smtp.incoming_imap_port', '993', 0, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = 'smtp.incoming_imap_port');

INSERT INTO settings (category, key_name, value_text, is_secret, created_at, updated_at)
SELECT 'smtp', 'smtp.incoming_imap_encryption', 'ssl', 0, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = 'smtp.incoming_imap_encryption');

INSERT INTO settings (category, key_name, value_text, is_secret, created_at, updated_at)
SELECT 'smtp', 'smtp.incoming_pop_host', 'pop.hostinger.com', 0, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = 'smtp.incoming_pop_host');

INSERT INTO settings (category, key_name, value_text, is_secret, created_at, updated_at)
SELECT 'smtp', 'smtp.incoming_pop_port', '995', 0, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = 'smtp.incoming_pop_port');

INSERT INTO settings (category, key_name, value_text, is_secret, created_at, updated_at)
SELECT 'smtp', 'smtp.incoming_pop_encryption', 'ssl', 0, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = 'smtp.incoming_pop_encryption');

INSERT INTO settings (category, key_name, value_text, is_secret, created_at, updated_at)
SELECT 'smtp', 'smtp.outgoing_host', 'smtp.hostinger.com', 0, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = 'smtp.outgoing_host');

INSERT INTO settings (category, key_name, value_text, is_secret, created_at, updated_at)
SELECT 'smtp', 'smtp.outgoing_port', '465', 0, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = 'smtp.outgoing_port');

INSERT INTO settings (category, key_name, value_text, is_secret, created_at, updated_at)
SELECT 'smtp', 'smtp.outgoing_encryption', 'ssl', 0, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE key_name = 'smtp.outgoing_encryption');
