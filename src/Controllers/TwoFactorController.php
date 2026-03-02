<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\TwoFactorService;
use App\Security\CSRF;
use App\Security\TOTP;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TwoFactorController extends BaseController
{
    private TwoFactorService $twoFactorService;

    public function __construct()
    {
        parent::__construct();
        $this->twoFactorService = new TwoFactorService();
    }

    /**
     * Show 2FA setup page
     */
    public function showSetup(Request $request, Response $response): Response
    {
        $this->requireAuth();
        
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $is2FAEnabled = $this->twoFactorService->is2FAEnabled($userId);
        $backupCodesCount = $this->twoFactorService->getBackupCodesCount($userId);
        
        ob_start();
        $title = 'Autenticação de Dois Fatores - AD Manager';
        include __DIR__ . '/../../views/2fa-setup.php';
        $content = ob_get_clean();
        
        $response->getBody()->write($content);
        return $response;
    }

    /**
     * Enable 2FA - Generate secret and QR code
     */
    public function enable(Request $request, Response $response): Response
    {
        $this->requireAuth();
        
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $username = $_SESSION['user']['username'] ?? 'user';
        
        try {
            $result = $this->twoFactorService->enable2FA($userId);
            
            // Store the temporary secret in the session for verification
            $_SESSION['2fa_temp_secret'] = $result['secret'];
            $_SESSION['2fa_temp_hashed_backup_codes'] = $result['hashed_backup_codes'];

            // Generate the provisioning URI for the QR code
            $provisioningUri = TOTP::getProvisioningUri($result['secret'], $username, 'AD Manager');
            
            return $this->jsonResponse($response, [
                'success' => true,
                'secret' => $result['secret'],
                'username' => $username,
                'backup_codes' => $result['backup_codes'],
                'provisioning_uri' => $provisioningUri
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Ocorreu um erro inesperado. Verifique se o relógio do servidor e do seu celular estão sincronizados. Detalhe: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify and activate 2FA
     */
    public function verify(Request $request, Response $response): Response
    {
        $this->requireAuth();
        
        $data = $request->getParsedBody();
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $code = $data['code'] ?? '';
        $secret = $_SESSION['2fa_temp_secret'] ?? null;
        
        if (empty($code)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Código é obrigatório'
            ], 400);
        }

        if (empty($secret)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Sessão de configuração 2FA expirou. Por favor, comece novamente.'
            ], 400);
        }
        
        try {
            if (!$this->twoFactorService->verify2FA($userId, $code, $secret)) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Código inválido. Verifique e tente novamente.'
                ], 400);
            }
            
            // Clean up temporary session data after verification
            unset($_SESSION['2fa_temp_secret']);
            unset($_SESSION['2fa_temp_hashed_backup_codes']);
            
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Autenticação de dois fatores ativada com sucesso!'
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disable 2FA
     */
    public function disable(Request $request, Response $response): Response
    {
        $this->requireAuth();
        
        $data = $request->getParsedBody();
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $code = $data['code'] ?? '';
        
        if (empty($code)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Código é obrigatório para desativar 2FA'
            ], 400);
        }
        
        try {
            if (!$this->twoFactorService->disable2FA($userId, $code)) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Código inválido'
                ], 400);
            }
            
            return $this->jsonResponse($response, [
                'success' => true,
                'message' => 'Autenticação de dois fatores desativada'
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Regenerate backup codes
     */
    public function regenerateBackupCodes(Request $request, Response $response): Response
    {
        $this->requireAuth();
        
        $data = $request->getParsedBody();
        $userId = (int)($_SESSION['user']['id'] ?? 0);
        $code = $data['code'] ?? '';
        
        if (empty($code)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Código é obrigatório'
            ], 400);
        }
        
        try {
            $backupCodes = $this->twoFactorService->regenerateBackupCodes($userId, $code);
            
            if (!$backupCodes) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Código inválido'
                ], 400);
            }
            
            return $this->jsonResponse($response, [
                'success' => true,
                'backup_codes' => $backupCodes,
                'message' => 'Códigos de backup regenerados com sucesso'
            ]);
            
        } catch (\Exception $e) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
