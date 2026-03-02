-- Migration: Tabela de Servidores de Impressão
-- Executar na base ad_manager

CREATE TABLE IF NOT EXISTS print_servers (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100)  NOT NULL COMMENT 'Nome amigável do servidor (ex: Servidor RH)',
    url         VARCHAR(255)  NOT NULL COMMENT 'URL da Print API (ex: https://10.0.0.5:5444)',
    api_key     VARCHAR(255)  NOT NULL COMMENT 'API Key configurada no print-api-service.js',
    description VARCHAR(255)  NULL     COMMENT 'Descrição opcional',
    enabled     BOOLEAN       NOT NULL DEFAULT TRUE,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_enabled (enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Servidores de impressão gerenciados pelo AD Manager';
