-- Ayurvedic Wellness House: initial schema (phase 1).
-- Import once via phpMyAdmin > Import. Requires MySQL 5.7+ / MariaDB 10.2+.
-- No business data is seeded: hours, slots, services, prices and
-- practitioners are entered by the clinic in Admin > Settings.

SET NAMES utf8mb4;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('developer','admin') NOT NULL DEFAULT 'admin',  -- developer = site builder, admin = clinic staff
  active TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(190) NULL,
  success TINYINT(1) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_time (ip, created_at),
  INDEX idx_email_time (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE practitioners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  role VARCHAR(100) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  type ENUM('experience','treatment','program') NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  name VARCHAR(190) NOT NULL,
  duration_minutes INT NULL,
  duration_days INT NULL,
  price_idr INT NULL,                 -- NULL = shown as "to be confirmed"
  image VARCHAR(255) NULL,            -- site path, e.g. /images/shirodhara.webp
  bookable_online TINYINT(1) NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE guests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  whatsapp VARCHAR(30) NOT NULL,
  email VARCHAR(190) NULL,
  country VARCHAR(80) NULL,
  language ENUM('en','id') NOT NULL DEFAULT 'en',
  notes TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_whatsapp (whatsapp),
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  guest_id INT NOT NULL,
  service_id INT NOT NULL,
  start_date DATE NOT NULL,
  start_time TIME NULL,
  guests_count TINYINT NOT NULL DEFAULT 1,
  staying_at VARCHAR(190) NULL,
  message TEXT NULL,
  status ENUM('requested','confirmed','checked_in','completed','cancelled','no_show')
         NOT NULL DEFAULT 'requested',
  source ENUM('website','whatsapp','email','walk_in','other') NOT NULL DEFAULT 'website',
  price_idr_snapshot INT NULL,        -- price the guest saw, after any offer
  offer_percent TINYINT NULL,         -- package offer applied, e.g. 30
  confirmed_at DATETIME NULL,
  confirmed_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (guest_id) REFERENCES guests(id),
  FOREIGN KEY (service_id) REFERENCES services(id),
  FOREIGN KEY (confirmed_by) REFERENCES users(id),
  INDEX idx_slot (start_date, start_time),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE health_checks (
  booking_id INT PRIMARY KEY,
  answers JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NULL,
  practitioner_id INT NULL,
  title VARCHAR(190) NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NOT NULL,
  kind ENUM('session','block') NOT NULL DEFAULT 'session',
  notes TEXT NULL,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  FOREIGN KEY (practitioner_id) REFERENCES practitioners(id),
  INDEX idx_starts (starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stays (
  id INT AUTO_INCREMENT PRIMARY KEY,
  guest_id INT NULL,
  booking_id INT NULL,
  place VARCHAR(190) NOT NULL,
  room VARCHAR(100) NULL,
  check_in DATE NOT NULL,
  check_out DATE NOT NULL,
  status ENUM('requested','reserved','checked_in','checked_out','cancelled')
         NOT NULL DEFAULT 'reserved',
  notes TEXT NULL,
  FOREIGN KEY (guest_id) REFERENCES guests(id),
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE opening_hours (
  weekday TINYINT PRIMARY KEY,        -- ISO-8601: 1 = Monday ... 7 = Sunday
  is_open TINYINT(1) NOT NULL DEFAULT 0,
  slot_times VARCHAR(255) NULL        -- e.g. "09:00,10:30,13:00"
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every day starts closed until the clinic sets its real hours.
INSERT INTO opening_hours (weekday, is_open, slot_times) VALUES
  (1,0,NULL),(2,0,NULL),(3,0,NULL),(4,0,NULL),(5,0,NULL),(6,0,NULL),(7,0,NULL);

CREATE TABLE closed_dates (
  date DATE PRIMARY KEY,
  reason VARCHAR(190) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  user_id INT NULL,
  action VARCHAR(50) NOT NULL,
  detail TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_queue (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NULL,
  template VARCHAR(60) NOT NULL,
  to_email VARCHAR(190) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  status ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  attempts TINYINT NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  send_after DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sent_at DATETIME NULL,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
  INDEX idx_queue (status, send_after)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE automations (
  key_name VARCHAR(60) PRIMARY KEY,
  enabled TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_hits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bucket VARCHAR(120) NOT NULL,       -- e.g. "booking:203.0.113.5"
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_bucket_time (bucket, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  key_name VARCHAR(60) PRIMARY KEY,
  value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Online booking stays off until the clinic switches it on in Settings.
INSERT INTO settings (key_name, value) VALUES
  ('booking_enabled', '0'),
  ('notify_email', NULL),
  ('slot_capacity', '1'),
  ('min_notice_hours', '24'),
  ('max_days_ahead', '180'),
  ('offer_percent', '0'),
  ('offer_from', NULL),
  ('offer_until', NULL);
