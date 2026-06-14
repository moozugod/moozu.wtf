-- MOOZU database setup for Railway MySQL
-- WARNING: this resets the Moozu tables to zero.

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS app_downloads;
DROP TABLE IF EXISTS license_keys;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    license_key_id INT NULL,
    hwid VARCHAR(255) NULL,
    license_key VARCHAR(100) UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_license_key (license_key),
    INDEX idx_license_key_id (license_key_id)
);

CREATE TABLE license_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(100) UNIQUE NOT NULL,
    user_id INT NULL,
    created_by_admin INT NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_license_key (license_key),
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active)
);

CREATE TABLE app_downloads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    license_key VARCHAR(100) NOT NULL,
    download_ip VARCHAR(45),
    download_user_agent TEXT,
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_license_key (license_key),
    INDEX idx_downloaded_at (downloaded_at)
);

ALTER TABLE users
ADD CONSTRAINT fk_users_license_key
FOREIGN KEY (license_key_id) REFERENCES license_keys(id) ON DELETE SET NULL;

ALTER TABLE license_keys
ADD CONSTRAINT fk_license_keys_user
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE license_keys
ADD CONSTRAINT fk_license_keys_created_by
FOREIGN KEY (created_by_admin) REFERENCES users(id) ON DELETE CASCADE;

-- Admin initial. This hash will be updated automatically after admin login using ADMIN_PASSWORD in config/database.php.
INSERT INTO users (username, email, password_hash, license_key, is_active)
VALUES (
    'moozu',
    'admin@moozu.wtf',
    '$2b$12$EFaeJLAnzAthAES6RpfynuTs5QkodTz8nFO3LSHMb9JaUK427r232',
    'MOOZU_ADMIN_KEY',
    TRUE
);

SET @admin_id = (SELECT id FROM users WHERE username = 'moozu' LIMIT 1);

INSERT INTO license_keys (license_key, created_by_admin, is_active) VALUES
('TEST_KEY_001', @admin_id, TRUE),
('TEST_KEY_002', @admin_id, TRUE),
('TEST_KEY_003', @admin_id, TRUE),
('DEMO_KEY_001', @admin_id, TRUE),
('DEMO_KEY_002', @admin_id, TRUE);

SELECT id, username, email, license_key, is_active FROM users;
