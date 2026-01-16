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

// Define plugin constants
define('FPSE_CORE_VERSION', '1.0.0');
define('FPSE_CORE_PATH', plugin_dir_path(__FILE__));
define('FPSE_CORE_URL', plugin_dir_url(__FILE__));
define('FPSE_CORE_BASENAME', plugin_basename(__FILE__));

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

// Register activation hook
register_activation_hook(__FILE__, [$plugin, 'activate']);

// Register deactivation hook
register_deactivation_hook(__FILE__, [$plugin, 'deactivate']);

// Initialize plugin on 'plugins_loaded' action
add_action('plugins_loaded', [$plugin, 'init']);
