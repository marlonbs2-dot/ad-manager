-- Migração para adicionar tabela de logs de compartilhamentos
-- Versão: 003
-- Data: 2025-01-21

CREATE TABLE IF NOT EXISTS share_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    server_name VARCHAR(255) NOT NULL,
    event_id INT,
    event_record_id VARCHAR(50),
    time_created DATETIME NOT NULL,
    action VARCHAR(50) NOT NULL,
    username VARCHAR(255),
    domain VARCHAR(255),
    source_ip VARCHAR(45),
    share_name VARCHAR(255),
    share_path TEXT,
    object_name TEXT,
    access_mask VARCHAR(20),
    process_name VARCHAR(255),
    raw_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices para performance
    INDEX idx_time_created (time_created),
    INDEX idx_username (username),
    INDEX idx_server_name (server_name),
    INDEX idx_action (action),
    INDEX idx_share_name (share_name),
    INDEX idx_source_ip (source_ip),
    INDEX idx_event_record (event_record_id, server_name),
    
    -- Índice composto para consultas comuns
    INDEX idx_server_time (server_name, time_created),
    INDEX idx_user_time (username, time_created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Adicionar configurações de compartilhamentos na tabela de configurações
INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES
('share_sync_enabled', '0', 'Habilitar sincronização automática de logs de compartilhamentos'),
('share_sync_interval', '60', 'Intervalo de sincronização em minutos'),
('share_retention_days', '90', 'Dias para manter logs de compartilhamentos'),
('share_default_server', 'default', 'Servidor padrão para sincronização');

-- Adicionar novos tipos de ação na auditoria
INSERT IGNORE INTO audit_action_types (action_type, description) VALUES
('share_sync_logs', 'Sincronização de logs de compartilhamentos'),
('share_export_logs', 'Exportação de logs de compartilhamentos'),
('share_view_logs', 'Visualização de logs de compartilhamentos');

-- Criar tabela para configurações de servidores de compartilhamento
CREATE TABLE IF NOT EXISTS share_servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    hostname VARCHAR(255) NOT NULL,
    username VARCHAR(255) NOT NULL,
    password_encrypted TEXT NOT NULL,
    domain VARCHAR(255),
    enabled BOOLEAN DEFAULT TRUE,
    last_sync DATETIME NULL,
    sync_status ENUM('success', 'error', 'never') DEFAULT 'never',
    sync_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_enabled (enabled),
    INDEX idx_last_sync (last_sync)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir servidor padrão
INSERT IGNORE INTO share_servers (name, hostname, username, password_encrypted, domain) VALUES
('default', 'localhost', 'Administrator', '', '');

-- Criar view para estatísticas rápidas
CREATE OR REPLACE VIEW share_stats_daily AS
SELECT 
    DATE(time_created) as date,
    server_name,
    COUNT(*) as total_accesses,
    COUNT(DISTINCT username) as unique_users,
    COUNT(DISTINCT share_name) as unique_shares
FROM share_logs 
WHERE time_created >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(time_created), server_name
ORDER BY date DESC;

-- Criar view para top usuários
CREATE OR REPLACE VIEW share_top_users AS
SELECT 
    username,
    server_name,
    COUNT(*) as access_count,
    COUNT(DISTINCT share_name) as shares_accessed,
    MAX(time_created) as last_access,
    MIN(time_created) as first_access
FROM share_logs 
WHERE time_created >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY username, server_name
ORDER BY access_count DESC;

-- Criar procedure para limpeza automática de logs antigos
DELIMITER //
CREATE PROCEDURE CleanOldShareLogs()
BEGIN
    DECLARE retention_days INT DEFAULT 90;
    
    -- Obter configuração de retenção
    SELECT CAST(setting_value AS UNSIGNED) INTO retention_days 
    FROM settings 
    WHERE setting_key = 'share_retention_days' 
    LIMIT 1;
    
    -- Deletar logs antigos
    DELETE FROM share_logs 
    WHERE time_created < DATE_SUB(NOW(), INTERVAL retention_days DAY);
    
    -- Log da limpeza
    INSERT INTO audit_logs (username, action, ip_address, result, details)
    VALUES ('SYSTEM', 'share_cleanup', '127.0.0.1', 'success', 
            JSON_OBJECT('retention_days', retention_days, 'deleted_records', ROW_COUNT()));
END //
DELIMITER ;

-- Criar evento para limpeza automática (executar diariamente às 2h)
-- Nota: Requer EVENT_SCHEDULER = ON no MySQL
SET GLOBAL event_scheduler = ON;

CREATE EVENT IF NOT EXISTS share_logs_cleanup
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURDATE() + INTERVAL 1 DAY, '02:00:00')
DO
  CALL CleanOldShareLogs();

COMMIT;