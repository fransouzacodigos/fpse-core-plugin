<?php
/**
 * Resolves territorial context for the authenticated user.
 *
 * Canonical source priority:
 * 1. user_meta `estado`
 * 2. xProfile field `estado` / `Estado`
 * 3. BuddyBoss group lookup by canonical slug `estado-{uf}`
 *
 * @package FortaleceePSE
 * @subpackage Services
 */

namespace FortaleceePSE\Core\Services;

use FortaleceePSE\Core\Plugin;

class UserTerritorialContextResolver {
    /**
     * @var Plugin
     */
    private $plugin;

    /**
     * @var \FortaleceePSE\Core\Utils\Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param Plugin $plugin Main plugin instance.
     */
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
        $this->logger = $plugin->getLogger();
    }

    /**
     * Resolve territorial context for a user.
     *
     * @param int|null $userId WordPress user ID. Defaults to current user.
     * @return array
     */
    public function resolve($userId = null) {
        $userId = $userId ? (int) $userId : get_current_user_id();
        $states = $this->plugin->getConfig('states', []);

        $context = [
            'success' => false,
            'authenticated' => $userId > 0,
            'user_id' => $userId,
            'uf' => null,
            'uf_source' => null,
            'group_slug' => null,
            'group_id' => null,
            'group_exists' => false,
            'group_base_url' => null,
            'group_directory_url' => $this->getGroupDirectoryUrl(),
            'errors' => [],
        ];

        if ($userId <= 0) {
            $context['errors'][] = 'user_not_authenticated';
            return $context;
        }

        $ufData = $this->resolveUserUf($userId, $states);
        if (!$ufData['uf']) {
            $context['errors'][] = 'uf_not_resolved';
            $this->logDiagnostic('warn', 'UF não resolvida para contexto territorial', [
                'user_id' => $userId,
                'sources_checked' => $ufData['sources_checked'],
            ]);
            return $context;
        }

        $context['uf'] = $ufData['uf'];
        $context['uf_source'] = $ufData['source'];
        $context['group_slug'] = $this->buildStateGroupSlug($ufData['uf']);

        $group = $this->findGroupBySlug($context['group_slug']);
        if ($group) {
            $context['group_exists'] = true;
            $context['group_id'] = isset($group->id) ? (int) $group->id : null;
            $context['group_base_url'] = $this->resolveGroupBaseUrl($group, $context['group_slug']);
        } else {
            $context['errors'][] = 'group_not_found';
            $context['group_base_url'] = $this->buildPredictedGroupBaseUrl($context['group_slug']);
            $this->logDiagnostic('warn', 'Grupo estadual não encontrado por slug; usando URL predita', [
                'user_id' => $userId,
                'uf' => $context['uf'],
                'group_slug' => $context['group_slug'],
            ]);
        }

        $context['success'] = !empty($context['uf']) && !empty($context['group_slug']) && !empty($context['group_base_url']);

        return apply_filters('fpse_user_territorial_context', $context, $userId);
    }

    /**
     * Resolve a user's UF using project canonical sources.
     *
     * @param int $userId WordPress user ID.
     * @param array $states Valid states map.
     * @return array
     */
    private function resolveUserUf($userId, array $states) {
        $sourcesChecked = [];

        $metaUf = $this->sanitizeUf(get_user_meta($userId, 'estado', true), $states);
        $sourcesChecked[] = 'user_meta.estado';
        if ($metaUf) {
            return [
                'uf' => $metaUf,
                'source' => 'user_meta.estado',
                'sources_checked' => $sourcesChecked,
            ];
        }

        $xprofileUf = $this->resolveUfFromXProfile($userId, $states, $sourcesChecked);
        if ($xprofileUf) {
            return [
                'uf' => $xprofileUf,
                'source' => 'xprofile',
                'sources_checked' => $sourcesChecked,
            ];
        }

        return [
            'uf' => null,
            'source' => null,
            'sources_checked' => $sourcesChecked,
        ];
    }

    /**
     * Resolve UF from xProfile.
     *
     * @param int $userId WordPress user ID.
     * @param array $states Valid states map.
     * @param array $sourcesChecked Diagnostic list by reference.
     * @return string|null
     */
    private function resolveUfFromXProfile($userId, array $states, array &$sourcesChecked) {
        if (!function_exists('xprofile_get_field_data')) {
            $sourcesChecked[] = 'xprofile_api_unavailable';
            return null;
        }

        $fieldId = (int) get_option('fpse_xprofile_field_estado', 0);
        if ($fieldId > 0) {
            $sourcesChecked[] = 'xprofile.field_id.estado';
            $value = xprofile_get_field_data($fieldId, $userId);
            $uf = $this->sanitizeUf($value, $states);
            if ($uf) {
                return $uf;
            }
        }

        foreach (['Estado', 'estado'] as $fieldName) {
            $sourcesChecked[] = 'xprofile.field_name.' . $fieldName;
            $value = xprofile_get_field_data($fieldName, $userId);
            $uf = $this->sanitizeUf($value, $states);
            if ($uf) {
                return $uf;
            }
        }

        return null;
    }

    /**
     * Find BuddyBoss group by slug.
     *
     * @param string $slug Group slug.
     * @return object|null
     */
    private function findGroupBySlug($slug) {
        if (!function_exists('groups_get_groups') || empty($slug)) {
            return null;
        }

        $groups = groups_get_groups([
            'slug' => $slug,
            'per_page' => 1,
            'show_hidden' => true,
        ]);

        if (!empty($groups['groups']) && isset($groups['groups'][0])) {
            return $groups['groups'][0];
        }

        return null;
    }

    /**
     * Resolve the canonical base URL for a BuddyBoss group.
     *
     * @param object $group BuddyBoss group object.
     * @param string $slug Canonical group slug.
     * @return string
     */
    private function resolveGroupBaseUrl($group, $slug) {
        if (function_exists('bp_get_group_permalink')) {
            $permalink = bp_get_group_permalink($group);
            if (is_string($permalink) && $permalink !== '') {
                return trailingslashit($permalink);
            }
        }

        return $this->buildPredictedGroupBaseUrl($slug);
    }

    /**
     * Build predicted group base URL using canonical slug.
     *
     * @param string $slug Group slug.
     * @return string
     */
    private function buildPredictedGroupBaseUrl($slug) {
        if (function_exists('bp_get_groups_directory_permalink')) {
            return trailingslashit(bp_get_groups_directory_permalink()) . trim($slug, '/') . '/';
        }

        return trailingslashit(home_url('/groups/' . trim($slug, '/') . '/'));
    }

    /**
     * Get groups directory URL.
     *
     * @return string
     */
    private function getGroupDirectoryUrl() {
        if (function_exists('bp_get_groups_directory_permalink')) {
            return trailingslashit(bp_get_groups_directory_permalink());
        }

        return trailingslashit(home_url('/groups/'));
    }

    /**
     * Build canonical state group slug.
     *
     * @param string $uf Valid UF.
     * @return string
     */
    private function buildStateGroupSlug($uf) {
        return 'estado-' . strtolower($uf);
    }

    /**
     * Normalize and validate UF.
     *
     * @param mixed $value Raw value.
     * @param array $states Valid states map.
     * @return string|null
     */
    private function sanitizeUf($value, array $states) {
        if (is_array($value)) {
            $value = reset($value);
        }

        $value = strtoupper(trim(sanitize_text_field((string) $value)));
        if ($value === '' || !isset($states[$value])) {
            return null;
        }

        return $value;
    }

    /**
     * Conditional diagnostic logging.
     *
     * @param string $level Logger level.
     * @param string $message Message.
     * @param array $context Context data.
     * @return void
     */
    private function logDiagnostic($level, $message, array $context = []) {
        $debug = $this->plugin->getConfig('debug', []);
        if (empty($debug['enable_debug'])) {
            return;
        }

        if ($level === 'warn') {
            $this->logger->warn('DynamicLinkContext', $message, $context);
            return;
        }

        $this->logger->info('DynamicLinkContext', $message, $context);
    }
}
