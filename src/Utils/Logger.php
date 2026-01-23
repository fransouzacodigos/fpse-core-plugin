<?php
/**
 * Unified logging system for payload contract alignment
 * Requirements 4.3, 4.4, 4.5: Backend logging for payload reception and validation
 */

namespace FortaleceePSE\Core\Utils;

class Logger {
    private string $log_file;
    private string $log_level;
    
    /**
     * Constructor
     * 
     * @param string $log_file Path to log file
     * @param string $log_level Minimum log level (debug, info, warn, error)
     */
    public function __construct(string $log_file = '', string $log_level = 'info') {
        if (empty($log_file)) {
            // Default to WordPress content directory
            $upload_dir = wp_upload_dir();
            $log_dir = $upload_dir['basedir'] . '/fpse-logs';
            
            // Create log directory if it doesn't exist
            if (!file_exists($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            
            $this->log_file = $log_dir . '/fpse-' . date('Y-m-d') . '.log';
        } else {
            $this->log_file = $log_file;
        }
        
        $this->log_level = $log_level;
    }
    
    /**
     * Logs payload reception
     * Requirement 4.3: Log received content and etapaAtual
     */
    public function log_payload_received(array $payload): void {
        $this->info('RestBridge', 'Payload recebido', [
            'etapaAtual' => $payload['etapaAtual'] ?? null,
            'fields' => isset($payload['data']) ? array_keys($payload['data']) : [],
            'payload' => $payload
        ]);
    }
    
    /**
     * Logs validation process
     * Requirement 4.4: Log required fields checked and validation results
     */
    public function log_validation(int $step, array $required_fields, array $received_fields, bool $valid, array $errors = []): void {
        $this->info('StepValidator', 'Validando etapa', [
            'etapa' => $step,
            'requiredFields' => $required_fields,
            'receivedFields' => $received_fields,
            'valid' => $valid,
            'errors' => $errors
        ]);
    }
    
    /**
     * Logs validation errors
     * Requirement 4.5: Log specific fields that failed validation
     */
    public function log_validation_error(int $step, array $errors, array $payload): void {
        $this->error('Validation', 'Validação falhou', [
            'etapa' => $step,
            'errors' => $errors,
            'payload' => $payload
        ]);
    }
    
    /**
     * Debug level logging
     */
    public function debug(string $component, string $message, array $data = []): void {
        $this->log('debug', $component, $message, $data);
    }
    
    /**
     * Info level logging
     */
    public function info(string $component, string $message, array $data = []): void {
        $this->log('info', $component, $message, $data);
    }
    
    /**
     * Warning level logging
     */
    public function warn(string $component, string $message, array $data = []): void {
        $this->log('warn', $component, $message, $data);
    }
    
    /**
     * Error level logging
     */
    public function error(string $component, string $message, array $data = []): void {
        $this->log('error', $component, $message, $data);
    }
    
    /**
     * Generic log method
     */
    private function log(string $level, string $component, string $message, array $data = []): void {
        // Check if we should log this level
        $levels = ['debug' => 0, 'info' => 1, 'warn' => 2, 'error' => 3];
        if ($levels[$level] < $levels[$this->log_level]) {
            return;
        }
        
        $entry = [
            'timestamp' => current_time('mysql'),
            'level' => strtoupper($level),
            'component' => $component,
            'message' => $message,
            'data' => $data
        ];
        
        $log_line = sprintf(
            "[%s] [%s] [%s] %s %s\n",
            $entry['timestamp'],
            $entry['level'],
            $entry['component'],
            $entry['message'],
            !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );
        
        // Write to log file
        error_log($log_line, 3, $this->log_file);
        
        // Also log to WordPress debug log if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[FPSE] " . $log_line);
        }
    }
    
    /**
     * Get log file path
     */
    public function get_log_file(): string {
        return $this->log_file;
    }
}
