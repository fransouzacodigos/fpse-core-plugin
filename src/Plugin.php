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

        // Register REST routes
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Register member types on every bp_init (they don't persist)
        // Note: Also can be triggered manually via admin settings page
        add_action('bp_init', [$this, 'registerMemberTypes'], 10);
    }

    /**
     * Register REST routes
     *
     * Action: rest_api_init
     *
     * @return void
     */
    public function registerRestRoutes() {
        $controller = new REST\RegistrationController($this);
        $controller->registerRoutes();
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

        // Flush rewrite rules
        flush_rewrite_rules();

        // Set up capabilities for admin
        $permissionService = new Services\PermissionService($this);
        foreach ($permissionService->getAdminRoles() as $role) {
            $permissionService->grantCapabilitiesToRole($role);
        }

        // Create user roles based on profiles
        $this->createProfileRoles();

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

        // Log deactivation
        error_log('FPSE Core plugin deactivated');
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

        // Log table creation
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
            error_log("FPSE: Events table created or verified");
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
        
        if (empty($states)) {
            error_log('FPSE: Nenhum estado configurado para criar grupos');
            return;
        }

        $seeder = new Seeders\StateGroupSeeder($states);
        $result = $seeder->seed();

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
