<?php
/**
 * Canonical MF3 scope matrix shared by fpse-core and MU-plugins.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('fpse_get_mf3_scope_matrix')) {
    /**
     * Return the canonical MF3 scope matrix keyed by profile slug.
     */
    function fpse_get_mf3_scope_matrix(): array
    {
        $aggregateFeatures = [
            'overview' => true,
            'states' => true,
            'schools' => true,
            'users' => false,
            'attention' => false,
            'export_aggregates' => true,
            'export_users' => false,
        ];

        $individualFeatures = [
            'overview' => true,
            'states' => true,
            'schools' => true,
            'users' => true,
            'attention' => false,
            'export_aggregates' => true,
            'export_users' => true,
        ];

        $noAccessFeatures = [
            'overview' => false,
            'states' => false,
            'schools' => false,
            'users' => false,
            'attention' => false,
            'export_aggregates' => false,
            'export_users' => false,
        ];

        $noAccessCapabilities = [
            'view_aggregates' => false,
            'view_users' => false,
            'view_attention' => false,
            'export_aggregates' => false,
            'export_users' => false,
        ];

        $ufCapabilities = [
            'view_aggregates' => true,
            'view_users' => false,
            'view_attention' => false,
            'export_aggregates' => true,
            'export_users' => false,
        ];

        $regionalCapabilities = [
            'view_aggregates' => true,
            'view_users' => true,
            'view_attention' => true,
            'export_aggregates' => true,
            'export_users' => true,
        ];

        return [
            'estudante-eaa' => [
                'priority' => 200,
                'panel_active' => true,
                'scope_mode' => 'uf_unica',
                'scope_class' => 'uf',
                'uf_origin' => 'estado_cadastro',
                'fallback_uf_origins' => [],
                'capabilities' => $ufCapabilities,
                'features' => $aggregateFeatures,
            ],
            'profissional-saude-eaa' => [
                'priority' => 200,
                'panel_active' => true,
                'scope_mode' => 'uf_unica',
                'scope_class' => 'uf',
                'uf_origin' => 'estado_cadastro',
                'fallback_uf_origins' => [],
                'capabilities' => $ufCapabilities,
                'features' => $aggregateFeatures,
            ],
            'profissional-educacao-eaa' => [
                'priority' => 200,
                'panel_active' => true,
                'scope_mode' => 'uf_unica',
                'scope_class' => 'uf',
                'uf_origin' => 'estado_cadastro',
                'fallback_uf_origins' => [],
                'capabilities' => $ufCapabilities,
                'features' => $aggregateFeatures,
            ],
            'outro-membro-eaa' => [
                'priority' => 200,
                'panel_active' => true,
                'scope_mode' => 'uf_unica',
                'scope_class' => 'uf',
                'uf_origin' => 'estado_cadastro',
                'fallback_uf_origins' => [],
                'capabilities' => $ufCapabilities,
                'features' => $aggregateFeatures,
            ],
            'bolsista-ies' => [
                'priority' => 200,
                'panel_active' => true,
                'scope_mode' => 'uf_unica',
                'scope_class' => 'uf',
                'uf_origin' => 'estado_cadastro',
                'fallback_uf_origins' => [],
                'capabilities' => $ufCapabilities,
                'features' => $aggregateFeatures,
            ],
            'voluntario-ies' => [
                'priority' => 200,
                'panel_active' => true,
                'scope_mode' => 'uf_unica',
                'scope_class' => 'uf',
                'uf_origin' => 'estado_cadastro',
                'fallback_uf_origins' => [],
                'capabilities' => $ufCapabilities,
                'features' => $aggregateFeatures,
            ],
            'coordenador-ies' => [
                'priority' => 200,
                'panel_active' => true,
                'scope_mode' => 'uf_unica',
                'scope_class' => 'uf',
                'uf_origin' => 'estado_cadastro',
                'fallback_uf_origins' => [],
                'capabilities' => $ufCapabilities,
                'features' => $aggregateFeatures,
            ],
            'jovem-mobilizador-nap' => [
                'priority' => 200,
                'panel_active' => true,
                'scope_mode' => 'uf_unica',
                'scope_class' => 'uf',
                'uf_origin' => 'estado_cadastro',
                'fallback_uf_origins' => [],
                'capabilities' => $ufCapabilities,
                'features' => $aggregateFeatures,
            ],
            'gti-m' => [
                'priority' => 200,
                'panel_active' => true,
                'scope_mode' => 'uf_unica',
                'scope_class' => 'uf',
                'uf_origin' => 'estado_cadastro',
                'fallback_uf_origins' => [],
                'capabilities' => $ufCapabilities,
                'features' => $aggregateFeatures,
            ],
            'gti-e' => [
                'priority' => 200,
                'panel_active' => true,
                'scope_mode' => 'uf_unica',
                'scope_class' => 'uf',
                'uf_origin' => 'estado_cadastro',
                'fallback_uf_origins' => [],
                'capabilities' => $ufCapabilities,
                'features' => $aggregateFeatures,
            ],
            'apoiador-pedagogico-nap' => [
                'priority' => 300,
                'panel_active' => true,
                'scope_mode' => 'multi_uf',
                'scope_class' => 'multi_uf',
                'uf_origin' => 'ufs_acompanhadas',
                'fallback_uf_origins' => [],
                'capabilities' => $regionalCapabilities,
                'features' => $individualFeatures,
            ],
            'coordenacao-nap' => [
                'priority' => 320,
                'panel_active' => true,
                'scope_mode' => 'nap_regiao',
                'scope_class' => 'multi_uf',
                'uf_origin' => 'nap_region',
                'fallback_uf_origins' => ['ufs_acompanhadas'],
                'capabilities' => $regionalCapabilities,
                'features' => $individualFeatures,
            ],
            'coordenacao-fortalece-pse' => [
                'priority' => 400,
                'panel_active' => true,
                'scope_mode' => 'nacional',
                'scope_class' => 'national',
                'uf_origin' => 'all_states',
                'fallback_uf_origins' => [],
                'capabilities' => $regionalCapabilities,
                'features' => $individualFeatures,
            ],
            'representante-mec' => [
                'priority' => 400,
                'panel_active' => true,
                'scope_mode' => 'nacional',
                'scope_class' => 'national',
                'uf_origin' => 'all_states',
                'fallback_uf_origins' => [],
                'capabilities' => $regionalCapabilities,
                'features' => $individualFeatures,
            ],
            'representante-ms' => [
                'priority' => 400,
                'panel_active' => true,
                'scope_mode' => 'nacional',
                'scope_class' => 'national',
                'uf_origin' => 'all_states',
                'fallback_uf_origins' => [],
                'capabilities' => $regionalCapabilities,
                'features' => $individualFeatures,
            ],
            'representante-ms-mec' => [
                'priority' => 400,
                'panel_active' => true,
                'scope_mode' => 'nacional',
                'scope_class' => 'national',
                'uf_origin' => 'all_states',
                'fallback_uf_origins' => [],
                'capabilities' => $regionalCapabilities,
                'features' => $individualFeatures,
            ],
            'professor-eaa' => [
                'priority' => 0,
                'panel_active' => false,
                'scope_mode' => 'sem_acesso',
                'scope_class' => 'personal',
                'uf_origin' => 'none',
                'fallback_uf_origins' => [],
                'capabilities' => $noAccessCapabilities,
                'features' => $noAccessFeatures,
            ],
            'gestor-eaa' => [
                'priority' => 0,
                'panel_active' => false,
                'scope_mode' => 'sem_acesso',
                'scope_class' => 'personal',
                'uf_origin' => 'none',
                'fallback_uf_origins' => [],
                'capabilities' => $noAccessCapabilities,
                'features' => $noAccessFeatures,
            ],
            'estudante-ies' => [
                'priority' => 0,
                'panel_active' => false,
                'scope_mode' => 'sem_acesso',
                'scope_class' => 'personal',
                'uf_origin' => 'none',
                'fallback_uf_origins' => [],
                'capabilities' => $noAccessCapabilities,
                'features' => $noAccessFeatures,
            ],
            'professor-ies' => [
                'priority' => 0,
                'panel_active' => false,
                'scope_mode' => 'sem_acesso',
                'scope_class' => 'personal',
                'uf_origin' => 'none',
                'fallback_uf_origins' => [],
                'capabilities' => $noAccessCapabilities,
                'features' => $noAccessFeatures,
            ],
            'pesquisador' => [
                'priority' => 0,
                'panel_active' => false,
                'scope_mode' => 'sem_acesso',
                'scope_class' => 'personal',
                'uf_origin' => 'none',
                'fallback_uf_origins' => [],
                'capabilities' => $noAccessCapabilities,
                'features' => $noAccessFeatures,
            ],
            'gestor-nap' => [
                'priority' => 0,
                'panel_active' => false,
                'scope_mode' => 'sem_acesso',
                'scope_class' => 'personal',
                'uf_origin' => 'none',
                'fallback_uf_origins' => [],
                'capabilities' => $noAccessCapabilities,
                'features' => $noAccessFeatures,
            ],
            'assistente-nap' => [
                'priority' => 0,
                'panel_active' => false,
                'scope_mode' => 'sem_acesso',
                'scope_class' => 'personal',
                'uf_origin' => 'none',
                'fallback_uf_origins' => [],
                'capabilities' => $noAccessCapabilities,
                'features' => $noAccessFeatures,
            ],
            'gestor-gti' => [
                'priority' => 0,
                'panel_active' => false,
                'scope_mode' => 'sem_acesso',
                'scope_class' => 'personal',
                'uf_origin' => 'none',
                'fallback_uf_origins' => [],
                'capabilities' => $noAccessCapabilities,
                'features' => $noAccessFeatures,
            ],
            'tecnico-gti' => [
                'priority' => 0,
                'panel_active' => false,
                'scope_mode' => 'sem_acesso',
                'scope_class' => 'personal',
                'uf_origin' => 'none',
                'fallback_uf_origins' => [],
                'capabilities' => $noAccessCapabilities,
                'features' => $noAccessFeatures,
            ],
            'coordenador-institucional' => [
                'priority' => 0,
                'panel_active' => false,
                'scope_mode' => 'sem_acesso',
                'scope_class' => 'personal',
                'uf_origin' => 'none',
                'fallback_uf_origins' => [],
                'capabilities' => $noAccessCapabilities,
                'features' => $noAccessFeatures,
            ],
            'monitor-programa' => [
                'priority' => 0,
                'panel_active' => false,
                'scope_mode' => 'sem_acesso',
                'scope_class' => 'personal',
                'uf_origin' => 'none',
                'fallback_uf_origins' => [],
                'capabilities' => $noAccessCapabilities,
                'features' => $noAccessFeatures,
            ],
        ];
    }
}

