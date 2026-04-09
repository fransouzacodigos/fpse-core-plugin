<?php
/**
 * MF3 V2 runtime audit for canonical course facts.
 *
 * Usage:
 * php wp-content/plugins/fpse-core/scripts/mf3-course-facts-audit.php [--scope-user-id=123] [--sample-user-id=1,2,3]
 *
 * @package FortaleceePSE
 */

use FortaleceePSE\Core\Plugin;
use FortaleceePSE\Core\Services\Mf3CourseFactsService;
use FortaleceePSE\Core\Services\Mf3PanelDataService;
use FortaleceePSE\Core\Services\Mf3PanelScopeResolver;

if (!defined('ABSPATH')) {
    $wpLoad = null;
    $dir = __DIR__;

    while ($dir !== dirname($dir)) {
        $candidate = $dir . '/wp-load.php';
        if (is_file($candidate)) {
            $wpLoad = $candidate;
            break;
        }

        $dir = dirname($dir);
    }

    if ($wpLoad === null) {
        fwrite(STDERR, "wp-load.php nao encontrado.\n");
        exit(1);
    }

    require_once $wpLoad;
}

if (!class_exists(Plugin::class)) {
    $pluginFile = dirname(__DIR__) . '/fpse-core.php';
    if (!is_file($pluginFile)) {
        fwrite(STDERR, "fpse-core.php nao encontrado.\n");
        exit(1);
    }

    require_once $pluginFile;
}

$plugin = Plugin::getInstance();
$plugin->init();

$args = $argv ?? [];
$scopeUserId = max(0, (int) (getenv('MF3_AUDIT_SCOPE_USER_ID') ?: 0));
$explicitSampleUserIds = parseSampleUserIds((string) (getenv('MF3_AUDIT_SAMPLE_USER_IDS') ?: ''));

foreach ($args as $arg) {
    if (strpos($arg, '--scope-user-id=') === 0) {
        $scopeUserId = max(0, (int) substr($arg, strlen('--scope-user-id=')));
        continue;
    }

    if (strpos($arg, '--sample-user-id=') === 0) {
        $explicitSampleUserIds = parseSampleUserIds(substr($arg, strlen('--sample-user-id=')));
    }
}

$scopeResolver = new Mf3PanelScopeResolver($plugin);
$courseFactsService = new Mf3CourseFactsService($plugin);
$panelDataService = new Mf3PanelDataService($plugin);

$scope = $scopeUserId > 0
    ? $scopeResolver->resolve($scopeUserId)
    : buildSyntheticNationalScope($plugin);

$rows = getScopedAnalyticalRows($panelDataService, $scope);
$factsByUserId = [];

foreach ($rows as $row) {
    $facts = is_array($row['mf3_course'] ?? null) ? $row['mf3_course'] : null;
    if ($facts === null) {
        continue;
    }

    $factsByUserId[(int) $row['user_id']] = $facts;
}

$explicitSampleFacts = [];
if (!empty($explicitSampleUserIds)) {
    $explicitSampleFacts = $courseFactsService->getFactsForUsers($explicitSampleUserIds);
}

$overview = invokePrivate($panelDataService, 'buildOverviewFacts', [$rows]);
$states = invokePrivate($panelDataService, 'buildStateAggregates', [$rows]);
$schools = invokePrivate($panelDataService, 'buildSchoolAggregates', [$rows]);

$rowsWithSchoolKey = 0;
foreach ($rows as $row) {
    $schoolKey = invokePrivate($panelDataService, 'buildSchoolKey', [$row]);
    if ($schoolKey !== null && $schoolKey !== '') {
        $rowsWithSchoolKey++;
    }
}

