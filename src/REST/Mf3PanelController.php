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
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function handleGetScope($request) {
        return new \WP_REST_Response([
            'scope' => $this->scopeResolver->resolve(get_current_user_id()),
            'features' => [
                'overview' => true,
                'states' => true,
                'schools' => true,
                'users' => false,
                'attention' => false,
                'reason_users_attention_unavailable' => 'rbac_individual_visibility_not_canonical_yet',
            ],
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
        return new \WP_REST_Response(
            $this->dataService->getSchools(get_current_user_id()),
            200
        );
    }

    /**
     * Gate panel endpoints by resolved scope capabilities.
     *
     * @param string $capabilityKey
     * @return true|\WP_Error
     */
    private function checkPanelCapability($capabilityKey, $request = null) {
        $authCheck = $this->checkAuthenticatedPermission($request);
        if ($authCheck !== true) {
            return $authCheck;
        }

        $scope = $this->scopeResolver->resolve($this->getAuthenticatedPanelUserId($request));
        if (!empty($scope[$capabilityKey])) {
            return true;
        }

        return new \WP_Error(
            'fpse_mf3_scope_denied',
            'O perfil autenticado nao possui escopo para este recorte do painel MF3.',
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
}
