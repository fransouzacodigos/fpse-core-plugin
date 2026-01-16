<?php
/**
 * Nonce Middleware
 *
 * WordPress security token generation and validation
 *
 * @package FortaleceePSE
 * @subpackage Security
 */

namespace FortaleceePSE\Core\Security;

class NonceMiddleware {
    /**
     * Nonce action name
     */
    const NONCE_ACTION = 'fpse_register_action';

    /**
     * Nonce field name
     */
    const NONCE_NAME = 'fpse_nonce';

    /**
     * Generate a WordPress nonce
     *
     * @return string Nonce token
     */
    public function generateNonce() {
        return wp_create_nonce(self::NONCE_ACTION);
    }

    /**
     * Verify a WordPress nonce
     *
     * @param string $nonce Nonce token to verify
     * @return bool True if nonce is valid
     */
    public function verifyNonce($nonce) {
        if (empty($nonce)) {
            return false;
        }

        $result = wp_verify_nonce($nonce, self::NONCE_ACTION);

        // wp_verify_nonce returns 1 for valid nonce, 2 for valid but different user
        // We accept both as valid in this context
        return $result !== false && $result > 0;
    }

    /**
     * Get nonce field name
     *
     * @return string
     */
    public function getNonceName() {
        return self::NONCE_NAME;
    }

    /**
     * Get nonce action
     *
     * @return string
     */
    public function getNonceAction() {
        return self::NONCE_ACTION;
    }
}
