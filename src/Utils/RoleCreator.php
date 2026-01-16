<?php
/**
 * Role Creator Utility
 *
 * Creates WordPress user roles based on profile configuration
 *
 * @package FortaleceePSE
 * @subpackage Utils
 */

namespace FortaleceePSE\Core\Utils;

use FortaleceePSE\Core\Plugin;

class RoleCreator {
    /**
     * @var Plugin
     */
    private $plugin;

    /**
     * Constructor
     *
     * @param Plugin $plugin Main plugin instance
     */
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Create WordPress roles from profiles configuration
     *
     * Reads config/profiles.php and creates a WordPress role for each profile
     *
     * @return array Result with 'created' and 'updated' keys
     */
    public function createRolesFromProfiles() {
        $profiles = $this->plugin->getConfig('profiles', []);
        $created = [];
        $updated = [];

        foreach ($profiles as $profileId => $profileData) {
            $roleName = $this->getRoleName($profileId);
            $roleLabel = $profileData['label'] ?? ucfirst(str_replace('-', ' ', $profileId));

            // Check if role exists
            $existingRole = get_role($roleName);

            if ($existingRole === null) {
                // Create new role
                $capabilities = $this->getDefaultCapabilities();
                add_role($roleName, $roleLabel, $capabilities);
                $created[] = $roleName;
            } else {
                // Update existing role (update display name if different)
                $existingRole->name = $roleLabel;
                $updated[] = $roleName;
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * Get WordPress role name from profile ID
     *
     * Converts profile ID to valid WordPress role name
     * WordPress role names must be lowercase, no spaces, max 40 chars
     *
     * @param string $profileId Profile identifier (e.g., 'estudante-eaa')
     * @return string WordPress role name (e.g., 'fpse_estudante_eaa')
     */
    private function getRoleName($profileId) {
        // Prefix with 'fpse_' to avoid conflicts
        $roleName = 'fpse_' . str_replace('-', '_', strtolower($profileId));

        // WordPress limits role names to 40 characters
        if (strlen($roleName) > 40) {
            $roleName = substr($roleName, 0, 40);
        }

        return $roleName;
    }

    /**
     * Get default capabilities for profile roles
     *
     * Base capabilities that all FPSE profile roles should have
     * More specific capabilities can be added per profile later
     *
     * @return array WordPress capabilities
     */
    private function getDefaultCapabilities() {
        return [
            // Read posts (basic WordPress capability)
            'read' => true,
            
            // FPSE specific capabilities
            'view_fpse_registrations' => true,
            
            // Allow users to edit their own profile
            // 'edit_user' => true, // Only for own profile (handled by WordPress)
        ];
    }

    /**
     * Remove all FPSE roles
     *
     * Useful for cleanup or reset
     *
     * @return array List of removed roles
     */
    public function removeAllRoles() {
        $profiles = $this->plugin->getConfig('profiles', []);
        $removed = [];

        foreach ($profiles as $profileId => $profileData) {
            $roleName = $this->getRoleName($profileId);
            $role = get_role($roleName);

            if ($role !== null) {
                remove_role($roleName);
                $removed[] = $roleName;
            }
        }

        return $removed;
    }

    /**
     * Assign role to user based on profile
     *
     * When a user registers with a specific profile, assign the corresponding role
     *
     * @param int $userId WordPress user ID
     * @param string $profileId Profile identifier
     * @return bool True if role was assigned successfully
     */
    public function assignRoleByProfile($userId, $profileId) {
        $roleName = $this->getRoleName($profileId);
        $role = get_role($roleName);

        if ($role === null) {
            // Role doesn't exist, create it
            $profiles = $this->plugin->getConfig('profiles', []);
            $profileData = $profiles[$profileId] ?? null;

            if ($profileData === null) {
                return false; // Profile not found
            }

            $roleLabel = $profileData['label'] ?? ucfirst(str_replace('-', ' ', $profileId));
            $capabilities = $this->getDefaultCapabilities();
            add_role($roleName, $roleLabel, $capabilities);
        }

        // Assign role to user
        $user = new \WP_User($userId);
        $user->set_role($roleName);

        return true;
    }

    /**
     * Get role name from profile ID (public method)
     *
     * @param string $profileId Profile identifier
     * @return string WordPress role name
     */
    public static function getRoleNameForProfile($profileId) {
        $roleName = 'fpse_' . str_replace('-', '_', strtolower($profileId));

        if (strlen($roleName) > 40) {
            $roleName = substr($roleName, 0, 40);
        }

        return $roleName;
    }

    /**
     * Check if a role exists for a profile
     *
     * @param string $profileId Profile identifier
     * @return bool True if role exists
     */
    public function roleExistsForProfile($profileId) {
        $roleName = $this->getRoleName($profileId);
        return get_role($roleName) !== null;
    }
}
