<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ComputerService;
use App\Security\CSRF;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ComputerController extends BaseController
{
    private ComputerService $computerService;

    public function __construct()
    {
        parent::__construct();
        $this->computerService = new ComputerService();
    }

    public function index(Request $request, Response $response): Response
    {
        $this->requireAuth();
        
        ob_start();
        include __DIR__ . '/../../views/computers.php';
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
            $computers = $this->computerService->searchComputers($query, $userDn);

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $computers
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
                'message' => 'Computer DN is required'
            ], 400);
        }

        try {
            $userDn = $this->getCurrentUserDn();
            $computer = $this->computerService->getComputer($dn, $userDn);

            if (!$computer) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Computer not found'
                ], 404);
            }

            return $this->jsonResponse($response, [
                'success' => true,
                'data' => $computer
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addComputerToGroup(Request $request, Response $response, array $args): Response
    {
        $this->requireAuth();

        $data = $request->getParsedBody();
        
        if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Invalid CSRF token'
            ], 403);
        }

        $groupDn = base64_decode($args['group_dn'] ?? ''); // group_dn is now in args
        $computerDn = $data['computer_dn'] ?? '';

        if (empty($groupDn) || empty($computerDn)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Group DN and Computer DN are required'
            ], 400);
        }

        try {
            $userDn = $this->getCurrentUserDn();
            $user = $this->getCurrentUser();
            $username = $user['username'] ?? '';
            $ip = $this->getClientIp($request);

            $this->computerService->addComputerToGroup($groupDn, $computerDn, $userDn, $username, $ip);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Computer added to group successfully'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Note: Removing a computer from a group is handled by GroupController::removeMember
    // as it's a generic member removal from a group.
    // The frontend will call /groups/{group_dn}/members with the computer's DN.
    // No specific removeComputerFromGroup method is needed here.

    public function delete(Request $request, Response $response, array $args): Response
    {
        $this->requireAuth();

        $data = $request->getParsedBody();
        
        if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Token CSRF inválido'
            ], 403);
        }

        $dn = $this->base64UrlDecode($args['dn'] ?? '');

        if (empty($dn)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'DN do computador é obrigatório'
            ], 400);
        }

        try {
            $userDn = $this->getCurrentUserDn();
            $username = $this->getCurrentUser()['username'] ?? '';
            $ip = $this->getClientIp($request);

            $this->computerService->deleteComputer($dn, $userDn, $username, $ip);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Computador excluído com sucesso'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}