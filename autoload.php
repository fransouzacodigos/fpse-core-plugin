<?php
/**
 * FPSE Core - Manual Autoloader (sem Composer)
 * 
 * Use este arquivo se o servidor não tiver Composer instalado.
 * Este é um autoloader PSR-4 simples que mapeia namespaces para diretórios.
 * 
 * Requer: PHP 5.4+
 */

// Definir o prefixo do namespace
define('FPSE_NAMESPACE_PREFIX', 'FortaleceePSE\\Core');
define('FPSE_NAMESPACE_SEPARATOR', '\\');
define('FPSE_BASE_PATH', dirname(__FILE__));

/**
 * Registra o autoloader
 */
spl_autoload_register(function ($class) {
    // Verificar se a classe começa com nosso namespace
    $prefix = FPSE_NAMESPACE_PREFIX . FPSE_NAMESPACE_SEPARATOR;
    $len = strlen($prefix);
    
    if (strncmp($prefix, $class, $len) !== 0) {
        return false;
    }
    
    // Remove o namespace prefix da classe
    $relative_class = substr($class, $len);
    
    // Converter namespace em caminho de arquivo
    $file = FPSE_BASE_PATH . '/src/' . str_replace(FPSE_NAMESPACE_SEPARATOR, '/', $relative_class) . '.php';
    
    // Se o arquivo existe, carregá-lo
    if (file_exists($file)) {
        require $file;
        return true;
    }
    
    return false;
});

/**
 * Função auxiliar para obter o plugin
 */
if (!function_exists('fpse_get_plugin')) {
    function fpse_get_plugin() {
        require_once FPSE_BASE_PATH . '/src/Plugin.php';
        return \FortaleceePSE\Core\Plugin::getInstance();
    }
}

/**
 * Exemplos de uso:
 * 
 * // Obter o plugin
 * $plugin = fpse_get_plugin();
 * 
 * // Usar serviços
 * $user_service = new \FortaleceePSE\Core\Services\UserService($plugin);
 * $profile_resolver = new \FortaleceePSE\Core\Services\ProfileResolver($plugin);
 * $permission_service = new \FortaleceePSE\Core\Services\PermissionService($plugin);
 * $event_recorder = new \FortaleceePSE\Core\Services\EventRecorder($plugin);
 * $reports = new \FortaleceePSE\Core\Reports\ReportRegistry($plugin);
 * 
 * // Usar segurança
 * $nonce = new \FortaleceePSE\Core\Security\NonceMiddleware($plugin);
 * $rate_limit = new \FortaleceePSE\Core\Security\RateLimit($plugin);
 * 
 * // Usar logger
 * $logger = $plugin->getLogger();
 * $logger->info('Mensagem de teste');
 */
