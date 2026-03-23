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
    public function checkAuthenticatedPermission() {
        if (is_user_logged_in()) {
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
    public function checkOverviewPermission() {
        return $this->checkPanelCapability('can_view_overview');
    }

    /**
     * @return array|\WP_Error
     */
    public function checkStatesPermission() {
        return $this->checkPanelCapability('can_view_states');
    }

    /**
     * @return array|\WP_Error
     */
    public function checkSchoolsPermission() {
        return $this->checkPanelCapability('can_view_schools');
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
    private function checkPanelCapability($capabilityKey) {
        $authCheck = $this->checkAuthenticatedPermission();
        if ($authCheck !== true) {
            return $authCheck;
        }

        $scope = $this->scopeResolver->resolve(get_current_user_id());
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
}
