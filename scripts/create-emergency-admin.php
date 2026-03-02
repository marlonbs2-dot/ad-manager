<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Services\AuthService;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "=== Criar Conta de Emergência ===\n\n";

$username = $_ENV['EMERGENCY_ADMIN_USER'] ?? null;
$password = $_ENV['EMERGENCY_ADMIN_PASSWORD'] ?? null;

if (!$username || !$password) {
    echo "ERRO: Configure EMERGENCY_ADMIN_USER e EMERGENCY_ADMIN_PASSWORD no arquivo .env\n";
    exit(1);
}

try {
    $authService = new AuthService();
    $authService->createEmergencyAccount($username, $password);
    
    echo "✓ Conta de emergência criada com sucesso!\n";
    echo "  Usuário: {$username}\n";
    echo "  Senha: (configurada no .env)\n\n";
    echo "IMPORTANTE: Esta conta deve ser usada apenas em emergências.\n";
    echo "Configure o Active Directory o mais rápido possível.\n";
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
