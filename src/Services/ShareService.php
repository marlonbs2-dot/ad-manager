<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Services\SettingsService;
use PDO;

class ShareService
{
    private PDO $db;
    private SettingsService $settings;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->settings = new SettingsService();
    }

    /**
     * Sincronizar logs do Windows Server via API HTTP
     */
    public function syncLogsFromServer(string $server = 'default', int $hours = 24, string $shareFilter = ''): array
    {
        // Se servidor vazio, usar default
        if (empty($server)) {
            $server = 'default';
        }
        
        $config = $this->getServerConfig($server);
        
        if (!$config) {
            throw new \Exception("Configuração do servidor '$server' não encontrada. Verifique se o servidor está cadastrado e ativo.");
        }

        // URL da API de Share Logs (configurável via interface web)
        $apiUrl = $this->settings->get('share_api_url', 'https://10.168.11.80:5444');
        
        try {
            // Fazer requisição para a API
            $postData = json_encode([
                'hours' => $hours,
                'shareFilter' => $shareFilter
            ]);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => [
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($postData)
                    ],
                    'content' => $postData,
                    'timeout' => 60
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
            
            $response = file_get_contents($apiUrl . '/api/sync-logs', false, $context);
            
            if ($response === false) {
                throw new \Exception('Falha na comunicação com a API de Share Logs. Verifique se o serviço está rodando na porta 5002.');
            }
            
            $data = json_decode($response, true);
            
            if (!$data) {
                throw new \Exception('Resposta inválida da API de Share Logs');
            }
            
            if (!$data['success']) {
                throw new \Exception($data['message'] ?? 'Erro desconhecido na API');
            }
            
            $logs = $data['data'] ?? [];
            
            if (empty($logs)) {
                $message = 'Nenhum log encontrado no período especificado';
                if ($shareFilter) {
                    $message .= " para o compartilhamento '$shareFilter'";
                }
                
                return [
                    'imported' => 0,
                    'skipped' => 0,
                    'total_processed' => 0,
                    'message' => $message
                ];
            }
            
            // Processar e inserir logs
            $result = $this->processAndStoreLogs($logs, $server);
            
            // Atualizar status do servidor
            $this->updateServerSyncStatus($server, 'success');
            
            return $result;
            
        } catch (\Exception $e) {
            // Atualizar status do servidor com erro
            $this->updateServerSyncStatus($server, 'error', $e->getMessage());
            throw new \Exception('Erro na sincronização: ' . $e->getMessage());
        }
    }

    /**
     * Construir script PowerShell para coletar logs
     */
    private function buildPowerShellScript(string $startTime): string
    {
        return "
        \$StartTime = Get-Date '$startTime'
        
        # Event IDs relevantes para compartilhamentos:
        # 5140 - Acesso a compartilhamento de rede
        # 5145 - Verificação de acesso a objeto compartilhado
        # 4656 - Handle para objeto solicitado
        # 4658 - Handle para objeto fechado
        # 4663 - Tentativa de acesso a objeto
        
        \$Events = Get-WinEvent -FilterHashtable @{
            LogName = 'Security'
            ID = 5140, 5145, 4656, 4658, 4663
            StartTime = \$StartTime
        } -ErrorAction SilentlyContinue | ForEach-Object {
            \$Event = \$_
            \$EventXML = [xml]\$Event.ToXml()
            
            # Extrair dados do evento
            \$EventData = @{}
            \$EventXML.Event.EventData.Data | ForEach-Object {
                if (\$_.Name) {
                    \$EventData[\$_.Name] = \$_.'#text'
                }
            }
            
            # Determinar ação baseada no Event ID
            \$Action = switch (\$Event.Id) {
                5140 { 'share_access' }
                5145 { 'share_object_access' }
                4656 { 'file_handle_requested' }
                4658 { 'file_handle_closed' }
                4663 { 'file_access_attempt' }
                default { 'unknown' }
            }
            
            # Extrair informações relevantes
            \$LogEntry = [PSCustomObject]@{
                EventId = \$Event.Id
                TimeCreated = \$Event.TimeCreated.ToString('yyyy-MM-dd HH:mm:ss')
                Action = \$Action
                Username = \$EventData['SubjectUserName'] -replace '\\$', ''
                Domain = \$EventData['SubjectDomainName']
                SourceIP = \$EventData['IpAddress'] -replace '::ffff:', ''
                ShareName = \$EventData['ShareName']
                SharePath = \$EventData['ShareLocalPath']
                ObjectName = \$EventData['ObjectName']
                AccessMask = \$EventData['AccessMask']
                ProcessName = \$EventData['ProcessName']
                EventRecordId = \$Event.RecordId
            }
            
            # Filtrar eventos do sistema e vazios
            if (\$LogEntry.Username -and 
                \$LogEntry.Username -ne '-' -and 
                \$LogEntry.Username -notlike '*\$' -and
                \$LogEntry.Username -ne 'SYSTEM' -and
                \$LogEntry.Username -ne 'ANONYMOUS LOGON') {
                \$LogEntry
            }
        }
        
        # Converter para JSON
        \$Events | ConvertTo-Json -Depth 3
        ";
    }

    /**
     * Executar PowerShell remoto
     */
    private function executePowerShellRemote(array $config, string $script): array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'share_sync_');
        file_put_contents($tempFile, $script);
        
        try {
            $command = sprintf(
                'powershell -Command "' .
                '$password = ConvertTo-SecureString \'%s\' -AsPlainText -Force; ' .
                '$credential = New-Object System.Management.Automation.PSCredential(\'%s\', $password); ' .
                'Invoke-Command -ComputerName %s -Credential $credential -FilePath \'%s\'"',
                addslashes($config['password']),
                addslashes($config['username']),
                $config['hostname'],
                $tempFile
            );
            
            $output = shell_exec($command);
            
            if (!$output) {
                throw new \Exception('Nenhum dado retornado do servidor');
            }
            
            $logs = json_decode($output, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Erro ao decodificar resposta JSON: ' . json_last_error_msg());
            }
            
            return is_array($logs) ? $logs : [];
            
        } finally {
            unlink($tempFile);
        }
    }

    /**
     * Processar e armazenar logs no banco
     */
    private function processAndStoreLogs(array $logs, string $server): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($logs as $log) {
            try {
                if (!$this->isValidLog($log)) {
                    $skipped++;
                    continue;
                }
                
                // Verificar se já existe (evitar duplicatas)
                if (isset($log['EventRecordId']) && $this->logExists($log['EventRecordId'], $server)) {
                    $skipped++;
                    continue;
                }
                
                // Inserir log
                $this->insertLog($log, $server);
                $imported++;
                
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
                $skipped++;
            }
        }
        
        $result = [
            'imported' => $imported,
            'skipped' => $skipped,
            'total_processed' => count($logs)
        ];
        
        if (!empty($errors)) {
            $result['errors'] = array_slice($errors, 0, 5); // Máximo 5 erros
        }
        
        return $result;
    }

    /**
     * Validar se o log é válido
     */
    private function isValidLog(array $log): bool
    {
        // Verificar se tem pelo menos os campos essenciais
        return isset($log['TimeCreated']) && 
               isset($log['Action']) && 
               !empty($log['TimeCreated']) && 
               !empty($log['Action']);
    }

    /**
     * Verificar se log já existe
     */
    private function logExists($eventRecordId, string $server): bool
    {
        if (empty($eventRecordId)) {
            return false;
        }
        
        // Converter para string se necessário
        $eventRecordId = (string)$eventRecordId;
        
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM share_logs 
            WHERE event_record_id = ? AND server_name = ?
        ');
        $stmt->execute([$eventRecordId, $server]);
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Inserir log no banco
     */
    private function insertLog(array $log, string $server): void
    {
        $stmt = $this->db->prepare('
            INSERT INTO share_logs (
                server_name, event_id, event_record_id, time_created, action,
                username, domain, source_ip, share_name, share_path,
                object_name, object_type, access_mask, process_name, raw_data
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        // Sanitizar e validar dados
        $eventRecordId = isset($log['EventRecordId']) ? (string)$log['EventRecordId'] : null;
        $timeCreated = $this->parseDateTime($log['TimeCreated'] ?? null);
        $username = $this->sanitizeString($log['Username'] ?? null);
        $domain = $this->sanitizeString($log['Domain'] ?? null);
        $sourceIP = $this->sanitizeString($log['SourceIP'] ?? null);
        $shareName = $this->sanitizeString($log['ShareName'] ?? null);
        $sharePath = $this->sanitizeString($log['SharePath'] ?? null);
        $objectName = $this->sanitizeString($log['ObjectName'] ?? null);
        $objectType = $this->sanitizeString($log['ObjectType'] ?? 'unknown');
        $accessMask = $this->sanitizeString($log['AccessMask'] ?? null);
        $processName = $this->sanitizeString($log['ProcessName'] ?? null);
        
        $stmt->execute([
            $server,
            isset($log['EventId']) ? (int)$log['EventId'] : null,
            $eventRecordId,
            $timeCreated,
            $log['Action'] ?? 'unknown',
            $username,
            $domain,
            $sourceIP,
            $shareName,
            $sharePath,
            $objectName,
            $objectType,
            $accessMask,
            $processName,
            json_encode($log, JSON_UNESCAPED_UNICODE)
        ]);
    }
    
    /**
     * Sanitizar string removendo caracteres problemáticos
     */
    private function sanitizeString(?string $value): ?string
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }
        
        // Remover caracteres de controle e limitar tamanho
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
        return mb_substr($sanitized, 0, 255);
    }
    
    /**
     * Converter string de data para formato MySQL
     */
    private function parseDateTime(?string $dateTime): ?string
    {
        if (empty($dateTime)) {
            return null;
        }
        
        try {
            $dt = new \DateTime($dateTime);
            return $dt->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Obter logs com filtros
     */
    public function getLogs(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['server'])) {
            $where[] = 'server_name = ?';
            $params[] = $filters['server'];
        }

        if (!empty($filters['username'])) {
            $where[] = 'username LIKE ?';
            $params[] = '%' . $filters['username'] . '%';
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['share_name'])) {
            $where[] = 'share_name LIKE ?';
            $params[] = '%' . $filters['share_name'] . '%';
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'time_created >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'time_created <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);
        
        $sql = "
            SELECT * FROM share_logs 
            WHERE $whereClause 
            ORDER BY time_created DESC 
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Contar logs com filtros
     */
    public function getLogCount(array $filters = []): int
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['server'])) {
            $where[] = 'server_name = ?';
            $params[] = $filters['server'];
        }

        if (!empty($filters['username'])) {
            $where[] = 'username LIKE ?';
            $params[] = '%' . $filters['username'] . '%';
        }

        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }

        if (!empty($filters['share_name'])) {
            $where[] = 'share_name LIKE ?';
            $params[] = '%' . $filters['share_name'] . '%';
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'time_created >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'time_created <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = implode(' AND ', $where);
        
        $sql = "SELECT COUNT(*) FROM share_logs WHERE $whereClause";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Obter estatísticas
     */
    public function getStatistics(int $days = 7): array
    {
        $stats = [];
        
        // Total de acessos nos últimos X dias
        $stmt = $this->db->prepare('
            SELECT COUNT(*) as total 
            FROM share_logs 
            WHERE time_created >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ');
        $stmt->execute([$days]);
        $stats['total_accesses'] = (int) $stmt->fetchColumn();
        
        // Usuários mais ativos
        $stmt = $this->db->prepare('
            SELECT username, COUNT(*) as access_count 
            FROM share_logs 
            WHERE time_created >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY username 
            ORDER BY access_count DESC 
            LIMIT 10
        ');
        $stmt->execute([$days]);
        $stats['top_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Compartilhamentos mais acessados
        $stmt = $this->db->prepare('
            SELECT share_name, COUNT(*) as access_count 
            FROM share_logs 
            WHERE time_created >= DATE_SUB(NOW(), INTERVAL ? DAY)
              AND share_name IS NOT NULL
            GROUP BY share_name 
            ORDER BY access_count DESC 
            LIMIT 10
        ');
        $stmt->execute([$days]);
        $stats['top_shares'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ações por tipo
        $stmt = $this->db->prepare('
            SELECT action, COUNT(*) as count 
            FROM share_logs 
            WHERE time_created >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY action 
            ORDER BY count DESC
        ');
        $stmt->execute([$days]);
        $stats['actions_by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Atividade por hora (últimas 24h)
        $stmt = $this->db->prepare('
            SELECT HOUR(time_created) as hour, COUNT(*) as count 
            FROM share_logs 
            WHERE time_created >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY HOUR(time_created) 
            ORDER BY hour
        ');
        $stmt->execute();
        $stats['activity_by_hour'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    }

    /**
     * Exportar logs
     */
    public function exportLogs(array $filters, string $format = 'csv'): array
    {
        $logs = $this->getLogs($filters, 10000, 0); // Máximo 10k registros
        
        if ($format === 'csv') {
            return $this->exportToCsv($logs);
        } elseif ($format === 'json') {
            return $this->exportToJson($logs);
        }
        
        throw new \Exception('Formato de exportação não suportado');
    }

    /**
     * Exportar para CSV
     */
    private function exportToCsv(array $logs): array
    {
        $csv = "Data/Hora,Servidor,Usuário,Domínio,Ação,Compartilhamento,Caminho,Objeto,IP Origem\n";
        
        foreach ($logs as $log) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $log['time_created'],
                $log['server_name'],
                $log['username'],
                $log['domain'] ?? '',
                $log['action'],
                $log['share_name'] ?? '',
                $log['share_path'] ?? '',
                $log['object_name'] ?? '',
                $log['source_ip'] ?? ''
            );
        }
        
        return [
            'content' => $csv,
            'content_type' => 'text/csv',
            'count' => count($logs)
        ];
    }

    /**
     * Exportar para JSON
     */
    private function exportToJson(array $logs): array
    {
        return [
            'content' => json_encode($logs, JSON_PRETTY_PRINT),
            'content_type' => 'application/json',
            'count' => count($logs)
        ];
    }

    /**
     * Obter configuração do servidor
     */
    private function getServerConfig(string $server): ?array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM share_servers 
            WHERE name = ? AND enabled = 1
        ');
        $stmt->execute([$server]);
        $serverData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$serverData) {
            return null;
        }
        
        return [
            'hostname' => $serverData['hostname'],
            'username' => $serverData['username'],
            'password' => $this->decryptPassword($serverData['password_encrypted']),
            'domain' => $serverData['domain'] ?? ''
        ];
    }
    
    /**
     * Obter lista de servidores ativos
     */
    public function getActiveServers(): array
    {
        $stmt = $this->db->prepare('
            SELECT name, hostname, domain, enabled, last_sync, sync_status 
            FROM share_servers 
            WHERE enabled = 1 
            ORDER BY name
        ');
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obter todos os servidores
     */
    public function getAllServers(): array
    {
        $stmt = $this->db->prepare('
            SELECT * FROM share_servers 
            ORDER BY name
        ');
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Adicionar novo servidor
     */
    public function addServer(array $data): bool
    {
        $stmt = $this->db->prepare('
            INSERT INTO share_servers (name, hostname, username, password_encrypted, domain, enabled)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        
        return $stmt->execute([
            $data['name'],
            $data['hostname'],
            $data['username'],
            $this->encryptPassword($data['password']),
            $data['domain'] ?? '',
            $data['enabled'] ?? true
        ]);
    }
    
    /**
     * Atualizar servidor
     */
    public function updateServer(int $id, array $data): bool
    {
        $fields = [];
        $params = [];
        
        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $params[] = $data['name'];
        }
        
        if (isset($data['hostname'])) {
            $fields[] = 'hostname = ?';
            $params[] = $data['hostname'];
        }
        
        if (isset($data['username'])) {
            $fields[] = 'username = ?';
            $params[] = $data['username'];
        }
        
        if (isset($data['password'])) {
            $fields[] = 'password_encrypted = ?';
            $params[] = $this->encryptPassword($data['password']);
        }
        
        if (isset($data['domain'])) {
            $fields[] = 'domain = ?';
            $params[] = $data['domain'];
        }
        
        if (isset($data['enabled'])) {
            $fields[] = 'enabled = ?';
            $params[] = $data['enabled'];
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $params[] = $id;
        
        $stmt = $this->db->prepare('
            UPDATE share_servers 
            SET ' . implode(', ', $fields) . ', updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ');
        
        return $stmt->execute($params);
    }
    
    /**
     * Remover servidor
     */
    public function deleteServer(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM share_servers WHERE id = ?');
        return $stmt->execute([$id]);
    }
    
    /**
     * Testar conexão com servidor
     */
    public function testServerConnection(string $serverName): array
    {
        // Se for um teste de servidor novo (não existe no banco ainda)
        // usar dados do formulário em vez do banco
        $config = $this->getServerConfig($serverName);
        
        if (!$config) {
            return [
                'success' => false,
                'message' => 'Servidor não encontrado ou não configurado'
            ];
        }
        
        try {
            // Script simples para testar conectividade
            $testScript = 'Get-Date | ConvertTo-Json';
            
            $tempFile = tempnam(sys_get_temp_dir(), 'share_test_');
            file_put_contents($tempFile, $testScript);
            
            $command = sprintf(
                'powershell -Command "' .
                '$password = ConvertTo-SecureString \'%s\' -AsPlainText -Force; ' .
                '$credential = New-Object System.Management.Automation.PSCredential(\'%s\', $password); ' .
                'Invoke-Command -ComputerName %s -Credential $credential -FilePath \'%s\'"',
                addslashes($config['password']),
                addslashes($config['username']),
                $config['hostname'],
                $tempFile
            );
            
            $output = shell_exec($command);
            unlink($tempFile);
            
            if ($output && json_decode($output)) {
                return [
                    'success' => true,
                    'message' => 'Conexão estabelecida com sucesso'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Falha na conexão: ' . ($output ?: 'Sem resposta do servidor')
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro na conexão: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Testar conexão com dados fornecidos (para novos servidores)
     */
    public function testServerConnectionWithData(array $data): array
    {
        // Validações básicas
        if (empty($data['hostname']) || empty($data['username']) || empty($data['password'])) {
            return [
                'success' => false,
                'message' => 'Hostname, usuário e senha são obrigatórios'
            ];
        }
        
        if (!filter_var($data['hostname'], FILTER_VALIDATE_IP) && !preg_match('/^[a-zA-Z0-9.-]+$/', $data['hostname'])) {
            return [
                'success' => false,
                'message' => 'Hostname/IP inválido'
            ];
        }
        
        if (strlen($data['username']) < 3) {
            return [
                'success' => false,
                'message' => 'Nome de usuário deve ter pelo menos 3 caracteres'
            ];
        }
        
        if (strlen($data['password']) < 6) {
            return [
                'success' => false,
                'message' => 'Senha deve ter pelo menos 6 caracteres'
            ];
        }
        
        // Testar via API HTTP (configurável via interface web)
        $apiUrl = $this->settings->get('share_api_url', 'https://10.168.11.80:5444');
        
        try {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => '{}',
                    'timeout' => 30
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
            
            $response = file_get_contents($apiUrl . '/api/test-connection', false, $context);
            
            if ($response === false) {
                return [
                    'success' => false,
                    'message' => 'Não foi possível conectar com a API de Share Logs. Verifique se o serviço está rodando na porta 5002.'
                ];
            }
            
            $data = json_decode($response, true);
            
            if (!$data) {
                return [
                    'success' => false,
                    'message' => 'Resposta inválida da API'
                ];
            }
            
            return $data;
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro na conexão: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Atualizar status de sincronização do servidor
     */
    public function updateServerSyncStatus(string $serverName, string $status, ?string $error = null): void
    {
        $stmt = $this->db->prepare('
            UPDATE share_servers 
            SET last_sync = CURRENT_TIMESTAMP, sync_status = ?, sync_error = ?
            WHERE name = ?
        ');
        
        $stmt->execute([$status, $error, $serverName]);
    }
    
    /**
     * Criptografar senha
     */
    private function encryptPassword(string $password): string
    {
        $key = $_ENV['ENCRYPTION_KEY'] ?? 'default-key';
        return base64_encode(openssl_encrypt($password, 'AES-256-CBC', $key, 0, substr(hash('sha256', $key), 0, 16)));
    }
    
    /**
     * Descriptografar senha
     */
    private function decryptPassword(string $encryptedPassword): string
    {
        if (empty($encryptedPassword)) {
            return '';
        }
        
        $key = $_ENV['ENCRYPTION_KEY'] ?? 'default-key';
        $decrypted = openssl_decrypt(base64_decode($encryptedPassword), 'AES-256-CBC', $key, 0, substr(hash('sha256', $key), 0, 16));
        
        return $decrypted ?: '';
    }
}