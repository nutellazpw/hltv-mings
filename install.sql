-- HLTV Awards Mingle — database schema (MySQL 5.7+/MariaDB 10.3+)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS hm_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  pass_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','coordinator') NOT NULL DEFAULT 'coordinator',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hm_activities (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  details TEXT NULL,               -- time / location / what to bring (shown to accepted guests)
  capacity INT NOT NULL DEFAULT 0, -- 0 = unlimited
  is_open TINYINT(1) NOT NULL DEFAULT 1,
  results_published TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hm_user_activity (
  user_id INT NOT NULL,
  activity_id INT NOT NULL,
  PRIMARY KEY (user_id, activity_id),
  FOREIGN KEY (user_id) REFERENCES hm_users(id) ON DELETE CASCADE,
  FOREIGN KEY (activity_id) REFERENCES hm_activities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hm_groups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  activity_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  info TEXT NULL,                  -- time slot / meeting point for this group
  result TEXT NULL,                -- group result, shown publicly once published
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (activity_id) REFERENCES hm_activities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hm_signups (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(40) NOT NULL,
  primary_activity_id INT NULL,
  secondary_activity_id INT NULL,
  status ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  activity_id INT NULL,            -- activity actually assigned when accepted
  group_id INT NULL,
  checked_in TINYINT(1) NOT NULL DEFAULT 0,
  checked_in_at DATETIME NULL,
  checked_in_by INT NULL,
  result VARCHAR(255) NULL,        -- individual result (placement, score, time...)
  token CHAR(40) NOT NULL UNIQUE,  -- personal status-page link
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (primary_activity_id) REFERENCES hm_activities(id) ON DELETE SET NULL,
  FOREIGN KEY (secondary_activity_id) REFERENCES hm_activities(id) ON DELETE SET NULL,
  FOREIGN KEY (activity_id) REFERENCES hm_activities(id) ON DELETE SET NULL,
  FOREIGN KEY (group_id) REFERENCES hm_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hm_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  activity_id INT NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(80) NOT NULL,
  uploaded_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (activity_id) REFERENCES hm_activities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hm_settings (
  k VARCHAR(60) PRIMARY KEY,
  v TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS hm_mail_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  to_email VARCHAR(190) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  ok TINYINT(1) NOT NULL,
  error TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Activities from the meeting (capacity 0 = unlimited; main admin sets limits in the panel)
INSERT INTO hm_activities (name, description, capacity, sort_order) VALUES
('Shooting range 1 (Barutana)', NULL, 0, 1),
('Shooting range 2 (Mus)', NULL, 0, 2),
('Padel by GRID', NULL, 0, 3),
('Football tournament', NULL, 0, 4),
('Karting', NULL, 0, 5),
('Pilates', 'Subject to number of sign-ups.', 0, 6);

INSERT INTO hm_settings (k, v) VALUES
('signups_open', '1'),
('intro_text', 'Pick the activity you would like to join during HLTV Awards week, plus a backup choice. Spots are limited — we will confirm your place by email.');
