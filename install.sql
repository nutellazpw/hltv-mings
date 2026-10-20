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
  image VARCHAR(500) NULL,         -- uploaded file (uploads/...) or full https URL
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
  discord VARCHAR(64) NULL,
  company VARCHAR(160) NULL,
  primary_activity_id INT NULL,
  secondary_activity_id INT NULL,
  status ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  notified VARCHAR(16) NULL,       -- last decision emailed to the guest
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
INSERT INTO hm_activities (name, description, capacity, sort_order, image) VALUES
('Shooting range 1 (Barutana)', NULL, 0, 1, 'https://images.unsplash.com/photo-1577905027675-fcbe1521b1c8?w=1200&h=675&fit=crop&q=80&auto=format'),
('Shooting range 2 (Mus)', NULL, 0, 2, 'https://images.unsplash.com/photo-1687384475723-6a1c70a91920?w=1200&h=675&fit=crop&q=80&auto=format'),
('Padel by GRID', NULL, 0, 3, 'https://images.unsplash.com/photo-1657704358775-ed705c7388d2?w=1200&h=675&fit=crop&q=80&auto=format'),
('Football tournament', NULL, 0, 4, 'https://images.unsplash.com/photo-1431324155629-1a6deb1dec8d?w=1200&h=675&fit=crop&q=80&auto=format'),
('Karting', NULL, 0, 5, 'https://images.unsplash.com/photo-1640084347692-e8f6b84caa7c?w=1200&h=675&fit=crop&q=80&auto=format'),
('Pilates', 'Subject to number of sign-ups.', 0, 6, 'https://images.unsplash.com/photo-1747239685045-fcbcf98985db?w=1200&h=675&fit=crop&q=80&auto=format');

INSERT INTO hm_settings (k, v) VALUES
('schema_version', '4'),
('decision_emails', 'immediate'),
('signups_open', '1'),
('intro_text', 'Pick the activity you would like to join during HLTV Awards week, plus a backup choice. Spots are limited — we will confirm your place by email.');
