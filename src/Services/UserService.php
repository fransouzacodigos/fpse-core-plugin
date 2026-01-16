<?php
/**
 * User Service for registration and updates
 *
 * Handles user creation, updates, and meta data management
 *
 * @package FortaleceePSE
 * @subpackage Services
 */

namespace FortaleceePSE\Core\Services;

use FortaleceePSE\Core\Domain\RegistrationDTO;

class UserService {
    /**
     * @var EventRecorder
     */
    private $eventRecorder;

    /**
     * Constructor
     *
     * @param EventRecorder $eventRecorder
     */
    public function __construct(EventRecorder $eventRecorder) {
        $this->eventRecorder = $eventRecorder;
    }

    /**
     * Create or update a user from registration data
     *
     * @param RegistrationDTO $dto Registration data
     * @return array Success status and user ID or error message
     */
    public function createOrUpdate(RegistrationDTO $dto) {
        // Validate minimum required fields
        $validation = $dto->getMinimumRequiredFields();
        if (!$validation['valid']) {
            $this->eventRecorder->recordValidationError(
                $dto->perfilUsuario ?? 'unknown',
                $dto->estado ?? 'unknown',
                $validation['missing']
            );

            return [
                'success' => false,
                'message' => 'Campos obrigatórios faltando: ' . implode(', ', $validation['missing']),
            ];
        }

        // Check for existing user by email_login (username) first
        $existingUser = get_user_by('login', $dto->emailLogin);
        
        // If not found, check by email
        if (!$existingUser && !empty($dto->email)) {
            $existingUser = get_user_by('email', $dto->email);
        }

        if ($existingUser) {
            return $this->updateUser($existingUser->ID, $dto);
        }

        return $this->createUser($dto);
    }

    /**
     * Create a new user
     *
     * @param RegistrationDTO $dto Registration data
     * @return array Success status and user ID or error message
     */
    private function createUser(RegistrationDTO $dto) {
        // Check if login is already taken
        if (username_exists($dto->emailLogin)) {
            error_log("FPSE: Username already exists: {$dto->emailLogin}");
            return [
                'success' => false,
                'message' => 'Email de login já cadastrado',
            ];
        }

        // Validate password (WordPress requires at least 6 characters)
        if (empty($dto->senhaLogin) || strlen($dto->senhaLogin) < 6) {
            error_log("FPSE: Invalid password (too short or empty)");
            return [
                'success' => false,
                'message' => 'Senha deve ter pelo menos 6 caracteres',
            ];
        }

        // Create WordPress user
        $userId = wp_create_user(
            $dto->emailLogin,
            $dto->senhaLogin,
            $dto->email ?? $dto->emailLogin
        );

        if (is_wp_error($userId)) {
            error_log("FPSE: User creation error - " . $userId->get_error_message());
            return [
                'success' => false,
                'message' => $userId->get_error_message(),
            ];
        }

        error_log("FPSE: User created successfully with ID: {$userId}");

        // Update user display name
        wp_update_user([
            'ID' => $userId,
            'display_name' => $dto->nomeCompleto,
        ]);

        // Store all registration data in user meta
        $this->storeUserMeta($userId, $dto);

        // Assign role based on profile
        if (!empty($dto->perfilUsuario)) {
            $roleCreator = new \FortaleceePSE\Core\Utils\RoleCreator(
                \FortaleceePSE\Core\Plugin::getInstance()
            );
            $roleCreator->assignRoleByProfile($userId, $dto->perfilUsuario);
        }

        // Assign BuddyBoss member type based on profile
        if (!empty($dto->perfilUsuario)) {
            $this->assignBuddyBossMemberType($userId, $dto->perfilUsuario);
        }

        // Assign user to state group (BuddyBoss)
        if (!empty($dto->estado)) {
            $this->assignUserToStateGroup($userId, $dto->estado, $dto->perfilUsuario ?? 'unknown');
        }

        // Record event
        $this->eventRecorder->recordRegistration(
            $userId,
            $dto->perfilUsuario ?? 'unknown',
            $dto->estado ?? 'unknown',
            ['action' => 'user_created']
        );

        return [
            'success' => true,
            'user_id' => $userId,
            'message' => 'Usuário criado com sucesso',
        ];
    }

