<?php
/**
 * Member Type Seeder
 *
 * Creates BuddyBoss member types based on profile configuration
 * Runs only on plugin activation
 *
 * @package FortaleceePSE
 * @subpackage Seeders
 */

namespace FortaleceePSE\Core\Seeders;

class MemberTypeSeeder {
    /**
     * @var array
     */
    private $profiles;

    /**
     * Constructor
     *
     * @param array $profiles Array of profiles from config
     */
    public function __construct($profiles = []) {
        $this->profiles = $profiles;
    }

    /**
     * Register all member types
     *
     * Creates member types as taxonomy terms in database (persistent)
     * Also registers them via bp_register_member_type() for runtime
     *
     * @return array Result with 'created', 'registered' keys
     */
    public function register() {
        // Protection: Check if BuddyBoss is loaded
        if (!function_exists('bp_register_member_type')) {
            error_log('FPSE: BuddyBoss member types API not loaded');
            return [
                'created' => [],
                'registered' => [],
                'errors' => ['BuddyBoss plugin não está ativo'],
            ];
        }

        $created = [];
        $registered = [];
        $errors = [];

        foreach ($this->profiles as $profileId => $profileData) {
            // First, create as taxonomy term (persistent in database)
            $createResult = $this->createMemberTypeTerm($profileId, $profileData);
            
            if ($createResult['success']) {
                if ($createResult['created']) {
                    $created[] = $profileId;
                }
                
                // Then register via BuddyBoss API (for runtime)
                $registerResult = $this->registerMemberType($profileId, $profileData);
                
                if ($registerResult['success']) {
                    $registered[] = $profileId;
                }
            } else {
                $errors[] = "Perfil {$profileId}: {$createResult['error']}";
            }
        }

        return [
            'created' => $created,
            'registered' => $registered,
            'errors' => $errors,
        ];
    }

    /**
     * Seed all member types (legacy method)
     *
     * @deprecated Use register() instead
     * @return array Result with 'created', 'updated', 'errors' keys
     */
    public function seed() {
        return $this->register();
    }

    /**
     * Create member type as custom post type (persistent)
     *
     * BuddyBoss stores member types as posts of custom post type 'bp-member-type'
     * This creates the post in the database so it persists
     *
     * @param string $profileId Profile identifier
     * @param array $profileData Profile configuration
     * @return array Result with 'success', 'created', 'error' keys
     */
    private function createMemberTypeTerm($profileId, $profileData) {
        $memberType = $this->getMemberTypeSlug($profileId);
        $label = $profileData['label'] ?? ucfirst(str_replace('-', ' ', $profileId));

        // BuddyBoss uses custom post type 'bp-member-type'
        $postType = 'bp-member-type';

        if (!post_type_exists($postType)) {
            return [
                'success' => false,
                'created' => false,
                'error' => 'Custom post type bp-member-type não existe. BuddyBoss pode não estar totalmente carregado.',
            ];
        }

        // Check if post already exists (by slug or meta)
        $existingPost = $this->findMemberTypePost($memberType);

        $postData = [
            'post_title' => $label,
            'post_name' => $memberType,
            'post_status' => 'publish',
            'post_type' => $postType,
            'post_content' => $profileData['description'] ?? '',
        ];

        if ($existingPost) {
            // Update existing post
            $postData['ID'] = $existingPost->ID;
            $postId = wp_update_post($postData);

            if (!is_wp_error($postId) && $postId > 0) {
                // Update meta
                update_post_meta($postId, '_bp_member_type_key', $memberType);
                update_post_meta($postId, '_bp_member_type_label_singular', $label);
                update_post_meta($postId, '_bp_member_type_label_plural', $label);
                update_post_meta($postId, '_bp_member_type_has_directory', '1');
                update_post_meta($postId, '_bp_member_type_show_in_loop', '0');

                return [
                    'success' => true,
                    'created' => false,
                ];
            } else {
                return [
                    'success' => false,
                    'created' => false,
                    'error' => is_wp_error($postId) ? $postId->get_error_message() : 'Falha ao atualizar post',
                ];
            }
        } else {
            // Create new post
            $postId = wp_insert_post($postData);

            if (!is_wp_error($postId) && $postId > 0) {
                // Set meta
                update_post_meta($postId, '_bp_member_type_key', $memberType);
                update_post_meta($postId, '_bp_member_type_label_singular', $label);
                update_post_meta($postId, '_bp_member_type_label_plural', $label);
                update_post_meta($postId, '_bp_member_type_has_directory', '1');
                update_post_meta($postId, '_bp_member_type_show_in_loop', '0');

                return [
                    'success' => true,
                    'created' => true,
                ];
            } else {
                return [
                    'success' => false,
                    'created' => false,
                    'error' => is_wp_error($postId) ? $postId->get_error_message() : 'Falha ao criar post',
                ];
            }
        }
    }

    /**
     * Find member type post by key
     *
     * @param string $memberType Member type key (slug)
     * @return \WP_Post|null Post object or null if not found
     */
    private function findMemberTypePost($memberType) {
        $posts = get_posts([
            'post_type' => 'bp-member-type',
            'post_status' => 'any',
            'meta_key' => '_bp_member_type_key',
            'meta_value' => $memberType,
            'posts_per_page' => 1,
        ]);

        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Register a member type via BuddyBoss API (runtime)
     *
     * Registers member type with BuddyBoss for runtime usage
     * This is idempotent - safe to call multiple times
     *
     * @param string $profileId Profile identifier
     * @param array $profileData Profile configuration
     * @return array Result with 'success', 'error' keys
     */
    private function registerMemberType($profileId, $profileData) {
        $memberType = $this->getMemberTypeSlug($profileId);
        $label = $profileData['label'] ?? ucfirst(str_replace('-', ' ', $profileId));

        $args = [
            'labels' => [
                'name' => $label,
                'singular_name' => $label,
            ],
            'has_directory' => true,
            'directory_slug' => $memberType,
        ];

        // Register member type (idempotent operation)
        $registered = bp_register_member_type($memberType, $args);

        if ($registered) {
            return [
                'success' => true,
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Falha ao registrar member type',
            ];
        }
    }

    /**
     * Create or update a member type (legacy method)
     *
     * @deprecated Use registerMemberType() instead
     * @param string $profileId Profile identifier
     * @param array $profileData Profile configuration
     * @return array Result with 'success', 'created', 'error' keys
     */
    public function createOrUpdateMemberType($profileId, $profileData) {
        $result = $this->registerMemberType($profileId, $profileData);
        
        // Legacy compatibility: always return 'created' => false since we just register
        $result['created'] = false;
        
        return $result;
    }

    /**
     * Get member type slug from profile ID
     *
     * @param string $profileId Profile identifier
     * @return string Member type slug
     */
    private function getMemberTypeSlug($profileId) {
        // Convert to member type format: fpse_estudante_eaa
        $slug = 'fpse_' . str_replace('-', '_', strtolower($profileId));

        // BuddyBoss limits member type slugs
        if (strlen($slug) > 40) {
            $slug = substr($slug, 0, 40);
        }

        return $slug;
    }

    /**
     * Get member type slug for profile
     *
     * Public static method for use elsewhere
     *
     * @param string $profileId Profile identifier
     * @return string Member type slug
     */
    public static function getMemberTypeForProfile($profileId) {
        $slug = 'fpse_' . str_replace('-', '_', strtolower($profileId));

        if (strlen($slug) > 40) {
            $slug = substr($slug, 0, 40);
        }

        return $slug;
    }
}