$sampleCases = buildSampleCases($rows, $factsByUserId, $explicitSampleFacts);
$aggregateAudit = buildAggregateAudit($overview, $states, $schools, count($rows), $rowsWithSchoolKey);
$versioning = [
    'workspace_gitignore' => [
        'fpse_core_ignored_in_root_repo' => isIgnoredByRootGit('fpse-core/src/Services/Mf3CourseFactsService.php'),
        'docs_ignored_in_root_repo' => isIgnoredByRootGit('.docs/MF3_V2_ANDAMENTO_CURSO_FATOS_CANONICOS_2026-04-07.md'),
    ],
    'real_versioning_path' => [
        'source_of_truth' => 'fpse-core/ como plugin instalavel',
        'deploy_artifact' => 'zip instalavel do fpse-core em releases/',
        'recommended_traceability' => 'gerar zip versionado apos homologacao runtime e registrar resumo em .docs',
        'warning' => 'alteracoes em fpse-core/ e .docs/ nao aparecem no git status da raiz enquanto permanecerem no .gitignore',
    ],
];

$report = [
    'generated_at' => gmdate('c'),
    'course' => $courseFactsService->getCourseConfig(),
    'scope' => [
        'scope_user_id' => $scopeUserId > 0 ? $scopeUserId : null,
        'scope_class' => $scope['scope_class'] ?? null,
        'scope_reason' => $scope['scope_reason'] ?? null,
        'allowed_ufs' => array_values($scope['allowed_ufs'] ?? []),
        'synthetic_national_scope' => $scopeUserId <= 0,
    ],
    'runtime_availability_reason' => $courseFactsService->getAvailabilityReason(),
    'sample_cases' => $sampleCases,
    'aggregate_audit' => $aggregateAudit,
    'semantic_change_notice' => [
        'metric' => 'progresso_medio_percentual',
        'v1' => 'universo parcial orientado por usuarios com acesso ao curso',
        'v2' => 'universo institucional completo do recorte analitico, incluindo usuarios sem acesso com 0%',
        'historical_comparison_warning' => true,
    ],
    'compatibility_v1' => [
        'legacy_aliases_expected' => ['concluintes', 'nao_iniciados', 'ultimo_acesso_mais_recente'],
        'users_v1_remains_isolated' => true,
        'rbac_requires_scope_user_id_for_real_audit' => $scopeUserId <= 0,
        'course_status_macro_preserved' => true,
    ],
    'versioning_and_deploy' => $versioning,
];

echo wp_json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

/**
 * @return array
 */
function buildSyntheticNationalScope(Plugin $plugin): array {
    $states = array_keys((array) $plugin->getConfig('states', []));

    return [
        'authenticated' => true,
        'user_id' => 0,
        'profiles' => [],
        'active_profiles' => [],
        'scope_class' => 'national',
        'scope_reason' => 'synthetic_runtime_audit_scope',
        'allowed_ufs' => array_values($states),
        'allowed_group_slugs' => [],
        'features' => [
            'overview' => true,
            'states' => true,
            'schools' => true,
            'users' => true,
            'attention' => false,
        ],
        'can_view_overview' => true,
        'can_view_states' => true,
        'can_view_schools' => true,
        'can_view_users' => true,
        'can_view_attention' => false,
        'can_export' => true,
    ];
}

/**
 * @return array
 */
function getScopedAnalyticalRows(Mf3PanelDataService $service, array $scope): array {
    return (array) invokePrivate($service, 'getScopedAnalyticalCourseUsers', [$scope])['rows'];
}

/**
 * @param object $object
 * @param string $method
 * @param array $args
 * @return mixed
 */
function invokePrivate($object, string $method, array $args = []) {
    $reflection = new ReflectionClass($object);
    $instanceMethod = $reflection->getMethod($method);
    $instanceMethod->setAccessible(true);

    return $instanceMethod->invokeArgs($object, $args);
}

/**
 * @param array $rows
 * @param array $factsByUserId
 * @param array $explicitSampleFacts
 * @return array
 */
