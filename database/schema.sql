-- AD Manager Database Schema
-- MySQL/MariaDB 8.0+

CREATE DATABASE IF NOT EXISTS ad_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ad_manager;

-- Users table (local cache and preferences)
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL UNIQUE,
    display_name VARCHAR(255),
    email VARCHAR(255),
    role ENUM('admin', 'operator', 'viewer') DEFAULT 'viewer',
    preferred_theme ENUM('light', 'dark', 'auto') DEFAULT 'auto',
    totp_secret VARCHAR(255) NULL,
    totp_enabled BOOLEAN DEFAULT FALSE,
    is_emergency_account BOOLEAN DEFAULT FALSE,
    password_hash VARCHAR(255) NULL COMMENT 'Only for emergency account',
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- AD Configuration
CREATE TABLE IF NOT EXISTS ad_config (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    host VARCHAR(255) NOT NULL,
    port INT DEFAULT 389,
    protocol ENUM('ldap', 'ldaps') DEFAULT 'ldap',
    use_tls BOOLEAN DEFAULT FALSE,
    base_dn VARCHAR(500) NOT NULL,
    bind_dn VARCHAR(500) NOT NULL,
    bind_password_enc TEXT NOT NULL COMMENT 'AES-256 encrypted',
    admin_ou VARCHAR(500) NOT NULL COMMENT 'OU of administrators',
    ou_reset_password TEXT COMMENT 'JSON array of OUs allowed to reset passwords',
    ou_manage_groups TEXT COMMENT 'JSON array of OUs allowed to manage groups',
    connection_timeout INT DEFAULT 10,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit Logs
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    username VARCHAR(255) NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_dn VARCHAR(500),
    target_ou VARCHAR(500),
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    result ENUM('success', 'failure', 'error') NOT NULL,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_action (action),
    INDEX idx_result (result),
    INDEX idx_created_at (created_at),
    INDEX idx_target_ou (target_ou),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings (key-value store)
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(255) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sessions
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT UNSIGNED,
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT NOT NULL,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login Attempts (rate limiting)
CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success BOOLEAN DEFAULT FALSE,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username_ip (username, ip_address),
    INDEX idx_attempted_at (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('password_min_length', '12', 'integer', 'Minimum password length for generated passwords'),
('password_require_uppercase', 'true', 'boolean', 'Require uppercase letters in passwords'),
('password_require_lowercase', 'true', 'boolean', 'Require lowercase letters in passwords'),
('password_require_numbers', 'true', 'boolean', 'Require numbers in passwords'),
('password_require_special', 'true', 'boolean', 'Require special characters in passwords'),
('session_timeout', '7200', 'integer', 'Session timeout in seconds'),
('max_login_attempts', '5', 'integer', 'Maximum login attempts before lockout'),
('lockout_duration', '900', 'integer', 'Lockout duration in seconds'),
('enable_2fa', 'false', 'boolean', 'Enable two-factor authentication'),
('app_version', '1.0.0', 'string', 'Application version');
