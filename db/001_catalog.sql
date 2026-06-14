-- Catalogue tables: areas, sectors, rocks and routes scraped from piskari.cz.

CREATE TABLE IF NOT EXISTS area (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  url VARCHAR(512) NOT NULL,
  scraped_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_area_url (url)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sector (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  area_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  url VARCHAR(512) NOT NULL,
  climbing_season VARCHAR(255) NULL,
  climbing_restriction VARCHAR(255) NULL,
  scraped_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sector_url (url),
  KEY idx_sector_area (area_id),
  CONSTRAINT fk_sector_area FOREIGN KEY (area_id) REFERENCES area (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rock (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  sector_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  name_fold VARCHAR(255) NOT NULL DEFAULT '',
  url VARCHAR(512) NOT NULL,
  area_name VARCHAR(255) NOT NULL,
  sub_area_name VARCHAR(255) NOT NULL,
  gps_raw VARCHAR(255) NULL,
  gps_lat DECIMAL(9,6) NULL,
  gps_lon DECIMAL(9,6) NULL,
  scraped_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rock_url (url),
  KEY idx_rock_sector (sector_id),
  KEY idx_rock_name_fold (name_fold),
  CONSTRAINT fk_rock_sector FOREIGN KEY (sector_id) REFERENCES sector (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS route (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  rock_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  name_fold VARCHAR(255) NOT NULL DEFAULT '',
  url VARCHAR(512) NOT NULL,
  difficulty VARCHAR(64) NULL,
  first_ascent_date DATE NULL,
  first_ascent_raw VARCHAR(64) NULL,
  -- stars: route quality, one of 0, 1, 2.
  stars TINYINT UNSIGNED NOT NULL DEFAULT 0,
  comments_count INT UNSIGNED NOT NULL DEFAULT 0,
  has_photos TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  scraped_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_route_url (url),
  KEY idx_route_rock (rock_id),
  KEY idx_route_name_fold (name_fold),
  CONSTRAINT fk_route_rock FOREIGN KEY (rock_id) REFERENCES rock (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
