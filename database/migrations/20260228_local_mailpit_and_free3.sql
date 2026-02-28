-- WisePlay local defaults: Mailpit delivery + free plan with 3 profiles

UPDATE billing_plans
   SET profile_limit = 3,
       updated_at = NOW()
 WHERE slug = 'free'
   AND profile_limit <> 3;

UPDATE settings
   SET value_text = '1',
       updated_at = NOW()
 WHERE key_name = 'smtp.enabled'
   AND value_text IN ('0', '');

UPDATE settings
   SET value_text = 'mailpit',
       updated_at = NOW()
 WHERE key_name = 'smtp.host'
   AND value_text IN ('', 'mailpit');

UPDATE settings
   SET value_text = '1025',
       updated_at = NOW()
 WHERE key_name = 'smtp.port'
   AND value_text IN ('', '587', '1025');

UPDATE settings
   SET value_text = 'none',
       updated_at = NOW()
 WHERE key_name = 'smtp.encryption'
   AND value_text IN ('', 'tls', 'none');

UPDATE settings
   SET value_text = '0',
       updated_at = NOW()
 WHERE key_name = 'email.dev_log_delivery'
   AND value_text IN ('1', '0', '');
