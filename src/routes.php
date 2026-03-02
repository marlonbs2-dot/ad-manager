<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\TwoFactorController;
use App\Controllers\DashboardController;
use App\Controllers\UserController;
use App\Controllers\GroupController;
use App\Controllers\ComputerController;
use App\Controllers\SettingsController;
use App\Controllers\AuditController;
use App\Controllers\ReportController;
use App\Controllers\PrintController;
use Slim\Routing\RouteCollectorProxy;

// Authentication routes
$app->get('/login', [AuthController::class, 'showLogin']);
$app->post('/login', [AuthController::class, 'login']);
$app->post('/verify-2fa', [AuthController::class, 'verify2FA']);
$app->get('/logout', [AuthController::class, 'logout']);
$app->post('/logout', [AuthController::class, 'logout']);

// Two-Factor Authentication routes
$app->group('/2fa', function (RouteCollectorProxy $group) {
    $group->get('/setup', [TwoFactorController::class, 'showSetup']);
    $group->post('/enable', [TwoFactorController::class, 'enable']);
    $group->post('/verify', [TwoFactorController::class, 'verify']);
    $group->post('/disable', [TwoFactorController::class, 'disable']);
    $group->post('/regenerate-backup-codes', [TwoFactorController::class, 'regenerateBackupCodes']);
});

// Redirect root to dashboard
$app->get('/', function ($request, $response) {
    return $response->withHeader('Location', '/dashboard')->withStatus(302);
});

// Dashboard
$app->get('/dashboard', [DashboardController::class, 'index']);
$app->get('/api/dashboard/stats', [DashboardController::class, 'getStats']);

// Users
$app->group('/users', function (RouteCollectorProxy $group) {
    $group->get('', [UserController::class, 'index']);
    $group->get('/search', [UserController::class, 'search']);
    $group->get('/ous', [UserController::class, 'getOUs']);
    $group->post('/create', [UserController::class, 'createUser']);
    $group->get('/{dn}', [UserController::class, 'get']);
    $group->get('/{dn}/copy', [UserController::class, 'getUserForCopy']);
    $group->post('/{dn}/reset-password', [UserController::class, 'resetPassword']);
    $group->post('/{dn}/enable', [UserController::class, 'enable']);
    $group->post('/{dn}/disable', [UserController::class, 'disable']);
});

// Groups
$app->group('/groups', function (RouteCollectorProxy $group) {
    $group->get('', [GroupController::class, 'index']);
    $group->get('/search', [GroupController::class, 'search']);
    $group->post('/create', [GroupController::class, 'createGroup']);
    $group->get('/{dn}', [GroupController::class, 'get']);
    $group->post('/{dn}/add-member', [GroupController::class, 'addMember']);
    $group->post('/{dn}/members', [GroupController::class, 'addMember']);
    // Note: removeMember is generic and can remove users or computers from groups.
    // It's already handled by GroupController.
    $group->delete('/{dn}/members', [GroupController::class, 'removeMember']);
});

// Computers
$app->group('/computers', function (RouteCollectorProxy $group) {
    $group->get('', [ComputerController::class, 'index']);
    $group->get('/search', [ComputerController::class, 'search']);
    $group->get('/{dn}', [ComputerController::class, 'get']);
    $group->delete('/{dn}', [ComputerController::class, 'delete']);
});

// Settings
$app->group('/settings', function (RouteCollectorProxy $group) {
    $group->get('', [SettingsController::class, 'index']);
    $group->get('/ad', [SettingsController::class, 'getConfig']);
    $group->post('/ad', [SettingsController::class, 'saveConfig']);
    $group->put('/ad', [SettingsController::class, 'saveConfig']);
    $group->post('/ad/test', [SettingsController::class, 'testConnection']);

    // API Configuration
    $group->get('/api', [SettingsController::class, 'getApiConfig']);
    $group->post('/api', [SettingsController::class, 'saveApiConfig']);
    $group->post('/api/test', [SettingsController::class, 'testApiConnection']);

    // Print Servers CRUD
    $group->get('/print-servers', [SettingsController::class, 'getPrintServers']);
    $group->post('/print-servers', [SettingsController::class, 'addPrintServer']);
    $group->post('/print-servers/test', [SettingsController::class, 'testPrintServer']);
    $group->put('/print-servers/{id}', [SettingsController::class, 'updatePrintServer']);
    $group->delete('/print-servers/{id}', [SettingsController::class, 'deletePrintServer']);
});

