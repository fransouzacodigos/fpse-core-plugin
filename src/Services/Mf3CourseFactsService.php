<?php
/**
 * Canonical LearnDash facts service for the MF3 panel.
 *
 * Uses the canonical course ID configured for the panel and keeps LearnDash as
 * the source of truth for course access, progress, completion and course activity.
 *
 * @package FortaleceePSE
 * @subpackage Services
 */

namespace FortaleceePSE\Core\Services;

use FortaleceePSE\Core\Plugin;

class Mf3CourseFactsService {
    /**
     * @var bool
     */
    private static $loggedDiagnostics = false;
    /**
     * @var Plugin
     */
    private $plugin;

    /**
     * @var int
     */
    private $courseId;

    /**
     * @var string
     */
    private $courseSlug;

    /**
     * @var string
     */
    private $courseTitle;

    /**
     * @var int
     */
    private $cacheTtl;

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;

        $config = (array) $plugin->getConfig('mf3_panel', []);
        $this->courseId = absint($config['course_id'] ?? 0);
        $this->courseSlug = sanitize_title((string) ($config['course_slug'] ?? ''));
        $this->courseTitle = sanitize_text_field((string) ($config['course_title'] ?? ''));
        $this->cacheTtl = max(60, (int) ($config['facts_cache_ttl'] ?? 300));
    }

    /**
     * Return canonical course metadata and runtime availability.
     *
     * @return array
     */
    public function getCourseConfig() {
        $postType = $this->courseId > 0 ? get_post_type($this->courseId) : null;
        $post = $this->courseId > 0 ? get_post($this->courseId) : null;
        $resolvedSlug = $post ? (string) $post->post_name : '';
        $resolvedTitle = $post ? get_the_title($post) : '';

        $config = [
            'course_id' => $this->courseId,
            'course_slug' => $this->courseSlug !== '' ? $this->courseSlug : $resolvedSlug,
            'course_title' => $this->courseTitle !== '' ? $this->courseTitle : $resolvedTitle,
            'course_post_type' => $postType,
            'is_valid_course' => $this->courseId > 0 && $postType === 'sfwd-courses',
            'runtime' => [
                'has_course_access_api' => function_exists('sfwd_lms_has_access'),
                'has_course_users_api' => function_exists('learndash_get_users_for_course'),
                'has_progress_api' => function_exists('learndash_user_get_course_progress'),
                'has_completion_api' => function_exists('learndash_course_completed'),
                'has_completion_date_api' => function_exists('learndash_user_get_course_completed_date'),
                'has_activity_api' => function_exists('learndash_get_user_activity'),
                'has_ld_db' => class_exists('\LDLMS_DB'),
            ],
        ];

        $this->logDiagnosticsOnce($config);

        return $config;
    }

    /**
     * Resolve canonical MF3 course facts for the given users.
     *
     * @param int[] $userIds
     * @return array<int, array>
     */
    public function getFactsForUsers(array $userIds) {
        $userIds = array_values(array_unique(array_filter(array_map('absint', $userIds))));
        if (empty($userIds) || $this->courseId <= 0) {
            return [];
        }

        $accessMap = $this->getCourseAccessMap($userIds);
        $activityMap = $this->getCourseActivityMap($userIds);
        $facts = [];

        foreach ($userIds as $userId) {
            $cacheKey = $this->getUserFactsCacheKey($userId);
            $cached = wp_cache_get($cacheKey, 'fpse-core');
            if (is_array($cached)) {
                $facts[$userId] = $cached;
                continue;
            }

            $facts[$userId] = $this->buildUserFacts(
                $userId,
                !empty($accessMap[$userId]),
                $activityMap[$userId] ?? null
            );

            wp_cache_set($cacheKey, $facts[$userId], 'fpse-core', $this->cacheTtl);
        }

        return $facts;
    }

    /**
     * Determine the runtime reason when course facts are not available.
     *
     * @return string
     */
    public function getAvailabilityReason() {
        $config = $this->getCourseConfig();

        if ($config['course_id'] <= 0) {
            return 'mf3_course_id_missing';
        }

        if (!$config['is_valid_course']) {
            return 'mf3_course_not_found_or_invalid_post_type';
        }

        if (!$config['runtime']['has_progress_api'] && !$config['runtime']['has_activity_api']) {
            return 'learndash_runtime_unavailable';
        }

        if (!$config['runtime']['has_course_access_api'] && !$config['runtime']['has_course_users_api']) {
            return 'learndash_course_access_api_unavailable';
        }

        return 'course_facts_enabled';
    }

    /**
     * Build canonical facts for a single user.
     *
     * @param int $userId
     * @param bool $hasAccess
     * @param array|null $activity
     * @return array
     */
    private function buildUserFacts($userId, $hasAccess, $activity = null) {
        $completed = $hasAccess && $this->isCourseCompleted($userId);
        $completionTs = $completed ? $this->getCourseCompletionDate($userId) : null;
        $progressSnapshot = $hasAccess ? $this->getCourseProgressSnapshot($userId, $completed) : [
            'progress_percent' => 0,
            'steps_completed' => 0,
            'steps_total' => null,
            'course_started_at' => null,
        ];
        $progress = isset($progressSnapshot['progress_percent']) ? (int) $progressSnapshot['progress_percent'] : 0;
        $stepsCompleted = isset($progressSnapshot['steps_completed']) ? max(0, (int) $progressSnapshot['steps_completed']) : 0;
        $activityStartedTs = is_array($activity) && !empty($activity['course_started_at'])
            ? (int) $activity['course_started_at']
            : null;
        $courseStartedTs = isset($progressSnapshot['course_started_at']) && $progressSnapshot['course_started_at'] !== null
            ? (int) $progressSnapshot['course_started_at']
            : $activityStartedTs;

        $lastActivityTs = $this->extractLastActivityTimestamp($activity, $completionTs);
        $lastActivityType = $this->resolveLastActivityType($activity, $completed, $hasAccess);
        $hasMeasurableProgress = $hasAccess && ($completed || $progress > 0 || $stepsCompleted > 0);
        $started = $hasAccess && (
            $progress > 0 ||
            $stepsCompleted > 0 ||
            $completed ||
            $courseStartedTs !== null ||
            ($lastActivityTs !== null && $lastActivityType !== 'course_access_detected')
        );
        $progressState = $this->resolveCourseProgressState($hasAccess, $started, $hasMeasurableProgress, $completed);
        $status = $this->resolveCourseStatus($progressState);

        return [
            'user_id' => (int) $userId,
            'course_id' => $this->courseId,
            'has_course_access' => $hasAccess,
            'has_started_course' => $started,
            'course_started_at' => $courseStartedTs ? gmdate('c', $courseStartedTs) : null,
            'course_started_at_ts' => $courseStartedTs,
            'steps_completed' => $stepsCompleted,
            'has_measurable_progress' => $hasMeasurableProgress,
            'progress_percent' => $progress,
            'has_completed_course' => $completed,
            'completed_at' => $completionTs ? gmdate('c', $completionTs) : null,
            'completed_at_ts' => $completionTs,
            'last_activity_at' => $lastActivityTs ? gmdate('c', $lastActivityTs) : null,
            'last_activity_at_ts' => $lastActivityTs,
            'last_activity_type' => $lastActivityType,
            'course_status' => $status,
            'course_progress_state' => $progressState,
            'has_access' => $hasAccess,
            'started' => $started,
            'completed' => $completed,
            'not_started' => $status === 'enrolled_not_started',
            'completion_date' => $completionTs,
            'last_access_ts' => $lastActivityTs,
            'last_access' => $lastActivityTs ? gmdate('c', $lastActivityTs) : null,
        ];
    }

    /**
     * Resolve course access in batch when possible.
     *
     * @param int[] $userIds
     * @return array<int, bool>
     */
    private function getCourseAccessMap(array $userIds) {
        $access = array_fill_keys($userIds, false);

        if (function_exists('learndash_get_users_for_course')) {
            $results = learndash_get_users_for_course($this->courseId, [
                'fields' => 'ID',
                'number' => count($userIds),
                'include' => $userIds,
            ]);

            if (is_array($results)) {
                foreach ($results as $value) {
                    $userId = is_object($value) ? absint($value->ID ?? 0) : absint($value);
                    if ($userId > 0) {
                        $access[$userId] = true;
                    }
                }
            } elseif ($results instanceof \WP_User_Query) {
                foreach ((array) $results->get_results() as $value) {
                    $userId = is_object($value) ? absint($value->ID ?? 0) : absint($value);
                    if ($userId > 0) {
                        $access[$userId] = true;
                    }
                }
            }
        }

        if (function_exists('sfwd_lms_has_access')) {
            foreach ($userIds as $userId) {
                if ($access[$userId]) {
                    continue;
                }

                $access[$userId] = (bool) sfwd_lms_has_access($this->courseId, $userId);
            }
        }

        return $access;
    }

    /**
     * Resolve course-level activity in batch from the LearnDash activity table.
     *
     * @param int[] $userIds
     * @return array<int, array>
     */
    private function getCourseActivityMap(array $userIds) {
        global $wpdb;

        $map = [];
        if (!class_exists('\LDLMS_DB') || !method_exists('\LDLMS_DB', 'get_table_name')) {
            return $map;
        }

        $table = \LDLMS_DB::get_table_name('user_activity');
        if (!$table) {
            return $map;
        }

        $placeholders = implode(',', array_fill(0, count($userIds), '%d'));
        $params = array_merge([$this->courseId], $userIds);

        $query = $wpdb->prepare(
            "
            SELECT user_id, activity_type, activity_status, activity_started, activity_completed, activity_updated
            FROM {$table}
            WHERE course_id = %d
              AND activity_type IN ('course', 'lesson', 'topic')
              AND user_id IN ({$placeholders})
            ",
            $params
        );

        $results = $wpdb->get_results($query, ARRAY_A);
        if (!is_array($results)) {
            return $map;
        }

        foreach ($results as $row) {
            $userId = absint($row['user_id'] ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $existingStartedAt = isset($map[$userId]['course_started_at']) && !empty($map[$userId]['course_started_at'])
                ? (int) $map[$userId]['course_started_at']
                : null;

            $candidate = [
                'activity_type' => (string) ($row['activity_type'] ?? ''),
                'activity_status' => (string) ($row['activity_status'] ?? ''),
                'activity_started' => $this->normalizeTimestamp($row['activity_started'] ?? null),
                'activity_completed' => $this->normalizeTimestamp($row['activity_completed'] ?? null),
                'activity_updated' => $this->normalizeTimestamp($row['activity_updated'] ?? null),
            ];

            $candidate['activity_ts'] = $this->extractActivityTimestamp($candidate);

            if (
                !isset($map[$userId])
                || (int) ($candidate['activity_ts'] ?? 0) > (int) ($map[$userId]['activity_ts'] ?? 0)
            ) {
                $map[$userId] = $candidate;
            }

            if ($existingStartedAt !== null) {
                $map[$userId]['course_started_at'] = $existingStartedAt;
            }

            if (!empty($candidate['activity_started'])) {
                if (!isset($map[$userId]['course_started_at']) || empty($map[$userId]['course_started_at'])) {
                    $map[$userId]['course_started_at'] = (int) $candidate['activity_started'];
                } else {
                    $map[$userId]['course_started_at'] = min(
                        (int) $map[$userId]['course_started_at'],
                        (int) $candidate['activity_started']
                    );
                }
            }
        }

        return $map;
    }

    /**
     * Resolve progress details without depending on the mutable internal course structure.
     *
     * @param int $userId
     * @param bool $completed
     * @return array<string, int|float|null>
     */
    private function getCourseProgressSnapshot($userId, $completed) {
        $snapshot = [
            'progress_percent' => $completed ? 100 : null,
            'steps_completed' => $completed ? null : 0,
            'steps_total' => null,
            'course_started_at' => null,
        ];

        if ($completed) {
            return $snapshot;
        }

        if (!function_exists('learndash_user_get_course_progress')) {
            return $snapshot;
        }

        $progress = learndash_user_get_course_progress($userId, $this->courseId, 'summary');
        if (!is_array($progress)) {
            $progress = learndash_user_get_course_progress($userId, $this->courseId);
        }

        if (!is_array($progress)) {
            return $snapshot;
        }

        foreach (['percentage', 'percent', 'progress'] as $key) {
            if (isset($progress[$key]) && is_numeric($progress[$key])) {
                $snapshot['progress_percent'] = $this->normalizePercent((float) $progress[$key]);
                break;
            }
        }

        $stepsCompleted = $this->extractFirstNumericValue($progress, [
            'steps_completed',
            'completed_steps',
            'completed',
        ]);
        $stepsTotal = $this->extractFirstNumericValue($progress, [
            'steps_total',
            'total_steps',
            'total',
        ]);
        $courseStartedAt = $this->extractFirstTimestampValue($progress, [
            'course_started_on',
            'course_started_at',
            'started_on',
            'started_at',
        ]);

        if ($stepsCompleted !== null) {
            $snapshot['steps_completed'] = $stepsCompleted;
        }

        if ($stepsTotal !== null) {
            $snapshot['steps_total'] = $stepsTotal;
        }

        if ($courseStartedAt !== null) {
            $snapshot['course_started_at'] = $courseStartedAt;
        }

        if ($snapshot['progress_percent'] === null && $stepsCompleted !== null && $stepsTotal !== null && $stepsTotal > 0) {
            $snapshot['progress_percent'] = $this->normalizePercent(($stepsCompleted / $stepsTotal) * 100);
        }

        if ($snapshot['progress_percent'] === null) {
            $snapshot['progress_percent'] = 0;
        }

        if ($snapshot['steps_completed'] === null) {
            $snapshot['steps_completed'] = 0;
        }

        return $snapshot;
    }

    /**
     * @param int $userId
     * @return bool
     */
    private function isCourseCompleted($userId) {
        return function_exists('learndash_course_completed')
            ? (bool) learndash_course_completed($userId, $this->courseId)
            : false;
    }

    /**
     * @param int $userId
     * @return int|null
     */
    private function getCourseCompletionDate($userId) {
        if (!function_exists('learndash_user_get_course_completed_date')) {
            return null;
        }

        return $this->normalizeTimestamp(
            learndash_user_get_course_completed_date($userId, $this->courseId)
        );
    }

    /**
     * @param array|null $activity
     * @param int|null $completionDate
     * @return int|null
     */
    private function extractLastActivityTimestamp($activity, $completionDate) {
        if (is_array($activity)) {
            $activityTs = $this->extractActivityTimestamp($activity);
            if ($activityTs !== null) {
                return $activityTs;
            }
        }

        return $completionDate;
    }

    /**
     * @param array $activity
     * @return int|null
     */
    private function extractActivityTimestamp(array $activity) {
        foreach (['activity_updated', 'activity_completed', 'activity_started'] as $key) {
            if (!empty($activity[$key])) {
                return (int) $activity[$key];
            }
        }

        return null;
    }

    /**
     * Normalize timestamps returned as int, mysql string or falsey values.
     *
     * @param mixed $value
     * @return int|null
     */
    private function normalizeTimestamp($value) {
        if ($value === null || $value === '' || $value === '0' || $value === 0) {
            return null;
        }

        if (is_numeric($value)) {
            $timestamp = (int) $value;
            return $timestamp > 0 ? $timestamp : null;
        }

        $parsed = strtotime((string) $value);
        return $parsed ?: null;
    }

    /**
     * @param float $value
     * @return float
     */
    private function normalizePercent($value) {
        return max(0, min(100, (int) round($value, 0, PHP_ROUND_HALF_UP)));
    }

    /**
     * @param array|null $activity
     * @param bool $completed
     * @param bool $hasAccess
     * @return string
     */
    private function resolveLastActivityType($activity, $completed, $hasAccess) {
        if ($completed) {
            return 'course_completed';
        }

        if (!is_array($activity)) {
            return $hasAccess ? 'course_access_detected' : 'unknown';
        }

        $type = (string) ($activity['activity_type'] ?? '');
        $status = strtolower((string) ($activity['activity_status'] ?? ''));
        $isCompleted = !empty($activity['activity_completed']) || in_array($status, ['1', 'complete', 'completed'], true);

        if ($type === 'lesson' && $isCompleted) {
            return 'lesson_completed';
        }

        if ($type === 'topic' && $isCompleted) {
            return 'topic_completed';
        }

        if ($type === 'course' && $isCompleted) {
            return 'course_completed';
        }

        if (in_array($type, ['course', 'lesson', 'topic'], true) && $this->extractActivityTimestamp($activity) !== null) {
            return 'progress_update';
        }

        return $hasAccess ? 'course_access_detected' : 'unknown';
    }

    /**
     * @param bool $hasAccess
     * @param bool $started
     * @param bool $completed
     * @return string
     */
    private function resolveCourseProgressState($hasAccess, $started, $hasMeasurableProgress, $completed) {
        if ($completed) {
            return 'completed';
        }

        if (!$hasAccess) {
            return 'no_access';
        }

        if (!$started) {
            return 'not_started';
        }

        if (!$hasMeasurableProgress) {
            return 'started_no_progress';
        }

        return 'in_progress';
    }

    /**
     * @param string $progressState
     * @return string
     */
    private function resolveCourseStatus($progressState) {
        if ($progressState === 'completed') {
            return 'completed';
        }

        if ($progressState === 'no_access') {
            return 'no_access';
        }

        if (in_array($progressState, ['not_started', 'started_no_progress'], true)) {
            return 'enrolled_not_started';
        }

        return 'in_progress';
    }

    /**
     * @param array $progress
     * @param array $keys
     * @return int|null
     */
    private function extractFirstNumericValue(array $progress, array $keys) {
        foreach ($keys as $key) {
            if (isset($progress[$key]) && is_numeric($progress[$key])) {
                return max(0, (int) $progress[$key]);
            }
        }

        return null;
    }

    /**
     * @param array $progress
     * @param array $keys
     * @return int|null
     */
    private function extractFirstTimestampValue(array $progress, array $keys) {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $progress)) {
                continue;
            }

            $timestamp = $this->normalizeTimestamp($progress[$key]);
            if ($timestamp !== null) {
                return $timestamp;
            }
        }

        return null;
    }

    /**
     * @param int $userId
     * @return string
     */
    private function getUserFactsCacheKey($userId) {
        return 'mf3_course_facts:' . $this->courseId . ':' . $userId;
    }

    /**
     * Emit one diagnostic line per request in debug mode.
     *
     * @param array $config
     * @return void
     */
    private function logDiagnosticsOnce(array $config) {
        if (self::$loggedDiagnostics || !defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        self::$loggedDiagnostics = true;

        error_log('FPSE MF3 FACTS: runtime diagnostics ' . wp_json_encode([
            'plugin_version' => defined('FPSE_CORE_VERSION') ? FPSE_CORE_VERSION : null,
            'course_id' => $config['course_id'] ?? null,
            'course_slug' => $config['course_slug'] ?? null,
            'course_title' => $config['course_title'] ?? null,
            'course_post_type' => $config['course_post_type'] ?? null,
            'is_valid_course' => $config['is_valid_course'] ?? false,
            'runtime' => $config['runtime'] ?? [],
            'reason' => $this->getAvailabilityReason(),
        ]));
    }
}
