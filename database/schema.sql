CREATE TABLE IF NOT EXISTS hm_states (
 id INT PRIMARY KEY, country_id INT NOT NULL DEFAULT 101, name VARCHAR(80) NOT NULL,
 code VARCHAR(8) NOT NULL, UNIQUE KEY(code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS hm_cities (
 id INT AUTO_INCREMENT PRIMARY KEY, state_id INT NOT NULL, name VARCHAR(100) NOT NULL,
 latitude DECIMAL(9,6) NULL, longitude DECIMAL(9,6) NULL,
 UNIQUE KEY(state_id,name), FOREIGN KEY(state_id) REFERENCES hm_states(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS hm_post_offices (
 id INT AUTO_INCREMENT PRIMARY KEY, pincode CHAR(6) NOT NULL, office_name VARCHAR(150) NOT NULL,
 state_id INT NOT NULL, city_id INT NULL, district VARCHAR(100) NOT NULL,
 latitude DECIMAL(9,6) NULL, longitude DECIMAL(9,6) NULL, source VARCHAR(40) NOT NULL,
 UNIQUE KEY(pincode,office_name,state_id,district), KEY(state_id,district), KEY(office_name),
 FOREIGN KEY(state_id) REFERENCES hm_states(id), FOREIGN KEY(city_id) REFERENCES hm_cities(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS hm_incidents (
 id CHAR(32) PRIMARY KEY, category VARCHAR(40) NOT NULL, place_type VARCHAR(30) NOT NULL,
 state_id INT NOT NULL, city_id INT NULL, post_office_id INT NOT NULL,
 cell_x SMALLINT NOT NULL, cell_y SMALLINT NOT NULL,
 incident_date DATE NOT NULL, incident_hour TINYINT UNSIGNED NOT NULL,
 incident_minute TINYINT UNSIGNED NOT NULL, venue_encrypted TEXT NULL, message_encrypted TEXT NULL,
 share_message TINYINT(1) NOT NULL DEFAULT 0,
 status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
 receipt_hash CHAR(64) NOT NULL UNIQUE, duplicate_hash CHAR(64) NOT NULL UNIQUE,
 submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, approved_at DATETIME NULL,
 delete_after DATETIME NOT NULL, KEY(status,approved_at,incident_date), KEY(cell_x,cell_y,status),
 FOREIGN KEY(state_id) REFERENCES hm_states(id), FOREIGN KEY(city_id) REFERENCES hm_cities(id),
 FOREIGN KEY(post_office_id) REFERENCES hm_post_offices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS hm_rate_limits (
 bucket CHAR(64) PRIMARY KEY, attempts INT NOT NULL DEFAULT 1, expires_at DATETIME NOT NULL,
 KEY(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS hm_moderation_actions (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, report_id CHAR(32) NULL, moderator VARCHAR(80) NOT NULL,
 action VARCHAR(20) NOT NULL, reason VARCHAR(60) NULL, notes TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(report_id) REFERENCES hm_incidents(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS hm_imports (
 id INT AUTO_INCREMENT PRIMARY KEY, source VARCHAR(200) NOT NULL, imported_rows INT NOT NULL,
 notes TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS hm_site_daily (
 day DATE PRIMARY KEY, visits INT UNSIGNED NOT NULL DEFAULT 0,
 received_reports INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS hm_live_visits (
 token_hash CHAR(64) PRIMARY KEY, expires_at DATETIME NOT NULL, KEY(expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS hm_contact_messages (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, ticket_id CHAR(16) NOT NULL UNIQUE,
 category ENUM('general', 'privacy_request', 'content_concern', 'legal_grievance', 'bug_report') NOT NULL DEFAULT 'general',
 name VARCHAR(100) NOT NULL, email VARCHAR(255) NOT NULL, subject VARCHAR(150) NOT NULL,
 message TEXT NOT NULL, status ENUM('new', 'in_progress', 'resolved', 'spam') NOT NULL DEFAULT 'new',
 admin_notes TEXT NULL, ip_hash CHAR(64) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 resolved_at DATETIME NULL, KEY(status, created_at), KEY(category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS hm_admins (
 id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL, totp_secret VARCHAR(64) NOT NULL,
 role ENUM('superadmin', 'moderator') NOT NULL DEFAULT 'moderator',
 is_active TINYINT(1) NOT NULL DEFAULT 1, last_login_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS hm_settings (
 setting_key VARCHAR(64) PRIMARY KEY, setting_value TEXT NOT NULL,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
