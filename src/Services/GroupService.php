<?php

declare(strict_types=1);

namespace App\Services;

use App\LDAP\LDAPConnection;

class GroupService
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

    public function searchGroups(string $query, string $userDn): array
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
            '(&(objectClass=group)(|(cn=*%s*)(description=*%s*)))',
            $escapedQuery,
            $escapedQuery
        );

        $attributes = ['dn', 'cn', 'description', 'member', 'whenCreated'];

        $results = $ldap->search($config['base_dn'], $filter, $attributes);

        // Filter by permissions
        $results = $this->permissions->filterByPermission($results, $userDn);

        return array_map([$this, 'normalizeGroup'], $results);
    }

    public function getGroup(string $dn, string $userDn): ?array
    {
        if (!$this->permissions->canManageGroups($userDn, $dn)) {
            throw new \RuntimeException('Permission denied');
        }

        $config = $this->adConfig->getActiveConfig();
        
        if (!$config) {
            throw new \RuntimeException('AD configuration not found');
        }

        $ldap = new LDAPConnection($config);
        $ldap->connect();
        $ldap->bind();

        $attributes = ['dn', 'cn', 'description', 'member', 'whenCreated'];

        $group = $ldap->searchOne($dn, '(objectClass=group)', $attributes);

        return $group ? $this->normalizeGroup($group) : null;
    }

    public function addMember(
        string $groupDn,
        string $memberDn,
        string $userDn,
        string $username,
        string $ip
    ): bool {
        if (!$this->permissions->canManageGroups($userDn, $groupDn)) {
            $this->audit->log($username, 'add_group_member', $groupDn, $ip, 'failure', [
                'reason' => 'Permission denied',
                'member' => $memberDn
            ]);
            throw new \RuntimeException('Permission denied');
        }

        // Check if group is protected by AdminSDHolder
        $config = $this->adConfig->getActiveConfig();
        if ($this->isGroupProtected($groupDn, $config)) {
            $this->audit->log($username, 'add_group_member', $groupDn, $ip, 'failure', [
                'reason' => 'Group is protected by AdminSDHolder',
                'member' => $memberDn
            ]);
            throw new \RuntimeException('Falha ao adicionar membro: O grupo é protegido pelo AD (AdminSDHolder). A conta de serviço configurada na aplicação precisa de privilégios elevados (ex: Administrador de Domínio) para modificar este grupo.');
        }

        $config = $this->adConfig->getActiveConfig();

        if (!$config) {
            throw new \RuntimeException('AD configuration not found');
        }

        try {
            $ldap = new LDAPConnection($config);
            $ldap->connect();
            $ldap->bind();

            $ldap->addMemberToGroup($groupDn, $memberDn);

            $this->audit->log($username, 'add_group_member', $groupDn, $ip, 'success', [
                'member' => $memberDn
            ]);

            return true;

        } catch (\Exception $e) {
            $this->audit->log($username, 'add_group_member', $groupDn, $ip, 'error', [
                'error' => $e->getMessage(),
                'member' => $memberDn
            ]);
            throw $e;
        }
    }

    public function removeMember(
        string $groupDn,
        string $memberDn,
        string $userDn,
        string $username,
        string $ip
    ): bool {
        if (!$this->permissions->canManageGroups($userDn, $groupDn)) {
            $this->audit->log($username, 'remove_group_member', $groupDn, $ip, 'failure', [
                'reason' => 'Permission denied',
                'member' => $memberDn
            ]);
            throw new \RuntimeException('Permission denied');
        }

        // Check if group is protected by AdminSDHolder
        $config = $this->adConfig->getActiveConfig();
        if ($this->isGroupProtected($groupDn, $config)) {
            $this->audit->log($username, 'remove_group_member', $groupDn, $ip, 'failure', [
                'reason' => 'Group is protected by AdminSDHolder',
                'member' => $memberDn
            ]);
            throw new \RuntimeException('Falha ao remover membro: O grupo é protegido pelo AD (AdminSDHolder). A conta de serviço configurada na aplicação precisa de privilégios elevados (ex: Administrador de Domínio) para modificar este grupo.');
        }

        $config = $this->adConfig->getActiveConfig();

        if (!$config) {
            throw new \RuntimeException('AD configuration not found');
        }

        try {
            $ldap = new LDAPConnection($config);
            $ldap->connect();
            $ldap->bind();

            $ldap->removeMemberFromGroup($groupDn, $memberDn);

            $this->audit->log($username, 'remove_group_member', $groupDn, $ip, 'success', [
                'member' => $memberDn
            ]);

            return true;

        } catch (\Exception $e) {
            $this->audit->log($username, 'remove_group_member', $groupDn, $ip, 'error', [
                'error' => $e->getMessage(),
                'member' => $memberDn
            ]);
            throw $e;
        }
    }

    public function createGroup(array $data, string $userDn, string $username, string $ip): bool
    {
        // Verifica se o usuário tem permissão para gerenciar grupos na OU de destino
        if (!$this->permissions->canManageGroups($userDn, $data['ou'])) {
            $this->audit->log($username, 'create_group', $data['ou'], $ip, 'failure', [
                'reason' => 'Permission denied on target OU'
            ]);
            throw new \RuntimeException('Permissão negada para criar grupos nesta Unidade Organizacional (OU).');
        }

        $config = $this->adConfig->getActiveConfig();
        if (!$config) {
            throw new \RuntimeException('AD configuration not found');
        }

        // O nome do grupo não pode conter caracteres especiais que invalidem o DN
        if (preg_match('/[\\\\#+<>;\"=,]/', $data['name'])) {
            throw new \RuntimeException('O nome do grupo não pode conter os seguintes caracteres: \\ # + < > ; " = ,');
        }

        $escapedCn = ldap_escape($data['name'], '', LDAP_ESCAPE_DN);
        $targetDn = "CN={$escapedCn}," . $data['ou'];

        // Definir o groupType com base nas seleções do usuário
        // Escopo: 2=Global, 4=DomainLocal, 8=Universal
        // Tipo: 0x80000000 para Segurança (Security)
        $groupType = 0;
        switch ($data['scope']) {
            case 'domain_local':
                $groupType |= 4;
                break;
            case 'universal':
                $groupType |= 8;
                break;
            case 'global':
            default:
                $groupType |= 2;
                break;
        }
        if ($data['type'] === 'security') {
            $groupType |= 0x80000000;
        }

        $entry = [
            'objectClass'    => ['top', 'group'],
            'cn'             => $data['name'],
            'sAMAccountName' => $data['name'],
            'description'    => $data['description'] ?? '',
            'groupType'      => (string) $groupType,
        ];

        try {
            $ldap = new LDAPConnection($config);
            $ldap->connect();
            $ldap->bind();

            $ldap->add($targetDn, $entry);

            $this->audit->log($username, 'create_group', $targetDn, $ip, 'success', [
                'group_name' => $data['name']
            ]);

            return true;
        } catch (\Exception $e) {
            $this->audit->log($username, 'create_group', $targetDn, $ip, 'error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function isGroupProtected(string $groupDn, ?array $config): bool
    {
        if (!$config) { // Pass config to avoid fetching it again
            return false;
        }

        $ldap = new LDAPConnection($config);
        $ldap->connect();
        $ldap->bind();

        $group = $ldap->searchOne($groupDn, '(objectClass=group)', ['adminCount']);

        // Groups protected by AdminSDHolder have adminCount=1
        return isset($group['admincount']) && (int) $group['admincount'] === 1;
    }

    private function normalizeGroup(array $group): array
    {
        $members = $group['member'] ?? [];

        if (!is_array($members)) {
            $members = [$members];
        }

        return [
            'dn' => $group['dn'] ?? '',
            'name' => $group['cn'] ?? '',
            'description' => $group['description'] ?? '',
            'member_count' => count($members),
            'members' => $members,
            'created_at' => $group['whencreated'] ?? ''
        ];
    }
}
