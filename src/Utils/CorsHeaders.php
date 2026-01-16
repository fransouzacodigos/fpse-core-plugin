<?php
/**
 * CORS Headers Utility
 *
 * Utility class for handling CORS headers in WordPress REST API
 * Configure allowed origins in config/permissions.php
 *
 * @package FortaleceePSE
 * @subpackage Utils
 */

namespace FortaleceePSE\Core\Utils;

use FortaleceePSE\Core\Admin\SettingsPage;

class CorsHeaders {
    /**
     * Add CORS headers to REST API responses
     *
     * Action: rest_api_init (priority 15)
     *
     * Gets allowed origins from WordPress admin settings (database)
     * Falls back to config file if settings not configured
     *
     * @return void
     */
    public static function addCorsHeaders() {
        // Prioridade 1: Buscar da configuração do admin (banco de dados)
        $allowedOrigins = SettingsPage::getCorsOrigins();
        
        // Prioridade 2: Se admin não configurou, buscar do config file (fallback)
        if (empty($allowedOrigins)) {
            $plugin = \FortaleceePSE\Core\Plugin::getInstance();
            $config = $plugin->getConfig('permissions', []);
            $allowedOrigins = $config['cors_allowed_origins'] ?? [];
        }
        
        // Prioridade 3: Se ainda estiver vazio, usar padrões de desenvolvimento
        if (empty($allowedOrigins)) {
            $allowedOrigins = [
                'http://localhost:5173',
                'http://localhost:3000',
                'http://127.0.0.1:5173',
                'http://127.0.0.1:3000',
            ];
        }
        
        // Get origin from request
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Check if origin is allowed
        if (!empty($origin) && in_array($origin, $allowedOrigins, true)) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
            header('Access-Control-Max-Age: 3600');
        }
        
        // Handle preflight OPTIONS request
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit(0);
        }
    }
}
