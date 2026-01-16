<?php
/**
 * Rate Limiter
 *
 * IP-based rate limiting using WordPress transients
 *
 * @package FortaleceePSE
 * @subpackage Security
 */

namespace FortaleceePSE\Core\Security;

class RateLimit {
    /**
     * Check if request is within rate limit
     *
     * Uses WordPress transients for rate limiting by IP and endpoint
     *
     * @param string $endpoint Endpoint identifier
     * @param int $limit Maximum requests per hour
     * @return bool True if within limit, false if exceeded
     */
    public function checkLimit($endpoint, $limit = 100) {
        // Disable rate limiting in development mode
        if (defined('WP_DEBUG') && WP_DEBUG && defined('FPSE_DISABLE_RATE_LIMIT') && FPSE_DISABLE_RATE_LIMIT) {
            return true;
        }

        $endpoint = sanitize_text_field($endpoint);
        $limit = absint($limit);
        $ip = $this->getClientIP();

        if (empty($ip)) {
            // In development, allow if IP cannot be determined
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return true;
            }
            return false;
        }

        // Create unique key for this IP and endpoint
        $key = 'fpse_rate_' . $endpoint . '_' . $ip;

        // Get current count
        $count = (int) get_transient($key);

        // If count exceeds limit, deny
        if ($count >= $limit) {
            error_log("FPSE Rate Limit: IP {$ip} exceeded limit of {$limit} for endpoint {$endpoint} (current: {$count})");
            return false;
        }

        // Increment count
        $count++;
        set_transient($key, $count, HOUR_IN_SECONDS);

        return true;
    }

    /**
     * Reset rate limit for an IP and endpoint
     *
     * Useful for testing or admin override
     *
     * @param string $endpoint Endpoint identifier
     * @param string $ip IP address (uses current IP if empty)
     * @return void
     */
    public function resetLimit($endpoint, $ip = '') {
        $endpoint = sanitize_text_field($endpoint);

        if (empty($ip)) {
            $ip = $this->getClientIP();
        }

        if (empty($ip)) {
            return;
        }

        $key = 'fpse_rate_' . $endpoint . '_' . $ip;
        delete_transient($key);
    }

    /**
     * Get client IP address
     *
     * Handles proxies and X-Forwarded-For headers
     *
     * @return string Client IP or empty string if unable to determine
     */
    private function getClientIP() {
        $ip = '';

        // Check for proxy header first
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Handle multiple IPs (take first one)
            $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
            $ip = $ips[0];
        } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // Sanitize IP
        $ip = sanitize_text_field($ip);

        // Validate IP format
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return '';
        }

        return $ip;
    }
}