function buildSampleCases(array $rows, array $factsByUserId, array $explicitSampleFacts): array {
    $cases = [];

    $progressStateToCase = [
        'no_access' => 'user_without_course_access',
        'not_started' => 'user_with_access_not_started',
        'started_no_progress' => 'user_started_no_progress',
        'in_progress' => 'user_in_progress',
        'completed' => 'user_completed',
    ];

    foreach ($progressStateToCase as $progressState => $caseId) {
        $sample = findRowByProgressState($rows, $progressState);
        $cases[$caseId] = buildCasePayload($caseId, $progressState, $sample);
    }

    $recentActivity = findMostRecentUsefulActivityRow($rows);
    $cases['user_with_recent_useful_activity'] = buildCasePayload(
        'user_with_recent_useful_activity',
        null,
        $recentActivity
    );

    $siteOnly = findSiteOnlyActivityCandidate($rows);
    $cases['user_with_site_activity_but_not_course'] = buildCasePayload(
        'user_with_site_activity_but_not_course',
        'no_access',
        $siteOnly
    );

    if (!empty($explicitSampleFacts)) {
        $cases['explicit_sample_user_ids'] = [];
        foreach ($explicitSampleFacts as $userId => $facts) {
            $cases['explicit_sample_user_ids'][] = [
                'user_id' => (int) $userId,
                'observed' => normalizeFactSnapshot($facts),
            ];
        }
    }

    return $cases;
}

/**
 * @param array $rows
 * @param string $progressState
 * @return array|null
 */
function findRowByProgressState(array $rows, string $progressState): ?array {
    foreach ($rows as $row) {
        $facts = $row['mf3_course'] ?? null;
        if (is_array($facts) && ($facts['course_progress_state'] ?? '') === $progressState) {
            return $row;
        }
    }

    return null;
}

/**
 * @param array $rows
 * @return array|null
 */
function findMostRecentUsefulActivityRow(array $rows): ?array {
    $winner = null;

    foreach ($rows as $row) {
        $facts = $row['mf3_course'] ?? null;
        $ts = (int) ($facts['last_activity_at_ts'] ?? 0);
        if (!is_array($facts) || $ts <= 0) {
            continue;
        }

        if ($winner === null || $ts > (int) (($winner['mf3_course']['last_activity_at_ts'] ?? 0))) {
            $winner = $row;
        }
    }

    return $winner;
}

/**
 * @param array $rows
 * @return array|null
 */
function findSiteOnlyActivityCandidate(array $rows): ?array {
    foreach ($rows as $row) {
        $facts = $row['mf3_course'] ?? null;
        if (!is_array($facts) || ($facts['course_status'] ?? '') !== 'no_access') {
            continue;
        }

        $genericActivity = get_user_meta((int) $row['user_id'], 'last_activity', true);
        if ($genericActivity !== '' && $genericActivity !== null) {
            $row['generic_site_activity'] = $genericActivity;
            return $row;
        }
    }

    return null;
}

/**
 * @param string $caseId
 * @param string|null $expectedProgressState
 * @param array|null $row
 * @return array
 */
function buildCasePayload(string $caseId, ?string $expectedProgressState, ?array $row): array {
    $expected = buildExpectedSnapshot($caseId, $expectedProgressState);

    if ($row === null) {
        return [
            'expected' => $expected,
            'observed' => null,
            'result' => 'sample_not_found',
        ];
    }

    $facts = is_array($row['mf3_course'] ?? null) ? $row['mf3_course'] : [];
    $observed = normalizeFactSnapshot($facts);
    $observed['user_context'] = [
        'user_id' => (int) ($row['user_id'] ?? 0),
        'estado' => (string) ($row['estado'] ?? ''),
        'perfil_usuario' => (string) ($row['perfil_usuario'] ?? ''),
        'municipio' => (string) ($row['municipio'] ?? ''),
        'escola_nome' => (string) ($row['escola_nome'] ?? ''),
    ];

    if (isset($row['generic_site_activity'])) {
        $observed['generic_site_activity'] = $row['generic_site_activity'];
    }

    return [
        'expected' => $expected,
        'observed' => $observed,
        'result' => evaluateCaseResult($caseId, $observed),
    ];
}

