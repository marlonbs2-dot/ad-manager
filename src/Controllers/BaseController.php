<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class BaseController
{
    /**
     * Tempo de inatividade da sessão em segundos.
     * Usa SESSION_TIMEOUT do .env ou padrão de 2 horas (7200 segundos).
     */
    private function getSessionTimeout(): int
    {
        return (int)($_ENV['SESSION_TIMEOUT'] ?? 7200);
    }

    public function __construct()
    {
        // A sessão já foi iniciada no index.php, não precisa iniciar novamente
        // Isso evita conflito de configurações
    }

    /**
     * Verifica se o usuário está autenticado e se a sessão não expirou.
     * Redireciona para a página de login se não estiver autenticado.
     */
    protected function requireAuth(): void
    {
        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit();
        }

        $this->checkSessionTimeout();
    }

    /**
     * Verifica o tempo de inatividade da sessão.
     */
    private function checkSessionTimeout(): void
    {
        $timeout = $this->getSessionTimeout();
        
        if (isset($_SESSION['last_activity'])) {
            $elapsedTime = time() - $_SESSION['last_activity'];

            if ($elapsedTime > $timeout) {
                // Destruir a sessão se o tempo de inatividade for excedido
                session_unset();
                session_destroy();
                header('Location: /login?status=session_expired');
                exit();
            }
        }

        // Atualiza o tempo da última atividade
        $_SESSION['last_activity'] = time();
    }

    /**
     * Helper para retornar respostas JSON.
     */
    protected function jsonResponse(Response $response, array $data, int $statusCode = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($statusCode);
    }

    /**
     * Retorna os dados do usuário logado.
     */
    protected function getCurrentUser(): array
    {
        return $_SESSION['user'] ?? [];
    }

    /**
     * Retorna o DN (Distinguished Name) do usuário logado.
     */
    protected function getCurrentUserDn(): string
    {
        return $_SESSION['user_dn'] ?? '';
    }

    /**
     * Retorna o endereço IP do cliente.
     */
    protected function getClientIp(Request $request): string
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

    /**
     * Decodifica Base64 URL-safe (usado para DNs com caracteres especiais).
     * Converte Base64 URL-safe de volta para Base64 padrão e decodifica.
     */
    protected function base64UrlDecode(string $input): string
    {
        // Converter URL-safe base64 para base64 padrão
        $base64 = strtr($input, '-_', '+/');
        
        // Adicionar padding se necessário
        $remainder = strlen($base64) % 4;
        if ($remainder) {
            $base64 .= str_repeat('=', 4 - $remainder);
        }
        
        return base64_decode($base64);
    }
}