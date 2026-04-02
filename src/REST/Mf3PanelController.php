<?php
/**
 * REST controller for the MF3 panel MVP.
 *
 * Exposes only the server-side-safe contract currently supported by the
 * codebase: scope resolution plus aggregates by overview, state and school.
 *
 * @package FortaleceePSE
 * @subpackage REST
 */

namespace FortaleceePSE\Core\REST;

use FortaleceePSE\Core\Plugin;
use FortaleceePSE\Core\Services\Mf3PanelDataService;
use FortaleceePSE\Core\Services\Mf3PanelScopeResolver;

class Mf3PanelController {
    /**
     * @var Plugin
     */
    private $plugin;

    /**
     * @var Mf3PanelScopeResolver
     */
    private $scopeResolver;

    /**
     * @var Mf3PanelDataService
     */
    private $dataService;

    /**
     * @param Plugin $plugin
     */
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
        $this->scopeResolver = new Mf3PanelScopeResolver($plugin);
        $this->dataService = new Mf3PanelDataService($plugin);
    }

    /**
     * Register MF3 panel routes.
     *
     * @return void
     */
    public function registerRoutes() {
        register_rest_route('fpse/v1', '/mf3/panel/scope', [
            'methods' => 'GET',
            'callback' => [$this, 'handleGetScope'],
            'permission_callback' => [$this, 'checkAuthenticatedPermission'],
        ]);

        register_rest_route('fpse/v1', '/mf3/panel/overview', [
            'methods' => 'GET',
            'callback' => [$this, 'handleGetOverview'],
            'permission_callback' => [$this, 'checkOverviewPermission'],
        ]);

        register_rest_route('fpse/v1', '/mf3/panel/states', [
            'methods' => 'GET',
            'callback' => [$this, 'handleGetStates'],
            'permission_callback' => [$this, 'checkStatesPermission'],
        ]);

        register_rest_route('fpse/v1', '/mf3/panel/schools', [
            'methods' => 'GET',
            'callback' => [$this, 'handleGetSchools'],
            'permission_callback' => [$this, 'checkSchoolsPermission'],
            'args' => $this->getSchoolsCollectionParams(),
        ]);

        register_rest_route('fpse/v1', '/mf3/panel/users', [
            'methods' => 'GET',
            'callback' => [$this, 'handleGetUsers'],
            'permission_callback' => [$this, 'checkUsersPermission'],
            'args' => $this->getUsersCollectionParams(),
        ]);
    }

    /**
     * @return array|\WP_Error
     */
    public function checkAuthenticatedPermission($request = null) {
        if ($this->getAuthenticatedPanelUserId($request) > 0) {
            return true;
        }

        return new \WP_Error(
            'fpse_mf3_auth_required',
            'Autenticação obrigatória para acessar o painel MF3.',
            ['status' => 401]
        );
    }

    /**
     * @return array|\WP_Error
     */
    public function checkOverviewPermission($request = null) {
        return $this->checkPanelCapability('can_view_overview', $request);
    }

    /**
     * @return array|\WP_Error
     */
    public function checkStatesPermission($request = null) {
        return $this->checkPanelCapability('can_view_states', $request);
    }

    /**
     * @return array|\WP_Error
     */
    public function checkSchoolsPermission($request = null) {
        return $this->checkPanelCapability('can_view_schools', $request);
    }

    /**
     * @return array|\WP_Error
     */
    public function checkUsersPermission($request = null) {
        return $this->checkPanelCapability(
            'can_view_users',
            $request,
            'fpse_mf3_users_scope_denied',
            'O perfil autenticado nao possui permissao institucional para visualizar participantes neste painel.'
        );
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleGetScope($request) {
        $scope = $this->scopeResolver->resolve(get_current_user_id());
        $features = (array) ($scope['features'] ?? []);
        $features['reason_users_unavailable'] = !$features['users']
            ? 'rbac_individual_visibility_not_enabled_for_scope'
            : null;
        $features['reason_attention_unavailable'] = !$features['attention']
            ? 'pedagogical_attention_reserved_for_future_phase'
            : null;
        $features['reason_users_attention_unavailable'] = (!$features['users'] || !$features['attention'])
            ? 'individual_and_attention_layers_have_distinct_release_tracks'
            : null;

        return new \WP_REST_Response([
            'scope' => $scope,
            'features' => $features,
        ], 200);
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleGetOverview($request) {
        return new \WP_REST_Response(
            $this->dataService->getOverview(get_current_user_id()),
            200
        );
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleGetStates($request) {
        return new \WP_REST_Response(
            $this->dataService->getStates(get_current_user_id()),
            200
        );
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleGetSchools($request) {
        $params = $this->getSchoolsRequestParams($request);

        if ($params['format'] === 'csv') {
            $exportCheck = $this->checkPanelCapability(
                'can_export_aggregates',
                $request,
                'fpse_mf3_aggregates_export_denied',
                'O perfil autenticado nao possui permissao institucional para exportar agregados deste painel.'
            );
            if ($exportCheck !== true) {
                return $exportCheck;
            }

            $csv = $this->dataService->getSchoolsCsv($this->getAuthenticatedPanelUserId($request), $params);
            $filename = sprintf('mf3-schools-%s.csv', gmdate('Y-m-d'));

            add_filter('rest_pre_serve_request', [$this, 'serveCsvResponse'], 10, 4);

            $response = new \WP_REST_Response($csv, 200);
            $response->header('Content-Type', 'text/csv; charset=utf-8');
            $response->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            return $response;
        }

        return new \WP_REST_Response(
            $this->dataService->getSchools($this->getAuthenticatedPanelUserId($request), $params),
            200
        );
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function handleGetUsers($request) {
        $params = $this->getUsersRequestParams($request);

        if ($params['format'] === 'csv') {
            $exportCheck = $this->checkPanelCapability(
                'can_export_users',
                $request,
                'fpse_mf3_users_export_denied',
                'O perfil autenticado nao possui permissao institucional para exportar participantes neste painel.'
            );
            if ($exportCheck !== true) {
                return $exportCheck;
            }

            $csv = $this->dataService->getUsersCsv($this->getAuthenticatedPanelUserId($request), $params);
            $filename = sprintf('mf3-users-%s.csv', gmdate('Y-m-d'));

            add_filter('rest_pre_serve_request', [$this, 'serveCsvResponse'], 10, 4);

            $response = new \WP_REST_Response($csv, 200);
            $response->header('Content-Type', 'text/csv; charset=utf-8');
            $response->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            return $response;
        }

        return new \WP_REST_Response(
            $this->dataService->getUsers($this->getAuthenticatedPanelUserId($request), $params),
            200
        );
    }

    /**
     * Print CSV responses without JSON encoding.
     *
     * @param bool $served
     * @param \WP_HTTP_Response $result
     * @param \WP_REST_Request $request
     * @param \WP_REST_Server $server
     * @return bool
     */
    public function serveCsvResponse($served, $result, $request, $server) {
        if (!$request instanceof \WP_REST_Request) {
            return $served;
        }

        if (!in_array($request->get_route(), ['/fpse/v1/mf3/panel/schools', '/fpse/v1/mf3/panel/users'], true)) {
            return $served;
        }

        if (($request->get_param('format') ?? '') !== 'csv') {
            return $served;
        }

        echo (string) $result->get_data();
        return true;
    }

    /**
     * Gate panel endpoints by resolved scope capabilities.
     *
     * @param string $capabilityKey
     * @return true|\WP_Error
     */
    private function checkPanelCapability(
        $capabilityKey,
        $request = null,
        $errorCode = 'fpse_mf3_scope_denied',
        $errorMessage = 'O perfil autenticado nao possui escopo para este recorte do painel MF3.'
    ) {
        $authCheck = $this->checkAuthenticatedPermission($request);
        if ($authCheck !== true) {
            return $authCheck;
        }

        $scope = $this->scopeResolver->resolve($this->getAuthenticatedPanelUserId($request));
        if (!empty($scope[$capabilityKey])) {
            return true;
        }

        return new \WP_Error(
            $errorCode,
            $errorMessage,
            [
                'status' => 403,
                'scope_class' => $scope['scope_class'] ?? 'none',
                'scope_reason' => $scope['scope_reason'] ?? 'unknown',
            ]
        );
    }

    /**
     * Resolve the authenticated user for same-domain read-only panel routes.
     *
     * WordPress REST cookie auth normally requires X-WP-Nonce and may zero the
     * current user for requests without it. For same-domain GET requests to the
     * protected MF3 panel, we restore the user from the logged_in cookie only.
     *
     * @param \WP_REST_Request|null $request
     * @return int
     */
    private function getAuthenticatedPanelUserId($request = null) {
        $currentUserId = get_current_user_id();
        if ($currentUserId > 0) {
            return $currentUserId;
        }

        if (!$this->isSameDomainReadOnlyPanelRequest($request)) {
            return 0;
        }

        if (!function_exists('wp_validate_auth_cookie') || !function_exists('wp_set_current_user')) {
            return 0;
        }

        $userId = (int) wp_validate_auth_cookie('', 'logged_in');
        if ($userId <= 0) {
            $userId = (int) wp_validate_auth_cookie('', 'secure_auth');
        }

        if ($userId <= 0) {
            $userId = (int) wp_validate_auth_cookie('', 'auth');
        }

        if ($userId > 0) {
            wp_set_current_user($userId);
            return get_current_user_id();
        }

        return 0;
    }

    /**
     * Limit cookie fallback to same-domain GET requests for panel routes.
     *
     * @param \WP_REST_Request|null $request
     * @return bool
     */
    private function isSameDomainReadOnlyPanelRequest($request = null) {
        $method = $request && method_exists($request, 'get_method')
            ? strtoupper((string) $request->get_method())
            : strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        if ($method !== 'GET') {
            return false;
        }

        $route = $request && method_exists($request, 'get_route')
            ? (string) $request->get_route()
            : (string) ($_SERVER['REQUEST_URI'] ?? '');

        if (strpos($route, '/fpse/v1/mf3/panel/') === false) {
            return false;
        }

        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin === '') {
            return true;
        }

        $originHost = parse_url($origin, PHP_URL_HOST);
        $siteHost = parse_url(home_url('/'), PHP_URL_HOST);

        if (!is_string($originHost) || !is_string($siteHost) || $originHost === '' || $siteHost === '') {
            return false;
        }

        return strtolower($originHost) === strtolower($siteHost);
    }

    /**
     * @return array
     */
    private function getSchoolsCollectionParams() {
        return [
            'page' => [
                'description' => 'Pagina atual da listagem agregada de escolas.',
                'type' => 'integer',
                'default' => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'description' => 'Quantidade de escolas agregadas por pagina.',
                'type' => 'integer',
                'default' => 10,
                'sanitize_callback' => 'absint',
            ],
            'search' => [
                'description' => 'Busca por escola, municipio ou INEP.',
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'uf' => [
                'description' => 'Filtro por UF.',
                'type' => 'string',
                'default' => 'all',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'rede' => [
                'description' => 'Filtro por rede de ensino.',
                'type' => 'string',
                'default' => 'all',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'inep_mode' => [
                'description' => 'Filtro por disponibilidade de INEP.',
                'type' => 'string',
                'default' => 'all',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'sort_by' => [
                'description' => 'Campo de ordenacao da listagem agregada.',
                'type' => 'string',
                'default' => 'total_cursistas',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'sort_dir' => [
                'description' => 'Direcao da ordenacao.',
                'type' => 'string',
                'default' => 'desc',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'format' => [
                'description' => 'Formato de retorno da rota.',
                'type' => 'string',
                'default' => 'json',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    /**
     * @param \WP_REST_Request $request
     * @return array
     */
    private function getSchoolsRequestParams($request) {
        return [
            'page' => (int) $request->get_param('page'),
            'per_page' => (int) $request->get_param('per_page'),
            'search' => (string) $request->get_param('search'),
            'uf' => (string) $request->get_param('uf'),
            'rede' => (string) $request->get_param('rede'),
            'inep_mode' => (string) $request->get_param('inep_mode'),
            'sort_by' => (string) $request->get_param('sort_by'),
            'sort_dir' => (string) $request->get_param('sort_dir'),
            'format' => (string) $request->get_param('format'),
        ];
    }

    /**
     * @return array
     */
    private function getUsersCollectionParams() {
        return [
            'page' => [
                'description' => 'Pagina atual da listagem individual de participantes.',
                'type' => 'integer',
                'default' => 1,
                'sanitize_callback' => 'absint',
            ],
            'per_page' => [
                'description' => 'Quantidade de participantes por pagina.',
                'type' => 'integer',
                'default' => 25,
                'sanitize_callback' => 'absint',
            ],
            'search' => [
                'description' => 'Busca textual por nome, e-mail, municipio ou escola.',
                'type' => 'string',
                'default' => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'uf' => [
                'description' => 'Filtro por UF.',
                'type' => 'string',
                'default' => 'all',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'municipio' => [
                'description' => 'Filtro por municipio.',
                'type' => 'string',
                'default' => 'all',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'escola' => [
                'description' => 'Filtro por escola.',
                'type' => 'string',
                'default' => 'all',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'perfil' => [
                'description' => 'Filtro por perfil de cadastro.',
                'type' => 'string',
                'default' => 'all',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'sort_by' => [
                'description' => 'Campo de ordenacao da listagem individual.',
                'type' => 'string',
                'default' => 'nome',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'sort_dir' => [
                'description' => 'Direcao da ordenacao.',
                'type' => 'string',
                'default' => 'asc',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'format' => [
                'description' => 'Formato de retorno da rota.',
                'type' => 'string',
                'default' => 'json',
                'sanitize_callback' => 'sanitize_text_field',
            ],
        ];
    }

    /**
     * @param \WP_REST_Request $request
     * @return array
     */
    private function getUsersRequestParams($request) {
        return [
            'page' => (int) $request->get_param('page'),
            'per_page' => (int) $request->get_param('per_page'),
            'search' => (string) $request->get_param('search'),
            'uf' => (string) $request->get_param('uf'),
            'municipio' => (string) $request->get_param('municipio'),
            'escola' => (string) $request->get_param('escola'),
            'perfil' => (string) $request->get_param('perfil'),
            'sort_by' => (string) $request->get_param('sort_by'),
            'sort_dir' => (string) $request->get_param('sort_dir'),
            'format' => (string) $request->get_param('format'),
        ];
    }
}
