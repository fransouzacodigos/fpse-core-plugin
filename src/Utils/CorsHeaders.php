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
        
        // Prioridade 3: Se ainda estiver vazio, usar padrões de desenvolvimento + produção
        if (empty($allowedOrigins)) {
            $allowedOrigins = [
                'http://localhost:5173',
                'http://localhost:3000',
                'http://127.0.0.1:5173',
                'http://127.0.0.1:3000',
                'https://form-fpse.vercel.app', // Frontend React na Vercel
            ];
        }
        
        // Get origin from request
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        $path = $_SERVER['REQUEST_URI'] ?? '';
        
        // Check if origin is allowed
        $isOriginAllowed = !empty($origin) && in_array($origin, $allowedOrigins, true);
        
        // Log temporário para diagnóstico (apenas se WP_DEBUG ativo)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'FPSE CORS [rest_api_init]: Method=%s | Path=%s | Origin=%s | Allowed=%s',
                $method,
                $path,
                $origin ?: '(none)',
                $isOriginAllowed ? 'YES' : 'NO'
            ));
        }
        
        // Log blocked origins for debugging (only if WP_DEBUG is enabled)
        if (!empty($origin) && !$isOriginAllowed && defined('WP_DEBUG') && WP_DEBUG) {
            error_log("FPSE CORS: Origin bloqueada - $origin (não está na lista de permitidas)");
        }
        
        // SEMPRE enviar headers CORS se origin estiver permitida
        // IMPORTANTE: Headers devem ser enviados ANTES de qualquer output
        if ($isOriginAllowed) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
            header('Access-Control-Max-Age: 3600');
        }
        
        // Handle preflight OPTIONS request
        // IMPORTANTE: Responder preflight mesmo se origin não estiver permitida
        // (para evitar bloqueio silencioso - navegador vai bloquear, mas retorna 200)
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            // Se origin permitida, já enviamos headers acima
            // Se não permitida, ainda assim responder 200 (mas sem CORS headers)
            // O navegador vai bloquear, mas pelo menos não retorna erro 405
            status_header(200);
            exit(0);
        }
    }

    /**
     * Add CORS headers to REST API responses (including errors)
     * 
     * Filter: rest_pre_serve_request
     * 
     * This ensures CORS headers are sent even when WordPress returns error responses
     * 
     * @param bool $served Whether the request has been served
     * @param \WP_REST_Response $result Result to send to the client
     * @param \WP_REST_Request $request Request used to generate the response
     * @param \WP_REST_Server $server Server instance
     * @return bool
     */
    public static function addCorsHeadersToResponse($served, $result, $request, $server) {
        // Only process if this is an FPSE endpoint
        $route = $request->get_route();
        if (strpos($route, '/fpse/v1/') === false) {
            return $served;
        }

        // Get allowed origins (same logic as addCorsHeaders)
        $allowedOrigins = SettingsPage::getCorsOrigins();
        
        if (empty($allowedOrigins)) {
            $plugin = \FortaleceePSE\Core\Plugin::getInstance();
            $config = $plugin->getConfig('permissions', []);
            $allowedOrigins = $config['cors_allowed_origins'] ?? [];
        }
        
        if (empty($allowedOrigins)) {
            $allowedOrigins = [
                'http://localhost:5173',
                'http://localhost:3000',
                'http://127.0.0.1:5173',
                'http://127.0.0.1:3000',
                'https://form-fpse.vercel.app',
            ];
        }
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        $isOriginAllowed = !empty($origin) && in_array($origin, $allowedOrigins, true);
        
        // Log temporário para diagnóstico (apenas se WP_DEBUG ativo)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'FPSE CORS: Route=%s | Method=%s | Origin=%s | Allowed=%s',
                $route,
                $method,
                $origin ?: '(none)',
                $isOriginAllowed ? 'YES' : 'NO'
            ));
        }
        
        // Add CORS headers if origin is allowed
        // IMPORTANTE: Headers devem ser enviados ANTES de qualquer output
        if ($isOriginAllowed) {
            header("Access-Control-Allow-Origin: $origin");
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-WP-Nonce');
            header('Access-Control-Max-Age: 3600');
        }
        
        return $served;
    }
}
