<?php
/**
 * Main Plugin Class
 *
 * Central plugin coordination, singleton pattern
 *
 * @package FortaleceePSE
 * @subpackage Plugin
 */

namespace FortaleceePSE\Core;

class Plugin {
    /**
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * @var array
     */
    private $config = [];

    /**
     * @var Utils\Logger|null
     */
    private $logger = null;

    /**
     * @var bool
     */
    private static $initialized = false;

    /**
     * Get singleton instance
     *
     * @return Plugin
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor (private for singleton)
     */
    private function __construct() {
        $this->loadConfig();
    }

    /**
     * Initialize plugin
     *
     * Hooks: plugins_loaded
     *
     * @return void
     */
    public function init() {
        // Prevent duplicate initialization
        if (self::$initialized) {
            error_log('FPSE: Plugin init() já foi executado, pulando inicialização duplicada');
            return;
        }
        self::$initialized = true;

        // Log plugin initialization (always log to help diagnose REST API issues)
        // IMPORTANTE: Não usar REST_REQUEST para decidir se registra rotas
        // As rotas DEVEM ser registradas sempre, independente de REST_REQUEST
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $isRestRequest = (defined('REST_REQUEST') && REST_REQUEST) || 
                             (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false);
            error_log('FPSE: Plugin init() executado | REST_REQUEST=' . ($isRestRequest ? 'true' : 'false') . ' | URI=' . $requestUri . ' | METHOD=' . $requestMethod);
        }

        // Load text domain
        load_plugin_textdomain('fpse-core', false, dirname(FPSE_CORE_BASENAME) . '/languages');

        // Initialize logger
        $this->logger = new Utils\Logger($this->getConfig('debug', []));

        // Initialize admin pages (only in admin)
        if (is_admin()) {
            $dashboardPage = new Admin\DashboardPage();
            $dashboardPage->init();
            
            $settingsPage = new Admin\SettingsPage();
            $settingsPage->init();
        }

        // Add CORS headers (before REST API init)
        add_action('rest_api_init', [Utils\CorsHeaders::class, 'addCorsHeaders'], 15);
        
        // Ensure CORS headers are sent even in error responses
        add_filter('rest_pre_serve_request', [Utils\CorsHeaders::class, 'addCorsHeadersToResponse'], 10, 4);

        // Register REST routes via hook (MUST be on rest_api_init, not directly in init)
        // WordPress requires REST routes to be registered on rest_api_init action
        // Priority 10 ensures routes are registered early, before other plugins
        // IMPORTANTE: Sempre registrar, independente de REST_REQUEST ou qualquer outra condição
        add_action('rest_api_init', [$this, 'registerRestRoutes'], 10);
        
