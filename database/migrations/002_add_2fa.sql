-- Migration: Add 2FA/TOTP support
-- Date: 2025-10-25

-- Add 2FA columns to users table (only if they don't exist)
SET @dbname = DATABASE();
SET @tablename = 'users';

-- Add totp_secret column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'totp_secret');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE users ADD COLUMN totp_secret VARCHAR(32) NULL COMMENT "TOTP secret key (base32 encoded)"',
    'SELECT "Column totp_secret already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add totp_enabled column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'totp_enabled');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE users ADD COLUMN totp_enabled BOOLEAN DEFAULT FALSE COMMENT "Whether 2FA is enabled"',
    'SELECT "Column totp_enabled already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add totp_verified_at column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'totp_verified_at');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE users ADD COLUMN totp_verified_at DATETIME NULL COMMENT "When 2FA was first verified"',
    'SELECT "Column totp_verified_at already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add backup_codes column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'backup_codes');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE users ADD COLUMN backup_codes TEXT NULL COMMENT "JSON array of backup codes (hashed)"',
    'SELECT "Column backup_codes already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add last_activity_at column
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = 'last_activity_at');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE users ADD COLUMN last_activity_at DATETIME NULL COMMENT "Last activity timestamp for session timeout"',
    'SELECT "Column last_activity_at already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop table if exists to recreate it properly
DROP TABLE IF EXISTS totp_attempts;

-- Create table for failed 2FA attempts
CREATE TABLE totp_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success BOOLEAN NOT NULL DEFAULT FALSE,
    attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_attempts (user_id, attempted_at),
    INDEX idx_ip_attempts (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for session timeout queries (only if it doesn't exist)
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = 'idx_last_activity');
SET @sql = IF(@index_exists = 0, 
    'CREATE INDEX idx_last_activity ON users(last_activity_at)',
    'SELECT "Index idx_last_activity already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Migration completed successfully!' AS status;
