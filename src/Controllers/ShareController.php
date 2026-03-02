<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ShareServiceHybrid;
use App\Services\AuditService;
use App\Security\CSRF;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ShareController
{
    private ShareServiceHybrid $shareService;
    private AuditService $audit;

    public function __construct()
    {
        $this->shareService = new ShareServiceHybrid();
        $this->audit = new AuditService();
    }

    /**
     * Get client IP address
     */
    private function getClientIp(): string
    {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return '127.0.0.1';
    }

    /**
     * Exibe a página de gerenciamento de compartilhamentos
     */
    public function index(Request $request, Response $response): Response
    {
        // Verificar se usuário está logado
        if (!isset($_SESSION['user'])) {
            return $response
                ->withHeader('Location', '/login')
                ->withStatus(302);
        }
        
        $data = [
            'title' => 'Logs de Compartilhamentos',
            'user' => $_SESSION['user'] ?? null
        ];

        ob_start();
        require __DIR__ . '/../../views/shares.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response;
    }

    /**
     * API: Obter logs de compartilhamentos
     */
    public function getLogs(Request $request, Response $response): Response
    {
        // Verificar autenticação
        if (!isset($_SESSION['user'])) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Não autenticado'
            ]));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(401);
        }

        try {
            $params = $request->getQueryParams();
            
            $filters = [
                'server' => $params['server'] ?? '',
                'username' => $params['username'] ?? '',
                'action' => $params['action'] ?? '',
                'share_name' => $params['share_name'] ?? '',
                'date_from' => $params['date_from'] ?? '',
                'date_to' => $params['date_to'] ?? '',
            ];
            
            $page = (int) ($params['page'] ?? 1);
            $limit = (int) ($params['limit'] ?? 50);
            $offset = ($page - 1) * $limit;
            
            $logs = $this->shareService->getLogs($page, $limit, $filters);
            
            // O método getLogs já retorna a paginação
            $result = $logs;
            $logsData = $result['logs'] ?? [];
            $pagination = $result['pagination'] ?? [];
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $logsData,
                'pagination' => [
                    'current_page' => $pagination['page'] ?? $page,
                    'total_pages' => $pagination['pages'] ?? 1,
                    'total_records' => $pagination['total'] ?? 0,
                    'per_page' => $pagination['limit'] ?? $limit
                ]
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    /**
     * API: Sincronizar logs do servidor
     */
    public function syncLogs(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            
            // Validar CSRF token
            if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Token CSRF inválido'
                ]));
                
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(403);
            }
            
            $username = $_SESSION['user']['username'] ?? 'unknown';
            $ip = $this->getClientIp();
            
            $server = $data['server'] ?? '';
            $hours = (int) ($data['hours'] ?? 24);
            $shareFilter = $data['share_filter'] ?? '';
            
            $result = $this->shareService->syncLogsFromServer($server, $hours, $shareFilter);
            
            // Log da sincronização
            $this->audit->log(
                $username,
                'share_sync_logs',
                null,
                $ip,
                'success',
                [
                    'server' => $server,
                    'hours_synced' => $hours,
                    'logs_imported' => $result['imported'],
                    'logs_skipped' => $result['skipped']
                ]
            );
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => "Sincronização concluída: {$result['imported']} novos logs importados",
                'data' => $result
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            // Log de erro na sincronização
            $username = $_SESSION['user']['username'] ?? 'unknown';
            $ip = $this->getClientIp();
            
            $this->audit->log(
                $username,
                'share_sync_logs',
                null,
                $ip,
                'error',
                [
                    'server' => $data['server'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]
            );
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    /**
     * API: Obter estatísticas dos compartilhamentos
     */
    public function getStatistics(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            $days = (int) ($params['days'] ?? 7);
            
            $stats = $this->shareService->getStatistics($days);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $stats
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    /**
     * API: Exportar logs
     */
    public function exportLogs(Request $request, Response $response): Response
    {
        try {
            $params = $request->getQueryParams();
            
            $filters = [
                'server' => $params['server'] ?? '',
                'username' => $params['username'] ?? '',
                'action' => $params['action'] ?? '',
                'share_name' => $params['share_name'] ?? '',
                'date_from' => $params['date_from'] ?? '',
                'date_to' => $params['date_to'] ?? '',
            ];
            
            $format = $params['format'] ?? 'csv';
            
            $username = $_SESSION['user']['username'] ?? 'unknown';
            $ip = $this->getClientIp();
            
            $result = $this->shareService->exportLogs($filters, $format);
            
            // Log da exportação
            $this->audit->log(
                $username,
                'share_export_logs',
                null,
                $ip,
                'success',
                [
                    'format' => $format,
                    'filters' => $filters,
                    'records_exported' => $result['count']
                ]
            );
            
            // Definir headers para download
            $filename = 'share_logs_' . date('Y-m-d_His') . '.' . $format;
            
            return $response
                ->withHeader('Content-Type', $result['content_type'])
                ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->withBody(\Slim\Psr7\Factory\StreamFactory::create($result['content']));
                
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    /**
     * API: Obter lista de servidores
     */
    public function getServers(Request $request, Response $response): Response
    {
        try {
            $servers = $this->shareService->getServers();
            
            // Remover senhas da resposta
            foreach ($servers as &$server) {
                unset($server['password_encrypted']);
            }
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $servers
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    /**
     * API: Adicionar servidor
     */
    public function addServer(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            
            // Validar CSRF token
            if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Token CSRF inválido'
                ]));
                
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(403);
            }
            
            // Validar dados obrigatórios
            $required = ['name', 'hostname', 'username', 'password'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    $response->getBody()->write(json_encode([
                        'success' => false,
                        'message' => "Campo '$field' é obrigatório"
                    ]));
                    
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus(400);
                }
            }
            
            $username = $_SESSION['user']['username'] ?? 'unknown';
            $ip = $this->getClientIp();
            
            $result = $this->shareService->addServer($data);
            
            if ($result) {
                // Log da adição
                $this->audit->log(
                    $username,
                    'share_add_server',
                    null,
                    $ip,
                    'success',
                    [
                        'server_name' => $data['name'],
                        'hostname' => $data['hostname'],
                        'username' => $data['username']
                    ]
                );
                
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Servidor adicionado com sucesso'
                ]));
            } else {
                throw new \Exception('Falha ao adicionar servidor');
            }
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $username = $_SESSION['user']['username'] ?? 'unknown';
            $ip = $this->getClientIp();
            
            $this->audit->log(
                $username,
                'share_add_server',
                null,
                $ip,
                'error',
                [
                    'server_name' => $data['name'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]
            );
            
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    /**
     * API: Atualizar servidor
     */
    public function updateServer(Request $request, Response $response, array $args): Response
    {
        try {
            $serverId = (int) $args['id'];
            $data = $request->getParsedBody();
            
            // Validar CSRF token
            if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Token CSRF inválido'
                ]));
                
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(403);
            }
            
            $username = $_SESSION['user']['username'] ?? 'unknown';
            $ip = $this->getClientIp();
            
            $result = $this->shareService->updateServer($serverId, $data);
            
            if ($result) {
                // Log da atualização
                $this->audit->log(
                    $username,
                    'share_update_server',
                    null,
                    $ip,
                    'success',
                    [
                        'server_id' => $serverId,
                        'updated_fields' => array_keys($data)
                    ]
                );
                
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Servidor atualizado com sucesso'
                ]));
            } else {
                throw new \Exception('Falha ao atualizar servidor');
            }
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    /**
     * API: Remover servidor
     */
    public function deleteServer(Request $request, Response $response, array $args): Response
    {
        try {
            $serverId = (int) $args['id'];
            $data = $request->getParsedBody();
            
            // Validar CSRF token
            if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Token CSRF inválido'
                ]));
                
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(403);
            }
            
            $username = $_SESSION['user']['username'] ?? 'unknown';
            $ip = $this->getClientIp();
            
            $result = $this->shareService->deleteServer($serverId);
            
            if ($result) {
                // Log da remoção
                $this->audit->log(
                    $username,
                    'share_delete_server',
                    null,
                    $ip,
                    'success',
                    [
                        'server_id' => $serverId
                    ]
                );
                
                $response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Servidor removido com sucesso'
                ]));
            } else {
                throw new \Exception('Falha ao remover servidor');
            }
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    /**
     * API: Testar conexão com servidor
     */
    public function testServer(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            
            // Se for teste com dados do formulário (servidor novo)
            if (isset($data['hostname']) && isset($data['username']) && isset($data['password'])) {
                $result = $this->shareService->testServerConnectionWithData($data);
            } else {
                // Teste com servidor existente
                $serverName = $data['server_name'] ?? '';
                
                if (empty($serverName)) {
                    $response->getBody()->write(json_encode([
                        'success' => false,
                        'message' => 'Nome do servidor é obrigatório'
                    ]));
                    
                    return $response
                        ->withHeader('Content-Type', 'application/json')
                        ->withStatus(400);
                }
                
                $result = $this->shareService->testServerConnection($serverName);
            }
            
            $response->getBody()->write(json_encode($result));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }
}