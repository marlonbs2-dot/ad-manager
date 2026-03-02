<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\DhcpServiceHybrid;
use App\Services\AuditService;
use App\Security\CSRF;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class DhcpController
{
    private DhcpServiceHybrid $dhcpService;
    private AuditService $audit;

    public function __construct()
    {
        $this->dhcpService = new DhcpServiceHybrid();
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
                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                return $ip;
            }
        }
        
        return '127.0.0.1';
    }

    /**
     * Exibe a página de gerenciamento DHCP
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
            'title' => 'Gerenciamento DHCP',
            'user' => $_SESSION['user'] ?? null
        ];

        ob_start();
        require __DIR__ . '/../../views/dhcp.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response;
    }

    /**
     * API: Obter todos os escopos DHCP
     */
    public function getScopes(Request $request, Response $response): Response
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
            $scopes = $this->dhcpService->getScopes();
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => count($scopes) . ' escopos encontrados',
                'data' => $scopes
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    /**
     * API: Obter reservas de um escopo
     */
    public function getReservations(Request $request, Response $response, array $args): Response
    {
        try {
            $scopeId = urldecode($args['scopeId']);
            $reservations = $this->dhcpService->getScopeReservations($scopeId);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => count($reservations) . ' reservas encontradas',
                'data' => $reservations
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    /**
     * API: Obter leases (IPs distribuídos) de um escopo
     */
    public function getLeases(Request $request, Response $response, array $args): Response
    {
        try {
            $scopeId = urldecode($args['scopeId']);
            $leases = $this->dhcpService->getScopeLeases($scopeId);
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => count($leases) . ' leases encontrados',
                'data' => $leases
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ]));
            
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    /**
     * API: Criar nova reserva
     */
    public function createReservation(Request $request, Response $response): Response
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
            
            $this->dhcpService->createReservation(
                $data['scopeId'],
                $data['ipAddress'],
                $data['macAddress'],
                $data['name'],
                $data['description'] ?? ''
            );
            
            // Log successful reservation creation
            $this->audit->log(
                $username,
                'dhcp_create_reservation',
                null, // DHCP operations don't have DN
                $ip,
                'success',
                [
                    'scope_id' => $data['scopeId'],
                    'ip_address' => $data['ipAddress'],
                    'mac_address' => $data['macAddress'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? ''
                ]
            );
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Reserva criada com sucesso'
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            // Log failed reservation creation
            $username = $_SESSION['user']['username'] ?? 'unknown';
            $ip = $this->getClientIp();
            
            $this->audit->log(
                $username,
                'dhcp_create_reservation',
                null,
                $ip,
                'error',
                [
                    'scope_id' => $data['scopeId'] ?? 'unknown',
                    'ip_address' => $data['ipAddress'] ?? 'unknown',
                    'mac_address' => $data['macAddress'] ?? 'unknown',
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
     * API: Remover reserva (via POST)
     */
    public function deleteReservationPost(Request $request, Response $response): Response
    {
        error_log('=== DELETE RESERVATION POST START ===');
        error_log('Method: ' . $request->getMethod());
        error_log('URI: ' . $request->getUri()->getPath());
        error_log('Headers: ' . json_encode($request->getHeaders()));
        
        try {
            $data = $request->getParsedBody();
            error_log('Parsed Body: ' . json_encode($data));
            
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
            
            $scopeId = $data['scopeId'] ?? '';
            $ipAddress = $data['ipAddress'] ?? '';
            
            if (empty($scopeId) || empty($ipAddress)) {
                $response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'ScopeId e IpAddress são obrigatórios'
                ]));
                
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(400);
            }
            
            $username = $_SESSION['user']['username'] ?? 'unknown';
            $ip = $this->getClientIp();
            
            $this->dhcpService->deleteReservation($scopeId, $ipAddress);
            
            // Log successful reservation deletion
            $this->audit->log(
                $username,
                'dhcp_delete_reservation',
                null, // DHCP operations don't have DN
                $ip,
                'success',
                [
                    'scope_id' => $scopeId,
                    'ip_address' => $ipAddress
                ]
            );
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Reserva removida com sucesso'
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log('DELETE POST ERROR: ' . $e->getMessage());
            error_log('DELETE POST TRACE: ' . $e->getTraceAsString());
            // Log failed reservation deletion
            $username = $_SESSION['user']['username'] ?? 'unknown';
            $ip = $this->getClientIp();
            
            $this->audit->log(
                $username,
                'dhcp_delete_reservation',
                null,
                $ip,
                'error',
                [
                    'scope_id' => $data['scopeId'] ?? 'unknown',
                    'ip_address' => $data['ipAddress'] ?? 'unknown',
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
     * API: Remover reserva
     */
    public function deleteReservation(Request $request, Response $response, array $args): Response
    {
        try {
            $scopeId = urldecode($args['scopeId']);
            $ipAddress = urldecode($args['ipAddress']);
            
            // Para DELETE, o token pode vir no header ou query string
            $csrfToken = $request->getHeaderLine('X-CSRF-Token') 
                      ?: $request->getQueryParams()['csrf_token'] ?? null;
            
            if (!CSRF::validateToken($csrfToken)) {
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
            
            $this->dhcpService->deleteReservation($scopeId, $ipAddress);
            
            // Log successful reservation deletion
            $this->audit->log(
                $username,
                'dhcp_delete_reservation',
                null, // DHCP operations don't have DN
                $ip,
                'success',
                [
                    'scope_id' => $scopeId,
                    'ip_address' => $ipAddress
                ]
            );
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Reserva removida com sucesso'
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            // Log failed reservation deletion
            $username = $_SESSION['user']['username'] ?? 'unknown';
            $ip = $this->getClientIp();
            
            $this->audit->log(
                $username,
                'dhcp_delete_reservation',
                null,
                $ip,
                'error',
                [
                    'scope_id' => $scopeId ?? 'unknown',
                    'ip_address' => $ipAddress ?? 'unknown',
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
     * API: Editar reserva existente
     */
    public function updateReservation(Request $request, Response $response, array $args): Response
    {
        try {
            $scopeId = urldecode($args['scopeId']);
            $currentIpAddress = urldecode($args['ipAddress']);
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
            
            $this->dhcpService->updateReservation(
                $scopeId,
                $currentIpAddress,
                $data['ipAddress'],
                $data['macAddress'],
                $data['name'],
                $data['description'] ?? ''
            );
            
            // Log successful reservation update
            $this->audit->log(
                $username,
                'dhcp_update_reservation',
                null, // DHCP operations don't have DN
                $ip,
                'success',
                [
                    'scope_id' => $scopeId,
                    'old_ip_address' => $currentIpAddress,
                    'new_ip_address' => $data['ipAddress'],
                    'mac_address' => $data['macAddress'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?? ''
                ]
            );
            
            $response->getBody()->write(json_encode([
                'success' => true,
                'message' => 'Reserva atualizada com sucesso'
            ]));
            
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            // Log failed reservation update
            $username = $_SESSION['user']['username'] ?? 'unknown';
            $ip = $this->getClientIp();
            
            $this->audit->log(
                $username,
                'dhcp_update_reservation',
                null,
                $ip,
                'error',
                [
                    'scope_id' => $scopeId ?? 'unknown',
                    'old_ip_address' => $currentIpAddress ?? 'unknown',
                    'new_ip_address' => $data['ipAddress'] ?? 'unknown',
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
}
