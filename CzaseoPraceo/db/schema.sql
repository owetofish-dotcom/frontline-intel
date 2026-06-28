-- ============================================================
-- CzaseoPraceo — schemat bazy danych (MySQL 8 / utf8mb4)
-- Faza Spec Kit: Tasks → T-0.1
-- Import: phpMyAdmin → wybierz bazę → Import → ten plik.
-- Zgodny z plan.md v0.3 (10 tabel) i decyzjami D-1..D-7.
-- ============================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- 1. Firmy (najemcy SaaS) + ustawienia firmy (FR-12)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tenants (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name            VARCHAR(190) NOT NULL,
  status          ENUM('active','blocked') NOT NULL DEFAULT 'active',
  -- Ustawienia firmy:
  rounding_mode   ENUM('exact','full_hour','min5','min15') NOT NULL DEFAULT 'full_hour', -- D-3
  allow_overnight TINYINT(1) NOT NULL DEFAULT 0,        -- D-5 (domyślnie bez pracy przez północ)
  hourly_rate     DECIMAL(10,2) NULL,                   -- FR-8 (opcjonalna stawka)
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. Zakłady / lokalizacje (FR-14) — należą do firmy
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS locations (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id   INT UNSIGNED NOT NULL,
  name        VARCHAR(190) NOT NULL,
  status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_locations_tenant (tenant_id),
  CONSTRAINT fk_locations_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. Konta panelu: super_admin / admin / location_manager (FR-9, FR-17)
--    tenant_id NULL = super_admin (ponad firmami)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS panel_users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id     INT UNSIGNED NULL,
  role          ENUM('super_admin','admin','location_manager') NOT NULL,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name     VARCHAR(190) NOT NULL DEFAULT '',
  status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_panel_users_email (email),
  KEY idx_panel_users_tenant (tenant_id),
  CONSTRAINT fk_panel_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. Przypisanie kierownika do zakładu (FR-17) — tylko rola location_manager
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS panel_user_locations (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  panel_user_id INT UNSIGNED NOT NULL,
  location_id   INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_pul (panel_user_id, location_id),
  KEY idx_pul_location (location_id),
  CONSTRAINT fk_pul_user     FOREIGN KEY (panel_user_id) REFERENCES panel_users (id) ON DELETE CASCADE,
  CONSTRAINT fk_pul_location FOREIGN KEY (location_id)   REFERENCES locations (id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 5. Pracownicy (FR-5)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS employees (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id   INT UNSIGNED NOT NULL,
  first_name  VARCHAR(120) NOT NULL,
  last_name   VARCHAR(120) NOT NULL,
  status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_employees_tenant (tenant_id),
  CONSTRAINT fk_employees_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 6. Przypisanie pracownik ↔ zakład (FR-16) — wiele-do-wielu
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS employee_locations (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id   INT UNSIGNED NOT NULL,   -- denormalizacja dla scopingu
  employee_id INT UNSIGNED NOT NULL,
  location_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_el (employee_id, location_id),
  KEY idx_el_location (location_id),
  KEY idx_el_tenant (tenant_id),
  CONSTRAINT fk_el_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE,
  CONSTRAINT fk_el_location FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 7. Karty RFID (FR-13, D-1: numer wyłącznie cyfrowy, unikalny w firmie)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rfid_cards (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id      INT UNSIGNED NOT NULL,
  employee_id    INT UNSIGNED NOT NULL,
  card_number    VARCHAR(64) NOT NULL,   -- przechowywany jako ciąg cyfr
  status         ENUM('active','backup','inactive') NOT NULL DEFAULT 'active',
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deactivated_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_card_tenant_number (tenant_id, card_number),
  KEY idx_cards_employee (employee_id),
  CONSTRAINT fk_cards_tenant   FOREIGN KEY (tenant_id)   REFERENCES tenants (id)   ON DELETE CASCADE,
  CONSTRAINT fk_cards_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 8. Kioski (urządzenia) — przypisane do zakładu (FR-15)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kiosks (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id    INT UNSIGNED NOT NULL,
  location_id  INT UNSIGNED NOT NULL,
  name         VARCHAR(190) NOT NULL,
  token_hash   CHAR(64) NOT NULL,        -- SHA-256 tokenu urządzenia
  status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
  last_seen_at DATETIME NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_kiosk_token (token_hash),
  KEY idx_kiosks_tenant (tenant_id),
  KEY idx_kiosks_location (location_id),
  CONSTRAINT fk_kiosks_tenant   FOREIGN KEY (tenant_id)   REFERENCES tenants (id)   ON DELETE CASCADE,
  CONSTRAINT fk_kiosks_location FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 9. Odbicia (FR-1..FR-4) — niezmienne; idempotencja po (kiosk_id, device_punch_id)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS punches (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id       INT UNSIGNED NOT NULL,
  location_id     INT UNSIGNED NOT NULL,
  employee_id     INT UNSIGNED NOT NULL,
  punch_type      ENUM('in','out') NOT NULL,
  device_time     DATETIME NOT NULL,         -- czas urządzenia w miejscu pracy (D-2)
  kiosk_id        INT UNSIGNED NULL,
  device_punch_id VARCHAR(64) NULL,          -- identyfikator z urządzenia (idempotencja)
  source          ENUM('kiosk','manual') NOT NULL DEFAULT 'kiosk',
  synced_at       DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_punch_device (kiosk_id, device_punch_id),
  KEY idx_punch_emp_time (tenant_id, employee_id, device_time),
  KEY idx_punch_location (location_id),
  CONSTRAINT fk_punch_tenant   FOREIGN KEY (tenant_id)   REFERENCES tenants (id)   ON DELETE CASCADE,
  CONSTRAINT fk_punch_location FOREIGN KEY (location_id) REFERENCES locations (id) ON DELETE CASCADE,
  CONSTRAINT fk_punch_employee FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE,
  CONSTRAINT fk_punch_kiosk    FOREIGN KEY (kiosk_id)    REFERENCES kiosks (id)    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 10. Korekty (FR-6, FR-7b) — ślad audytowy; oryginał odbicia pozostaje niezmienny
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS punch_corrections (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  tenant_id     INT UNSIGNED NOT NULL,
  employee_id   INT UNSIGNED NOT NULL,
  work_date     DATE NOT NULL,                 -- dzień, którego dotyczy
  kind          ENUM('adjust_punch','manual_entry','set_day_hours') NOT NULL,
  punch_id      BIGINT UNSIGNED NULL,          -- powiązane odbicie (jeśli dotyczy)
  old_value     VARCHAR(255) NULL,
  new_value     VARCHAR(255) NULL,
  reason        TEXT NOT NULL,                 -- powód korekty (wymagany)
  author_user_id INT UNSIGNED NOT NULL,        -- kto dokonał korekty
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_corr_emp_date (tenant_id, employee_id, work_date),
  CONSTRAINT fk_corr_tenant   FOREIGN KEY (tenant_id)      REFERENCES tenants (id)     ON DELETE CASCADE,
  CONSTRAINT fk_corr_employee FOREIGN KEY (employee_id)    REFERENCES employees (id)   ON DELETE CASCADE,
  CONSTRAINT fk_corr_punch    FOREIGN KEY (punch_id)       REFERENCES punches (id)     ON DELETE SET NULL,
  CONSTRAINT fk_corr_author   FOREIGN KEY (author_user_id) REFERENCES panel_users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Konto startowe super-admina tworzy skrypt db/seed.php
-- (nie umieszczamy haseł w schemacie).
-- ============================================================
