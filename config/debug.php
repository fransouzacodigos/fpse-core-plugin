<?php
/**
 * Debug and Logging Configuration
 *
 * Configure debugging, logging, and event tracking
 *
 * @package FortaleceePSE
 * @subpackage Config
 */

return [
    'enable_debug' => defined('WP_DEBUG') ? WP_DEBUG : false,
    'log_file' => WP_CONTENT_DIR . '/fpse-core.log',
    'log_levels' => ['error', 'warning', 'info'],
    'track_events' => [
        'registered',
        'user_updated',
        'validation_error',
        'profile_assigned',
        'state_assigned',
    ],
    'mask_sensitive_fields' => [
        'cpf',
        'email',
        'email_login',
        'matricula',
        'telefone',
    ],
];
