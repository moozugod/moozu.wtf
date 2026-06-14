-- Migration: Add missing columns to users table
-- This fixes the "Column not found: license_key_id" error
-- 
-- IMPORTANT: If you get errors about columns already existing, that's okay - 
-- just ignore those errors and continue with the rest of the script.

-- Add license_key_id column (foreign key to license_keys table)
ALTER TABLE users 
ADD COLUMN license_key_id INT NULL AFTER password_hash;

-- Add index for license_key_id
ALTER TABLE users 
ADD INDEX idx_license_key_id (license_key_id);

-- Add foreign key constraint
-- Note: This may fail if the constraint already exists - that's okay
ALTER TABLE users 
ADD CONSTRAINT fk_users_license_key 
FOREIGN KEY (license_key_id) REFERENCES license_keys(id) ON DELETE SET NULL;

-- Add hwid column (hardware ID for device locking)
ALTER TABLE users 
ADD COLUMN hwid VARCHAR(255) NULL AFTER license_key_id;

-- Add last_login column
ALTER TABLE users 
ADD COLUMN last_login TIMESTAMP NULL AFTER updated_at;
