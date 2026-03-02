<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\TwoFactorService;
use App\Security\CSRF;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    private AuthService $authService;
    private TwoFactorService $twoFactorService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->twoFactorService = new TwoFactorService();
    }

    private function logDebug(string $message): void
    {
        file_put_contents(
            '/var/www/html/logs/auth-debug.log',
            date('[Y-m-d H:i:s] ') . $message . PHP_EOL,
            FILE_APPEND
        );
    }

    public function showLogin(Request $request, Response $response): Response
    {
        if (isset($_SESSION['user'])) {
            return $response
                ->withHeader('Location', '/dashboard')
                ->withStatus(302);
        }

        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Generate CSRF token
        $csrfToken = CSRF::generateToken();
        
        // Debug log
        $this->logDebug('=== SHOW LOGIN PAGE ===');
        $this->logDebug('Session ID: ' . session_id());
        $this->logDebug('CSRF Token generated: ' . $csrfToken);
        $this->logDebug('Session data: ' . json_encode($_SESSION));
        
        // Start output buffering and include view
        ob_start();
        include __DIR__ . '/../../views/login.php';
        $html = ob_get_clean();

        $response->getBody()->write($html);
        return $response;
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        // Debug logging
        $this->logDebug('=== LOGIN ATTEMPT ===');
        $this->logDebug('Username: ' . ($data['username'] ?? 'null'));
        $this->logDebug('CSRF Token recebido: ' . ($data['csrf_token'] ?? 'null'));
        $this->logDebug('CSRF Token na sessão: ' . ($_SESSION['csrf_token'] ?? 'null'));
        $this->logDebug('Dados recebidos: ' . json_encode($data));
        
        // Validate CSRF
        if (!CSRF::validateToken($data['csrf_token'] ?? null)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Invalid CSRF token',
                'debug' => [
                    'received_token' => $data['csrf_token'] ?? null,
                    'session_token' => $_SESSION['csrf_token'] ?? null,
                    'session_id' => session_id()
                ]
            ], 403);
        }

        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        $ip = $this->getClientIp($request);

        if (empty($username) || empty($password)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Username and password are required'
            ], 400);
        }

        try {
            $this->logDebug('Calling authService->authenticate()...');
            $user = $this->authService->authenticate($username, $password, $ip);
            $this->logDebug('Authentication result: ' . ($user ? 'SUCCESS' : 'FAILED'));

            if (!$user) {
                $this->logDebug('User is null - returning 401');
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Invalid credentials or insufficient permissions'
                ], 401);
            }
            
            $this->logDebug('User authenticated: ' . json_encode($user));

            // Check if 2FA is enabled
            if ($this->twoFactorService->isTrustedDevice((int)$user['id'])) {
                // Device is trusted, bypass 2FA
                $_SESSION['user'] = $user;
                $_SESSION['user_dn'] = $user['dn'] ?? '';
                session_regenerate_id(true);
                return $this->jsonResponse($response, ['success' => true, 'redirect' => '/dashboard']);
            }
            if ($this->twoFactorService->is2FAEnabled((int)$user['id'])) {
                // Store temporary session for 2FA verification
                $_SESSION['2fa_user_id'] = $user['id'];
                $_SESSION['2fa_user_data'] = $user;
                
                return $this->jsonResponse($response, [
                    'success' => true,
                    'requires_2fa' => true,
                    'message' => 'Digite o código de autenticação'
                ]);
            }

            // No 2FA required - complete login
            $_SESSION['user'] = $user;
            $_SESSION['user_dn'] = $user['dn'] ?? '';
            
            $this->logDebug('Stored user_dn in session: ' . $_SESSION['user_dn']);
            
            // Regenerate session ID for security
            session_regenerate_id(true);

            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Login successful',
                'redirect' => '/dashboard'
            ]);

        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function verify2FA(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        
        $this->logDebug('=== VERIFY 2FA ===');
        $this->logDebug('Data received: ' . json_encode($data));
        $this->logDebug('Session 2fa_user_id: ' . ($_SESSION['2fa_user_id'] ?? 'NOT SET'));
        
        // Check if we have a pending 2FA session
        if (!isset($_SESSION['2fa_user_id'])) {
            $this->logDebug('No pending 2FA session');
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'No pending 2FA verification'
            ], 400);
        }
        
        $userId = (int)$_SESSION['2fa_user_id'];
        $code = $data['code'] ?? '';
        $trustDevice = (bool)($data['trust_device'] ?? false);
        
        $this->logDebug("User ID: $userId");
        $this->logDebug("Code: $code");
        $this->logDebug("Code length: " . strlen($code));
        $this->logDebug("Trust device: " . ($trustDevice ? 'yes' : 'no'));
        
        if (empty($code)) {
            $this->logDebug('Code is empty');
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Código é obrigatório'
            ], 400);
        }
        
        try {
            // Validate 2FA code
            $this->logDebug('Calling validate2FACode...');
            $isValid = $this->twoFactorService->validate2FACode($userId, $code);
            $this->logDebug('Validation result: ' . ($isValid ? 'VALID' : 'INVALID'));
            
            if (!$isValid) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Código inválido ou expirado'
                ], 401);
            }
            
            // 2FA successful - complete login
            $user = $_SESSION['2fa_user_data'];
            $_SESSION['user'] = $user;
            $_SESSION['user_dn'] = $user['dn'] ?? '';
            
            // Set trust cookie if requested
            if ($trustDevice) {
                $this->twoFactorService->trustDevice($userId);
            }
            
            // Clean up 2FA session data
            unset($_SESSION['2fa_user_id']);
            unset($_SESSION['2fa_user_data']);
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Autenticação completa',
                'redirect' => '/dashboard'
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request, Response $response): Response
    {
        session_destroy();
        
        return $response
            ->withHeader('Location', '/login')
            ->withStatus(302);
    }

    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();
        
        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $serverParams['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($serverParams['HTTP_X_REAL_IP'])) {
            $ip = $serverParams['HTTP_X_REAL_IP'];
        } else {
            $ip = $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
        }

        return trim($ip);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
