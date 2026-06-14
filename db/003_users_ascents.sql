-- Users (Google sign-in), sessions, and per-user route entries (ascents).

CREATE TABLE IF NOT EXISTS app_user (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  google_sub VARCHAR(64) NOT NULL,
  email VARCHAR(255) NOT NULL,
  name VARCHAR(255) NULL,
  picture VARCHAR(512) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_sub (google_sub),
  UNIQUE KEY uq_user_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_session (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  token CHAR(64) NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_session_token (token),
  KEY idx_session_user (user_id),
  CONSTRAINT fk_session_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One entry per (user, route), editable. The protection sequence is stored as
-- an ordered JSON array of types: kruh, uzel, hodiny, hrot, strom, jine.
CREATE TABLE IF NOT EXISTS route_ascent (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL,
  route_id INT UNSIGNED NOT NULL,
  route_stars TINYINT UNSIGNED NULL,
  belay_stars TINYINT UNSIGNED NULL,
  protection_json TEXT NULL,
  note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ascent_user_route (user_id, route_id),
  KEY idx_ascent_route (route_id),
  CONSTRAINT fk_ascent_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE,
  CONSTRAINT fk_ascent_route FOREIGN KEY (route_id) REFERENCES route (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
