SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE billing_plans
  MODIFY billing_cycle ENUM('MONTHLY','YEARLY','ONE_TIME') NOT NULL DEFAULT 'MONTHLY';

SET @free_plan_id := (SELECT id FROM billing_plans WHERE slug = 'free' LIMIT 1);

INSERT INTO billing_plans (
  plan_name,
  slug,
  price,
  currency,
  billing_cycle,
  profile_limit,
  max_streams,
  quality_level,
  active,
  is_selectable,
  requires_payment_method,
  sort_order,
  created_at,
  updated_at
)
SELECT
  'Free',
  'free',
  0.00,
  'BRL',
  'MONTHLY',
  3,
  1,
  '720p',
  1,
  1,
  0,
  10,
  NOW(),
  NOW()
WHERE @free_plan_id IS NULL;

SET @free_plan_id := (
  SELECT id
  FROM billing_plans
  WHERE slug = 'free'
  ORDER BY id ASC
  LIMIT 1
);

SET @one_time_plan_id := (SELECT id FROM billing_plans WHERE slug = 'pagamento-unico' LIMIT 1);

INSERT INTO billing_plans (
  plan_name,
  slug,
  price,
  currency,
  billing_cycle,
  profile_limit,
  max_streams,
  quality_level,
  active,
  is_selectable,
  requires_payment_method,
  sort_order,
  created_at,
  updated_at
)
SELECT
  'Pagamento unico',
  'pagamento-unico',
  129.90,
  'BRL',
  'ONE_TIME',
  1,
  1,
  '1080p',
  1,
  1,
  1,
  40,
  NOW(),
  NOW()
WHERE @one_time_plan_id IS NULL;

ALTER TABLE titles
  ADD COLUMN billing_plan_id BIGINT UNSIGNED NULL AFTER id;

UPDATE titles
SET billing_plan_id = @free_plan_id
WHERE billing_plan_id IS NULL;

ALTER TABLE titles
  MODIFY billing_plan_id BIGINT UNSIGNED NOT NULL,
  ADD INDEX idx_titles_billing_plan (billing_plan_id),
  ADD CONSTRAINT fk_titles_billing_plan FOREIGN KEY (billing_plan_id) REFERENCES billing_plans(id) ON DELETE RESTRICT;

SET FOREIGN_KEY_CHECKS = 1;
