-- Migrate: add 'standard' to reg_payment_method and convert existing free pending accounts if packages disabled
ALTER TABLE users MODIFY COLUMN reg_payment_method ENUM('code','ewallet','pending','standard') NOT NULL DEFAULT 'code';

-- One-off conversion: existing free pending accounts created while in disabled-packages context
-- If packages are currently disabled, convert free pending (pending referral) accounts to Standard
-- These are accounts with reg_payment_method='pending' and package_id IS NULL (free referrals)
UPDATE users u
SET u.reg_payment_method = 'standard',
    u.status = 'active'
WHERE u.reg_payment_method = 'pending'
  AND u.package_id IS NULL
  AND u.status = 'pending'
  AND EXISTS (SELECT 1 FROM settings s WHERE s.key_name = 'packages_disabled' AND s.value = '1');
