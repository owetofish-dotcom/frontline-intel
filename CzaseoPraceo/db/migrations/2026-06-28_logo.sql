-- Migracja: logo firmy (dla baz zaimportowanych przed dodaniem kolumn).
-- Bezpieczna do uruchomienia raz w phpMyAdmin na istniejącej bazie.
ALTER TABLE tenants
  ADD COLUMN logo_mime VARCHAR(50) NULL AFTER hourly_rate,
  ADD COLUMN logo_data MEDIUMTEXT NULL AFTER logo_mime;