        // Log when rest_api_init fires (for debugging)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('rest_api_init', function() {
                error_log('FPSE: Hook rest_api_init disparado!');
            }, 1);
        }

        // Register member types on every bp_init (they don't persist)
        // Note: Also can be triggered manually via admin settings page
        add_action('bp_init', [$this, 'registerMemberTypes'], 10);
        
        // Create xProfile fields when BuddyBoss is loaded (if flag is set)
        add_action('bp_init', [$this, 'createXProfileFields'], 15);
    }

    /**
     * Register REST routes
     *
     * Action: rest_api_init
     *
     * @return void
     */
    public function registerRestRoutes() {
        // Prevent duplicate registration
        static $registered = false;
        if ($registered) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FPSE: registerRestRoutes() já executado, pulando registro duplicado');
            }
            return;
        }
        $registered = true;

        // Log route registration (always log for debugging route issues)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('FPSE: registerRestRoutes() executado no hook rest_api_init');
        }

        // Verify REST API is available
        if (!function_exists('register_rest_route')) {
            error_log('FPSE: ERRO - register_rest_route() não está disponível. REST API pode não estar carregada.');
            return;
        }

        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FPSE: Criando RegistrationController...');
            }
            $registrationController = new REST\RegistrationController($this);
            $registrationController->registerRoutes();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FPSE: RegistrationController::registerRoutes() concluído');
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FPSE: Criando StatsController...');
            }
            $statsController = new REST\StatsController();
            $statsController->registerRoutes();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FPSE: StatsController::registerRoutes() concluído');
            }

            // Log successful registration summary
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('FPSE: Todas as rotas registradas com sucesso: /fpse/v1/register, /fpse/v1/nonce, /fpse/v1/registration/(?P<id>\\d+)');
            }

            // Schedule route verification for later (after REST API is fully initialized)
            // This prevents triggering other plugins' initialization too early
            add_action('wp_loaded', [$this, 'verifyRegisteredRoutes'], 999);
        } catch (\Exception $e) {
            // Log error (always log errors, not just in debug mode)
            if (function_exists('error_log')) {
                error_log('FPSE Core - ERRO FATAL em registerRestRoutes: ' . $e->getMessage());
                error_log('FPSE Core - Stack trace: ' . $e->getTraceAsString());
            }
            // Don't re-throw to avoid breaking WordPress REST API
            // Errors are logged but don't break the registration process
        }
    }

    /**
     * Plugin activation
     *
     * Creates database table and sets up initial configuration
     *
     * @return void
     */
    public function activate() {
        // Create events table
        $this->createEventTable();

        // Flush rewrite rules to ensure REST routes are available
        // This must be called after all route registrations
        flush_rewrite_rules(false);

        // Set up capabilities for admin
        $permissionService = new Services\PermissionService($this);
        foreach ($permissionService->getAdminRoles() as $role) {
            $permissionService->grantCapabilitiesToRole($role);
        }

        // Create user roles based on profiles
        $this->createProfileRoles();

        // Mark flag to create xProfile fields when BuddyBoss is loaded
        // (BuddyBoss may not be loaded during activation)
        update_option('fpse_create_xprofile_fields', true);

        // Note: BuddyBoss seeders (groups and member types) must be run manually
        // via Admin > FPSE > Configurações > "Criar Grupos Estaduais" button
        // This ensures BuddyBoss is fully loaded before creating resources

        // Log activation
        error_log('FPSE Core plugin activated');
    }

    /**
     * Plugin deactivation
     *
     * @return void
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Log deactivation (only if WP_DEBUG is enabled to avoid output during deactivation)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('FPSE Core plugin deactivated');
        }
    }

    /**
     * Create events table
     *
     * Called during plugin activation
     *
     * @return void
     */
    private function createEventTable() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'fpse_events';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id bigint(20) UNSIGNED DEFAULT 0,
            event varchar(100) NOT NULL,
            perfil varchar(100) DEFAULT '',
            estado varchar(2) DEFAULT '',
            metadata longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_id (user_id),
            KEY idx_event (event),
            KEY idx_estado (estado),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Log table creation (only if WP_DEBUG is enabled to avoid output during activation)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
                error_log("FPSE: Events table created or verified");
            }
        }
    }

    /**
     * Load all configuration files
     *
     * @return void
     */
    private function loadConfig() {
        $configDir = FPSE_CORE_PATH . 'config/';

        $configs = [
            'states' => 'states.php',
            'profiles' => 'profiles.php',
            'report_fields' => 'report_fields.php',
            'debug' => 'debug.php',
            'permissions' => 'permissions.php',
        ];

        foreach ($configs as $key => $file) {
            $path = $configDir . $file;
            if (file_exists($path)) {
                $this->config[$key] = require $path;
            } else {
                error_log("FPSE: Config file not found: $path");
                $this->config[$key] = [];
            }
        }
    }

    /**
     * Get configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     * @return mixed Configuration value
     */
    public function getConfig($key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    /**
     * Get logger instance
     *
     * @return Utils\Logger
     */
    public function getLogger() {
        if ($this->logger === null) {
            $this->logger = new Utils\Logger($this->getConfig('debug', []));
        }
        return $this->logger;
    }

    /**
     * Register BuddyBoss member types (runtime registration)
     *
     * Registers member types via bp_register_member_type() on every bp_init
     * This ensures they are available at runtime
     * 
     * Note: Terms are created via admin action (persistent)
     * This method just registers them for runtime use
     *
     * Action: bp_init (priority 10)
     *
     * @return void
     */
    public function registerMemberTypes() {
        // Only register if BuddyBoss is loaded
        if (!function_exists('bp_register_member_type')) {
            return;
        }

        $profiles = $this->getConfig('profiles', []);

        if (empty($profiles)) {
            return;
        }

        $seeder = new Seeders\MemberTypeSeeder($profiles);
        
        // Only register for runtime (terms should already exist in database)
        foreach ($profiles as $profileId => $profileData) {
            $memberType = \FortaleceePSE\Core\Seeders\MemberTypeSeeder::getMemberTypeForProfile($profileId);
            $label = $profileData['label'] ?? ucfirst(str_replace('-', ' ', $profileId));

            $args = [
                'labels' => [
                    'name' => $label,
                    'singular_name' => $label,
                ],
                'has_directory' => true,
                'directory_slug' => $memberType,
            ];

            // Register for runtime (idempotent)
            bp_register_member_type($memberType, $args);
        }
    }

    /**
     * Create user roles based on profile configuration
     *
     * Called during plugin activation
     * Creates a WordPress role for each profile defined in config/profiles.php
     *
     * @return void
     */
    private function createProfileRoles() {
        $roleCreator = new Utils\RoleCreator($this);
        $result = $roleCreator->createRolesFromProfiles();

        // Log only if WP_DEBUG is enabled to avoid output during activation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (!empty($result['created'])) {
                error_log(sprintf(
                    'FPSE: Created %d user roles: %s',
                    count($result['created']),
                    implode(', ', $result['created'])
                ));
            }

            if (!empty($result['updated'])) {
                error_log(sprintf(
                    'FPSE: Updated %d user roles: %s',
                    count($result['updated']),
                    implode(', ', $result['updated'])
                ));
            }
        }
    }

    /**
     * Create state groups (BuddyBoss)
     *
     * Called during plugin activation
     * Creates BuddyBoss groups for each Brazilian state
     *
     * @return void
     */
    private function createStateGroups() {
        $states = $this->getConfig('states', []);
        
        // Log only if WP_DEBUG is enabled to avoid output during activation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (empty($states)) {
                error_log('FPSE: Nenhum estado configurado para criar grupos');
            }
        }
        
        if (empty($states)) {
            return;
        }

        $seeder = new Seeders\StateGroupSeeder($states);
        $result = $seeder->seed();

        // Log only if WP_DEBUG is enabled to avoid output during activation
        if (defined('WP_DEBUG') && WP_DEBUG) {
            if (!empty($result['created'])) {
                error_log(sprintf(
                    'FPSE: Criados %d grupos estaduais: %s',
                    count($result['created']),
                    implode(', ', $result['created'])
                ));
            }

            if (!empty($result['updated'])) {
                error_log(sprintf(
                    'FPSE: Atualizados %d grupos estaduais: %s',
                    count($result['updated']),
                    implode(', ', $result['updated'])
                ));
            }

            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    error_log('FPSE: Erro ao criar grupo - ' . $error);
                }
            }
        }
    }

    /**
     * Create BuddyBoss xProfile fields
     *
     * Creates custom xProfile fields for storing registration form data
     *
     * @return void
     */
    /**
     * Create xProfile fields when BuddyBoss is loaded
     *
     * Action: bp_init (priority 15)
     * Only creates fields if flag is set (from activation or manual trigger)
     *
     * @return void
     */
    public function createXProfileFields() {
        // Only create if flag is set (prevents creating on every bp_init)
        if (!get_option('fpse_create_xprofile_fields')) {
            return;
        }

        if (!function_exists('xprofile_insert_field_group')) {
            error_log('FPSE: BuddyBoss xProfile não está disponível. Campos serão criados quando BuddyBoss estiver ativo.');
            return;
        }

        $seeder = new Seeders\XProfileFieldSeeder();
        $result = $seeder->seed();

        if (!empty($result['created'])) {
            error_log(sprintf(
                'FPSE: Criados %d campos xProfile: %s',
                count($result['created']),
                implode(', ', $result['created'])
            ));
        }

        if (!empty($result['updated'])) {
            error_log(sprintf(
                'FPSE: Atualizados %d campos xProfile: %s',
                count($result['updated']),
                implode(', ', $result['updated'])
            ));
        }

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                error_log('FPSE: Erro ao criar campo xProfile - ' . $error);
            }
        }

        // Clear flag
        delete_option('fpse_create_xprofile_fields');
    }

    /**
     * Verify registered REST routes (for debugging)
     *
     * Called on wp_loaded hook (after REST API is fully initialized)
     * This prevents triggering other plugins' initialization too early
     *
     * Action: wp_loaded (priority 999)
     *
     * @return void
     */
    public function verifyRegisteredRoutes() {
        // Verify routes (only log in debug mode to avoid spam)
        // IMPORTANTE: Este método sempre executa, mas só loga se WP_DEBUG estiver ativo
        try {
            // Get REST server instance (safe to call now)
            if (!class_exists('WP_REST_Server')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('FPSE: ERRO - WP_REST_Server não está disponível');
                }
                return;
            }

            $wp_rest_server = rest_get_server();
            if (!$wp_rest_server) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('FPSE: ERRO - WP_REST_Server não disponível para verificação');
                }
                return;
            }

            $routes = $wp_rest_server->get_routes();
            $fpseRoutes = array_filter(array_keys($routes), function($route) {
                return strpos($route, '/fpse/v1/') !== false;
            });

            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (!empty($fpseRoutes)) {
                    error_log('FPSE: ✅ Rotas registradas com sucesso: ' . implode(', ', $fpseRoutes));
                } else {
                    error_log('FPSE: ❌ ERRO CRÍTICO - Nenhuma rota fpse encontrada após registro!');
                    error_log('FPSE: Total de rotas REST disponíveis: ' . count($routes));
                    // Log first 10 routes for debugging
                    $sampleRoutes = array_slice(array_keys($routes), 0, 10);
                    error_log('FPSE: Exemplo de rotas disponíveis: ' . implode(', ', $sampleRoutes));
                }
            }
        } catch (\Exception $e) {
            // Always log errors, even if WP_DEBUG is off
            if (function_exists('error_log')) {
                error_log('FPSE Core - ERRO em verifyRegisteredRoutes: ' . $e->getMessage());
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('FPSE Core - Stack trace: ' . $e->getTraceAsString());
                }
            }
        }
    }

    /**
     * Prevent cloning
     */
    private function __clone() {
    }

    /**
     * Prevent unserializing
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}
