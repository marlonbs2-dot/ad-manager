<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\GroupService;
use App\Security\CSRF;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class GroupController extends BaseController
{
    private GroupService $groupService;

    public function __construct()
    {
        parent::__construct();
        $this->groupService = new GroupService();
    }

    public function index(Request $request, Response $response): Response
    {
        $this->requireAuth();
        
        ob_start();
        include __DIR__ . '/../../views/groups.php';
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
            $userDn = $this->getCurrentUserDn();
            $groups = $this->groupService->searchGroups($query, $userDn);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $groups
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
                'message' => 'Group DN is required'
            ], 400);
        }

        try {
            $userDn = $this->getCurrentUserDn();
            $group = $this->groupService->getGroup($dn, $userDn);

            if (!$group) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Group not found'
                ], 404);
            }

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $group
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addMember(Request $request, Response $response, array $args): Response
    {
        $this->requireAuth();

        $data = $request->getParsedBody();
        
        if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Invalid CSRF token'
            ], 403);
        }

        $groupDn = $this->base64UrlDecode($args['dn'] ?? '');
        $memberDn = $data['member_dn'] ?? '';

        if (empty($groupDn) || empty($memberDn)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Group DN and member DN are required'
            ], 400);
        }

        try {
            $userDn = $this->getCurrentUserDn();
            $user = $this->getCurrentUser();
            $username = $user['username'] ?? '';
            $ip = $this->getClientIp($request);

            $this->groupService->addMember($groupDn, $memberDn, $userDn, $username, $ip);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Member added successfully'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function removeMember(Request $request, Response $response, array $args): Response
    {
        $this->requireAuth();

        $data = $request->getParsedBody();
        $groupDn = $this->base64UrlDecode($args['dn'] ?? '');
        $memberDn = $data['member_dn'] ?? '';

        if (empty($groupDn) || empty($memberDn)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Group DN and member DN are required'
            ], 400);
        }
        
        if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Invalid CSRF token'
            ], 403);
        }

        try {
            $userDn = $this->getCurrentUserDn();
            $user = $this->getCurrentUser();
            $username = $user['username'] ?? '';
            $ip = $this->getClientIp($request);

            $this->groupService->removeMember($groupDn, $memberDn, $userDn, $username, $ip);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Member removed successfully'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function createGroup(Request $request, Response $response): Response
    {
        $this->requireAuth();

        $data = $request->getParsedBody();

        // Validação básica
        if (empty($data['name']) || empty($data['ou'])) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Nome do grupo e OU são obrigatórios.'
            ], 400);
        }

        if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Token CSRF inválido'
            ], 403);
        }

        try {
            $userDn = $this->getCurrentUserDn();
            $username = $this->getCurrentUser()['username'] ?? '';
            $ip = $this->getClientIp($request);

            $this->groupService->createGroup($data, $userDn, $username, $ip);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Grupo criado com sucesso!'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, ['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
