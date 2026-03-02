<?php

declare(strict_types=1);

namespace App\Services;

use App\LDAP\LDAPConnection;

class ComputerService
{
    private ADConfigService $adConfig;
    private PermissionService $permissions;
    private AuditService $audit;

    public function __construct()
    {
        $this->adConfig = new ADConfigService();
        $this->permissions = new PermissionService();
        $this->audit = new AuditService();
    }

    public function searchComputers(string $query, string $userDn): array
    {
        $config = $this->adConfig->getActiveConfig();
        
        if (!$config) {
            throw new \RuntimeException('AD configuration not found');
        }

        $ldap = new LDAPConnection($config);
        $ldap->connect();
        $ldap->bind();

        $escapedQuery = ldap_escape($query, '', LDAP_ESCAPE_FILTER);
        $filter = sprintf(
            '(&(objectClass=computer)(|(cn=*%s*)(dNSHostName=*%s*)))',
            $escapedQuery,
            $escapedQuery
        );

        $attributes = [
            'dn', 'cn', 'dNSHostName', 'operatingSystem', 
            'operatingSystemVersion', 'whenCreated', 'memberOf'
        ];

        $results = $ldap->search($config['base_dn'], $filter, $attributes);

        // Filter by permissions (reusing group management permission for now)
        $results = $this->permissions->filterByPermission($results, $userDn);

        return array_map([$this, 'normalizeComputer'], $results);
    }

    public function getComputer(string $dn, string $userDn): ?array
    {
        // For now, assume if you can manage groups, you can view computer details.
        // A more granular permission might be needed here in the future.
        if (!$this->permissions->canManageGroups($userDn, $dn)) {
            throw new \RuntimeException('Permission denied to view computer details');
        }

        $config = $this->adConfig->getActiveConfig();
        
        if (!$config) {
            throw new \RuntimeException('AD configuration not found');
        }

        $ldap = new LDAPConnection($config);
        $ldap->connect();
        $ldap->bind();

        $attributes = [
            'dn', 'cn', 'dNSHostName', 'operatingSystem', 
            'operatingSystemVersion', 'whenCreated', 'memberOf'
        ];

        $computer = $ldap->searchOne($dn, '(objectClass=computer)', $attributes);

        return $computer ? $this->normalizeComputer($computer) : null;
    }

    public function addComputerToGroup(
        string $groupDn,
        string $computerDn,
        string $userDn,
        string $username,
        string $ip
    ): bool {
        // Reusing canManageGroups permission for adding computers to groups
        if (!$this->permissions->canManageGroups($userDn, $groupDn)) {
            $this->audit->log($username, 'add_computer_to_group', $groupDn, $ip, 'failure', [
                'reason' => 'Permission denied',
                'computer' => $computerDn
            ]);
            throw new \RuntimeException('Permission denied to add computer to this group.');
        }

        $config = $this->adConfig->getActiveConfig();
        
        if (!$config) {
            throw new \RuntimeException('AD configuration not found');
        }

        try {
            $ldap = new LDAPConnection($config);
            $ldap->connect();
            $ldap->bind();

            $ldap->addMemberToGroup($groupDn, $computerDn);

            $this->audit->log($username, 'add_computer_to_group', $groupDn, $ip, 'success', [
                'computer' => $computerDn
            ]);

            return true;

        } catch (\Exception $e) {
            $this->audit->log($username, 'add_computer_to_group', $groupDn, $ip, 'error', [
                'error' => $e->getMessage(),
                'computer' => $computerDn
            ]);
            throw $e;
        }
    }

    public function deleteComputer(
        string $computerDn,
        string $userDn,
        string $username,
        string $ip
    ): bool {
        // Verificar permissão para excluir computador
        if (!$this->permissions->canManageGroups($userDn, $computerDn)) {
            $this->audit->log($username, 'delete_computer', $computerDn, $ip, 'failure', [
                'reason' => 'Permission denied'
            ]);
            throw new \RuntimeException('Permissão negada para excluir este computador.');
        }

        $config = $this->adConfig->getActiveConfig();
        
        if (!$config) {
            throw new \RuntimeException('AD configuration not found');
        }

        try {
            $ldap = new LDAPConnection($config);
            $ldap->connect();
            $ldap->bind();

            // Obter nome do computador antes de excluir (para log)
            $computer = $ldap->searchOne($computerDn, '(objectClass=computer)', ['cn']);
            $computerName = $computer['cn'] ?? 'Unknown';

            // Excluir computador
            $ldap->delete($computerDn);

            $this->audit->log($username, 'delete_computer', $computerDn, $ip, 'success', [
                'computer_name' => $computerName
            ]);

            return true;

        } catch (\Exception $e) {
            $this->audit->log($username, 'delete_computer', $computerDn, $ip, 'error', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function normalizeComputer(array $computer): array
    {
        $memberOf = $computer['memberof'] ?? [];
        if (!is_array($memberOf)) {
            $memberOf = [$memberOf];
        }

        return [
            'dn' => $computer['dn'] ?? '',
            'name' => $computer['cn'] ?? '',
            'hostname' => $computer['dnshostname'] ?? '',
            'os' => $computer['operatingsystem'] ?? '',
            'os_version' => $computer['operatingsystemversion'] ?? '',
            'created_at' => $computer['whencreated'] ?? '',
            'member_of_groups' => $memberOf,
            'group_count' => count($memberOf)
        ];
    }
}