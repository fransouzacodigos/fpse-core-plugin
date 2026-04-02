<?php
/**
 * Canonical scope resolver for the MF3 panel.
 *
 * @package FortaleceePSE
 * @subpackage Services
 */

namespace FortaleceePSE\Core\Services;

use FortaleceePSE\Core\Plugin;

class Mf3PanelScopeResolver {
    /**
     * @var Plugin
     */
    private $plugin;

    /**
     * @var array
     */
    private $states;

    /**
     * @var string[]
     */
    private $knownProfiles = [];

    /**
     * @var string[]
     */
    private $activeProfiles = [];

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
        $this->states = (array) $plugin->getConfig('states', []);
        $this->bootstrapCanonicalMatrix();
    }

    /**
     * Resolve the current user's MF3 panel scope.
     *
     * @param int|null $userId
     * @return array
     */
    public function resolve($userId = null) {
        $userId = $userId ? (int) $userId : get_current_user_id();
        $profiles = $userId > 0 ? $this->getUserProfiles($userId) : [];
        $recognizedProfiles = array_values(array_intersect($profiles, $this->knownProfiles));

        $scope = [
            'authenticated' => $userId > 0,
            'user_id' => $userId,
            'profiles' => $profiles,
            'active_profiles' => $recognizedProfiles,
            'resolved_profile' => null,
            'scope_type' => 'sem_acesso',
            'scope_class' => 'none',
            'scope_reason' => 'not_authenticated',
            'uf_source' => 'none',
            'allowed_ufs' => [],
            'allowed_group_slugs' => [],
            'capabilities' => [
                'view_aggregates' => false,
                'view_users' => false,
                'view_attention' => false,
                'export_aggregates' => false,
                'export_users' => false,
            ],
            'features' => [
                'overview' => false,
                'states' => false,
                'schools' => false,
                'users' => false,
                'attention' => false,
                'export_aggregates' => false,
                'export_users' => false,
            ],
            'can_view_overview' => false,
            'can_view_states' => false,
            'can_view_schools' => false,
            'can_view_users' => false,
            'can_view_attention' => false,
            'can_export_aggregates' => false,
            'can_export_users' => false,
            'can_export' => false,
            'data_availability' => [
                'course_progress' => false,
                'last_access' => false,
                'attention_queue' => false,
            ],
        ];

        if ($userId <= 0) {
            return $scope;
        }

        if (current_user_can('manage_options')) {
            return $this->finalizeScope(
                $scope,
                [
                    'scope_mode' => 'nacional',
                    'scope_class' => 'national',
                    'uf_origin' => 'all_states',
                    'capabilities' => [
                        'view_aggregates' => true,
                        'view_users' => true,
                        'view_attention' => true,
                        'export_aggregates' => true,
                        'export_users' => true,
                    ],
                    'features' => [
                        'overview' => true,
                        'states' => true,
                        'schools' => true,
                        'users' => true,
                        'attention' => false,
                        'export_aggregates' => true,
                        'export_users' => true,
                    ],
                ],
                'all_states',
                array_keys($this->states),
                'admin_manage_options'
            );
        }

        $resolved = function_exists('fpse_resolve_mf3_scope_profile')
            ? fpse_resolve_mf3_scope_profile($recognizedProfiles)
            : null;

        if ($resolved === null) {
            return $this->finalizeScope(
                $scope,
                [
                    'scope_mode' => 'sem_acesso',
                    'scope_class' => 'personal',
                    'uf_origin' => 'none',
                    'capabilities' => [
                        'view_aggregates' => false,
                        'view_users' => false,
                        'view_attention' => false,
                        'export_aggregates' => false,
                        'export_users' => false,
                    ],
                    'features' => [
                        'overview' => false,
                        'states' => false,
                        'schools' => false,
                        'users' => false,
                        'attention' => false,
                        'export_aggregates' => false,
                        'export_users' => false,
                    ],
                ],
                'none',
                [],
                'fallback_personal'
            );
        }

        $scope['resolved_profile'] = $resolved['profile'];

        $resolvedScope = $this->resolveAllowedUfsByDefinition($userId, $resolved['definition']);

        return $this->finalizeScope(
            $scope,
            $resolved['definition'],
            $resolvedScope['uf_source'],
            $resolvedScope['allowed_ufs'],
            $resolvedScope['scope_reason']
        );
    }

    /**
     * Expose active panel profiles for analytical services.
     *
     * @return string[]
     */
    public function getActivePanelProfiles() {
        return $this->activeProfiles;
    }

    /**
     * Resolve allowed UFs for a canonical profile definition.
     *
     * @param int $userId
     * @param array $definition
     * @return array
     */
    private function resolveAllowedUfsByDefinition($userId, array $definition) {
        $scopeMode = (string) ($definition['scope_mode'] ?? 'sem_acesso');

        if ($scopeMode === 'nacional') {
            return [
                'allowed_ufs' => array_keys($this->states),
                'uf_source' => 'all_states',
                'scope_reason' => 'national_profile',
            ];
        }

        if ($scopeMode === 'multi_uf') {
            return [
                'allowed_ufs' => $this->getUserFollowedUfs($userId),
                'uf_source' => 'ufs_acompanhadas',
                'scope_reason' => 'ufs_acompanhadas',
            ];
        }

        if ($scopeMode === 'nap_regiao') {
            $allowedUfs = $this->getUserNapRegionUfs($userId);
            if (!empty($allowedUfs)) {
                return [
                    'allowed_ufs' => $allowedUfs,
                    'uf_source' => 'nap_region',
                    'scope_reason' => 'nap_region',
                ];
            }

            return [
                'allowed_ufs' => $this->getUserFollowedUfs($userId),
                'uf_source' => 'ufs_acompanhadas',
                'scope_reason' => 'nap_region_fallback_ufs_acompanhadas',
            ];
        }

        if ($scopeMode === 'uf_unica') {
            $uf = $this->getUserRegistrationUf($userId);

            return [
                'allowed_ufs' => $uf ? [$uf] : [],
                'uf_source' => 'estado_cadastro',
                'scope_reason' => 'estado_cadastro',
            ];
        }

        return [
            'allowed_ufs' => [],
            'uf_source' => 'none',
            'scope_reason' => 'fallback_personal',
        ];
    }

    /**
     * Finalize scope payload with derived capabilities.
     *
     * @param array $scope
     * @param array $definition
     * @param string $ufSource
     * @param array $allowedUfs
     * @param string $reason
     * @return array
     */
    private function finalizeScope(array $scope, array $definition, $ufSource, array $allowedUfs, $reason) {
        $allowedUfs = $this->normalizeUfValues($allowedUfs);
        $capabilities = array_merge($scope['capabilities'], (array) ($definition['capabilities'] ?? []));
        $features = array_merge($scope['features'], (array) ($definition['features'] ?? []));
        $hasTerritorialCoverage = (($definition['scope_class'] ?? 'personal') === 'national') || !empty($allowedUfs);

        $scope['scope_type'] = (string) ($definition['scope_mode'] ?? 'sem_acesso');
        $scope['scope_class'] = (string) ($definition['scope_class'] ?? 'personal');
        $scope['scope_reason'] = (string) $reason;
        $scope['uf_source'] = (string) $ufSource;
        $scope['allowed_ufs'] = $allowedUfs;
        $scope['allowed_group_slugs'] = array_map(static function ($uf) {
            return 'estado-' . strtolower($uf);
        }, $allowedUfs);
        $scope['capabilities'] = $capabilities;
        $scope['features'] = $features;
        $scope['can_view_overview'] = $hasTerritorialCoverage && !empty($capabilities['view_aggregates']) && !empty($features['overview']);
        $scope['can_view_states'] = $hasTerritorialCoverage && !empty($capabilities['view_aggregates']) && !empty($features['states']);
        $scope['can_view_schools'] = $hasTerritorialCoverage && !empty($capabilities['view_aggregates']) && !empty($features['schools']);
        $scope['can_view_users'] = $hasTerritorialCoverage && !empty($capabilities['view_users']) && !empty($features['users']);
        $scope['can_view_attention'] = $hasTerritorialCoverage && !empty($capabilities['view_attention']) && !empty($features['attention']);
        $scope['can_export_aggregates'] = $hasTerritorialCoverage
            && !empty($capabilities['export_aggregates'])
            && !empty($features['export_aggregates']);
        $scope['can_export_users'] = $hasTerritorialCoverage
            && !empty($capabilities['export_users'])
            && !empty($features['export_users']);
        $scope['can_export'] = $scope['can_export_aggregates'];

        return $scope;
    }

    /**
     * Get all detectable user profiles.
     *
     * @param int $userId
     * @return array
     */
    private function getUserProfiles($userId) {
        $profiles = [];

        if (function_exists('bp_get_member_type')) {
            $memberTypes = bp_get_member_type($userId, false);
            if (!is_array($memberTypes)) {
                $memberTypes = $memberTypes ? [$memberTypes] : [];
            }

            foreach ($memberTypes as $memberType) {
                $normalized = $this->normalizeProfileSlug($memberType);
                if ($normalized !== '') {
                    $profiles[$normalized] = true;
                }
            }
        }

        foreach (['perfil_usuario', 'perfilUsuario', 'fpse_perfil_usuario', 'fpse_perfilUsuario'] as $metaKey) {
            $normalized = $this->normalizeProfileSlug(get_user_meta($userId, $metaKey, true));
            if ($normalized !== '') {
                $profiles[$normalized] = true;
            }
        }

        return array_values(array_keys($profiles));
    }

    /**
     * Resolve the user's base UF.
     *
     * @param int $userId
     * @return string|null
     */
    private function getUserRegistrationUf($userId) {
        $value = $this->getFirstUserMetaValue($userId, ['estado', 'fpse_estado', 'uf', 'fpse_uf']);

        if ($value === null && function_exists('xprofile_get_field_data')) {
            foreach (['Estado', 'estado', 'UF', 'uf'] as $fieldName) {
                $value = xprofile_get_field_data($fieldName, $userId);
                if ($value !== '' && $value !== null) {
                    break;
                }
            }
        }

        $ufs = $this->normalizeUfValues($value);
        return $ufs[0] ?? null;
    }

    /**
     * Resolve followed UFs for multi-UF profiles.
     *
     * @param int $userId
     * @return array
     */
    private function getUserFollowedUfs($userId) {
        $value = $this->getFirstUserMetaValue($userId, [
            'ufs_acompanhadas',
            'fpse_ufs_acompanhadas',
            'ufs_acompanhadas_nap',
            'fpse_ufs_acompanhadas_nap',
        ]);

        if ($value === null && function_exists('xprofile_get_field_data')) {
            foreach (['UFs Acompanhadas (NAP)', 'UF(s) acompanhada(s)', 'ufs_acompanhadas'] as $fieldName) {
                $value = xprofile_get_field_data($fieldName, $userId);
                if ($value !== '' && $value !== null) {
                    break;
                }
            }
        }

        return $this->normalizeUfValues($value);
    }

    /**
     * Resolve NAP region UFs using the canonical MU-plugin helper when available.
     *
     * @param int $userId
     * @return array
     */
    private function getUserNapRegionUfs($userId) {
        if (function_exists('fpse_get_user_nap_region_ufs')) {
            return $this->normalizeUfValues(fpse_get_user_nap_region_ufs($userId));
        }

        $value = $this->getFirstUserMetaValue($userId, [
            'regiao_responsavel',
            'fpse_regiao_responsavel',
        ]);

        if ($value === null && function_exists('xprofile_get_field_data')) {
            foreach (['Região Responsável', 'regiao_responsavel'] as $fieldName) {
                $value = xprofile_get_field_data($fieldName, $userId);
                if ($value !== '' && $value !== null) {
                    break;
                }
            }
        }

        $region = $this->normalizeLookupKey($value);
        if ($region === '') {
            return [];
        }

        $regions = [
            'norte' => ['AC', 'AM', 'AP', 'PA', 'RO', 'RR', 'TO'],
            'nordeste' => ['AL', 'BA', 'CE', 'MA', 'PB', 'PE', 'PI', 'RN', 'SE'],
            'centro oeste' => ['DF', 'GO', 'MS', 'MT'],
            'centro-oeste' => ['DF', 'GO', 'MS', 'MT'],
            'sudeste' => ['ES', 'MG', 'RJ', 'SP'],
            'sul' => ['PR', 'RS', 'SC'],
        ];

        return $this->normalizeUfValues($regions[$region] ?? []);
    }

    /**
     * Read the first non-empty user meta among candidate keys.
     *
     * @param int $userId
     * @param array $keys
     * @return mixed|null
     */
    private function getFirstUserMetaValue($userId, array $keys) {
        foreach ($keys as $key) {
            $value = get_user_meta($userId, $key, true);
            if ($value !== '' && $value !== null && $value !== []) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Normalize profile slugs from member type/meta variants.
     *
     * @param mixed $value
     * @return string
     */
    private function normalizeProfileSlug($value) {
        $value = is_scalar($value) ? strtolower(trim((string) $value)) : '';
        if ($value === '') {
            return '';
        }

        if (strpos($value, 'fpse_') === 0) {
            $value = substr($value, 5);
        }

        return str_replace('_', '-', $value);
    }

    /**
     * Normalize arbitrary UF payloads into a canonical unique list.
     *
     * @param mixed $rawValue
     * @return array
     */
    private function normalizeUfValues($rawValue) {
        if (is_string($rawValue)) {
            $trimmed = trim($rawValue);
            if ($trimmed === '') {
                return [];
            }

            if (is_serialized($trimmed)) {
                $rawValue = maybe_unserialize($trimmed);
            } elseif (($trimmed[0] ?? '') === '[' || ($trimmed[0] ?? '') === '{') {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $rawValue = $decoded;
                }
            }
        }

        if (is_array($rawValue)) {
            $items = [];
            array_walk_recursive($rawValue, static function ($value) use (&$items) {
                $items[] = $value;
            });
        } else {
            $items = preg_split('/[\s,;\|\/]+/', (string) $rawValue) ?: [];
        }

        $allowed = array_fill_keys(array_keys($this->states), true);
        $ufs = [];

        foreach ($items as $value) {
            if (!is_scalar($value)) {
                continue;
            }

            if (preg_match_all('/\b[A-Z]{2}\b/', strtoupper((string) $value), $matches)) {
                foreach ($matches[0] as $candidate) {
                    if (isset($allowed[$candidate])) {
                        $ufs[$candidate] = true;
                    }
                }
            }
        }

        return array_values(array_keys($ufs));
    }

    /**
     * Normalize generic lookup values.
     *
     * @param mixed $value
     * @return string
     */
    private function normalizeLookupKey($value) {
        $value = is_scalar($value) ? (string) $value : '';
        $value = strtolower(trim(wp_strip_all_tags($value)));

        if ($value === '') {
            return '';
        }

        $value = remove_accents($value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    /**
     * Load the shared canonical scope matrix.
     *
     * @return void
     */
    private function bootstrapCanonicalMatrix() {
        $canonicalPath = defined('FPSE_CORE_PATH')
            ? FPSE_CORE_PATH . 'includes/mf3-panel-scope-canonical.php'
            : dirname(__DIR__, 2) . '/includes/mf3-panel-scope-canonical.php';

        if (file_exists($canonicalPath)) {
            require_once $canonicalPath;
        }

        if (function_exists('fpse_get_mf3_scope_known_profiles')) {
            $this->knownProfiles = fpse_get_mf3_scope_known_profiles();
            $this->activeProfiles = function_exists('fpse_get_mf3_scope_active_panel_profiles')
                ? fpse_get_mf3_scope_active_panel_profiles()
                : [];
        }
    }
}