// Audit
$app->group('/audit', function (RouteCollectorProxy $group) {
    $group->get('', [AuditController::class, 'index']);
    $group->get('/logs', [AuditController::class, 'getLogs']);
    $group->get('/logs/{id}', [AuditController::class, 'getLogDetails']);
    $group->get('/statistics', [AuditController::class, 'getStatistics']);
});

// Reports
$app->group('/reports', function (RouteCollectorProxy $group) {
    $group->get('', [ReportController::class, 'index']);
    $group->get('/export', [ReportController::class, 'export']);
});

// DHCP Management
$app->group('/dhcp', function (RouteCollectorProxy $group) {
    $group->get('', [\App\Controllers\DhcpController::class, 'index']);
    $group->get('/api/scopes', [\App\Controllers\DhcpController::class, 'getScopes']);
    $group->get('/api/scopes/{scopeId}/reservations', [\App\Controllers\DhcpController::class, 'getReservations']);
    $group->get('/api/scopes/{scopeId}/leases', [\App\Controllers\DhcpController::class, 'getLeases']);
    $group->post('/api/reservations', [\App\Controllers\DhcpController::class, 'createReservation']);
    $group->post('/api/reservations/delete', [\App\Controllers\DhcpController::class, 'deleteReservationPost']);
    $group->put('/api/scopes/{scopeId}/reservations/{ipAddress}', [\App\Controllers\DhcpController::class, 'updateReservation']);
    $group->delete('/api/scopes/{scopeId}/reservations/{ipAddress}', [\App\Controllers\DhcpController::class, 'deleteReservation']);
});

// Share Logs Management
$app->group('/shares', function (RouteCollectorProxy $group) {
    $group->get('', [\App\Controllers\ShareController::class, 'index']);
    $group->get('/api/logs', [\App\Controllers\ShareController::class, 'getLogs']);
    $group->post('/api/sync', [\App\Controllers\ShareController::class, 'syncLogs']);
    $group->get('/api/statistics', [\App\Controllers\ShareController::class, 'getStatistics']);
    $group->get('/api/export', [\App\Controllers\ShareController::class, 'exportLogs']);

    // Server management
    $group->get('/api/servers', [\App\Controllers\ShareController::class, 'getServers']);
    $group->post('/api/servers', [\App\Controllers\ShareController::class, 'addServer']);
    $group->put('/api/servers/{id}', [\App\Controllers\ShareController::class, 'updateServer']);
    $group->delete('/api/servers/{id}', [\App\Controllers\ShareController::class, 'deleteServer']);
    $group->post('/api/servers/test', [\App\Controllers\ShareController::class, 'testServer']);
});

// Print Server Management
$app->group('/print', function (RouteCollectorProxy $group) {
    $group->get('', [PrintController::class, 'index']);
    $group->get('/api/servers', [PrintController::class, 'getServers']);
    $group->get('/api/servers/{serverId}/printers', [PrintController::class, 'getPrinters']);
    $group->post('/api/servers/{serverId}/printers', [PrintController::class, 'createPrinter']);
    $group->put('/api/servers/{serverId}/printers/{name}', [PrintController::class, 'updatePrinter']);
    $group->delete('/api/servers/{serverId}/printers/{name}', [PrintController::class, 'deletePrinter']);
    $group->get('/api/servers/{serverId}/drivers', [PrintController::class, 'getDrivers']);
    $group->get('/api/servers/{serverId}/ports', [PrintController::class, 'getPorts']);
    $group->get('/api/servers/{serverId}/printers/{name}/jobs', [PrintController::class, 'getPrinterJobs']);
    $group->post('/api/servers/{serverId}/printers/{name}/pause', [PrintController::class, 'pausePrinter']);
    $group->post('/api/servers/{serverId}/printers/{name}/resume', [PrintController::class, 'resumePrinter']);
    $group->delete('/api/servers/{serverId}/printers/{name}/jobs', [PrintController::class, 'clearQueue']);
    $group->delete('/api/servers/{serverId}/printers/{name}/jobs/{jobId}', [PrintController::class, 'cancelJob']);
    $group->post('/api/servers/{serverId}/printers/{name}/jobs/{jobId}/pause', [PrintController::class, 'pauseJob']);
    $group->post('/api/servers/{serverId}/printers/{name}/jobs/{jobId}/resume', [PrintController::class, 'resumeJob']);
});
