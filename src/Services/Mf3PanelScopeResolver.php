<?php
/**
 * Canonical scope resolver for the MF3 panel.
 *
 * Resolves the authenticated user's active profiles, visibility class,
 * allowed UFs and high-level panel capabilities.
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
    private $activeProfiles = [
        'estudante-eaa',
        'profissional-saude-eaa',
        'profissional-educacao-eaa',
        'outro-membro-eaa',
        'bolsista-ies',
        'voluntario-ies',
        'coordenador-ies',
        'jovem-mobilizador-nap',
        'apoiador-pedagogico-nap',
        'coordenacao-nap',
        'gti-m',
        'gti-e',
        'coordenacao-fortalece-pse',
        'representante-mec',
        'representante-ms',
    ];

    /**
     * @var string[]
     */
    private $nationalProfiles = [
        'coordenacao-fortalece-pse',
        'representante-mec',
        'representante-ms',
        'representante-ms-mec',
    ];

    /**
     * @var string[]
     */
    private $multiUfProfiles = [
        'apoiador-pedagogico-nap',
    ];

    /**
     * @var string[]
     */
    private $napRegionProfiles = [
        'coordenacao-nap',
    ];

    /**
     * @var string[]
     */
    private $ufProfiles = [
        'estudante-eaa',
        'profissional-saude-eaa',
        'profissional-educacao-eaa',
        'outro-membro-eaa',
        'bolsista-ies',
        'voluntario-ies',
        'coordenador-ies',
        'jovem-mobilizador-nap',
        'gti-m',
        'gti-e',
    ];

    /**
     * Constructor.
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
        $this->states = (array) $plugin->getConfig('states', []);
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

        $scope = [
            'authenticated' => $userId > 0,
            'user_id' => $userId,
            'profiles' => $profiles,
            'active_profiles' => array_values(array_intersect($profiles, $this->activeProfiles)),
            'scope_class' => 'none',
            'scope_reason' => 'not_authenticated',
            'allowed_ufs' => [],
            'allowed_group_slugs' => [],
            'can_view_overview' => false,
            'can_view_states' => false,
            'can_view_schools' => false,
            'can_view_users' => false,
            'can_view_attention' => false,
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

        if (current_user_can('manage_options') || array_intersect($profiles, $this->nationalProfiles)) {
            $allowedUfs = array_keys($this->states);
            return $this->finalizeScope($scope, 'national', 'national_profile', $allowedUfs, true);
        }

        if (array_intersect($profiles, $this->multiUfProfiles)) {
            $allowedUfs = $this->getUserFollowedUfs($userId);
            return $this->finalizeScope($scope, 'multi_uf', 'ufs_acompanhadas', $allowedUfs, true);
        }

        if (array_intersect($profiles, $this->napRegionProfiles)) {
            $allowedUfs = $this->getUserNapRegionUfs($userId);
            $reason = 'nap_region';

            if (empty($allowedUfs)) {
                $allowedUfs = $this->getUserFollowedUfs($userId);
                $reason = 'nap_region_fallback_ufs_acompanhadas';
            }

            return $this->finalizeScope($scope, 'multi_uf', $reason, $allowedUfs, true);
        }

        if (array_intersect($profiles, $this->ufProfiles)) {
            $uf = $this->getUserRegistrationUf($userId);
            $allowedUfs = $uf ? [$uf] : [];
            return $this->finalizeScope($scope, 'uf', 'estado_cadastro', $allowedUfs, false);
        }

        return $this->finalizeScope($scope, 'personal', 'fallback_personal', [], false);
    }

    /**
     * Finalize scope payload with derived capabilities.
     *
     * @param array $scope
     * @param string $scopeClass
     * @param string $reason
     * @param array $allowedUfs
     * @param bool $canViewUsers
     * @return array
     */
    private function finalizeScope(array $scope, $scopeClass, $reason, array $allowedUfs, $canViewUsers) {
        $allowedUfs = $this->normalizeUfValues($allowedUfs);
        $canViewAggregates = $scopeClass === 'national' || !empty($allowedUfs);

        $scope['scope_class'] = $scopeClass;
        $scope['scope_reason'] = $reason;
        $scope['allowed_ufs'] = $allowedUfs;
        $scope['allowed_group_slugs'] = array_map(function ($uf) {
            return 'estado-' . strtolower($uf);
        }, $allowedUfs);
        $scope['can_view_overview'] = $canViewAggregates;
        $scope['can_view_states'] = $canViewAggregates;
        $scope['can_view_schools'] = $canViewAggregates;
        $scope['can_view_users'] = $canViewAggregates && $canViewUsers;
        $scope['can_view_attention'] = $canViewAggregates && $canViewUsers;

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
}