    /**
     * Update an existing user
     *
     * @param int $userId WordPress user ID
     * @param RegistrationDTO $dto Registration data
     * @return array Success status and user ID or error message
     */
    private function updateUser($userId, RegistrationDTO $dto) {
        // Update user display name and email if changed
        wp_update_user([
            'ID' => $userId,
            'display_name' => $dto->nomeCompleto,
            'user_email' => $dto->email,
        ]);

        // Update password if provided and is different
        if (!empty($dto->senhaLogin)) {
            wp_set_password($dto->senhaLogin, $userId);
        }

        // Update all registration data in user meta
        $this->storeUserMeta($userId, $dto);

        // Update role based on profile (if profile changed)
        if (!empty($dto->perfilUsuario)) {
            $roleCreator = new \FortaleceePSE\Core\Utils\RoleCreator(
                \FortaleceePSE\Core\Plugin::getInstance()
            );
            $roleCreator->assignRoleByProfile($userId, $dto->perfilUsuario);
        }

        // Update BuddyBoss member type based on profile
        if (!empty($dto->perfilUsuario)) {
            $this->assignBuddyBossMemberType($userId, $dto->perfilUsuario);
        }

        // Update user state group (BuddyBoss)
        if (!empty($dto->estado)) {
            $this->assignUserToStateGroup($userId, $dto->estado, $dto->perfilUsuario ?? 'unknown');
        }

        // Record event
        $this->eventRecorder->recordUpdate(
            $userId,
            $dto->perfilUsuario ?? 'unknown',
            $dto->estado ?? 'unknown',
            ['action' => 'user_updated']
        );

        return [
            'success' => true,
            'user_id' => $userId,
            'message' => 'Usuário atualizado com sucesso',
        ];
    }

    /**
     * Store all registration data in user meta
     *
     * Converts camelCase properties to snake_case for consistency
     *
     * @param int $userId WordPress user ID
     * @param RegistrationDTO $dto Registration data
     * @return void
     */
    private function storeUserMeta($userId, RegistrationDTO $dto) {
        $data = $dto->toArray();
        
        error_log("FPSE: Storing user meta for user {$userId}");
        error_log("FPSE: Total de campos recebidos: " . count($data));
        error_log("FPSE: Chaves dos campos: " . wp_json_encode(array_keys($data)));
        error_log("FPSE: Dados completos (valores mascarados): " . wp_json_encode(array_map(function($v) {
            if (is_string($v) && strlen($v) > 20) {
                return substr($v, 0, 20) . '...';
            }
            return $v;
        }, $data)));

        $savedCount = 0;
        $skippedCount = 0;
        
        foreach ($data as $key => $value) {
            // Skip empty values (but keep 0 and false)
            if ($value === '' || $value === null) {
                error_log("FPSE: Pulando campo vazio: {$key}");
                $skippedCount++;
                continue;
            }
            
            // Convert camelCase to snake_case if not already
            $metaKey = $this->camelToSnakeCase($key);
            
            // Store in user meta with prefix for organization
            $fullMetaKey = 'fpse_' . $metaKey;
            
            $result1 = update_user_meta($userId, $fullMetaKey, $value);
            $result2 = update_user_meta($userId, $metaKey, $value);
            
            if ($result1 || $result2) {
                error_log("FPSE: ✓ Salvo {$metaKey} (também como {$fullMetaKey})");
                $savedCount++;
            } else {
                error_log("FPSE: ✗ Falha ao salvar {$metaKey}");
            }
        }
        
        error_log("FPSE: User meta stored - Salvos: {$savedCount}, Pulados: {$skippedCount}");
        
        // Verificar se alguns campos importantes foram salvos
        $checkFields = ['cpf', 'telefone', 'logradouro', 'instituicao_nome', 'setor_gti'];
        foreach ($checkFields as $field) {
            $value = get_user_meta($userId, 'fpse_' . $field, true);
            if (!empty($value)) {
                error_log("FPSE: ✓ Verificação - {$field} foi salvo: " . (is_string($value) ? substr($value, 0, 20) : $value));
            } else {
                $value2 = get_user_meta($userId, $field, true);
                if (!empty($value2)) {
                    error_log("FPSE: ✓ Verificação - {$field} foi salvo sem prefixo: " . (is_string($value2) ? substr($value2, 0, 20) : $value2));
                } else {
                    error_log("FPSE: ✗ Verificação - {$field} NÃO foi salvo");
                }
            }
        }
    }

    /**
     * Get user registration data
     *
     * @param int $userId WordPress user ID
     * @return array User registration data
     */
    public function getUserData($userId) {
        $user = get_userdata($userId);

        if (!$user) {
            return [];
        }

        $data = [
            'id' => $user->ID,
            'email' => $user->user_email,
            'display_name' => $user->display_name,
        ];

        // Get all user meta that starts with our field names
        $allMeta = get_user_meta($userId);
        foreach ($allMeta as $key => $values) {
            // Skip private meta (starting with _)
            if (strpos($key, '_') === 0) {
                continue;
            }

            // Get first value if array
            $value = is_array($values) ? reset($values) : $values;
            $data[$key] = maybe_unserialize($value);
        }

        return $data;
    }