/**
 * @param string $caseId
 * @param string|null $expectedProgressState
 * @return array
 */
function buildExpectedSnapshot(string $caseId, ?string $expectedProgressState): array {
    $base = ['course_progress_state' => $expectedProgressState];

    if ($caseId === 'user_without_course_access') {
        return $base + [
            'has_course_access' => false,
            'has_started_course' => false,
            'has_measurable_progress' => false,
            'steps_completed' => 0,
            'progress_percent' => 0,
            'has_completed_course' => false,
            'completed_at' => null,
            'course_status' => 'no_access',
        ];
    }

    if ($caseId === 'user_with_access_not_started') {
        return $base + [
            'has_course_access' => true,
            'has_started_course' => false,
            'has_measurable_progress' => false,
            'steps_completed' => 0,
            'course_started_at' => null,
            'progress_percent' => 0,
            'has_completed_course' => false,
            'course_status' => 'enrolled_not_started',
        ];
    }

    if ($caseId === 'user_started_no_progress') {
        return $base + [
            'has_course_access' => true,
            'has_started_course' => true,
            'has_measurable_progress' => false,
            'steps_completed' => 0,
            'course_started_at_or_last_activity' => 'not_null',
            'progress_percent' => 0,
            'has_completed_course' => false,
            'course_status' => 'enrolled_not_started',
        ];
    }

    if ($caseId === 'user_in_progress') {
        return $base + [
            'has_course_access' => true,
            'has_started_course' => true,
            'has_measurable_progress' => true,
            'steps_completed_or_progress_percent' => 'positive',
            'progress_percent_between' => [1, 99],
            'has_completed_course' => false,
            'course_status' => 'in_progress',
        ];
    }

    if ($caseId === 'user_completed') {
        return $base + [
            'has_course_access' => true,
            'has_started_course' => true,
            'has_measurable_progress' => true,
            'progress_percent' => 100,
            'has_completed_course' => true,
            'completed_at' => 'not_null',
            'course_status' => 'completed',
        ];
    }

    if ($caseId === 'user_with_recent_useful_activity') {
        return [
            'last_activity_at' => 'not_null',
            'last_activity_type' => 'course_event_only',
        ];
    }

    if ($caseId === 'user_with_site_activity_but_not_course') {
        return [
            'has_course_access' => false,
            'has_started_course' => false,
            'course_progress_state' => 'no_access',
            'last_activity_at' => null,
            'generic_site_activity_must_not_promote_course_start' => true,
        ];
    }

    return $base;
}

/**
 * @param array $facts
 * @return array
 */
function normalizeFactSnapshot(array $facts): array {
    return [
        'user_id' => (int) ($facts['user_id'] ?? 0),
        'course_id' => (int) ($facts['course_id'] ?? 0),
        'has_course_access' => (bool) ($facts['has_course_access'] ?? false),
        'has_started_course' => (bool) ($facts['has_started_course'] ?? false),
        'course_started_at' => $facts['course_started_at'] ?? null,
        'steps_completed' => (int) ($facts['steps_completed'] ?? 0),
        'has_measurable_progress' => (bool) ($facts['has_measurable_progress'] ?? false),
        'progress_percent' => (int) ($facts['progress_percent'] ?? 0),
        'has_completed_course' => (bool) ($facts['has_completed_course'] ?? false),
        'completed_at' => $facts['completed_at'] ?? null,
        'last_activity_at' => $facts['last_activity_at'] ?? null,
        'last_activity_type' => $facts['last_activity_type'] ?? null,
        'course_status' => $facts['course_status'] ?? null,
        'course_progress_state' => $facts['course_progress_state'] ?? null,
    ];
}

/**
 * @param string $caseId
 * @param array $observed
 * @return string
 */
