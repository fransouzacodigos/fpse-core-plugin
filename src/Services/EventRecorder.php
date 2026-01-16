<?php
/**
 * Event Recorder for audit trail
 *
 * Records all important events in wp_fpse_events table
 *
 * @package FortaleceePSE
 * @subpackage Services
 */

namespace FortaleceePSE\Core\Services;

class EventRecorder {
    /**
     * @var \wpdb
     */
    private $wpdb;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Record a user registration event
     *
     * Evento 'registered' conforme especificação oficial
     *
     * @param int $userId WordPress user ID
     * @param string $perfil User profile
     * @param string $estado State code
     * @param array $metadata Additional metadata
     * @return int|false Event ID or false on failure
     */
    public function recordRegistration($userId, $perfil, $estado, $metadata = []) {
        return $this->recordEvent($userId, 'registered', $perfil, $estado, $metadata);
    }

    /**
     * Record a user update event
     *
     * @param int $userId WordPress user ID
     * @param string $perfil User profile
     * @param string $estado State code
     * @param array $metadata Additional metadata
     * @return int|false Event ID or false on failure
     */
    public function recordUpdate($userId, $perfil, $estado, $metadata = []) {
        return $this->recordEvent($userId, 'user_updated', $perfil, $estado, $metadata);
    }

    /**
     * Record a profile assignment event
     *
     * @param int $userId WordPress user ID
     * @param string $perfil User profile
     * @param string $estado State code
     * @return int|false Event ID or false on failure
     */
    public function recordProfileAssigned($userId, $perfil, $estado) {
        return $this->recordEvent($userId, 'profile_assigned', $perfil, $estado, []);
    }

    /**
     * Record a state assignment event
     *
     * @param int $userId WordPress user ID
     * @param string $perfil User profile
     * @param string $estado State code
     * @return int|false Event ID or false on failure
     */
    public function recordStateAssigned($userId, $perfil, $estado) {
        return $this->recordEvent($userId, 'state_assigned', $perfil, $estado, []);
    }

    /**
     * Record a group assignment event
     *
     * @param int $userId WordPress user ID
     * @param string $estado State code
     * @param string $perfil User profile
     * @return int|false Event ID or false on failure
     */
    public function recordGroupAssigned($userId, $estado, $perfil) {
        return $this->recordEvent($userId, 'group_assigned', $perfil, $estado, [
            'group_slug' => 'estado-' . strtolower($estado),
        ]);
    }

    /**
     * Record a validation error event
     *
     * @param string $perfil User profile attempted
     * @param string $estado State code attempted
     * @param array $errors Validation errors
     * @return int|false Event ID or false on failure
     */
    public function recordValidationError($perfil, $estado, $errors = []) {
        return $this->recordEvent(0, 'validation_error', $perfil, $estado, ['errors' => $errors]);
    }

    /**
     * Record a member type assignment event
     *
     * @param int $userId WordPress user ID
     * @param string $perfil User profile
     * @param string|null $estado State code (optional)
     * @param array $metadata Additional metadata (e.g., member_type)
     * @return int|false Event ID or false on failure
     */
    public function recordMemberTypeAssigned($userId, $perfil, $estado = null, $metadata = []) {
        return $this->recordEvent($userId, 'member_type_assigned', $perfil, $estado ?? '', $metadata);
    }

    /**
     * Record a generic event
     *
     * @param int $userId WordPress user ID (0 for anonymous events)
     * @param string $event Event name
     * @param string $perfil User profile
     * @param string $estado State code
     * @param array $metadata Additional metadata
     * @return int|false Event ID or false on failure
     */
    private function recordEvent($userId, $event, $perfil, $estado, $metadata = []) {
        $table = $this->wpdb->prefix . 'fpse_events';

        $data = [
            'user_id' => absint($userId),
            'event' => sanitize_text_field($event),
            'perfil' => sanitize_text_field($perfil),
            'estado' => sanitize_text_field($estado),
            'metadata' => wp_json_encode($metadata),
            'created_at' => current_time('mysql'),
        ];

        $result = $this->wpdb->insert($table, $data);

        if ($result === false) {
            error_log('FPSE: Failed to record event: ' . $this->wpdb->last_error);
            return false;
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Get events for a user
     *
     * @param int $userId WordPress user ID
     * @param int $limit Number of events to return
     * @return array
     */
    public function getUserEvents($userId, $limit = 100) {
        $table = $this->wpdb->prefix . 'fpse_events';

        $query = $this->wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
            absint($userId),
            absint($limit)
        );

        return $this->wpdb->get_results($query);
    }

    /**
     * Get events by type
     *
     * @param string $event Event type
     * @param int $limit Number of events to return
     * @return array
     */
    public function getEventsByType($event, $limit = 100) {
        $table = $this->wpdb->prefix . 'fpse_events';

        $query = $this->wpdb->prepare(
            "SELECT * FROM $table WHERE event = %s ORDER BY created_at DESC LIMIT %d",
            sanitize_text_field($event),
            absint($limit)
        );

        return $this->wpdb->get_results($query);
    }

    /**
     * Get events by state
     *
     * @param string $estado State code
     * @param int $limit Number of events to return
     * @return array
     */
    public function getEventsByState($estado, $limit = 100) {
        $table = $this->wpdb->prefix . 'fpse_events';

        $query = $this->wpdb->prepare(
            "SELECT * FROM $table WHERE estado = %s ORDER BY created_at DESC LIMIT %d",
            sanitize_text_field($estado),
            absint($limit)
        );

        return $this->wpdb->get_results($query);
    }
}
