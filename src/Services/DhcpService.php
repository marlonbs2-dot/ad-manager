<?php

declare(strict_types=1);

namespace App\Services;

class DhcpService
{
    private string $dhcpServer;
    private string $domain;
    private string $username;
    private string $password;

    public function __construct()
    {
        // Carregar configurações do .env
        $this->dhcpServer = $_ENV['DHCP_SERVER'] ?? 'srv-ad-01';
        $this->domain = $_ENV['DHCP_DOMAIN'] ?? 'meudominio.local';
        $this->username = $_ENV['DHCP_USERNAME'] ?? 'svc-admanager';
        $this->password = $_ENV['DHCP_PASSWORD'] ?? '';
    }

    /**
     * Executa comando PowerShell remoto
     */
    private function executePowerShellCommand(string $command): string
    {
        $scriptContent = <<<PS
\$ErrorActionPreference = 'Stop'
\$ConfirmPreference = 'None'
try {
    \$password = "{$this->password}" | ConvertTo-SecureString -AsPlainText -Force
    \$credential = New-Object System.Management.Automation.PSCredential("{$this->domain}\\{$this->username}", \$password)
    \$sessionOption = New-PSSessionOption -SkipCACheck -SkipCNCheck -SkipRevocationCheck
    
    Invoke-Command -ComputerName "{$this->dhcpServer}" -Credential \$credential -SessionOption \$sessionOption -ScriptBlock { 
        {$command}
    } | ConvertTo-Json -Depth 3 -Compress | Out-String
} catch {
    throw "Erro PowerShell: \$(\$_.Exception.Message)"
}
PS;

        $tempFile = tempnam(sys_get_temp_dir(), 'dhcp_') . '.ps1';
        file_put_contents($tempFile, $scriptContent);

        try {
            $output = shell_exec("powershell -ExecutionPolicy Bypass -File \"$tempFile\" 2>&1");

            if ($output === null) {
                throw new \Exception('Falha ao executar comando PowerShell');
            }

            return $output;
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Obter todos os escopos DHCP
     */
    public function getScopes(): array
    {
        $command = 'Get-DhcpServerv4Scope | Select-Object ScopeId, Name, State, StartRange, EndRange, SubnetMask';
        $output = $this->executePowerShellCommand($command);

        $data = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Erro ao parsear resposta do servidor DHCP');
        }

        // Normalizar dados
        if (!is_array($data)) {
            return [];
        }

        // Se for um único objeto, transformar em array
        if (isset($data['ScopeId'])) {
            $data = [$data];
        }

        $scopes = [];
        foreach ($data as $item) {
            $scopes[] = [
                'scopeId' => $this->extractIPAddress($item['ScopeId'] ?? ''),
                'name' => $item['Name'] ?? '',
                'state' => $item['State'] ?? '',
                'startRange' => $this->extractIPAddress($item['StartRange'] ?? ''),
                'endRange' => $this->extractIPAddress($item['EndRange'] ?? ''),
                'subnetMask' => $this->extractIPAddress($item['SubnetMask'] ?? '')
            ];
        }

        return $scopes;
    }

    /**
     * Obter reservas de um escopo
     */
    public function getReservations(string $scopeId): array
    {
        $command = "Get-DhcpServerv4Reservation -ScopeId '$scopeId' | Select-Object IPAddress, ClientId, Name, Description";
        $output = $this->executePowerShellCommand($command);

        $data = json_decode($output, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return []; // Sem reservas
        }

        if (!is_array($data)) {
            return [];
        }

        // Se for um único objeto, transformar em array
        if (isset($data['IPAddress'])) {
            $data = [$data];
        }

        $reservations = [];
        foreach ($data as $item) {
            $reservations[] = [
                'ipAddress' => $this->extractIPAddress($item['IPAddress'] ?? ''),
                'clientId' => $item['ClientId'] ?? '',
                'name' => $item['Name'] ?? '',
                'description' => $item['Description'] ?? ''
            ];
        }

        return $reservations;
    }

    /**
     * Criar nova reserva
     */
    public function createReservation(
        string $scopeId,
        string $ipAddress,
        string $macAddress,
        string $name,
        string $description = ''
    ): void {
        $command = "Add-DhcpServerv4Reservation -ScopeId '$scopeId' -IPAddress '$ipAddress' -ClientId '$macAddress' -Name '$name'";

        if (!empty($description)) {
            $command .= " -Description '$description'";
        }

        $this->executePowerShellCommand($command);
    }

    /**
     * Remover reserva
     */
    public function deleteReservation(string $scopeId, string $ipAddress): void
    {
        // Usar apenas -IPAddress para evitar conflito de parâmetros
        $command = "Remove-DhcpServerv4Reservation -IPAddress '$ipAddress'";
        $this->executePowerShellCommand($command);
    }

    /**
     * Extrai endereço IP de objeto ou string
     */
    private function extractIPAddress($value): string
    {
        if (is_array($value) && isset($value['IPAddressToString'])) {
            return $value['IPAddressToString'];
        }

        if (is_string($value)) {
            return $value;
        }

        return '';
    }
}
