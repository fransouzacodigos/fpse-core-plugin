<?php
/**
 * Permissions Configuration
 *
 * Define capabilities, roles, endpoint permissions, and rate limits
 *
 * @package FortaleceePSE
 * @subpackage Config
 */

return [
    'capabilities' => [
        'manage_fpse_registrations',
        'view_fpse_registrations',
        'view_fpse_reports',
        'export_fpse_reports',
    ],
    'admin_roles' => [
        'administrator',
        'fpse_admin',
    ],
    'endpoint_permissions' => [
        'register' => 'public',
        'nonce' => 'public',
        'registration' => 'manage_fpse_registrations',
    ],
    'rate_limits' => [
        'register' => defined('WP_DEBUG') && WP_DEBUG ? 1000 : 10,  // 1000 em dev, 10 em produção
        'default' => 100,     // 100 requests per hour
    ],
    // CORS: Origins permitidas para desenvolvimento e produção
    // IMPORTANTE: Em produção, liste apenas domínios específicos
    'cors_allowed_origins' => [
        // Desenvolvimento local
        'http://localhost:5173',
        'http://localhost:3000',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:3000',
        // Produção - React na Vercel
        'https://cadastro.fortalecepse.com.br',
        // WordPress (opcional, se React for embedado)
        // 'https://mf3.fortalecepse.com.br',
        // Domínio antigo (manter por compatibilidade se necessário)
        // 'https://avab.fortalecepse.com.br',
    ],
];
