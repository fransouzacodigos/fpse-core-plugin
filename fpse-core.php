<?php
/**
 * Plugin Name: Fortalece PSE Core
 * Plugin URI: https://fortalecepse.org
 * Description: Core plugin for Fortalece PSE institutional registration and event tracking
 * Version: 1.0.0
 * Author: Fortalece Team
 * Author URI: https://fortalecepse.org
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: fpse-core
 * Domain Path: /languages
 * Requires at least: 5.9
 * Requires PHP: 8.0
 *
 * @package FortaleceePSE
 */

// Prevent direct file access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants (only if not already defined to prevent conflicts)
if (!defined('FPSE_CORE_VERSION')) {
    define('FPSE_CORE_VERSION', '1.0.0');
}
if (!defined('FPSE_CORE_PATH')) {
    define('FPSE_CORE_PATH', plugin_dir_path(__FILE__));
}
if (!defined('FPSE_CORE_URL')) {
    define('FPSE_CORE_URL', plugin_dir_url(__FILE__));
}
if (!defined('FPSE_CORE_BASENAME')) {
    define('FPSE_CORE_BASENAME', plugin_basename(__FILE__));
}

// Require autoloader (com fallback se Composer não estiver disponível)
if (file_exists(FPSE_CORE_PATH . 'vendor/autoload.php')) {
    // Priorizar Composer se estiver disponível
    require_once FPSE_CORE_PATH . 'vendor/autoload.php';
} else {
    // Fallback para autoload manual (sem Composer)
    require_once FPSE_CORE_PATH . 'autoload.php';
}

// Get main plugin instance
$plugin = \FortaleceePSE\Core\Plugin::getInstance();

// Log plugin file loading (very early, before any hooks)
$uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
if (strpos($uri, '/wp-json/') !== false) {
    error_log('FPSE: Plugin file carregado para requisição REST | URI=' . $uri);
}

// Register activation hook
register_activation_hook(__FILE__, [$plugin, 'activate']);

// Register deactivation hook
register_deactivation_hook(__FILE__, [$plugin, 'deactivate']);

// Log early to catch REST requests (before plugins_loaded) - apenas para debug
// IMPORTANTE: Este log não afeta o registro de rotas
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('init', function() {
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
        if (strpos($uri, '/wp-json/') !== false) {
            error_log('FPSE: Requisição REST detectada no hook init | URI=' . $uri . ' | METHOD=' . $method);
        }
    }, 1);
}

// Initialize plugin on 'plugins_loaded' action (priority 10 to ensure early initialization)
// This ensures the plugin is initialized before rest_api_init fires
add_action('plugins_loaded', [$plugin, 'init'], 10);

// Also register routes directly on rest_api_init as a fallback
// This ensures routes are registered even if plugins_loaded hasn't fired yet
// IMPORTANTE: Sempre executar, independente de qualquer condição
add_action('rest_api_init', function() use ($plugin) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('FPSE: rest_api_init disparado - verificando se plugin foi inicializado');
    }
    // Force initialization if not already done
    if (!did_action('plugins_loaded')) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('FPSE: plugins_loaded ainda não foi disparado, inicializando plugin agora');
        }
        $plugin->init();
    }
}, 5);
