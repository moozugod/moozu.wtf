-- Simple Key-Based System Database Setup
-- This replaces the complex payment system with a simple key management system

-- Drop existing tables if they exist
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS subscriptions;
DROP TABLE IF EXISTS api_access_logs;
DROP TABLE IF EXISTS app_versions;
DROP TABLE IF EXISTS webhook_events;

-- Users table (simplified)
CREATE TABLE IF NOT EXISTS users (
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

-- License keys table (for admin management)
CREATE TABLE IF NOT EXISTS license_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    license_key VARCHAR(100) UNIQUE NOT NULL,
    user_id INT NULL, -- NULL if not assigned to a user yet
    created_by_admin INT NOT NULL, -- Admin user who created this key
    is_active BOOLEAN DEFAULT TRUE,
    expires_at TIMESTAMP NULL, -- NULL for permanent keys
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_license_key (license_key),
    INDEX idx_user_id (user_id),
    INDEX idx_is_active (is_active)
);

-- Add foreign key constraints after both tables are created
ALTER TABLE users 
ADD CONSTRAINT fk_users_license_key 
FOREIGN KEY (license_key_id) REFERENCES license_keys(id) ON DELETE SET NULL;

ALTER TABLE license_keys 
ADD CONSTRAINT fk_license_keys_user 
FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

ALTER TABLE license_keys 
ADD CONSTRAINT fk_license_keys_created_by 
FOREIGN KEY (created_by_admin) REFERENCES users(id) ON DELETE CASCADE;

-- App downloads table (for tracking downloads)
CREATE TABLE IF NOT EXISTS app_downloads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    license_key VARCHAR(100) NOT NULL,
    download_ip VARCHAR(45),
    download_user_agent TEXT,
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_license_key (license_key),
    INDEX idx_downloaded_at (downloaded_at)
);

-- Insert admin user (you can change the password)
INSERT INTO users (username, email, password_hash, license_key, is_active) 
VALUES ('moozu', 'admin@moozu.wtf', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ADMIN_KEY_12345', TRUE)
ON DUPLICATE KEY UPDATE username = username;

-- Insert some sample license keys for testing
INSERT INTO license_keys (license_key, created_by_admin, is_active) VALUES
('TEST_KEY_001', 1, TRUE),
('TEST_KEY_002', 1, TRUE),
('TEST_KEY_003', 1, TRUE),
('DEMO_KEY_001', 1, TRUE),
('DEMO_KEY_002', 1, TRUE);
