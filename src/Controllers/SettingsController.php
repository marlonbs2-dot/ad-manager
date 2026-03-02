<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ADConfigService;
use App\Services\SettingsService;
use App\LDAP\LDAPConnection;
use App\Security\CSRF;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SettingsController extends BaseController
{
    private ADConfigService $adConfig;
    private SettingsService $settings;

    public function __construct()
    {
        parent::__construct();
        $this->adConfig = new ADConfigService();
        $this->settings = new SettingsService();
    }

    public function index(Request $request, Response $response): Response
    {
        $this->requireAuth();

        ob_start();
        include __DIR__ . '/../../views/settings.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response;
    }

    public function getConfig(Request $request, Response $response): Response
    {
        $this->requireAuth();

        try {
            $config = $this->adConfig->getActiveConfig();

            if (!$config) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'No configuration found'
                ], 404);
            }

            // Remove sensitive data
            unset($config['bind_password_enc']);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $config
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function saveConfig(Request $request, Response $response): Response
    {
        $this->requireAuth();

        $data = $request->getParsedBody();

        if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Invalid CSRF token'
            ], 403);
        }

        // Validate required fields
        $required = ['host', 'base_dn', 'bind_dn', 'bind_password', 'admin_ou'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => "Field '$field' is required"
                ], 400);
            }
        }

        try {
            $configData = [
                'host' => $data['host'],
                'port' => (int) ($data['port'] ?? 389),
                'protocol' => $data['protocol'] ?? 'ldap',
                'use_tls' => (int) (($data['use_tls'] ?? 'false') === 'true' || $data['use_tls'] === '1' || $data['use_tls'] === 1),
                'base_dn' => $data['base_dn'],
                'bind_dn' => $data['bind_dn'],
                'bind_password' => $data['bind_password'],
                'admin_ou' => $data['admin_ou'],
                'ou_reset_password' => $data['ou_reset_password'] ?? [],
                'ou_manage_groups' => $data['ou_manage_groups'] ?? [],
                'connection_timeout' => (int) ($data['connection_timeout'] ?? 10)
            ];

            $id = $this->adConfig->saveConfig($configData);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Configuration saved successfully',
                'id' => $id
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function testConnection(Request $request, Response $response): Response
    {
        $this->requireAuth();

        $data = $request->getParsedBody();

        if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Invalid CSRF token'
            ], 403);
        }

        try {
            // Use provided config or active config
            if (!empty($data['host'])) {
                $config = [
                    'host' => $data['host'],
                    'port' => (int) ($data['port'] ?? 389),
                    'protocol' => $data['protocol'] ?? 'ldap',
                    'use_tls' => ($data['use_tls'] ?? 'false') === 'true',
                    'base_dn' => $data['base_dn'],
                    'bind_dn' => $data['bind_dn'],
                    'bind_password_enc' => (new \App\Security\Encryption())->encrypt($data['bind_password']),
                    'connection_timeout' => (int) ($data['connection_timeout'] ?? 10)
                ];
            } else {
                $config = $this->adConfig->getActiveConfig();

                if (!$config) {
                    return $this->jsonResponse($response, [
                        'success' => false,
                        'message' => 'No configuration found'
                    ], 404);
                }
            }

            $ldap = new LDAPConnection($config);
            $result = $ldap->testConnection();

            return $this->jsonResponse($response, $result);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obter configurações das APIs
     */
    public function getApiConfig(Request $request, Response $response): Response
    {
        $this->requireAuth();

        try {
            $config = $this->settings->getApiSettings();

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $config
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Salvar configurações das APIs
     */
    public function saveApiConfig(Request $request, Response $response): Response
    {
        $this->requireAuth();

        try {
            $data = $request->getParsedBody();

            // Validar CSRF token
            if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ], 403);
            }

            // Validar dados obrigatórios
            if (empty($data['dhcp_api_url']) || empty($data['share_api_url'])) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'URLs das APIs são obrigatórias'
                ]);
            }

            // Validar formato das URLs
            if (
                !filter_var($data['dhcp_api_url'], FILTER_VALIDATE_URL) ||
                !filter_var($data['share_api_url'], FILTER_VALIDATE_URL)
            ) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'URLs das APIs devem ter formato válido'
                ]);
            }

            // Preparar dados para salvar
            $apiSettings = [
                'dhcp_api_url' => trim($data['dhcp_api_url']),
                'dhcp_api_key' => trim($data['dhcp_api_key'] ?? ''),
                'share_api_url' => trim($data['share_api_url']),
                'dhcp_api_enabled' => isset($data['dhcp_api_enabled']) ? '1' : '0',
                'share_api_enabled' => isset($data['share_api_enabled']) ? '1' : '0',
                'api_timeout' => max(5, min(120, (int) ($data['api_timeout'] ?? 30))),
                'api_retry_attempts' => max(1, min(10, (int) ($data['api_retry_attempts'] ?? 3)))
            ];

            // Salvar configurações
            $success = $this->settings->saveApiSettings($apiSettings);

            if ($success) {
                // Limpar cache de configurações
                SettingsService::clearCache();

                return $this->jsonResponse($response, [
                    'success' => true,
                    'message' => 'Configurações das APIs salvas com sucesso'
                ]);
            } else {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Erro ao salvar configurações das APIs'
                ], 500);
            }

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ─── PRINT SERVERS CRUD ───────────────────────────────────────────────────

    /** GET /settings/print-servers — Lista todos os servidores de impressão */
    public function getPrintServers(Request $request, Response $response): Response
    {
        $this->requireAuth();
        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $this->settings->getPrintServers()
        ]);
    }

    /** POST /settings/print-servers — Adiciona servidor */
    public function addPrintServer(Request $request, Response $response): Response
    {
        $this->requireAuth();
        $data = $request->getParsedBody();
        if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
            return $this->jsonResponse($response, ['success' => false, 'message' => 'Token CSRF inválido'], 403);
        }
        if (empty($data['name']) || empty($data['url']) || empty($data['api_key'])) {
            return $this->jsonResponse($response, ['success' => false, 'message' => 'Nome, URL e API Key são obrigatórios'], 400);
        }
        if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
            return $this->jsonResponse($response, ['success' => false, 'message' => 'URL inválida'], 400);
        }
        $ok = $this->settings->addPrintServer(
            trim($data['name']),
            trim($data['url']),
            trim($data['api_key']),
            trim($data['description'] ?? ''),
            ($data['enabled'] ?? '1') === '1'
        );
        return $this->jsonResponse($response, $ok
            ? ['success' => true, 'message' => 'Servidor adicionado com sucesso']
            : ['success' => false, 'message' => 'Erro ao salvar no banco de dados'], $ok ? 200 : 500);
    }

    /** PUT /settings/print-servers/{id} — Atualiza servidor */
    public function updatePrintServer(Request $request, Response $response, array $args): Response
    {
        $this->requireAuth();
        $data = $request->getParsedBody();
        if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
            return $this->jsonResponse($response, ['success' => false, 'message' => 'Token CSRF inválido'], 403);
        }
        if (empty($data['name']) || empty($data['url']) || empty($data['api_key'])) {
            return $this->jsonResponse($response, ['success' => false, 'message' => 'Nome, URL e API Key são obrigatórios'], 400);
        }
        $ok = $this->settings->updatePrintServer(
            (int) $args['id'],
            trim($data['name']),
            trim($data['url']),
            trim($data['api_key']),
            trim($data['description'] ?? ''),
            ($data['enabled'] ?? '1') === '1'
        );
        return $this->jsonResponse($response, $ok
            ? ['success' => true, 'message' => 'Servidor atualizado com sucesso']
            : ['success' => false, 'message' => 'Erro ao atualizar'], $ok ? 200 : 500);
    }

    /** DELETE /settings/print-servers/{id} — Remove servidor */
    public function deletePrintServer(Request $request, Response $response, array $args): Response
    {
        $this->requireAuth();
        $csrfToken = $request->getHeaderLine('X-CSRF-Token') ?: ($request->getQueryParams()['csrf_token'] ?? null);
        if (!CSRF::validateToken($csrfToken)) {
            return $this->jsonResponse($response, ['success' => false, 'message' => 'Token CSRF inválido'], 403);
        }
        $ok = $this->settings->deletePrintServer((int) $args['id']);
        return $this->jsonResponse($response, $ok
            ? ['success' => true, 'message' => 'Servidor removido com sucesso']
            : ['success' => false, 'message' => 'Erro ao remover'], $ok ? 200 : 500);
    }

    /** POST /settings/print-servers/test — Testa conectividade */
    public function testPrintServer(Request $request, Response $response): Response
    {
        $this->requireAuth();
        $data = $request->getParsedBody();
        $url = trim($data['url'] ?? '');
        $key = trim($data['api_key'] ?? '');
        if (empty($url) || empty($key)) {
            return $this->jsonResponse($response, ['success' => false, 'message' => 'URL e API Key são obrigatórios'], 400);
        }
        return $this->jsonResponse($response, \App\Services\PrintService::testConnection($url, $key));
    }

    /**
     * Testar conectividade com uma API
     */
    public function testApiConnection(Request $request, Response $response): Response
    {
        $this->requireAuth();

        try {
            $data = $request->getParsedBody();
            $apiType = $data['api_type'] ?? '';
            $url = $data['url'] ?? '';

            if (empty($url)) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'URL da API é obrigatória'
                ]);
            }

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'URL da API deve ter formato válido'
                ]);
            }

            // Testar conectividade
            $result = $this->settings->testApiConnection($url);

            return $this->jsonResponse($response, $result);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
