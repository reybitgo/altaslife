-- ============================================================
--  Site branding: AltasFarmLife / Livelihood
--  Run once: mysql -u root -p DATABASE < migrate_site_branding.sql
--
--  settings.key_name is the primary key, so this is idempotent and
--  also seeds the rows on a database that predates them.
-- ============================================================

INSERT INTO settings (key_name, value) VALUES
  ('site_name',    'AltasFarmLife'),
  ('site_tagline', 'Livelihood')
ON DUPLICATE KEY UPDATE value = VALUES(value);