function evaluateCaseResult(string $caseId, array $observed): string {
    if ($caseId === 'user_without_course_access') {
        return (!$observed['has_course_access'] && !$observed['has_started_course'] && !$observed['has_measurable_progress'] && $observed['steps_completed'] === 0 && $observed['progress_percent'] === 0 && !$observed['has_completed_course'] && $observed['completed_at'] === null && $observed['course_status'] === 'no_access' && $observed['course_progress_state'] === 'no_access')
            ? 'pass'
            : 'fail';
    }

    if ($caseId === 'user_with_access_not_started') {
        return ($observed['has_course_access'] && !$observed['has_started_course'] && !$observed['has_measurable_progress'] && $observed['steps_completed'] === 0 && $observed['course_started_at'] === null && $observed['progress_percent'] === 0 && !$observed['has_completed_course'] && $observed['course_status'] === 'enrolled_not_started' && $observed['course_progress_state'] === 'not_started')
            ? 'pass'
            : 'fail';
    }

    if ($caseId === 'user_started_no_progress') {
        return ($observed['has_course_access'] && $observed['has_started_course'] && !$observed['has_measurable_progress'] && $observed['steps_completed'] === 0 && $observed['progress_percent'] === 0 && !$observed['has_completed_course'] && $observed['course_status'] === 'enrolled_not_started' && $observed['course_progress_state'] === 'started_no_progress' && ($observed['course_started_at'] !== null || $observed['last_activity_at'] !== null))
            ? 'pass'
            : 'fail';
    }

    if ($caseId === 'user_in_progress') {
        return ($observed['has_course_access'] && $observed['has_started_course'] && $observed['has_measurable_progress'] && ($observed['steps_completed'] > 0 || ($observed['progress_percent'] >= 1 && $observed['progress_percent'] <= 99)) && !$observed['has_completed_course'] && $observed['course_status'] === 'in_progress' && $observed['course_progress_state'] === 'in_progress')
            ? 'pass'
            : 'fail';
    }

    if ($caseId === 'user_completed') {
        return ($observed['has_course_access'] && $observed['has_started_course'] && $observed['has_measurable_progress'] && $observed['progress_percent'] === 100 && $observed['has_completed_course'] && $observed['completed_at'] !== null && $observed['course_status'] === 'completed' && $observed['course_progress_state'] === 'completed')
            ? 'pass'
            : 'fail';
    }

    if ($caseId === 'user_with_recent_useful_activity') {
        return ($observed['last_activity_at'] !== null && in_array((string) $observed['last_activity_type'], ['progress_update', 'lesson_completed', 'topic_completed', 'course_completed'], true))
            ? 'pass'
            : 'fail';
    }

    if ($caseId === 'user_with_site_activity_but_not_course') {
        return (!$observed['has_course_access'] && !$observed['has_started_course'] && $observed['last_activity_at'] === null)
            ? 'pass'
            : 'fail';
    }

    return 'not_evaluated';
}

/**
 * @param array $overview
 * @param array $states
 * @param array $schools
 * @param int $rowCount
 * @param int $rowsWithSchoolKey
 * @return array
 */
