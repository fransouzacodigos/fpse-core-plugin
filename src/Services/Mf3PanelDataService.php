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
     * @var Mf3SchoolCanonicalService
     */
    private $schoolCanonicalService;

    /**
     * @var Mf3SchoolReconciliationService
     */
    private $schoolReconciliationService;

    /**
     * @var string[]
     */
    private $activeProfiles = [];

    /**
     * Constructor.
     *
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
        $this->scopeResolver = new Mf3PanelScopeResolver($plugin);
        $this->activeProfiles = $this->scopeResolver->getActivePanelProfiles();
        $this->courseFactsService = new Mf3CourseFactsService($plugin);
        $this->schoolCanonicalService = new Mf3SchoolCanonicalService();
        $this->schoolReconciliationService = new Mf3SchoolReconciliationService();
    }

    /**
     * Return the authenticated user's scope plus high-level dashboard aggregates.
     *
     * @param int|null $userId
     * @return array
     */
    public function getOverview($userId = null) {
        $scope = $this->scopeResolver->resolve($userId);
        $analyticalContext = $this->getScopedAnalyticalCourseUsers($scope);
        $rows = $analyticalContext['rows'];
        $states = $this->buildStateAggregates($rows);
        $schools = $this->buildSchoolAggregates($rows);
        $overviewFacts = $this->buildOverviewFacts($rows);
        $courseConfig = $this->courseFactsService->getCourseConfig();
        $availabilityReason = $this->courseFactsService->getAvailabilityReason();

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
                'last_access' => (bool) ($courseConfig['runtime']['has_activity_api'] || $courseConfig['runtime']['has_ld_db']),
                'attention_queue' => false,
                'reason' => $availabilityReason,
            ],
            'course' => [
                'course_id' => $courseConfig['course_id'],
                'course_slug' => $courseConfig['course_slug'],
                'course_title' => $courseConfig['course_title'],
                'course_post_type' => $courseConfig['course_post_type'],
                'is_valid_course' => $courseConfig['is_valid_course'],
            ],
            'runtime_diagnostics' => [
                'plugin_version' => defined('FPSE_CORE_VERSION') ? FPSE_CORE_VERSION : null,
                'scoped_registration_users' => count($this->getScopedUsers($scope)),
                'scoped_course_users' => count($rows),
                'availability_reason' => $availabilityReason,
                'learn_dash_runtime' => $courseConfig['runtime'],
                'school_reconciliation_observability' => $analyticalContext['observability'],
                'school_canonical_summary' => $analyticalContext['canonical_summary'],
            ],
            'school_reconciliation_observability' => $analyticalContext['observability'],
            'school_canonical_summary' => $analyticalContext['canonical_summary'],
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
        $analyticalContext = $this->getScopedAnalyticalCourseUsers($scope);
        return [
            'scope' => $scope,
            'items' => array_values($this->buildStateAggregates($analyticalContext['rows'])),
            'school_reconciliation_observability' => $analyticalContext['observability'],
            'school_canonical_summary' => $analyticalContext['canonical_summary'],
        ];
    }

    /**
     * Return school aggregates for the current scope.
     *
     * @param int|null $userId
     * @return array
     */
    public function getSchools($userId = null, array $params = []) {
        $scope = $this->scopeResolver->resolve($userId);
        $analyticalContext = $this->getScopedAnalyticalCourseUsers($scope);
        $allItems = array_values($this->buildSchoolAggregates($analyticalContext['rows']));
        $query = $this->normalizeSchoolQueryParams($params);
        $filteredItems = $this->filterSchoolAggregates($allItems, $query);
        $sortedItems = $this->sortSchoolAggregates($filteredItems, $query['sort_by'], $query['sort_dir']);
        $paginated = $this->paginateSchoolAggregates($sortedItems, $query['page'], $query['per_page']);

        return [
            'scope' => $scope,
            'items' => $paginated['items'],
            'pagination' => $paginated['pagination'],
            'filters' => [
                'applied' => [
                    'search' => $query['search'],
                    'uf' => $query['uf'] !== '' ? $query['uf'] : 'all',
                    'rede' => $query['rede'] !== '' ? $query['rede'] : 'all',
                    'inep_mode' => $query['inep_mode'],
                ],
                'available' => [
                    'ufs' => $this->extractSchoolFilterOptions($allItems, 'estado'),
                    'redes' => $this->extractSchoolFilterOptions($allItems, 'rede_escola'),
                    'inep_modes' => ['all', 'with_inep', 'without_inep'],
                ],
            ],
            'sorting' => [
                'sort_by' => $query['sort_by'],
                'sort_dir' => $query['sort_dir'],
                'available' => [
                    'sort_by' => ['total_cursistas', 'escola_nome', 'estado'],
                    'sort_dir' => ['asc', 'desc'],
                ],
            ],
            'school_key_strategy' => [
                'primary' => 'escola_inep',
                'fallback' => 'escola_nome_normalizada|municipio|uf',
                'analytical_layer' => [
                    'enabled' => true,
                    'canonical_contract' => 'school_canonical',
                    'link_contract' => 'school_reconciliation_link',
                    'legacy_fallback_preserved' => true,
                ],
            ],
            'school_reconciliation_observability' => $analyticalContext['observability'],
            'school_canonical_summary' => $analyticalContext['canonical_summary'],
        ];
    }

    /**
     * Return a CSV export for the filtered school aggregates.
     *
     * @param int|null $userId
     * @param array $params
     * @return string
     */
    public function getSchoolsCsv($userId = null, array $params = []) {
        $scope = $this->scopeResolver->resolve($userId);
        $analyticalContext = $this->getScopedAnalyticalCourseUsers($scope);
        $query = $this->normalizeSchoolQueryParams($params);
        $allItems = array_values($this->buildSchoolAggregates($analyticalContext['rows']));
        $filteredItems = $this->filterSchoolAggregates($allItems, $query);
        $sortedItems = $this->sortSchoolAggregates($filteredItems, $query['sort_by'], $query['sort_dir']);

        return $this->buildSchoolsCsv($sortedItems);
    }

    /**
     * Return individual participants for the current scope using the canonical
     * operational registration metadata already stored in WordPress.
     *
     * @param int|null $userId
     * @param array $params
     * @return array
     */
    public function getUsers($userId = null, array $params = []) {
        $scope = $this->scopeResolver->resolve($userId);
        $query = $this->normalizeUserQueryParams($params);
        $allItems = $this->buildUserRows($this->getScopedUsers($scope));
        $filteredItems = $this->filterUserRows($allItems, $query);
        $sortedItems = $this->sortUserRows($filteredItems, $query['sort_by'], $query['sort_dir']);
        $paginated = $this->paginateUserRows($sortedItems, $query['page'], $query['per_page']);

        return [
            'scope' => $scope,
            'items' => $paginated['items'],
            'pagination' => $paginated['pagination'],
            'filters' => [
                'applied' => [
                    'search' => $query['search_raw'],
                    'uf' => $query['uf'] !== '' ? $query['uf'] : 'all',
                    'municipio' => $query['municipio_raw'] !== '' ? $query['municipio_raw'] : 'all',
                    'escola' => $query['escola_raw'] !== '' ? $query['escola_raw'] : 'all',
                    'perfil' => $query['perfil'] !== '' ? $query['perfil'] : 'all',
                ],
                'available' => [
                    'ufs' => $this->extractSchoolFilterOptions($allItems, 'estado'),
                    'municipios' => $this->extractSchoolFilterOptions($allItems, 'municipio'),
                    'escolas' => $this->extractSchoolFilterOptions($allItems, 'escola_nome'),
                    'perfis' => $this->extractSchoolFilterOptions($allItems, 'perfil_usuario'),
                ],
            ],
            'sorting' => [
                'sort_by' => $query['sort_by'],
                'sort_dir' => $query['sort_dir'],
                'available' => [
                    'sort_by' => ['nome', 'perfil', 'estado', 'municipio', 'escola'],
                    'sort_dir' => ['asc', 'desc'],
                ],
            ],
            'data_policy' => [
                'report_version' => 'v1_cadastro_export_operacional',
                'includes_email' => false,
                'includes_course_progress' => false,
                'includes_tasks' => false,
                'blocked_future_layers' => ['andamento_curso', 'tarefas_entregas', 'observabilidade_pedagogica_fina'],
            ],
        ];
    }

    /**
     * Return a CSV export for the scoped individual layer.
     *
     * @param int|null $userId
     * @param array $params
     * @return string
     */
    public function getUsersCsv($userId = null, array $params = []) {
        $scope = $this->scopeResolver->resolve($userId);
        $query = $this->normalizeUserQueryParams($params);
        $allItems = $this->buildUserRows($this->getScopedUsers($scope));
        $filteredItems = $this->filterUserRows($allItems, $query);
        $sortedItems = $this->sortUserRows($filteredItems, $query['sort_by'], $query['sort_dir']);

        return $this->buildUsersCsv($sortedItems);
    }

    /**
     * Build analytical school context without removing the legacy fallback path.
     *
     * @param array $scope
     * @return array
     */
    private function getScopedAnalyticalCourseUsers(array $scope) {
        $rows = $this->getScopedCourseUsers($scope);
        $canonicalBase = $this->schoolCanonicalService->buildCanonicalBase($this->getPanelUsers());

        if (empty($rows)) {
            return [
                'rows' => [],
                'observability' => $this->emptySchoolReconciliationObservability(),
                'canonical_summary' => $canonicalBase['summary'],
            ];
        }

        $reconciled = $this->schoolReconciliationService->reconcileRows($rows, $canonicalBase);

        return [
            'rows' => $reconciled['rows'],
            'observability' => $reconciled['observability'],
            'canonical_summary' => $canonicalBase['summary'],
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
     * Return scoped users only.
     *
     * @param array $scope
     * @return array
     */
    private function getScopedUsers(array $scope) {
        return $this->getPanelUsers($scope);
    }

    /**
     * Query panel users using canonical registration metadata.
     *
     * @param array|null $scope
     * @return array
     */
    private function getPanelUsers(?array $scope = null) {
        global $wpdb;

        if ($scope !== null && !$scope['authenticated']) {
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
        $allowedUfs = $scope !== null ? array_fill_keys($scope['allowed_ufs'], true) : [];
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

            if ($scope !== null && $scope['scope_class'] !== 'national' && !isset($allowedUfs[$uf])) {
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

            $schoolIdentity = $this->resolveSchoolIdentity($row, $schoolKey);

            if (!isset($items[$schoolKey])) {
                $items[$schoolKey] = [
                    'school_key' => $schoolIdentity['school_key'],
                    'school_key_type' => $schoolIdentity['school_key_type'],
                    'escola_nome' => $schoolIdentity['escola_nome'],
                    'escola_inep' => $schoolIdentity['escola_inep'],
                    'estado' => $schoolIdentity['estado'],
                    'municipio' => $schoolIdentity['municipio'],
                    'rede_escola' => $row['rede_escola'] !== '' ? $row['rede_escola'] : null,
                    'school_canonical_id' => $schoolIdentity['school_canonical_id'],
                    'reconciliation_status' => $schoolIdentity['reconciliation_status'],
                    'reconciliation_confidence' => $schoolIdentity['reconciliation_confidence'],
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
     * @param array $rows
     * @return array
     */
    private function buildUserRows(array $rows) {
        $items = [];

        foreach ($rows as $row) {
            $items[] = [
                'user_id' => (int) ($row['user_id'] ?? 0),
                'nome' => (string) ($row['display_name'] ?? ''),
                'perfil_usuario' => (string) ($row['perfil_usuario'] ?? ''),
                'estado' => (string) ($row['estado'] ?? ''),
                'municipio' => (string) ($row['municipio'] ?? ''),
                'escola_nome' => (string) ($row['escola_nome'] ?? ''),
                'rede_escola' => (string) ($row['rede_escola'] ?? ''),
            ];
        }

        return $items;
    }

    /**
     * @param array $params
     * @return array
     */
    private function normalizeSchoolQueryParams(array $params) {
        $sortBy = $this->normalizeSchoolSortBy($params['sort_by'] ?? 'total_cursistas');

        return [
            'page' => max(1, (int) ($params['page'] ?? 1)),
            'per_page' => min(100, max(1, (int) ($params['per_page'] ?? 10))),
            'search' => $this->normalizeSearchTerm((string) ($params['search'] ?? '')),
            'uf' => $this->normalizeSchoolFilterValue((string) ($params['uf'] ?? '')),
            'rede' => $this->normalizeSchoolFilterValue((string) ($params['rede'] ?? '')),
            'inep_mode' => $this->normalizeInepMode((string) ($params['inep_mode'] ?? 'all')),
            'sort_by' => $sortBy,
            'sort_dir' => $this->normalizeSchoolSortDir(
                (string) ($params['sort_dir'] ?? ''),
                $sortBy
            ),
            'format' => strtolower(trim((string) ($params['format'] ?? 'json'))),
        ];
    }

    /**
     * @param array $params
     * @return array
     */
    private function normalizeUserQueryParams(array $params) {
        $searchRaw = trim((string) ($params['search'] ?? ''));
        $municipioRaw = $this->normalizeTextFilterValue((string) ($params['municipio'] ?? ''));
        $escolaRaw = $this->normalizeTextFilterValue((string) ($params['escola'] ?? ''));
        $sortBy = $this->normalizeUserSortBy((string) ($params['sort_by'] ?? 'nome'));

        return [
            'page' => max(1, (int) ($params['page'] ?? 1)),
            'per_page' => min(200, max(1, (int) ($params['per_page'] ?? 25))),
            'search' => $this->normalizeSearchTerm($searchRaw),
            'search_raw' => $searchRaw,
            'uf' => $this->normalizeSchoolFilterValue((string) ($params['uf'] ?? '')),
            'municipio' => $this->normalizeSearchTerm($municipioRaw),
            'municipio_raw' => $municipioRaw,
            'escola' => $this->normalizeSearchTerm($escolaRaw),
            'escola_raw' => $escolaRaw,
            'perfil' => $this->normalizeProfileFilterValue((string) ($params['perfil'] ?? '')),
            'sort_by' => $sortBy,
            'sort_dir' => $this->normalizeUserSortDir((string) ($params['sort_dir'] ?? ''), $sortBy),
            'format' => strtolower(trim((string) ($params['format'] ?? 'json'))),
        ];
    }

    /**
     * @param array $items
     * @param array $query
     * @return array
     */
    private function filterSchoolAggregates(array $items, array $query) {
        return array_values(array_filter($items, function ($item) use ($query) {
            if ($query['uf'] !== '' && strtoupper((string) ($item['estado'] ?? '')) !== $query['uf']) {
                return false;
            }

            if ($query['rede'] !== '' && (string) ($item['rede_escola'] ?? '') !== $query['rede']) {
                return false;
            }

            if ($query['inep_mode'] === 'with_inep' && empty($item['escola_inep'])) {
                return false;
            }

            if ($query['inep_mode'] === 'without_inep' && !empty($item['escola_inep'])) {
                return false;
            }

            if ($query['search'] === '') {
                return true;
            }

            $haystacks = [
                $this->normalizeSearchTerm((string) ($item['escola_nome'] ?? '')),
                $this->normalizeSearchTerm((string) ($item['municipio'] ?? '')),
                $this->normalizeSearchTerm((string) ($item['escola_inep'] ?? '')),
            ];

            foreach ($haystacks as $haystack) {
                if ($haystack !== '' && strpos($haystack, $query['search']) !== false) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param array $items
     * @param array $query
     * @return array
     */
    private function filterUserRows(array $items, array $query) {
        return array_values(array_filter($items, function ($item) use ($query) {
            if ($query['uf'] !== '' && strtoupper((string) ($item['estado'] ?? '')) !== $query['uf']) {
                return false;
            }

            if (
                $query['municipio'] !== ''
                && $this->normalizeSearchTerm((string) ($item['municipio'] ?? '')) !== $query['municipio']
            ) {
                return false;
            }

            if (
                $query['escola'] !== ''
                && $this->normalizeSearchTerm((string) ($item['escola_nome'] ?? '')) !== $query['escola']
            ) {
                return false;
            }

            if ($query['perfil'] !== '' && (string) ($item['perfil_usuario'] ?? '') !== $query['perfil']) {
                return false;
            }

            if ($query['search'] === '') {
                return true;
            }

            $haystacks = [
                $this->normalizeSearchTerm((string) ($item['nome'] ?? '')),
                $this->normalizeSearchTerm((string) ($item['municipio'] ?? '')),
                $this->normalizeSearchTerm((string) ($item['escola_nome'] ?? '')),
                $this->normalizeSearchTerm((string) ($item['perfil_usuario'] ?? '')),
            ];

            foreach ($haystacks as $haystack) {
                if ($haystack !== '' && strpos($haystack, $query['search']) !== false) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * @param array $items
     * @param string $sortBy
     * @param string $sortDir
     * @return array
     */
    private function sortSchoolAggregates(array $items, $sortBy, $sortDir) {
        usort($items, function ($left, $right) use ($sortBy, $sortDir) {
            $direction = $sortDir === 'asc' ? 1 : -1;
            $comparison = 0;

            if ($sortBy === 'escola_nome') {
                $comparison = strcasecmp(
                    (string) ($left['escola_nome'] ?? ''),
                    (string) ($right['escola_nome'] ?? '')
                );
            } elseif ($sortBy === 'estado') {
                $comparison = strcmp(
                    (string) ($left['estado'] ?? ''),
                    (string) ($right['estado'] ?? '')
                );

                if ($comparison === 0) {
                    $comparison = strcasecmp(
                        (string) ($left['escola_nome'] ?? ''),
                        (string) ($right['escola_nome'] ?? '')
                    );
                }
            } else {
                $comparison = ((int) ($left['total_cursistas'] ?? 0)) <=> ((int) ($right['total_cursistas'] ?? 0));

                if ($comparison === 0) {
                    $comparison = strcasecmp(
                        (string) ($left['escola_nome'] ?? ''),
                        (string) ($right['escola_nome'] ?? '')
                    );
                }
            }

            if ($comparison === 0) {
                $comparison = strcmp(
                    (string) ($left['school_key'] ?? ''),
                    (string) ($right['school_key'] ?? '')
                );
            }

            return $comparison * $direction;
        });

        return $items;
    }

    /**
     * @param array $items
     * @param string $sortBy
     * @param string $sortDir
     * @return array
     */
    private function sortUserRows(array $items, $sortBy, $sortDir) {
        usort($items, function ($left, $right) use ($sortBy, $sortDir) {
            $direction = $sortDir === 'desc' ? -1 : 1;
            $fieldMap = [
                'nome' => 'nome',
                'perfil' => 'perfil_usuario',
                'estado' => 'estado',
                'municipio' => 'municipio',
                'escola' => 'escola_nome',
            ];

            $field = $fieldMap[$sortBy] ?? 'nome';
            $comparison = strcasecmp(
                (string) ($left[$field] ?? ''),
                (string) ($right[$field] ?? '')
            );

            if ($comparison === 0) {
                $comparison = ((int) ($left['user_id'] ?? 0)) <=> ((int) ($right['user_id'] ?? 0));
            }

            return $comparison * $direction;
        });

        return $items;
    }

    /**
     * @param array $items
     * @param int $page
     * @param int $perPage
     * @return array
     */
    private function paginateSchoolAggregates(array $items, $page, $perPage) {
        $totalItems = count($items);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $safePage = min(max(1, (int) $page), $totalPages);

        return [
            'items' => array_slice($items, ($safePage - 1) * $perPage, $perPage),
            'pagination' => [
                'page' => $safePage,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * @param array $items
     * @param int $page
     * @param int $perPage
     * @return array
     */
    private function paginateUserRows(array $items, $page, $perPage) {
        $totalItems = count($items);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $safePage = min(max(1, (int) $page), $totalPages);

        return [
            'items' => array_slice($items, ($safePage - 1) * $perPage, $perPage),
            'pagination' => [
                'page' => $safePage,
                'per_page' => $perPage,
                'total_items' => $totalItems,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * @param array $items
     * @param string $field
     * @return array
     */
    private function extractSchoolFilterOptions(array $items, $field) {
        $values = [];

        foreach ($items as $item) {
            $value = trim((string) ($item[$field] ?? ''));
            if ($value === '') {
                continue;
            }

            $values[$value] = true;
        }

        $options = array_keys($values);
        sort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return array_values($options);
    }

    /**
     * @param array $items
     * @return string
     */
    private function buildSchoolsCsv(array $items) {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return '';
        }

        fprintf($stream, chr(239) . chr(187) . chr(191));
        fputcsv($stream, [
            'Escola',
            'UF',
            'Municipio',
            'Rede',
            'Participantes',
            'Progresso Medio',
            'Concluintes',
            'Nao Iniciados',
        ], ';');

        foreach ($items as $item) {
            fputcsv($stream, [
                (string) ($item['escola_nome'] ?? ''),
                (string) ($item['estado'] ?? ''),
                (string) ($item['municipio'] ?? ''),
                (string) ($item['rede_escola'] ?? ''),
                (int) ($item['total_cursistas'] ?? 0),
                $item['progresso_medio'] !== null ? (string) $item['progresso_medio'] : '',
                $item['concluintes'] !== null ? (string) $item['concluintes'] : '',
                $item['nao_iniciados'] !== null ? (string) $item['nao_iniciados'] : '',
            ], ';');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv !== false ? $csv : '';
    }

    /**
     * @param array $items
     * @return string
     */
    private function buildUsersCsv(array $items) {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return '';
        }

        fprintf($stream, chr(239) . chr(187) . chr(191));
        fputcsv($stream, [
            'ID',
            'Nome',
            'Perfil',
            'UF',
            'Municipio',
            'Escola',
            'Rede',
        ], ';');

        foreach ($items as $item) {
            fputcsv($stream, [
                (int) ($item['user_id'] ?? 0),
                (string) ($item['nome'] ?? ''),
                (string) ($item['perfil_usuario'] ?? ''),
                (string) ($item['estado'] ?? ''),
                (string) ($item['municipio'] ?? ''),
                (string) ($item['escola_nome'] ?? ''),
                (string) ($item['rede_escola'] ?? ''),
            ], ';');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv !== false ? $csv : '';
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
        $link = $row['school_reconciliation_link'] ?? null;
        $canonical = $row['school_canonical'] ?? null;

        if (
            is_array($link) &&
            is_array($canonical) &&
            !empty($link['school_canonical_id']) &&
            in_array((string) ($link['status_reconciliacao'] ?? ''), ['confirmado_por_inep', 'confirmado_por_nome_municipio_uf'], true)
        ) {
            return (string) ($canonical['school_canonical_key'] ?? '');
        }

        return $this->buildLegacySchoolKey($row);
    }

    /**
     * Build canonical school aggregation key using the legacy hybrid strategy.
     *
     * @param array $row
     * @return string|null
     */
    private function buildLegacySchoolKey(array $row) {
        if (!empty($row['escola_inep'])) {
            return 'inep:' . $row['escola_inep'];
        }

        if (empty($row['escola_nome']) || empty($row['estado'])) {
            return null;
        }

        return 'name:' . $this->normalizeKey($row['escola_nome']) . '|' . $this->normalizeKey($row['municipio']) . '|' . strtoupper($row['estado']);
    }

    /**
     * Resolve the school identity returned to the panel, preferring the analytical
     * canonical layer when reconciliation is confirmed and preserving legacy data otherwise.
     *
     * @param array $row
     * @param string $schoolKey
     * @return array
     */
    private function resolveSchoolIdentity(array $row, $schoolKey) {
        $link = is_array($row['school_reconciliation_link'] ?? null)
            ? $row['school_reconciliation_link']
            : null;
        $canonical = is_array($row['school_canonical'] ?? null)
            ? $row['school_canonical']
            : null;

        if (
            $link !== null &&
            $canonical !== null &&
            !empty($link['school_canonical_id']) &&
            in_array((string) ($link['status_reconciliacao'] ?? ''), ['confirmado_por_inep', 'confirmado_por_nome_municipio_uf'], true)
        ) {
            return [
                'school_key' => (string) ($canonical['school_canonical_key'] ?? $schoolKey),
                'school_key_type' => !empty($canonical['inep_preferencial']) ? 'inep' : 'normalized_name',
                'escola_nome' => (string) ($canonical['nome_canonico'] ?? 'Escola sem nome informado'),
                'escola_inep' => !empty($canonical['inep_preferencial']) ? (string) $canonical['inep_preferencial'] : null,
                'estado' => (string) ($canonical['estado_canonico'] ?? $row['estado']),
                'municipio' => (string) ($canonical['municipio_canonico'] ?? $row['municipio']),
                'school_canonical_id' => (string) $link['school_canonical_id'],
                'reconciliation_status' => (string) ($link['status_reconciliacao'] ?? ''),
                'reconciliation_confidence' => (string) ($link['nivel_confianca'] ?? ''),
            ];
        }

        return [
            'school_key' => $schoolKey,
            'school_key_type' => $row['escola_inep'] !== '' ? 'inep' : 'normalized_name',
            'escola_nome' => $row['escola_nome'] !== '' ? $row['escola_nome'] : 'Escola sem nome informado',
            'escola_inep' => $row['escola_inep'] !== '' ? $row['escola_inep'] : null,
            'estado' => $row['estado'],
            'municipio' => $row['municipio'],
            'school_canonical_id' => null,
            'reconciliation_status' => $link['status_reconciliacao'] ?? null,
            'reconciliation_confidence' => $link['nivel_confianca'] ?? null,
        ];
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

    /**
     * @param string $value
     * @return string
     */
    private function normalizeSearchTerm($value) {
        return $this->normalizeKey($value);
    }

    /**
     * @param string $value
     * @return string
     */
    private function normalizeSchoolFilterValue($value) {
        $value = trim((string) $value);
        if ($value === '' || strtolower($value) === 'all') {
            return '';
        }

        return strtoupper($value) === $value ? strtoupper($value) : $value;
    }

    /**
     * @param string $value
     * @return string
     */
    private function normalizeTextFilterValue($value) {
        $value = trim((string) $value);
        if ($value === '' || strtolower($value) === 'all') {
            return '';
        }

        return sanitize_text_field($value);
    }

    /**
     * @param string $value
     * @return string
     */
    private function normalizeInepMode($value) {
        $value = strtolower(trim($value));

        if ($value === 'with') {
            return 'with_inep';
        }

        if ($value === 'without') {
            return 'without_inep';
        }

        if (in_array($value, ['all', 'with_inep', 'without_inep'], true)) {
            return $value;
        }

        return 'all';
    }

    /**
     * @param string $value
     * @return string
     */
    private function normalizeProfileFilterValue($value) {
        $value = trim((string) $value);
        if ($value === '' || strtolower($value) === 'all') {
            return '';
        }

        return $this->normalizeProfile($value);
    }

    /**
     * @param string $value
     * @return string
     */
    private function normalizeSchoolSortBy($value) {
        $value = strtolower(trim($value));
        $map = [
            'cursistas' => 'total_cursistas',
            'participants' => 'total_cursistas',
            'total_cursistas' => 'total_cursistas',
            'school_name' => 'escola_nome',
            'escola_nome' => 'escola_nome',
            'name' => 'escola_nome',
            'uf' => 'estado',
            'estado' => 'estado',
        ];

        return $map[$value] ?? 'total_cursistas';
    }

    /**
     * @param string $value
     * @return string
     */
    private function normalizeUserSortBy($value) {
        $value = strtolower(trim($value));
        $map = [
            'nome' => 'nome',
            'name' => 'nome',
            'perfil' => 'perfil',
            'profile' => 'perfil',
            'estado' => 'estado',
            'uf' => 'estado',
            'municipio' => 'municipio',
            'city' => 'municipio',
            'escola' => 'escola',
            'school' => 'escola',
        ];

        return $map[$value] ?? 'nome';
    }

    /**
     * @param string $value
     * @param string $sortBy
     * @return string
     */
    private function normalizeSchoolSortDir($value, $sortBy) {
        $value = strtolower(trim($value));
        if (in_array($value, ['asc', 'desc'], true)) {
            return $value;
        }

        return $sortBy === 'total_cursistas' ? 'desc' : 'asc';
    }

    /**
     * @param string $value
     * @param string $sortBy
     * @return string
     */
    private function normalizeUserSortDir($value, $sortBy) {
        $value = strtolower(trim($value));
        if (in_array($value, ['asc', 'desc'], true)) {
            return $value;
        }

        return in_array($sortBy, ['nome', 'perfil', 'estado', 'municipio', 'escola'], true) ? 'asc' : 'desc';
    }

    /**
     * @return array
     */
    private function emptySchoolReconciliationObservability() {
        return [
            'total_rows' => 0,
            'confirmado_por_inep' => 0,
            'confirmado_por_nome_municipio_uf' => 0,
            'pendente_reconciliacao' => 0,
            'conflito' => 0,
            'sem_vinculo' => 0,
        ];
    }
}
