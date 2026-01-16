<?php
/**
 * Report Registry
 *
 * Builds queries for common report patterns
 * Does NOT implement export (prepared for future reports)
 *
 * @package FortaleceePSE
 * @subpackage Reports
 */

namespace FortaleceePSE\Core\Reports;

use FortaleceePSE\Core\Plugin;

class ReportRegistry {
    /**
     * @var \wpdb
     */
    private $wpdb;

    /**
     * @var Plugin
     */
    private $plugin;

    /**
     * @var string
     */
    private $table;

    /**
     * Constructor
     *
     * @param Plugin $plugin Main plugin instance
     */
    public function __construct(Plugin $plugin) {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->plugin = $plugin;
        $this->table = $this->wpdb->prefix . 'fpse_events';
    }

    /**
     * Get registrations by state
     *
     * @param string $estado State code
     * @return array Raw report data
     */
    public function byState($estado) {
        $estado = sanitize_text_field($estado);

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table}
            WHERE estado = %s AND event = 'registered'
            ORDER BY created_at DESC",
            $estado
        );

        return $this->wpdb->get_results($query);
    }

    /**
     * Get registrations by profile
     *
     * @param string $perfil Profile name
     * @return array Raw report data
     */
    public function byProfile($perfil) {
        $perfil = sanitize_text_field($perfil);

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table}
            WHERE perfil = %s AND event = 'registered'
            ORDER BY created_at DESC",
            $perfil
        );

        return $this->wpdb->get_results($query);
    }

    /**
     * Get registrations by state and profile
     *
     * @param string $estado State code
     * @param string $perfil Profile name
     * @return array Raw report data
     */
    public function byStateAndProfile($estado, $perfil) {
        $estado = sanitize_text_field($estado);
        $perfil = sanitize_text_field($perfil);

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table}
            WHERE estado = %s AND perfil = %s AND event = 'registered'
            ORDER BY created_at DESC",
            $estado,
            $perfil
        );

        return $this->wpdb->get_results($query);
    }

    /**
     * Get registrations in date range
     *
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return array Raw report data
     */
    public function byDateRange($startDate, $endDate) {
        $startDate = sanitize_text_field($startDate);
        $endDate = sanitize_text_field($endDate);

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table}
            WHERE event = 'registered'
            AND created_at >= %s
            AND created_at <= %s
            ORDER BY created_at DESC",
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59'
        );

        return $this->wpdb->get_results($query);
    }

    /**
     * Get registrations by state and date range
     *
     * @param string $estado State code
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return array Raw report data
     */
    public function byStateAndDate($estado, $startDate, $endDate) {
        $estado = sanitize_text_field($estado);
        $startDate = sanitize_text_field($startDate);
        $endDate = sanitize_text_field($endDate);

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table}
            WHERE estado = %s AND event = 'registered'
            AND created_at >= %s
            AND created_at <= %s
            ORDER BY created_at DESC",
            $estado,
            $startDate . ' 00:00:00',
            $endDate . ' 23:59:59'
        );

        return $this->wpdb->get_results($query);
    }

    /**
     * Get all registrations (paginated)
     *
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @return array Raw report data with pagination info
     */
    public function getAllRegistrations($page = 1, $perPage = 100) {
        $page = absint($page);
        $perPage = absint($perPage);
        $offset = ($page - 1) * $perPage;

        // Get total count
        $countQuery = "SELECT COUNT(*) as total FROM {$this->table} WHERE event = 'registered'";
        $total = $this->wpdb->get_var($countQuery);

        // Get paginated results
        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table}
            WHERE event = 'registered'
            ORDER BY created_at DESC
            LIMIT %d OFFSET %d",
            $perPage,
            $offset
        );

        return [
            'data' => $this->wpdb->get_results($query),
            'total' => (int) $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Get registration count by state
     *
     * @return array State codes with registration counts
     */
    public function countByState() {
        $query = "SELECT estado, COUNT(*) as count
            FROM {$this->table}
            WHERE event = 'registered'
            GROUP BY estado
            ORDER BY count DESC";

        return $this->wpdb->get_results($query, OBJECT_K);
    }

    /**
     * Get registration count by profile
     *
     * @return array Profile names with registration counts
     */
    public function countByProfile() {
        $query = "SELECT perfil, COUNT(*) as count
            FROM {$this->table}
            WHERE event = 'registered'
            GROUP BY perfil
            ORDER BY count DESC";

        return $this->wpdb->get_results($query, OBJECT_K);
    }

    /**
     * Get registration count by state and profile
     *
     * @return array Cross-tabulation of states and profiles
     */
    public function countByStateAndProfile() {
        $query = "SELECT estado, perfil, COUNT(*) as count
            FROM {$this->table}
            WHERE event = 'registered'
            GROUP BY estado, perfil
            ORDER BY estado ASC, perfil ASC";

        return $this->wpdb->get_results($query);
    }

    /**
     * Get registrations per day
     *
     * @param int $days Number of days to include
     * @return array Daily registration counts
     */
    public function registrationsPerDay($days = 30) {
        $days = absint($days);

        $query = $this->wpdb->prepare(
            "SELECT DATE(created_at) as date, COUNT(*) as count
            FROM {$this->table}
            WHERE event = 'registered'
            AND created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)
            GROUP BY DATE(created_at)
            ORDER BY date DESC",
            $days
        );

        return $this->wpdb->get_results($query);
    }

    /**
     * Get validation errors statistics
     *
     * @return array Error counts and types
     */
    public function validationErrorStats() {
        $query = "SELECT COUNT(*) as total_errors
            FROM {$this->table}
            WHERE event = 'validation_error'";

        $total = $this->wpdb->get_var($query);

        return [
            'total_errors' => (int) $total,
            'note' => 'Full error details available in event metadata',
        ];
    }

    /**
     * Get all events for a user
     *
     * @param int $userId WordPress user ID
     * @return array All events for user
     */
    public function userAuditTrail($userId) {
        $userId = absint($userId);

        $query = $this->wpdb->prepare(
            "SELECT * FROM {$this->table}
            WHERE user_id = %d
            ORDER BY created_at DESC",
            $userId
        );

        return $this->wpdb->get_results($query);
    }

    /**
     * Get raw query results for custom report building
     *
     * @param string $sql Raw SQL query
     * @return array Query results
     */
    public function raw($sql) {
        // Only allow SELECT queries
        if (strpos(strtoupper(trim($sql)), 'SELECT') !== 0) {
            return [];
        }

        return $this->wpdb->get_results($sql);
    }

    /**
     * Get table name for external queries
     *
     * @return string Full table name with prefix
     */
    public function getTableName() {
        return $this->table;
    }

    /**
     * Get wpdb instance for advanced queries
     *
     * For queries not covered by prepared methods
     *
     * @return \wpdb
     */
    public function getDatabase() {
        return $this->wpdb;
    }
}
