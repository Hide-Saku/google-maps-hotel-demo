-- 施設データのスキーマ（設計の考え方は docs/DESIGN_PROPOSALS.md）
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS import_skipped, import_runs, admin_users, facilities, hotel_categories, hotels;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE hotels (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(50)  NOT NULL UNIQUE,
  name        VARCHAR(100) NOT NULL,
  center_lat  DECIMAL(9,6) NOT NULL,
  center_lng  DECIMAL(9,6) NOT NULL,
  zoom        TINYINT UNSIGNED NOT NULL DEFAULT 14,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ホテルごとに「地図に出すカテゴリ」を持つ
CREATE TABLE hotel_categories (
  hotel_id INT UNSIGNED NOT NULL,
  category VARCHAR(30)  NOT NULL,
  PRIMARY KEY (hotel_id, category),
  CONSTRAINT fk_hc_hotel FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 取得元(source)＋外部ID(external_id)で、取り込みの冪等性を保つ（手入力の行は external_id が NULL）
CREATE TABLE facilities (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  hotel_id    INT UNSIGNED NOT NULL,
  source      VARCHAR(30)  NOT NULL DEFAULT 'manual',
  external_id VARCHAR(64)  NULL,
  name        VARCHAR(100) NOT NULL,
  category    VARCHAR(30)  NOT NULL,
  lat         DECIMAL(9,6) NOT NULL,
  lng         DECIMAL(9,6) NOT NULL,
  address     VARCHAR(200) NOT NULL DEFAULT '',
  description VARCHAR(500) NOT NULL DEFAULT '',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hotel_source_ext (hotel_id, source, external_id),
  KEY idx_hotel_cat (hotel_id, category),
  CONSTRAINT fk_fac_hotel FOREIGN KEY (hotel_id) REFERENCES hotels(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE admin_users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE import_runs (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  source     VARCHAR(30)  NOT NULL,
  file       VARCHAR(200) NOT NULL,
  inserted   INT UNSIGNED NOT NULL DEFAULT 0,
  updated    INT UNSIGNED NOT NULL DEFAULT 0,
  unchanged  INT UNSIGNED NOT NULL DEFAULT 0,
  skipped    INT UNSIGNED NOT NULL DEFAULT 0,
  status     VARCHAR(20)  NOT NULL DEFAULT 'ok'
) ENGINE=InnoDB;

CREATE TABLE import_skipped (
  id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  run_id    BIGINT UNSIGNED NOT NULL,
  row_index INT UNSIGNED NOT NULL,
  reason    VARCHAR(200) NOT NULL,
  raw_json  TEXT NOT NULL,
  CONSTRAINT fk_skip_run FOREIGN KEY (run_id) REFERENCES import_runs(id) ON DELETE CASCADE
) ENGINE=InnoDB;
