<?php

declare(strict_types=1);

namespace App\Services;

class DhcpServiceHybrid
{
    private DhcpServiceHttp $httpService;

    public function __construct()
    {
        $this->httpService = new DhcpServiceHttp();
    }

    private function logDebug(string $message): void
    {
        file_put_contents(
            '/var/www/html/logs/dhcp-hybrid-debug.log',
            date('[Y-m-d H:i:s] ') . $message . PHP_EOL,
            FILE_APPEND
        );
    }

    public function getScopes(): array
    {
        $this->logDebug("Using HTTP API for DHCP operations");
        return $this->httpService->getScopes();
    }

    public function getScopeReservations(string $scopeId): array
    {
        $this->logDebug("Using HTTP API for DHCP reservations");
        return $this->httpService->getReservations($scopeId);
    }

    public function getScopeLeases(string $scopeId): array
    {
        $this->logDebug("Using HTTP API for DHCP leases");
        return $this->httpService->getLeases($scopeId);
    }

    public function createReservation(string $scopeId, string $ipAddress, string $macAddress, string $name, string $description = ''): void
    {
        $this->logDebug("Using HTTP API for DHCP reservation creation");
        $this->httpService->createReservation($scopeId, $ipAddress, $macAddress, $name, $description);
    }

    public function updateReservation(string $scopeId, string $currentIpAddress, string $newIpAddress, string $macAddress, string $name, string $description = ''): void
    {
        $this->logDebug("Using HTTP API for DHCP reservation update");
        $this->httpService->updateReservation($scopeId, $currentIpAddress, $newIpAddress, $macAddress, $name, $description);
    }

    public function deleteReservation(string $scopeId, string $ipAddress): void
    {
        $this->logDebug("Using HTTP API for DHCP reservation deletion - ScopeId: $scopeId, IP: $ipAddress");
        $this->httpService->deleteReservation($scopeId, $ipAddress);
    }
}