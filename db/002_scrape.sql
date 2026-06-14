-- Scrape orchestration: a run is planned up front as a set of per-sector jobs,
-- then processed one sector per HTTP request. Persisting the plan makes an
-- interrupted run resumable.

CREATE TABLE IF NOT EXISTS scrape_run (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  status ENUM('planned','running','done','failed') NOT NULL DEFAULT 'planned',
  started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finished_at TIMESTAMP NULL DEFAULT NULL,
  sectors_total INT UNSIGNED NOT NULL DEFAULT 0,
  sectors_done INT UNSIGNED NOT NULL DEFAULT 0,
  rocks_count INT UNSIGNED NOT NULL DEFAULT 0,
  routes_count INT UNSIGNED NOT NULL DEFAULT 0,
  error_message TEXT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS scrape_job (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  run_id INT UNSIGNED NOT NULL,
  area_name VARCHAR(255) NOT NULL,
  sector_name VARCHAR(255) NOT NULL,
  sector_url VARCHAR(512) NOT NULL,
  status ENUM('pending','running','done','failed') NOT NULL DEFAULT 'pending',
  rocks_count INT UNSIGNED NULL,
  routes_count INT UNSIGNED NULL,
  error_message TEXT NULL,
  started_at TIMESTAMP NULL DEFAULT NULL,
  finished_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_job_run_sector (run_id, sector_url),
  KEY idx_job_run_status (run_id, status),
  CONSTRAINT fk_job_run FOREIGN KEY (run_id) REFERENCES scrape_run (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
