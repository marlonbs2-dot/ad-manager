<?php
// Test CSRF token generation and validation
require __DIR__ . '/../vendor/autoload.php';

// Start session with same config as index.php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

use App\Security\CSRF;

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Generate token
    $token = CSRF::generateToken();
    
    echo json_encode([
        'action' => 'generate',
        'session_id' => session_id(),
        'csrf_token' => $token,
        'session_data' => $_SESSION,
        'cookies_received' => $_COOKIE,
        'headers' => getallheaders()
    ]);
    
} elseif ($method === 'POST') {
    // Validate token
    $input = json_decode(file_get_contents('php://input'), true);
    $receivedToken = $input['csrf_token'] ?? null;
    
    $isValid = CSRF::validateToken($receivedToken);
    
    echo json_encode([
        'action' => 'validate',
        'session_id' => session_id(),
        'received_token' => $receivedToken,
        'session_token' => $_SESSION['csrf_token'] ?? null,
        'is_valid' => $isValid,
        'session_data' => $_SESSION,
        'cookies_received' => $_COOKIE
    ]);
}
