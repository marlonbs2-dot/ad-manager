<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Dotenv\Dotenv;

// Disable PHP error display to prevent HTML errors in JSON responses
// This must be done BEFORE any other code that might generate errors
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// Timezone da aplicação
date_default_timezone_set('America/Sao_Paulo');

// Log errors to file instead of displaying them
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_errors.log');

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Start session before any output
// Configure session settings
// Start session before any output
// Configure session settings
if (session_status() === PHP_SESSION_NONE) {
    // Session save path to a writable directory
    $sessionPath = '/var/www/html/storage/sessions';
    if (!file_exists($sessionPath)) {
        mkdir($sessionPath, 0777, true);
    }

    // Configure session storage
    ini_set('session.save_handler', 'files');
    ini_set('session.save_path', $sessionPath);
    ini_set('session.gc_maxlifetime', 86400); // 24 hours
    ini_set('session.gc_probability', 1);
    ini_set('session.gc_divisor', 100);

    // Cookie parameters
    $lifetime = (int) ($_ENV['SESSION_LIFETIME'] ?? 86400); // Default 1 day
    $path = '/';
    $domain = ''; // Empty for localhost
    $secure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'; // True for HTTPS
    $httponly = true;
    $samesite = 'Lax';

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => $path,
        'domain' => $domain,
        'secure' => $secure,
        'httponly' => $httponly,
        'samesite' => $samesite
    ]);

    session_start();
}

// Create Slim app
$app = AppFactory::create();

// Add output buffer middleware to capture any unexpected output
$app->add(new \App\Middleware\OutputBufferMiddleware());

$app->addBodyParsingMiddleware();

// Add error middleware with custom error handler
$errorMiddleware = $app->addErrorMiddleware(
    (bool) ($_ENV['APP_DEBUG'] ?? false),
    true,
    true
);

// Custom error handler to always return JSON for API requests
$errorHandler = $errorMiddleware->getDefaultErrorHandler();
$errorHandler->forceContentType('application/json');

// Add routing middleware
$app->addRoutingMiddleware();

// Load routes
require __DIR__ . '/../src/routes.php';

// Run app
$app->run();
