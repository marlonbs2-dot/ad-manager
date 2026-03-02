<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Middleware para capturar qualquer saída inesperada (warnings, notices, etc)
 * que possa interferir nas respostas JSON da API
 */
class OutputBufferMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Iniciar buffer de saída para capturar qualquer output indesejado
        ob_start();
        
        try {
            // Processar a requisição
            $response = $handler->handle($request);
            
            // Capturar qualquer saída inesperada
            $unexpectedOutput = ob_get_clean();
            
            // Se houver saída inesperada, logar para debug
            if (!empty($unexpectedOutput)) {
                error_log('Unexpected output captured: ' . $unexpectedOutput);
            }
            
            return $response;
            
        } catch (\Throwable $e) {
            // Limpar o buffer em caso de erro
            ob_end_clean();
            throw $e;
        }
    }
}
