-- Dados de teste para AD Manager

-- Criar usuário admin de teste
INSERT INTO users (username, password_hash, role, is_active, created_at) 
VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- senha: admin123
    'admin',
    1,
    NOW()
) ON DUPLICATE KEY UPDATE username=username;

-- Criar usuário normal de teste
INSERT INTO users (username, password_hash, role, is_active, created_at) 
VALUES (
    'user',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- senha: admin123
    'user',
    1,
    NOW()
) ON DUPLICATE KEY UPDATE username=username;

-- Log de auditoria de exemplo
INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, created_at)
VALUES 
    (1, 'login', 'user', 1, 'Login bem-sucedido', '127.0.0.1', NOW()),
    (1, 'view', 'dhcp', NULL, 'Visualizou escopos DHCP', '127.0.0.1', NOW());

-- Configurações padrão
INSERT INTO settings (setting_key, setting_value, created_at, updated_at)
VALUES 
    ('app_name', 'AD Manager', NOW(), NOW()),
    ('maintenance_mode', '0', NOW(), NOW()),
    ('dhcp_enabled', '1', NOW(), NOW())
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
