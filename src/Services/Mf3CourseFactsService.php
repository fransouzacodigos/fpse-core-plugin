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
        $completionDate = $completed ? $this->getCourseCompletionDate($userId) : null;
        $progress = $hasAccess ? $this->getCourseProgressPercent($userId, $completed) : null;

        $started = $hasAccess && (
            !empty($activity['activity_started']) ||
            !empty($activity['activity_updated']) ||
            $completed ||
            ($progress !== null && $progress > 0)
        );

        if ($hasAccess && !$started && !$completed && $progress === null) {
            $progress = 0.0;
        }

        $lastAccessTs = $this->extractLastAccessTimestamp($activity, $completionDate);

        return [
            'course_id' => $this->courseId,
            'has_access' => $hasAccess,
            'started' => $started,
            'completed' => $completed,
            'not_started' => $hasAccess && !$started && !$completed,
            'progress_percent' => $progress,
            'completion_date' => $completionDate,
            'last_access_ts' => $lastAccessTs,
            'last_access' => $lastAccessTs ? gmdate('c', $lastAccessTs) : null,
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
            SELECT user_id, activity_status, activity_started, activity_completed, activity_updated
            FROM {$table}
            WHERE course_id = %d
              AND activity_type = 'course'
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

            $map[$userId] = [
                'activity_status' => (string) ($row['activity_status'] ?? ''),
                'activity_started' => $this->normalizeTimestamp($row['activity_started'] ?? null),
                'activity_completed' => $this->normalizeTimestamp($row['activity_completed'] ?? null),
                'activity_updated' => $this->normalizeTimestamp($row['activity_updated'] ?? null),
            ];
        }

        return $map;
    }

    /**
     * Resolve progress without depending on the mutable internal course structure.
     *
     * @param int $userId
     * @param bool $completed
     * @return float|null
     */
    private function getCourseProgressPercent($userId, $completed) {
        if ($completed) {
            return 100.0;
        }

        if (!function_exists('learndash_user_get_course_progress')) {
            return null;
        }

        $progress = learndash_user_get_course_progress($userId, $this->courseId, 'summary');
        if (!is_array($progress)) {
            $progress = learndash_user_get_course_progress($userId, $this->courseId);
        }

        if (!is_array($progress)) {
            return null;
        }

        foreach (['percentage', 'percent', 'progress'] as $key) {
            if (isset($progress[$key]) && is_numeric($progress[$key])) {
                return $this->clampPercent((float) $progress[$key]);
            }
        }

        if (isset($progress['completed'], $progress['total']) && (int) $progress['total'] > 0) {
            return $this->clampPercent(((int) $progress['completed'] / (int) $progress['total']) * 100);
        }

        if (isset($progress['steps_completed'], $progress['steps_total']) && (int) $progress['steps_total'] > 0) {
            return $this->clampPercent(((int) $progress['steps_completed'] / (int) $progress['steps_total']) * 100);
        }

        if (isset($progress['completed_steps'], $progress['total_steps']) && (int) $progress['total_steps'] > 0) {
            return $this->clampPercent(((int) $progress['completed_steps'] / (int) $progress['total_steps']) * 100);
        }

        return null;
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
    private function extractLastAccessTimestamp($activity, $completionDate) {
        if (is_array($activity)) {
            foreach (['activity_updated', 'activity_completed', 'activity_started'] as $key) {
                if (!empty($activity[$key])) {
                    return (int) $activity[$key];
                }
            }
        }

        return $completionDate;
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
    private function clampPercent($value) {
        return max(0.0, min(100.0, round($value, 2)));
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
