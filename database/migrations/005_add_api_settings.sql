-- Migração para adicionar configurações das APIs
-- Arquivo: 005_add_api_settings.sql

-- Criar tabela de configurações do sistema
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Inserir configurações padrão das APIs
INSERT INTO system_settings (setting_key, setting_value, setting_description) VALUES
('dhcp_api_url', 'http://10.168.11.80:5001', 'URL da API DHCP'),
('share_api_url', 'http://10.168.11.80:5002', 'URL da API Share Logs'),
('dhcp_api_enabled', '1', 'Habilitar API DHCP (1=sim, 0=não)'),
('share_api_enabled', '1', 'Habilitar API Share Logs (1=sim, 0=não)'),
('api_timeout', '30', 'Timeout das APIs em segundos'),
('api_retry_attempts', '3', 'Número de tentativas de reconexão')
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    setting_description = VALUES(setting_description);