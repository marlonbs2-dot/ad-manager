<?php
// Test session functionality
session_start();

header('Content-Type: application/json');

// Generate a test token
if (!isset($_SESSION['test_token'])) {
    $_SESSION['test_token'] = bin2hex(random_bytes(16));
}

echo json_encode([
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_save_path' => session_save_path(),
    'test_token' => $_SESSION['test_token'],
    'all_session_data' => $_SESSION,
    'cookie_params' => session_get_cookie_params(),
    'php_version' => PHP_VERSION
]);