    /**
     * Convert camelCase to snake_case
     *
     * If string is already in snake_case, return as is.
     *
     * @param string $str
     * @return string
     */
    private function camelToSnakeCase($str) {
        // Se já está em snake_case, retornar como está
        if (preg_match('/^[a-z][a-z0-9_]*(_[a-z0-9]+)*$/', $str)) {
            return $str;
        }
        
        // Converter camelCase para snake_case
        $str = preg_replace('/[A-Z]/', '_$0', $str);
        return strtolower(trim($str, '_'));
    }

    /**
     * Get users by profile
     *
     * @param string $perfil Profile name
     * @param int $limit Number of users to return
     * @return array
     */
    public function getUsersByProfile($perfil, $limit = 100) {
        $args = [
            'meta_key' => 'perfil_usuario',
            'meta_value' => sanitize_text_field($perfil),
            'number' => absint($limit),
        ];

        return get_users($args);
    }

    /**
     * Get users by state
     *
     * @param string $estado State code
     * @param int $limit Number of users to return
     * @return array
     */
    public function getUsersByState($estado, $limit = 100) {
        $args = [
            'meta_key' => 'estado',
            'meta_value' => sanitize_text_field($estado),
            'number' => absint($limit),
        ];

        return get_users($args);
    }

    /**
     * Get users by profile and state
     *
     * @param string $perfil Profile name
     * @param string $estado State code
     * @param int $limit Number of users to return
     * @return array
     */
    public function getUsersByProfileAndState($perfil, $estado, $limit = 100) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT u.* FROM {$wpdb->users} u
            INNER JOIN {$wpdb->usermeta} pm ON u.ID = pm.user_id AND pm.meta_key = 'perfil_usuario' AND pm.meta_value = %s
            INNER JOIN {$wpdb->usermeta} sm ON u.ID = sm.user_id AND sm.meta_key = 'estado' AND sm.meta_value = %s
            ORDER BY u.user_registered DESC
            LIMIT %d",
            sanitize_text_field($perfil),
            sanitize_text_field($estado),
            absint($limit)
        );

        return $wpdb->get_results($query);
    }

    /**
     * Assign user to state group (BuddyBoss)
     *
     * Finds or creates state group and adds user to it
     *
     * @param int $userId WordPress user ID
     * @param string $estado State code (UF)
     * @param string $perfil User profile
     * @return bool True if successful
     */
    private function assignUserToStateGroup($userId, $estado, $perfil) {
        if (!function_exists('groups_join_group')) {
            return false; // BuddyBoss not active
        }

        $uf = strtolower(trim($estado));
        $slug = 'estado-' . $uf;

        // Find group by slug using BuddyBoss API
        if (!function_exists('groups_get_groups')) {
            return false;
        }

        $groups = groups_get_groups([
            'slug' => $slug,
            'per_page' => 1,
            'show_hidden' => true,
        ]);

        $group = null;
        if (!empty($groups['groups']) && isset($groups['groups'][0])) {
            $group = (object) ['id' => $groups['groups'][0]->id];
        }

        if (!$group) {
            // Group doesn't exist, try to create it
            $plugin = \FortaleceePSE\Core\Plugin::getInstance();
            $states = $plugin->getConfig('states', []);
            
            if (!isset($states[$estado])) {
                return false; // Invalid state
            }

            $seeder = new \FortaleceePSE\Core\Seeders\StateGroupSeeder($states);
            $result = $seeder->createOrUpdateStateGroup($estado, $states[$estado]);
            
            if (!$result['success'] || !isset($result['group_id'])) {
                return false;
            }

            $groupId = $result['group_id'];
        } else {
            $groupId = $group->id;
        }

        // Add user to group
        $joined = groups_join_group($groupId, $userId);

        if ($joined) {
            // Record event
            $this->eventRecorder->recordGroupAssigned($userId, $estado, $perfil);
            return true;
        }

        return false;
    }

    /**
     * Assign BuddyBoss member type to user
     *
     * @param int $userId WordPress user ID
     * @param string $perfilUsuario Profile identifier
     * @return bool True if successful
     */
    private function assignBuddyBossMemberType($userId, $perfilUsuario) {
        if (!function_exists('bp_set_member_type')) {
            return false; // BuddyBoss not active
        }

        // Get member type slug from profile
        $memberType = \FortaleceePSE\Core\Seeders\MemberTypeSeeder::getMemberTypeForProfile($perfilUsuario);
        
        if (empty($memberType)) {
            error_log("FPSE: Member type not found for profile: {$perfilUsuario}");
            return false;
        }

        // Set member type for user
        $result = bp_set_member_type($userId, $memberType);

        if ($result) {
            // Record event
            $this->eventRecorder->recordMemberTypeAssigned(
                $userId,
                $perfilUsuario,
                null,
                ['member_type' => $memberType]
            );
            return true;
        }

        error_log("FPSE: Failed to assign member type {$memberType} to user {$userId}");
        return false;
    }
}
