<?php

declare(strict_types=1);

namespace App\Services;

class PermissionService
{
    private ADConfigService $adConfig;

    public function __construct()
    {
        $this->adConfig = new ADConfigService();
    }

    public function canResetPassword(string $userDn, string $targetDn): bool
    {
        $config = $this->adConfig->getActiveConfig();
        
        if (!$config) {
            return false;
        }

        // Admins and Operators have permissions across the Base DN
        $userRole = $_SESSION['user']['role'] ?? '';
        if ($userRole === 'admin' || $userRole === 'operator') {
            return true;
        }

        // Check if user is in any OU with reset permission
        $allowedOUs = $config['ou_reset_password'] ?? [];
        foreach ($allowedOUs as $ou) {
            if ($this->isInOU($userDn, $ou) && $this->isInOU($targetDn, $ou)) {
                return true;
            }
        }

        return false;
    }

    public function canManageGroups(string $userDn, string $targetDn): bool
    {
        $config = $this->adConfig->getActiveConfig();
        
        if (!$config) {
            return false;
        }

        // Admins and Operators have permissions across the Base DN
        $userRole = $_SESSION['user']['role'] ?? '';
        if ($userRole === 'admin' || $userRole === 'operator') {
            return true;
        }

        // Check if user is in any OU with group management permission
        $allowedOUs = $config['ou_manage_groups'] ?? [];
        foreach ($allowedOUs as $ou) {
            if ($this->isInOU($userDn, $ou) && $this->isInOU($targetDn, $ou)) {
                return true;
            }
        }

        return false;
    }

    public function canManageUser(string $userDn, string $targetDn): bool
    {
        $config = $this->adConfig->getActiveConfig();
        
        if (!$config) {
            return false;
        }

        // Admins and Operators have permissions across the Base DN
        $userRole = $_SESSION['user']['role'] ?? '';
        if ($userRole === 'admin' || $userRole === 'operator') {
            return true;
        }

        // Check if both users are in same managed OU
        $managedOUs = array_merge(
            $config['ou_reset_password'] ?? [],
            $config['ou_manage_groups'] ?? []
        );

        foreach ($managedOUs as $ou) {
            if ($this->isInOU($userDn, $ou) && $this->isInOU($targetDn, $ou)) {
                return true;
            }
        }

        return false;
    }

    public function getAllowedOUs(string $userDn): array
    {
        $config = $this->adConfig->getActiveConfig();
        
        if (!$config) {
            return [];
        }

        // Check if user is operator (has role in session)
        // Admins (by OU) and Operators (by group) should see all users in the Base DN.
        // The role is determined during login by AuthService.
        // 'admin' role is for users in the admin_ou.
        // 'operator' role is for users in the groups defined in .env.
        $userRole = $_SESSION['user']['role'] ?? '';
        if ($userRole === 'admin' || $userRole === 'operator') {
            // Operators can see all users in base DN
            return ['*'];
        }

        // For any other role (or no role), they can't see anyone by default.
        // Specific permissions are handled by can... methods.
        // For search visibility, non-admins/operators see nothing.
        return [];
    }

    private function isInOU(string $dn, string $ou): bool
    {
        return stripos($dn, $ou) !== false;
    }

    public function filterByPermission(array $entries, string $userDn): array
    {
        $allowedOUs = $this->getAllowedOUs($userDn);

        // If user can see all OUs
        if (in_array('*', $allowedOUs)) {
            return $entries;
        }

        // If allowedOUs is empty, no entries should be returned.
        if (empty($allowedOUs)) {
            return [];
        }

        $filteredEntries = array_filter($entries, function($entry) use ($allowedOUs) {
            $entryDn = $entry['dn'] ?? '';
            foreach ($allowedOUs as $ou) {
                if ($this->isInOU($entryDn, $ou)) {
                    return true;
                }
            }
            return false;
        });

        // Re-index the array to ensure it's a JSON array, not an object.
        return array_values($filteredEntries);
    }
}
