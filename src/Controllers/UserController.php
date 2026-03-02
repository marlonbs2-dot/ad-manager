<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\UserService;
use App\Security\CSRF;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController extends BaseController
{
    private UserService $userService;

    public function __construct()
    {
        parent::__construct();
        $this->userService = new UserService();
    }

    public function index(Request $request, Response $response): Response
    {
        $this->requireAuth();
        
        ob_start();
        include __DIR__ . '/../../views/users.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response;
    }

    public function search(Request $request, Response $response): Response
    {
        $this->requireAuth();

        $params = $request->getQueryParams();
        $query = $params['q'] ?? '';

        if (empty($query)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Search query is required'
            ], 400);
        }

        try {
            $userDn = $_SESSION['user_dn'] ?? '';
            $users = $this->userService->searchUsers($query, $userDn);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $users
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function get(Request $request, Response $response, array $args): Response
    {
        $this->requireAuth();

        $dn = $this->base64UrlDecode($args['dn'] ?? '');

        if (empty($dn)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'User DN is required'
            ], 400);
        }

        try {
            $userDn = $_SESSION['user_dn'] ?? '';
            $user = $this->userService->getUser($dn, $userDn);

            if (!$user) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $user
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function resetPassword(Request $request, Response $response, array $args): Response
    {
        $this->requireAuth();

        $data = $request->getParsedBody();
        
        if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Invalid CSRF token'
            ], 403);
        }

        $dn = $this->base64UrlDecode($args['dn'] ?? '');
        $newPassword = $data['password'] ?? '';
        $generatePassword = ($data['generate'] ?? 'false') === 'true';
        $mustChange = ($data['must_change'] ?? 'true') === 'true';

        if (empty($dn)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'User DN is required'
            ], 400);
        }

        if ($generatePassword) {
            $newPassword = $this->userService->generatePassword();
        } elseif (empty($newPassword)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Password is required'
            ], 400);
        }

        try {
            $userDn = $_SESSION['user_dn'] ?? '';
            $username = $_SESSION['user']['username'] ?? '';
            $ip = $this->getClientIp($request);

            $this->userService->resetPassword(
                $dn,
                $newPassword,
                $mustChange,
                $userDn,
                $username,
                $ip
            );

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Password reset successfully',
                'password' => $generatePassword ? $newPassword : null
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function enable(Request $request, Response $response, array $args): Response
    {
        $this->requireAuth();

        $data = $request->getParsedBody();
        
        if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Invalid CSRF token'
            ], 403);
        }

        $dn = $this->base64UrlDecode($args['dn'] ?? '');

        if (empty($dn)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'User DN is required'
            ], 400);
        }

        try {
            $userDn = $_SESSION['user_dn'] ?? '';
            $username = $_SESSION['user']['username'] ?? '';
            $ip = $this->getClientIp($request);

            $this->userService->enableUser($dn, $userDn, $username, $ip);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'User enabled successfully'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function disable(Request $request, Response $response, array $args): Response
    {
        $this->requireAuth();

        $data = $request->getParsedBody();
        
        if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Invalid CSRF token'
            ], 403);
        }

        $dn = $this->base64UrlDecode($args['dn'] ?? '');

        if (empty($dn)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'User DN is required'
            ], 400);
        }

        try {
            $userDn = $_SESSION['user_dn'] ?? '';
            $username = $_SESSION['user']['username'] ?? '';
            $ip = $this->getClientIp($request);

            $this->userService->disableUser($dn, $userDn, $username, $ip);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'User disabled successfully'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getOUs(Request $request, Response $response): Response
    {
        $this->requireAuth();

        try {
            $ous = $this->userService->searchOUs();

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $ous
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function createUser(Request $request, Response $response): Response
    {
        try {
            $this->requireAuth();

            $data = $request->getParsedBody();
            
            // Validar dados básicos
            if (empty($data['username'])) {
                return $this->jsonResponse($response, ['success' => false, 'message' => 'Nome de usuário é obrigatório'], 400);
            }
            
            if (empty($data['password'])) {
                return $this->jsonResponse($response, ['success' => false, 'message' => 'Senha é obrigatória'], 400);
            }
            
            if (empty($data['ou'])) {
                return $this->jsonResponse($response, ['success' => false, 'message' => 'OU (Unidade Organizacional) é obrigatória'], 400);
            }
            
            if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
                return $this->jsonResponse($response, ['success' => false, 'message' => 'Token CSRF inválido'], 403);
            }

            $userDn = $this->getCurrentUserDn();
            $username = $this->getCurrentUser()['username'] ?? '';
            $ip = $this->getClientIp($request);

            // Verificar se está copiando de outro usuário
            $copyGroups = !empty($data['copy_groups']);
            
            $this->userService->createUser($data, $userDn, $username, $ip, $copyGroups);

            return $this->jsonResponse($response, ['success' => true, 'message' => 'Usuário criado com sucesso!']);
            
        } catch (\RuntimeException $e) {
            // Erros de negócio (permissões, validações)
            return $this->jsonResponse($response, ['success' => false, 'message' => $e->getMessage()], 400);
        } catch (\Exception $e) {
            // Erros inesperados - loga no arquivo de debug da aplicação
            error_log('Error creating user: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->jsonResponse($response, ['success' => false, 'message' => 'Erro ao criar usuário: ' . $e->getMessage()], 500);
        }
    }
    
    public function getUserForCopy(Request $request, Response $response, array $args): Response
    {
        $this->requireAuth();

        $dn = $this->base64UrlDecode($args['dn'] ?? '');

        if (empty($dn)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'User DN is required'
            ], 400);
        }

        try {
            $userDn = $_SESSION['user_dn'] ?? '';
            $user = $this->userService->getUser($dn, $userDn);

            if (!$user) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Retornar dados úteis para copiar
            return $this->jsonResponse($response, [
                'success' => true,
                'data' => [
                    'department' => $user['department'] ?? '',
                    'title' => $user['title'] ?? '',
                    'ou' => $this->extractOU($dn),
                    'groups' => $user['groups'] ?? [],
                    'source_dn' => $dn,
                    'source_name' => $user['display_name'] ?? $user['username']
                ]
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    private function extractOU(string $dn): string
    {
        // Extrair a OU do DN (remover o CN)
        $parts = explode(',', $dn, 2);
        return $parts[1] ?? '';
    }
}
