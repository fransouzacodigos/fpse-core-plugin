<?php
/**
 * REST Stats Controller
 *
 * Handles REST API endpoints for dashboard statistics
 *
 * @package FortaleceePSE
 * @subpackage REST
 */

namespace FortaleceePSE\Core\REST;

class StatsController {
    /**
     * Register REST routes
     *
     * Called during plugin initialization
     */
    public function registerRoutes() {
        register_rest_route('fpse/v1', '/stats', [
            'methods' => 'GET',
            'callback' => [$this, 'handleGetStats'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    /**
     * Check if user has permission to view stats
     *
     * @return bool
     */
    public function checkPermission() {
        return current_user_can('manage_options');
    }

    /**
     * Handle get stats request
     *
     * Returns statistics about BuddyBoss data
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleGetStats($request) {
        global $wpdb;

        $stats = [
            'users' => $this->getUserStats(),
            'buddyboss' => $this->getBuddyBossStats(),
            'events' => $this->getEventStats(),
            'profiles' => $this->getProfileStats(),
        ];

        return new \WP_REST_Response($stats, 200);
    }

    /**
     * Get user statistics
     *
     * @return array
     */
    private function getUserStats() {
        global $wpdb;

        $totalUsers = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        
        // Users with FPSE meta
        $fpseUsers = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) 
             FROM {$wpdb->usermeta} 
             WHERE meta_key LIKE 'fpse_%'"
        );

        // Recent registrations (last 30 days)
        $recentRegistrations = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$wpdb->users} 
                 WHERE user_registered >= %s",
                date('Y-m-d H:i:s', strtotime('-30 days'))
            )
        );

        return [
            'total' => (int) $totalUsers,
            'fpse_users' => (int) $fpseUsers,
            'recent_30_days' => (int) $recentRegistrations,
        ];
    }

    /**
     * Get BuddyBoss statistics
     *
     * @return array
     */
    private function getBuddyBossStats() {
        global $wpdb;

        $stats = [
            'groups' => 0,
            'member_types' => 0,
            'xprofile_fields' => 0,
            'xprofile_data' => 0,
        ];

        // Check if BuddyBoss tables exist
        $groupsTable = $wpdb->prefix . 'bp_groups';
        $memberTypesTable = $wpdb->posts; // Member types are stored as posts
        $xprofileFieldsTable = $wpdb->prefix . 'bp_xprofile_fields';
        $xprofileDataTable = $wpdb->prefix . 'bp_xprofile_data';

        // Count groups
        if ($wpdb->get_var("SHOW TABLES LIKE '{$groupsTable}'") === $groupsTable) {
            $stats['groups'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$groupsTable}");
        }

        // Count member types
        if (post_type_exists('bp-member-type')) {
            $stats['member_types'] = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
                    'bp-member-type'
                )
            );
        }

        // Count xProfile fields
        if ($wpdb->get_var("SHOW TABLES LIKE '{$xprofileFieldsTable}'") === $xprofileFieldsTable) {
            $stats['xprofile_fields'] = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$xprofileFieldsTable} WHERE parent_id = 0"
            );
        }

        // Count xProfile data entries
        if ($wpdb->get_var("SHOW TABLES LIKE '{$xprofileDataTable}'") === $xprofileDataTable) {
            $stats['xprofile_data'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$xprofileDataTable}");
        }

        return $stats;
    }

    /**
     * Get event statistics
     *
     * @return array
     */
    private function getEventStats() {
        global $wpdb;

        $eventsTable = $wpdb->prefix . 'fpse_events';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$eventsTable}'") !== $eventsTable) {
            return [
                'total' => 0,
                'by_type' => [],
                'recent_30_days' => 0,
            ];
        }

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$eventsTable}");

        // Events by type
        $eventsByType = $wpdb->get_results(
            "SELECT event, COUNT(*) as count 
             FROM {$eventsTable} 
             GROUP BY event 
             ORDER BY count DESC",
            ARRAY_A
        );

        $byType = [];
        foreach ($eventsByType as $row) {
            $byType[$row['event']] = (int) $row['count'];
        }

        // Recent events (last 30 days)
        $recentEvents = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) 
                 FROM {$eventsTable} 
                 WHERE created_at >= %s",
                date('Y-m-d H:i:s', strtotime('-30 days'))
            )
        );

        return [
            'total' => $total,
            'by_type' => $byType,
            'recent_30_days' => (int) $recentEvents,
        ];
    }

    /**
     * Get profile statistics
     *
     * @return array
     */
    private function getProfileStats() {
        global $wpdb;

        // Get users by profile from user meta
        $profiles = $wpdb->get_results(
            "SELECT meta_value as profile, COUNT(*) as count 
             FROM {$wpdb->usermeta} 
             WHERE meta_key = 'fpse_perfil_usuario' 
             GROUP BY meta_value 
             ORDER BY count DESC",
            ARRAY_A
        );

        $profileStats = [];
        foreach ($profiles as $row) {
            $profileStats[$row['profile']] = (int) $row['count'];
        }

        return $profileStats;
    }
}
