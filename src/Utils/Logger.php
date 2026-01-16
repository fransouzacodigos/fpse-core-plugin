<?php
/**
 * Logger Utility
 *
 * Handles logging with sensitive field masking
 *
 * @package FortaleceePSE
 * @subpackage Utils
 */

namespace FortaleceePSE\Core\Utils;

class Logger {
    /**
     * @var array
     */
    private $config;

    /**
     * @var string
     */
    private $logFile;

    /**
     * Constructor
     *
     * @param array $config Debug configuration
     */
    public function __construct($config = []) {
        $this->config = $config;
        $this->logFile = $config['log_file'] ?? (WP_CONTENT_DIR . '/fpse-core.log');
    }

    /**
     * Log a message
     *
     * @param string $level Log level (error, warning, info, debug)
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    private function log($level, $message, $context = []) {
        // Check if debug is enabled
        if (!$this->config['enable_debug'] ?? false) {
            return;
        }

        // Check if level is in allowed levels
        if (!in_array($level, $this->config['log_levels'] ?? [])) {
            return;
        }

        // Mask sensitive data in context
        $context = $this->maskSensitiveData($context);

        // Format log message
        $timestamp = wp_date('Y-m-d H:i:s');
        $logMessage = sprintf(
            '[%s] [%s] %s',
            $timestamp,
            strtoupper($level),
            $message
        );

        // Add context if present
        if (!empty($context)) {
            $logMessage .= ' | Context: ' . wp_json_encode($context);
        }

        // Write to log file
        error_log($logMessage . "\n", 3, $this->logFile);
    }

    /**
     * Log error message
     *
     * @param string $message Error message
     * @param array $context Additional context
     * @return void
     */
    public function error($message, $context = []) {
        $this->log('error', $message, $context);
    }

    /**
     * Log warning message
     *
     * @param string $message Warning message
     * @param array $context Additional context
     * @return void
     */
    public function warning($message, $context = []) {
        $this->log('warning', $message, $context);
    }

    /**
     * Log info message
     *
     * @param string $message Info message
     * @param array $context Additional context
     * @return void
     */
    public function info($message, $context = []) {
        $this->log('info', $message, $context);
    }

    /**
     * Log debug message
     *
     * @param string $message Debug message
     * @param array $context Additional context
     * @return void
     */
    public function debug($message, $context = []) {
        $this->log('debug', $message, $context);
    }

    /**
     * Mask sensitive data in arrays
     *
     * @param array $data Data to mask
     * @return array Masked data
     */
    private function maskSensitiveData($data) {
        $sensitiveFields = $this->config['mask_sensitive_fields'] ?? [];

        if (empty($sensitiveFields) || !is_array($data)) {
            return $data;
        }

        $masked = [];
        foreach ($data as $key => $value) {
            // Check if field should be masked (case-insensitive)
            $shouldMask = false;
            foreach ($sensitiveFields as $sensitiveField) {
                if (strcasecmp($key, $sensitiveField) === 0) {
                    $shouldMask = true;
                    break;
                }
            }

            if ($shouldMask && !empty($value)) {
                $masked[$key] = '***MASKED***';
            } elseif (is_array($value)) {
                $masked[$key] = $this->maskSensitiveData($value);
            } else {
                $masked[$key] = $value;
            }
        }

        return $masked;
    }

    /**
     * Get log file path
     *
     * @return string
     */
    public function getLogFile() {
        return $this->logFile;
    }
}
