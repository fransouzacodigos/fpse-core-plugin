<?php
/**
 * Aggregated data service for the MF3 panel MVP.
 *
 * This service combines two canonical layers:
 * - fpse-core registration dimensions (profile, UF, municipality, school, INEP, network)
 * - LearnDash MF3 course facts resolved by course ID
 *
 * @package FortaleceePSE
 * @subpackage Services
 */

namespace FortaleceePSE\Core\Services;

use FortaleceePSE\Core\Plugin;

class Mf3PanelDataService {
    /**
     * @var Plugin
     */
    private $plugin;

    /**
     * @var Mf3PanelScopeResolver
     */
    private $scopeResolver;

    /**
     * @var Mf3CourseFactsService
     */
    private $courseFactsService;

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
     * Constructor.
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
        $this->scopeResolver = new Mf3PanelScopeResolver($plugin);
        $this->courseFactsService = new Mf3CourseFactsService($plugin);
    }

    /**
     * Return the authenticated user's scope plus high-level dashboard aggregates.
     *
     * @param int|null $userId
     * @return array
     */
    public function getOverview($userId = null) {
        $scope = $this->scopeResolver->resolve($userId);
        $rows = $this->getScopedCourseUsers($scope);
        $states = $this->buildStateAggregates($rows);
        $schools = $this->buildSchoolAggregates($rows);
        $overviewFacts = $this->buildOverviewFacts($rows);
        $courseConfig = $this->courseFactsService->getCourseConfig();

        return [
            'scope' => $scope,
            'kpis' => [
                'total_cursistas' => count($rows),
                'total_estados' => count($states),
                'total_escolas' => count($schools),
                'progresso_medio' => $overviewFacts['progresso_medio'],
                'concluintes' => $overviewFacts['concluintes'],
                'sem_acesso_recente' => null,
                'nao_iniciados' => $overviewFacts['nao_iniciados'],
                'ultimo_acesso_mais_recente' => $overviewFacts['ultimo_acesso'],
                'ultimo_acesso_mais_recente_ts' => $overviewFacts['ultimo_acesso_ts'],
                'em_atencao' => null,
            ],
            'top_states' => array_slice(array_values($states), 0, 10),
            'top_schools' => array_slice(array_values($schools), 0, 10),
            'data_availability' => [
                'course_progress' => (bool) $courseConfig['runtime']['has_progress_api'],
                'last_access' => true,
                'attention_queue' => false,
                'reason' => $courseConfig['is_valid_course'] ? 'course_facts_enabled' : 'invalid_mf3_course_config',
            ],
            'course' => [
                'course_id' => $courseConfig['course_id'],
                'course_slug' => $courseConfig['course_slug'],
                'course_title' => $courseConfig['course_title'],
            ],
        ];
    }

    /**
     * Return state aggregates for the current scope.
     *
     * @param int|null $userId
     * @return array
     */
    public function getStates($userId = null) {
        $scope = $this->scopeResolver->resolve($userId);
        return [
            'scope' => $scope,
            'items' => array_values($this->buildStateAggregates($this->getScopedCourseUsers($scope))),
        ];
    }

    /**
     * Return school aggregates for the current scope.
     *
     * @param int|null $userId
     * @return array
     */
    public function getSchools($userId = null) {
        $scope = $this->scopeResolver->resolve($userId);
        return [
            'scope' => $scope,
            'items' => array_values($this->buildSchoolAggregates($this->getScopedCourseUsers($scope))),
            'school_key_strategy' => [
                'primary' => 'escola_inep',
                'fallback' => 'escola_nome_normalizada|municipio|uf',
            ],
        ];
    }

    /**
     * Intersect scoped registration rows with canonical LearnDash course facts.
     *
     * @param array $scope
     * @return array
     */
    private function getScopedCourseUsers(array $scope) {
        $rows = $this->getScopedUsers($scope);
        if (empty($rows)) {
            return [];
        }

        $factsByUser = $this->courseFactsService->getFactsForUsers(array_column($rows, 'user_id'));
        $scoped = [];

        foreach ($rows as $row) {
            $facts = $factsByUser[$row['user_id']] ?? null;
            if (!is_array($facts) || empty($facts['has_access'])) {
                continue;
            }

            $row['mf3_course'] = $facts;
            $scoped[] = $row;
        }

        return $scoped;
    }

    /**
     * Query scoped users using canonical registration metadata.
     *
     * @param array $scope
     * @return array
     */
    private function getScopedUsers(array $scope) {
        global $wpdb;

        if (!$scope['authenticated']) {
            return [];
        }

        $metaKeys = [
            'perfil_usuario',
            'fpse_perfil_usuario',
            'estado',
            'fpse_estado',
            'municipio',
            'fpse_municipio',
            'escola_nome',
            'fpse_escola_nome',
            'escola_inep',
            'fpse_escola_inep',
            'rede_escola',
            'fpse_rede_escola',
        ];

        $placeholders = implode(',', array_fill(0, count($metaKeys), '%s'));
        $query = $wpdb->prepare(
            "
            SELECT
                u.ID AS user_id,
                u.display_name,
                u.user_email,
                MAX(CASE WHEN um.meta_key IN ('perfil_usuario', 'fpse_perfil_usuario') THEN um.meta_value END) AS perfil_usuario,
                MAX(CASE WHEN um.meta_key IN ('estado', 'fpse_estado') THEN um.meta_value END) AS estado,
                MAX(CASE WHEN um.meta_key IN ('municipio', 'fpse_municipio') THEN um.meta_value END) AS municipio,
                MAX(CASE WHEN um.meta_key IN ('escola_nome', 'fpse_escola_nome') THEN um.meta_value END) AS escola_nome,
                MAX(CASE WHEN um.meta_key IN ('escola_inep', 'fpse_escola_inep') THEN um.meta_value END) AS escola_inep,
                MAX(CASE WHEN um.meta_key IN ('rede_escola', 'fpse_rede_escola') THEN um.meta_value END) AS rede_escola
            FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} um
                ON u.ID = um.user_id
                AND um.meta_key IN ({$placeholders})
            GROUP BY u.ID, u.display_name, u.user_email
            ",
            $metaKeys
        );

        $results = $wpdb->get_results($query, ARRAY_A);
        if (!is_array($results)) {
            return [];
        }

        $allowedProfiles = array_fill_keys($this->activeProfiles, true);
        $allowedUfs = array_fill_keys($scope['allowed_ufs'], true);
        $states = array_fill_keys(array_keys((array) $this->plugin->getConfig('states', [])), true);

        $rows = [];
        foreach ($results as $row) {
            $profile = $this->normalizeProfile((string) ($row['perfil_usuario'] ?? ''));
            $uf = strtoupper(trim((string) ($row['estado'] ?? '')));

            if ($profile === '' || !isset($allowedProfiles[$profile])) {
                continue;
            }

            if ($uf === '' || !isset($states[$uf])) {
                continue;
            }

            if ($scope['scope_class'] !== 'national' && !isset($allowedUfs[$uf])) {
                continue;
            }

            $rows[] = [
                'user_id' => (int) $row['user_id'],
                'display_name' => (string) ($row['display_name'] ?? ''),
                'user_email' => (string) ($row['user_email'] ?? ''),
                'perfil_usuario' => $profile,
                'estado' => $uf,
                'municipio' => $this->sanitizeText($row['municipio'] ?? ''),
                'escola_nome' => $this->sanitizeText($row['escola_nome'] ?? ''),
                'escola_inep' => $this->sanitizeInep($row['escola_inep'] ?? ''),
                'rede_escola' => $this->sanitizeText($row['rede_escola'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * Build aggregates by state.
     *
     * @param array $rows
     * @return array
     */
    private function buildStateAggregates(array $rows) {
        $items = [];

        foreach ($rows as $row) {
            $uf = $row['estado'];
            $courseFacts = $row['mf3_course'] ?? [];
            if (!isset($items[$uf])) {
                $items[$uf] = [
                    'uf' => $uf,
                    'group_slug' => 'estado-' . strtolower($uf),
                    'group_name' => $uf,
                    'total_cursistas' => 0,
                    'total_escolas' => 0,
                    'escolas_keys' => [],
                    'progress_sum' => 0.0,
                    'progress_count' => 0,
                    'progresso_medio' => null,
                    'concluintes' => 0,
                    'nao_iniciados' => 0,
                    'ultimo_acesso_ts' => null,
                    'ultimo_acesso' => null,
                    'sem_acesso_recente' => null,
                    'em_atencao' => null,
                ];
            }

            $items[$uf]['total_cursistas']++;
            $this->applyCourseFactsToAggregate($items[$uf], $courseFacts);

            $schoolKey = $this->buildSchoolKey($row);
            if ($schoolKey !== null) {
                $items[$uf]['escolas_keys'][$schoolKey] = true;
            }
        }

        foreach ($items as $uf => $item) {
            $items[$uf]['total_escolas'] = count($item['escolas_keys']);
            $items[$uf]['progresso_medio'] = $this->finalizeAveragePercent(
                $item['progress_sum'],
                $item['progress_count']
            );
            $items[$uf]['ultimo_acesso'] = $item['ultimo_acesso_ts']
                ? gmdate('c', (int) $item['ultimo_acesso_ts'])
                : null;
            unset($items[$uf]['escolas_keys']);
            unset($items[$uf]['progress_sum'], $items[$uf]['progress_count']);
        }

        uasort($items, function ($a, $b) {
            if ($a['total_cursistas'] === $b['total_cursistas']) {
                return strcmp($a['uf'], $b['uf']);
            }
            return $b['total_cursistas'] <=> $a['total_cursistas'];
        });

        return $items;
    }

    /**
     * Build aggregates by school.
     *
     * @param array $rows
     * @return array
     */
    private function buildSchoolAggregates(array $rows) {
        $items = [];

        foreach ($rows as $row) {
            $schoolKey = $this->buildSchoolKey($row);
            if ($schoolKey === null) {
                continue;
            }

            if (!isset($items[$schoolKey])) {
                $items[$schoolKey] = [
                    'school_key' => $schoolKey,
                    'school_key_type' => $row['escola_inep'] !== '' ? 'inep' : 'normalized_name',
                    'escola_nome' => $row['escola_nome'] !== '' ? $row['escola_nome'] : 'Escola sem nome informado',
                    'escola_inep' => $row['escola_inep'] !== '' ? $row['escola_inep'] : null,
                    'estado' => $row['estado'],
                    'municipio' => $row['municipio'],
                    'rede_escola' => $row['rede_escola'] !== '' ? $row['rede_escola'] : null,
                    'total_cursistas' => 0,
                    'progress_sum' => 0.0,
                    'progress_count' => 0,
                    'progresso_medio' => null,
                    'concluintes' => 0,
                    'nao_iniciados' => 0,
                    'ultimo_acesso_ts' => null,
                    'ultimo_acesso' => null,
                    'em_atencao' => null,
                    'sem_acesso_recente' => null,
                ];
            }

            $items[$schoolKey]['total_cursistas']++;
            $this->applyCourseFactsToAggregate($items[$schoolKey], $row['mf3_course'] ?? []);
        }

        foreach ($items as $schoolKey => $item) {
            $items[$schoolKey]['progresso_medio'] = $this->finalizeAveragePercent(
                $item['progress_sum'],
                $item['progress_count']
            );
            $items[$schoolKey]['ultimo_acesso'] = $item['ultimo_acesso_ts']
                ? gmdate('c', (int) $item['ultimo_acesso_ts'])
                : null;
            unset($items[$schoolKey]['progress_sum'], $items[$schoolKey]['progress_count']);
        }

        uasort($items, function ($a, $b) {
            if ($a['total_cursistas'] === $b['total_cursistas']) {
                return strcmp($a['escola_nome'], $b['escola_nome']);
            }
            return $b['total_cursistas'] <=> $a['total_cursistas'];
        });

        return $items;
    }

    /**
     * Build course-aware overview facts from scoped rows.
     *
     * @param array $rows
     * @return array
     */
    private function buildOverviewFacts(array $rows) {
        $aggregate = [
            'progress_sum' => 0.0,
            'progress_count' => 0,
            'concluintes' => 0,
            'nao_iniciados' => 0,
            'ultimo_acesso_ts' => null,
        ];

        foreach ($rows as $row) {
            $this->applyCourseFactsToAggregate($aggregate, $row['mf3_course'] ?? []);
        }

        return [
            'progresso_medio' => $this->finalizeAveragePercent(
                $aggregate['progress_sum'],
                $aggregate['progress_count']
            ),
            'concluintes' => $aggregate['concluintes'],
            'nao_iniciados' => $aggregate['nao_iniciados'],
            'ultimo_acesso_ts' => $aggregate['ultimo_acesso_ts'],
            'ultimo_acesso' => $aggregate['ultimo_acesso_ts']
                ? gmdate('c', (int) $aggregate['ultimo_acesso_ts'])
                : null,
        ];
    }

    /**
     * Apply course facts to a generic aggregate bucket.
     *
     * @param array $aggregate
     * @param array $courseFacts
     * @return void
     */
    private function applyCourseFactsToAggregate(array &$aggregate, array $courseFacts) {
        if (isset($courseFacts['progress_percent']) && is_numeric($courseFacts['progress_percent'])) {
            $aggregate['progress_sum'] += (float) $courseFacts['progress_percent'];
            $aggregate['progress_count']++;
        }

        if (!empty($courseFacts['completed'])) {
            $aggregate['concluintes']++;
        }

        if (!empty($courseFacts['not_started'])) {
            $aggregate['nao_iniciados']++;
        }

        if (!empty($courseFacts['last_access_ts'])) {
            $aggregate['ultimo_acesso_ts'] = max(
                (int) ($aggregate['ultimo_acesso_ts'] ?? 0),
                (int) $courseFacts['last_access_ts']
            );
        }
    }

    /**
     * Finalize an average percentage or return null when not available.
     *
     * @param float $sum
     * @param int $count
     * @return float|null
     */
    private function finalizeAveragePercent($sum, $count) {
        if ($count <= 0) {
            return null;
        }

        return round(((float) $sum / (int) $count), 2);
    }

    /**
     * Build canonical school aggregation key.
     *
     * @param array $row
     * @return string|null
     */
    private function buildSchoolKey(array $row) {
        if (!empty($row['escola_inep'])) {
            return 'inep:' . $row['escola_inep'];
        }

        if (empty($row['escola_nome']) || empty($row['estado'])) {
            return null;
        }

        return 'name:' . $this->normalizeKey($row['escola_nome']) . '|' . $this->normalizeKey($row['municipio']) . '|' . strtoupper($row['estado']);
    }

    /**
     * Normalize a stored profile slug.
     *
     * @param string $value
     * @return string
     */
    private function normalizeProfile($value) {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }

        if (strpos($value, 'fpse_') === 0) {
            $value = substr($value, 5);
        }

        return str_replace('_', '-', $value);
    }

    /**
     * Normalize arbitrary text for fallback school keys.
     *
     * @param string $value
     * @return string
     */
    private function normalizeKey($value) {
        $value = remove_accents(strtolower(trim((string) $value)));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        return trim((string) preg_replace('/\s+/', ' ', $value));
    }

    /**
     * Sanitize free text value.
     *
     * @param mixed $value
     * @return string
     */
    private function sanitizeText($value) {
        return trim(sanitize_text_field((string) $value));
    }

    /**
     * Sanitize optional INEP code.
     *
     * @param mixed $value
     * @return string
     */
    private function sanitizeInep($value) {
        $digits = preg_replace('/\D+/', '', (string) $value);
        return preg_match('/^\d{8}$/', $digits) ? $digits : '';
    }
}