if (!function_exists('fpse_get_mf3_scope_profile')) {
    /**
     * Return a single canonical profile definition.
     */
    function fpse_get_mf3_scope_profile(string $profile): ?array
    {
        $matrix = fpse_get_mf3_scope_matrix();
        return $matrix[$profile] ?? null;
    }
}

if (!function_exists('fpse_get_mf3_scope_known_profiles')) {
    /**
     * Return all known canonical profile slugs.
     */
    function fpse_get_mf3_scope_known_profiles(): array
    {
        return array_keys(fpse_get_mf3_scope_matrix());
    }
}

if (!function_exists('fpse_get_mf3_scope_active_panel_profiles')) {
    /**
     * Return profiles with aggregate MF3 panel access.
     */
    function fpse_get_mf3_scope_active_panel_profiles(): array
    {
        $profiles = [];

        foreach (fpse_get_mf3_scope_matrix() as $profile => $definition) {
            if (!empty($definition['panel_active'])) {
                $profiles[] = $profile;
            }
        }

        return $profiles;
    }
}

if (!function_exists('fpse_resolve_mf3_scope_profile')) {
    /**
     * Resolve the highest-priority canonical profile among detected profiles.
     */
    function fpse_resolve_mf3_scope_profile(array $profiles): ?array
    {
        $matrix = fpse_get_mf3_scope_matrix();
        $resolvedProfile = null;
        $resolvedDefinition = null;
        $resolvedPriority = -1;

        foreach ($profiles as $profile) {
            if (!isset($matrix[$profile])) {
                continue;
            }

            $priority = (int) ($matrix[$profile]['priority'] ?? 0);
            if ($priority > $resolvedPriority) {
                $resolvedPriority = $priority;
                $resolvedProfile = $profile;
                $resolvedDefinition = $matrix[$profile];
            }
        }

        if ($resolvedProfile === null || $resolvedDefinition === null) {
            return null;
        }

        return [
            'profile' => $resolvedProfile,
            'definition' => $resolvedDefinition,
        ];
    }
}
