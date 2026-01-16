<?php
/**
 * Permission Service
 *
 * Handles access control and permission validation
 *
 * @package FortaleceePSE
 * @subpackage Services
 */

namespace FortaleceePSE\Core\Services;

use FortaleceePSE\Core\Plugin;

class PermissionService {
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
     * Check if user can access registration endpoint
     *
     * Public endpoint, no authentication required
     *
     * @return bool True if access allowed
     */
    public function canRegister() {
        $permissions = $this->plugin->getConfig('permissions', []);
        $endpointPerms = $permissions['endpoint_permissions']['register'] ?? 'public';
        return $endpointPerms === 'public';
    }

    /**
     * Check if user can view registrations
     *
     * Requires login and specific capability
     *
     * @return bool True if user is logged in and has capability
     */
    public function canViewRegistrations() {
        return is_user_logged_in() && current_user_can('view_fpse_registrations');
    }

    /**
     * Check if user can manage registrations
     *
     * Requires specific capability (admin or fpse_admin)
     *
     * @return bool True if user has capability
     */
    public function canManageRegistrations() {
        return current_user_can('manage_fpse_registrations');
    }

    /**
     * Check if user can view reports
     *
     * Requires specific capability (admin or fpse_admin)
     *
     * @return bool True if user has capability
     */
    public function canViewReports() {
        return current_user_can('view_fpse_reports');
    }

    /**
     * Check if user can access endpoint
     *
     * Generic endpoint permission check
     *
     * @param string $endpoint Endpoint name
     * @return bool True if access allowed
     */
    public function canAccessEndpoint($endpoint) {
        $endpoint = sanitize_text_field($endpoint);
        $permissions = $this->plugin->getConfig('permissions', []);
        $endpointPerms = $permissions['endpoint_permissions'][$endpoint] ?? null;

        // If permission not defined, deny by default
        if ($endpointPerms === null) {
            return false;
        }

        // Public endpoints always allowed
        if ($endpointPerms === 'public') {
            return true;
        }

        // Logged in only
        if ($endpointPerms === 'logged_in') {
            return is_user_logged_in();
        }

        // Capability based
        return current_user_can($endpointPerms);
    }

    /**
     * Get capability required for endpoint
     *
     * @param string $endpoint Endpoint name
     * @return string|null Capability name or null if public
     */
    public function getEndpointCapability($endpoint) {
        $endpoint = sanitize_text_field($endpoint);
        $permissions = $this->plugin->getConfig('permissions', []);
        $endpointPerms = $permissions['endpoint_permissions'][$endpoint] ?? null;

        // Public
        if ($endpointPerms === 'public' || $endpointPerms === 'logged_in') {
            return null;
        }

        return $endpointPerms;
    }

    /**
     * Get all available capabilities
     *
     * @return array Capabilities configuration
     */
    public function getCapabilities() {
        $permissions = $this->plugin->getConfig('permissions', []);
        return $permissions['capabilities'] ?? [];
    }

    /**
     * Get all roles that can access FPSE
     *
     * @return array Role names
     */
    public function getAdminRoles() {
        $permissions = $this->plugin->getConfig('permissions', []);
        return $permissions['admin_roles'] ?? ['administrator'];
    }

    /**
     * Grant all FPSE capabilities to a role
     *
     * @param string $roleName Role name
     * @return bool True if successful
     */
    public function grantCapabilitiesToRole($roleName) {
        $roleName = sanitize_text_field($roleName);
        $role = get_role($roleName);

        if (!$role) {
            return false;
        }

        $capabilities = $this->getCapabilities();
        foreach ($capabilities as $cap) {
            $role->add_cap($cap);
        }

        return true;
    }

    /**
     * Revoke all FPSE capabilities from a role
     *
     * @param string $roleName Role name
     * @return bool True if successful
     */
    public function revokeCapabilitiesFromRole($roleName) {
        $roleName = sanitize_text_field($roleName);
        $role = get_role($roleName);

        if (!$role) {
            return false;
        }

        $capabilities = $this->getCapabilities();
        foreach ($capabilities as $cap) {
            $role->remove_cap($cap);
        }

        return true;
    }

    /**
     * Get rate limit for endpoint
     *
     * @param string $endpoint Endpoint name
     * @return int Requests per hour
     */
    public function getRateLimit($endpoint) {
        $endpoint = sanitize_text_field($endpoint);
        $permissions = $this->plugin->getConfig('permissions', []);
        $rateLimits = $permissions['rate_limits'] ?? [];
        return $rateLimits[$endpoint] ?? $rateLimits['default'] ?? 100;
    }

    /**
     * Check if user can perform action in state
     *
     * For future enhancement: restrict state access by user role
     *
     * @param string $estado State code
     * @return bool True if allowed (currently always true for logged-in users)
     */
    public function canAccessState($estado) {
        if (!is_user_logged_in()) {
            return false;
        }

        $estado = sanitize_text_field($estado);
        // Validation: check if state code exists
        $states = $this->plugin->getConfig('states', []);
        return isset($states[$estado]);
    }

    /**
     * Get accessible states for current user
     *
     * For future enhancement: can be restricted by user role
     *
     * @return array State codes user can access
     */
    public function getAccessibleStates() {
        if (!is_user_logged_in()) {
            return [];
        }

        // Currently all states are accessible
        // Can be enhanced per user role
        return $this->plugin->getConfig('states', []);
    }
}