function buildAggregateAudit(array $overview, array $states, array $schools, int $rowCount, int $rowsWithSchoolKey): array {
    $stateTotals = [
        'total_cursistas' => 0,
        'com_acesso' => 0,
        'sem_acesso' => 0,
        'nao_iniciados' => 0,
        'iniciados_sem_progresso' => 0,
        'em_andamento' => 0,
        'concluidos' => 0,
    ];

    foreach ($states as $state) {
        $stateTotals['total_cursistas'] += (int) ($state['total_cursistas'] ?? 0);
        $stateTotals['com_acesso'] += (int) ($state['com_acesso'] ?? 0);
        $stateTotals['sem_acesso'] += (int) ($state['sem_acesso'] ?? 0);
        $stateTotals['nao_iniciados'] += (int) ($state['nao_iniciados'] ?? 0);
        $stateTotals['iniciados_sem_progresso'] += (int) ($state['iniciados_sem_progresso'] ?? 0);
        $stateTotals['em_andamento'] += (int) ($state['em_andamento'] ?? 0);
        $stateTotals['concluidos'] += (int) ($state['concluidos'] ?? 0);
    }

    $schoolTotals = [
        'total_cursistas' => 0,
        'com_acesso' => 0,
        'sem_acesso' => 0,
        'nao_iniciados' => 0,
        'iniciados_sem_progresso' => 0,
        'em_andamento' => 0,
        'concluidos' => 0,
    ];

    foreach ($schools as $school) {
        $schoolTotals['total_cursistas'] += (int) ($school['total_cursistas'] ?? 0);
        $schoolTotals['com_acesso'] += (int) ($school['com_acesso'] ?? 0);
        $schoolTotals['sem_acesso'] += (int) ($school['sem_acesso'] ?? 0);
        $schoolTotals['nao_iniciados'] += (int) ($school['nao_iniciados'] ?? 0);
        $schoolTotals['iniciados_sem_progresso'] += (int) ($school['iniciados_sem_progresso'] ?? 0);
        $schoolTotals['em_andamento'] += (int) ($school['em_andamento'] ?? 0);
        $schoolTotals['concluidos'] += (int) ($school['concluidos'] ?? 0);
    }

    return [
        'overview' => [
            'total_cursistas' => $rowCount,
            'total_com_acesso' => (int) ($overview['total_com_acesso'] ?? 0),
            'total_sem_acesso' => (int) ($overview['total_sem_acesso'] ?? 0),
            'total_nao_iniciados' => (int) ($overview['total_nao_iniciados'] ?? 0),
            'total_iniciados_sem_progresso' => (int) ($overview['total_iniciados_sem_progresso'] ?? 0),
            'total_em_andamento' => (int) ($overview['total_em_andamento'] ?? 0),
            'total_concluidos' => (int) ($overview['total_concluidos'] ?? 0),
            'progresso_medio_percentual' => $overview['progresso_medio_percentual'] ?? null,
        ],
        'states_sum' => $stateTotals,
        'schools_sum' => $schoolTotals,
        'schools_coverage' => [
            'rows_with_school_key' => $rowsWithSchoolKey,
            'rows_without_school_key' => $rowCount - $rowsWithSchoolKey,
        ],
        'consistency_checks' => [
            'states_match_overview_universe' => $stateTotals['total_cursistas'] === $rowCount,
            'states_match_overview_access' => $stateTotals['com_acesso'] === (int) ($overview['total_com_acesso'] ?? 0),
            'states_match_overview_no_access' => $stateTotals['sem_acesso'] === (int) ($overview['total_sem_acesso'] ?? 0),
            'states_match_overview_not_started' => $stateTotals['nao_iniciados'] === (int) ($overview['total_nao_iniciados'] ?? 0),
            'states_match_overview_started_no_progress' => $stateTotals['iniciados_sem_progresso'] === (int) ($overview['total_iniciados_sem_progresso'] ?? 0),
            'states_match_overview_in_progress' => $stateTotals['em_andamento'] === (int) ($overview['total_em_andamento'] ?? 0),
            'states_match_overview_completed' => $stateTotals['concluidos'] === (int) ($overview['total_concluidos'] ?? 0),
            'schools_match_rows_with_school_key' => $schoolTotals['total_cursistas'] === $rowsWithSchoolKey,
        ],
    ];
}

/**
 * @param string $path
 * @return bool
 */
function isIgnoredByRootGit(string $path): bool {
    $command = sprintf(
        'git check-ignore -q %s >/dev/null 2>&1; echo $?',
        escapeshellarg($path)
    );

    $result = trim((string) shell_exec($command));
    return $result === '0';
}

/**
 * @param string $raw
 * @return array<int, int>
 */
function parseSampleUserIds(string $raw): array {
    if ($raw === '') {
        return [];
    }

    $rawIds = explode(',', $raw);
    return array_values(array_unique(array_filter(array_map('intval', $rawIds))));
}
