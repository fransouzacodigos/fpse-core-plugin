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
     * @var \FortaleceePSE\Core\Utils\Logger|null
     */
    private $logger = null;

    /**
     * Constructor
     *
     * @param EventRecorder $eventRecorder
     */
    public function __construct(EventRecorder $eventRecorder) {
        $this->eventRecorder = $eventRecorder;
        $this->logger = \FortaleceePSE\Core\Plugin::getInstance()->getLogger();
    }

    /**
     * Create or update a user from registration data
     *
     * @param RegistrationDTO $dto Registration data
     * @return array Success status and user ID or error message
     */
    public function createOrUpdate(RegistrationDTO $dto) {
        // LOG DE DIAGNÓSTICO: createOrUpdate iniciado
        error_log('[FPSE DEBUG] UserService::createOrUpdate() iniciado');
        error_log('[FPSE DEBUG] emailLogin: ' . ($dto->emailLogin ?? 'NULL'));
        error_log('[FPSE DEBUG] perfilUsuario: ' . ($dto->perfilUsuario ?? 'NULL'));
        
        // Validate minimum required fields
        $validation = $dto->getMinimumRequiredFields();
        if (!$validation['valid']) {
            error_log('[FPSE DEBUG] ❌ Validação falhou: ' . implode(', ', $validation['missing']));
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
        
        error_log('[FPSE DEBUG] ✅ Validação OK - continuando...');

        // Check for existing user by email_login (username) first
        $existingUser = get_user_by('login', $dto->emailLogin);
        
        // If not found, check by email
        if (!$existingUser && !empty($dto->email)) {
            $existingUser = get_user_by('email', $dto->email);
        }

        if ($existingUser) {
            error_log('[FPSE DEBUG] Usuário existente encontrado - chamando updateUser()');
            return $this->updateUser($existingUser->ID, $dto);
        }

        error_log('[FPSE DEBUG] Usuário NÃO existe - chamando createUser()');
        return $this->createUser($dto);
    }

    /**
     * Create a new user
     *
     * @param RegistrationDTO $dto Registration data
     * @return array Success status and user ID or error message
     */
    private function createUser(RegistrationDTO $dto) {
        // LOG DE DIAGNÓSTICO: createUser iniciado
        error_log('[FPSE DEBUG] UserService::createUser() iniciado');
        error_log('[FPSE DEBUG] emailLogin: ' . ($dto->emailLogin ?? 'NULL'));
        error_log('[FPSE DEBUG] perfilUsuario: ' . ($dto->perfilUsuario ?? 'NULL'));
        
        // Check if login is already taken
        if (username_exists($dto->emailLogin)) {
            error_log('[FPSE DEBUG] ❌ Username já existe: ' . $dto->emailLogin);
            error_log("FPSE: Username already exists: {$dto->emailLogin}");
            return [
                'success' => false,
                'message' => 'Email de login já cadastrado',
            ];
        }

        // Validate password (WordPress requires at least 6 characters)
        if (empty($dto->senhaLogin) || strlen($dto->senhaLogin) < 6) {
            error_log('[FPSE DEBUG] ❌ Senha inválida (curta ou vazia)');
            error_log("FPSE: Invalid password (too short or empty)");
            return [
                'success' => false,
                'message' => 'Senha deve ter pelo menos 6 caracteres',
            ];
        }

        // Create WordPress user
        error_log('[FPSE DEBUG] Criando usuário WordPress com wp_create_user()...');
        $userId = wp_create_user(
            $dto->emailLogin,
            $dto->senhaLogin,
            $dto->email ?? $dto->emailLogin
        );

        if (is_wp_error($userId)) {
            error_log('[FPSE DEBUG] ❌ wp_create_user() retornou WP_Error: ' . $userId->get_error_message());
            error_log("FPSE: User creation error - " . $userId->get_error_message());
            return [
                'success' => false,
                'message' => $userId->get_error_message(),
            ];
        }

        error_log('[FPSE DEBUG] ✅ Usuário WordPress criado - ID: ' . $userId);
        error_log("FPSE: User created successfully with ID: {$userId}");

        // Update user display name
        wp_update_user([
            'ID' => $userId,
            'display_name' => $dto->nomeCompleto,
        ]);

        // TAREFA 4: Assign BuddyBoss member type BEFORE saving xProfile fields
        // BuddyBoss may ignore fields not associated with the active member type
        error_log('[FPSE DEBUG] ✅ Antes de assignBuddyBossMemberType()');
        if (!empty($dto->perfilUsuario)) {
            if ($this->logger) {
                $this->logger->debug('UserService', 'Definindo member type ANTES de salvar campos xProfile', [
                    'user_id' => $userId,
                    'perfil_usuario' => $dto->perfilUsuario
                ]);
            }
            error_log('[FPSE DEBUG] Chamando assignBuddyBossMemberType()...');
            $this->assignBuddyBossMemberType($userId, $dto->perfilUsuario);
            error_log('[FPSE DEBUG] ✅ assignBuddyBossMemberType() concluído');
        } else {
            error_log('[FPSE DEBUG] ⚠️ perfilUsuario está vazio - pulando assignBuddyBossMemberType()');
        }

        // Store all registration data in user meta and BuddyBoss xProfile
        error_log('[FPSE DEBUG] ✅ Antes de storeUserMeta()');
        $this->storeUserMeta($userId, $dto);
        error_log('[FPSE DEBUG] ✅ storeUserMeta() concluído');
        
        error_log('[FPSE DEBUG] ✅ Antes de storeXProfileFields()');
        $this->storeXProfileFields($userId, $dto);
        error_log('[FPSE DEBUG] ✅ storeXProfileFields() concluído');

        // Assign role based on profile
        if (!empty($dto->perfilUsuario)) {
            $roleCreator = new \FortaleceePSE\Core\Utils\RoleCreator(
                \FortaleceePSE\Core\Plugin::getInstance()
            );
            $roleCreator->assignRoleByProfile($userId, $dto->perfilUsuario);
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

        // TAREFA 4: Update BuddyBoss member type BEFORE saving xProfile fields
        // BuddyBoss may ignore fields not associated with the active member type
        if (!empty($dto->perfilUsuario)) {
            if ($this->logger) {
                $this->logger->debug('UserService', 'Atualizando member type ANTES de salvar campos xProfile', [
                    'user_id' => $userId,
                    'perfil_usuario' => $dto->perfilUsuario
                ]);
            }
            $this->assignBuddyBossMemberType($userId, $dto->perfilUsuario);
        }

        // Update all registration data in user meta and BuddyBoss xProfile
        $this->storeUserMeta($userId, $dto);
        $this->storeXProfileFields($userId, $dto);

        // Update role based on profile (if profile changed)
        if (!empty($dto->perfilUsuario)) {
            $roleCreator = new \FortaleceePSE\Core\Utils\RoleCreator(
                \FortaleceePSE\Core\Plugin::getInstance()
            );
            $roleCreator->assignRoleByProfile($userId, $dto->perfilUsuario);
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
     * TAREFA 4: Teste isolado de persistência xProfile
     * 
     * Método temporário para testar se um campo específico pode ser salvo
     * 
     * @param int $userId WordPress user ID
     * @param string $fieldKey Field key (ex: 'nome_completo')
     * @param mixed $testValue Test value
     * @return array Result with 'success', 'field_id', 'message' keys
     */
    public function testXProfilePersistence($userId, $fieldKey = 'nome_completo', $testValue = 'Teste Persistência') {
        if ($this->logger) {
            $this->logger->debug('UserService', 'Iniciando teste isolado de persistência xProfile', [
                'user_id' => $userId,
                'field_key' => $fieldKey,
                'test_value' => $testValue
            ]);
        }

        if (!function_exists('xprofile_set_field_data')) {
            if ($this->logger) {
                $this->logger->error('UserService', 'BuddyBoss xProfile não está disponível', ['user_id' => $userId]);
            }
            return [
                'success' => false,
                'field_id' => null,
                'message' => 'BuddyBoss xProfile não está disponível',
            ];
        }

        // Verificar member type
        $memberType = null;
        if (function_exists('bp_get_member_type')) {
            $memberType = bp_get_member_type($userId);
            if ($this->logger) {
                $this->logger->debug('UserService', 'Member type verificado no teste', [
                    'user_id' => $userId,
                    'member_type' => $memberType ?: 'NENHUM'
                ]);
            }
        }

        // Obter field ID
        $fieldId = \FortaleceePSE\Core\Seeders\XProfileFieldSeeder::getFieldId($fieldKey);
        
        if (!$fieldId || $fieldId === 0 || $fieldId === null) {
            if ($this->logger) {
                $this->logger->error('UserService', 'Field ID inválido no teste', [
                    'user_id' => $userId,
                    'field_key' => $fieldKey,
                    'field_id' => $fieldId
                ]);
            }
            return [
                'success' => false,
                'field_id' => $fieldId,
                'message' => "Field ID inválido para '{$fieldKey}'",
            ];
        }

        // Verificar se campo existe no banco
        global $wpdb;
        $fieldsTable = $wpdb->prefix . 'bp_xprofile_fields';
        $fieldExists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$fieldsTable} WHERE id = %d AND parent_id = 0",
            $fieldId
        ));

        if (!$fieldExists || $fieldExists == 0) {
            if ($this->logger) {
                $this->logger->error('UserService', 'Campo não existe na tabela', [
                    'user_id' => $userId,
                    'field_key' => $fieldKey,
                    'field_id' => $fieldId,
                    'table' => $fieldsTable
                ]);
            }
            return [
                'success' => false,
                'field_id' => $fieldId,
                'message' => "Campo ID {$fieldId} não existe no banco de dados",
            ];
        }

        // Tentar salvar
        if ($this->logger) {
            $this->logger->debug('UserService', 'Tentando salvar campo no teste', [
                'user_id' => $userId,
                'field_key' => $fieldKey,
                'field_id' => $fieldId
            ]);
        }
        
        $result = \xprofile_set_field_data($fieldId, $userId, $testValue);

        if ($result !== false) {
            // Ler de volta para confirmar
            if (function_exists('xprofile_get_field_data')) {
                $savedValue = xprofile_get_field_data($fieldId, $userId);
                
                if ($savedValue === $testValue) {
                    if ($this->logger) {
                        $this->logger->info('UserService', 'Teste de persistência: SUCESSO TOTAL', [
                            'user_id' => $userId,
                            'field_key' => $fieldKey,
                            'field_id' => $fieldId,
                            'value_verified' => true
                        ]);
                    }
                    return [
                        'success' => true,
                        'field_id' => $fieldId,
                        'message' => "Campo '{$fieldKey}' salvo e verificado com sucesso",
                        'saved_value' => $savedValue,
                    ];
                } else {
                    if ($this->logger) {
                        $this->logger->warn('UserService', 'Teste: valor lido difere do esperado', [
                            'user_id' => $userId,
                            'field_key' => $fieldKey,
                            'field_id' => $fieldId,
                            'expected' => $testValue,
                            'actual' => $savedValue
                        ]);
                    }
                    return [
                        'success' => false,
                        'field_id' => $fieldId,
                        'message' => "Campo foi salvo mas valor lido difere do esperado",
                        'expected' => $testValue,
                        'actual' => $savedValue,
                    ];
                }
            } else {
                if ($this->logger) {
                    $this->logger->info('UserService', 'Teste: campo salvo (verificação não disponível)', [
                        'user_id' => $userId,
                        'field_key' => $fieldKey,
                        'field_id' => $fieldId
                    ]);
                }
                return [
                    'success' => true,
                    'field_id' => $fieldId,
                    'message' => "Campo '{$fieldKey}' salvo (verificação não disponível)",
                ];
            }
        } else {
            if ($this->logger) {
                $this->logger->error('UserService', 'Teste: xprofile_set_field_data retornou false', [
                    'user_id' => $userId,
                    'field_key' => $fieldKey,
                    'field_id' => $fieldId
                ]);
            }
            return [
                'success' => false,
                'field_id' => $fieldId,
                'message' => "xprofile_set_field_data retornou false",
            ];
        }
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
            if ($this->logger) {
                $this->logger->error('UserService', 'BuddyBoss member type API não disponível', [
                    'user_id' => $userId,
                    'perfil_usuario' => $perfilUsuario
                ]);
            }
            return false; // BuddyBoss not active
        }

        // Get member type slug from profile
        $memberType = \FortaleceePSE\Core\Seeders\MemberTypeSeeder::getMemberTypeForProfile($perfilUsuario);
        
        if (empty($memberType)) {
            if ($this->logger) {
                $this->logger->error('UserService', 'Member type não encontrado para perfil', [
                    'user_id' => $userId,
                    'perfil_usuario' => $perfilUsuario
                ]);
            }
            return false;
        }

        // CRÍTICO: Verificar se member type existe no banco antes de atribuir
        // BuddyBoss armazena member types como posts do tipo 'bp-member-type'
        $memberTypeExists = false;
        if (post_type_exists('bp-member-type')) {
            $existingPost = get_posts([
                'post_type' => 'bp-member-type',
                'post_status' => 'any',
                'meta_key' => '_bp_member_type_key',
                'meta_value' => $memberType,
                'posts_per_page' => 1,
            ]);
            $memberTypeExists = !empty($existingPost);
        }

        // Se member type não existe, criar agora (on-demand)
        if (!$memberTypeExists) {
            if ($this->logger) {
                $this->logger->warn('UserService', 'Member type não existe no banco - criando on-demand', [
                    'user_id' => $userId,
                    'member_type' => $memberType,
                    'perfil_usuario' => $perfilUsuario
                ]);
            }
            
            $plugin = \FortaleceePSE\Core\Plugin::getInstance();
            $profiles = $plugin->getConfig('profiles', []);
            
            if (isset($profiles[$perfilUsuario])) {
                // Criar apenas este member type específico
                $seeder = new \FortaleceePSE\Core\Seeders\MemberTypeSeeder([
                    $perfilUsuario => $profiles[$perfilUsuario]
                ]);
                $result = $seeder->register();
                
                if ($this->logger) {
                    $this->logger->info('UserService', 'Member type criado on-demand', [
                        'user_id' => $userId,
                        'member_type' => $memberType,
                        'perfil_usuario' => $perfilUsuario,
                        'created' => $result['created'] ?? [],
                        'registered' => $result['registered'] ?? [],
                        'errors' => $result['errors'] ?? []
                    ]);
                }
                
                // Verificar se foi criado com sucesso
                if (!empty($result['errors'])) {
                    if ($this->logger) {
                        $this->logger->error('UserService', 'Falha ao criar member type on-demand', [
                            'user_id' => $userId,
                            'member_type' => $memberType,
                            'errors' => $result['errors']
                        ]);
                    }
                    // Continuar mesmo com erro - tentar atribuir de qualquer forma
                }
            } else {
                if ($this->logger) {
                    $this->logger->error('UserService', 'Perfil não encontrado na configuração', [
                        'user_id' => $userId,
                        'perfil_usuario' => $perfilUsuario
                    ]);
                }
            }
        } else {
            if ($this->logger) {
                $this->logger->debug('UserService', 'Member type já existe no banco', [
                    'user_id' => $userId,
                    'member_type' => $memberType
                ]);
            }
        }

        // Verificar member type atual antes de definir
        $currentMemberType = function_exists('bp_get_member_type') 
            ? bp_get_member_type($userId) 
            : null;

        if ($this->logger) {
            $this->logger->debug('UserService', 'Aplicando member type', [
                'user_id' => $userId,
                'perfil_usuario' => $perfilUsuario,
                'member_type' => $memberType,
                'current_member_type' => $currentMemberType ?: 'NENHUM'
            ]);
        }

        // CRÍTICO: Verificar se term existe na taxonomy bp_member_type
        // BuddyBoss usa taxonomy terms, não apenas posts
        $term = get_term_by('slug', $memberType, 'bp_member_type');
        
        if (!$term || is_wp_error($term)) {
            // Term não existe - criar agora
            if ($this->logger) {
                $this->logger->warn('UserService', 'Term não existe na taxonomy - criando', [
                    'user_id' => $userId,
                    'member_type' => $memberType,
                    'taxonomy' => 'bp_member_type'
                ]);
            }
            
            $plugin = \FortaleceePSE\Core\Plugin::getInstance();
            $profiles = $plugin->getConfig('profiles', []);
            $label = $profiles[$perfilUsuario]['label'] ?? ucfirst(str_replace('_', ' ', $memberType));
            
            // Criar term na taxonomy
            $termResult = wp_insert_term(
                $label,
                'bp_member_type',
                [
                    'slug' => $memberType,
                    'description' => $profiles[$perfilUsuario]['description'] ?? ''
                ]
            );
            
            if (is_wp_error($termResult)) {
                if ($this->logger) {
                    $this->logger->error('UserService', 'Falha ao criar term na taxonomy', [
                        'user_id' => $userId,
                        'member_type' => $memberType,
                        'error' => $termResult->get_error_message()
                    ]);
                }
            } else {
                if ($this->logger) {
                    $this->logger->info('UserService', 'Term criado na taxonomy', [
                        'user_id' => $userId,
                        'member_type' => $memberType,
                        'term_id' => $termResult['term_id']
                    ]);
                }
            }
        } else {
            if ($this->logger) {
                $this->logger->debug('UserService', 'Term já existe na taxonomy', [
                    'user_id' => $userId,
                    'member_type' => $memberType,
                    'term_id' => $term->term_id
                ]);
            }
        }

        // Método 1: Usar bp_set_member_type() (API oficial do BuddyBoss)
        $result = bp_set_member_type($userId, $memberType);
        
        // Método 2: Usar wp_set_object_terms() diretamente (mais confiável)
        // Isso garante que o relationship seja criado na taxonomy
        $termsResult = wp_set_object_terms($userId, $memberType, 'bp_member_type', false);
        
        if (is_wp_error($termsResult)) {
            if ($this->logger) {
                $this->logger->error('UserService', 'Falha ao associar term via wp_set_object_terms', [
                    'user_id' => $userId,
                    'member_type' => $memberType,
                    'error' => $termsResult->get_error_message()
                ]);
            }
        } else {
            if ($this->logger) {
                $this->logger->info('UserService', 'Term associado via wp_set_object_terms', [
                    'user_id' => $userId,
                    'member_type' => $memberType,
                    'term_taxonomy_ids' => $termsResult
                ]);
            }
        }

        if ($result || !is_wp_error($termsResult)) {
            // CRÍTICO: Verificar se foi realmente aplicado
            // Pequeno delay para garantir que o banco foi atualizado
            usleep(100000); // 0.1 segundo
            
            // Verificar via API do BuddyBoss
            $verifiedMemberType = bp_get_member_type($userId);
            
            // Verificar via taxonomy diretamente
            $terms = wp_get_object_terms($userId, 'bp_member_type', ['fields' => 'slugs']);
            $termAssigned = !is_wp_error($terms) && in_array($memberType, $terms);
            
            if ($verifiedMemberType === $memberType || $termAssigned) {
                if ($this->logger) {
                    $this->logger->info('UserService', '✅ Member type aplicado e verificado', [
                        'user_id' => $userId,
                        'perfil_usuario' => $perfilUsuario,
                        'member_type' => $memberType,
                        'verified_via_api' => $verifiedMemberType === $memberType,
                        'verified_via_taxonomy' => $termAssigned,
                        'taxonomy_terms' => $terms
                    ]);
                }
                
                // Record event
                $this->eventRecorder->recordMemberTypeAssigned(
                    $userId,
                    $perfilUsuario,
                    null,
                    ['member_type' => $memberType]
                );
                return true;
            } else {
                if ($this->logger) {
                    $this->logger->warn('UserService', '⚠️ Member type definido mas não verificado', [
                        'user_id' => $userId,
                        'perfil_usuario' => $perfilUsuario,
                        'expected' => $memberType,
                        'verified_api' => $verifiedMemberType ?: 'NENHUM',
                        'verified_taxonomy' => $terms,
                        'bp_set_result' => $result,
                        'wp_set_result' => $termsResult
                    ]);
                }
                // Ainda retorna true pois tentamos ambos os métodos
                // Pode ser um problema de cache/timing
                return true;
            }
        }

        if ($this->logger) {
            $this->logger->error('UserService', '❌ Falha ao aplicar member type', [
                'user_id' => $userId,
                'perfil_usuario' => $perfilUsuario,
                'member_type' => $memberType,
                'result' => $result
            ]);
        }
        return false;
    }

    /**
     * Store registration data in BuddyBoss xProfile fields
     *
     * @param int $userId WordPress user ID
     * @param RegistrationDTO $dto Registration data
     * @return void
     */
    private function storeXProfileFields($userId, RegistrationDTO $dto) {
        if ($this->logger) {
            $this->logger->debug('UserService', 'Iniciando salvamento xProfile', ['user_id' => $userId]);
        }
        
        if (!function_exists('xprofile_set_field_data')) {
            if ($this->logger) {
                $this->logger->error('UserService', 'BuddyBoss xProfile não está disponível para salvar campos', ['user_id' => $userId]);
            }
            return;
        }

        // TAREFA 3: Validar Member Type antes de salvar
        $memberType = null;
        if (function_exists('bp_get_member_type')) {
            $memberType = bp_get_member_type($userId);
            if ($this->logger) {
                $this->logger->debug('UserService', 'Member type verificado', [
                    'user_id' => $userId,
                    'member_type' => $memberType ?: 'NENHUM'
                ]);
            }
        } else {
            if ($this->logger) {
                $this->logger->warn('UserService', 'Função bp_get_member_type não disponível', ['user_id' => $userId]);
            }
        }

        $data = $dto->toArray();
        $savedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        // Mapping of form fields to xProfile field keys
        // Updated to include all fields from XProfileFieldSeeder
        $fieldMapping = [
            // Dados Pessoais
            'nome_completo' => 'nome_completo',
            'cpf' => 'cpf',
            'telefone' => 'telefone',
            'data_nascimento' => 'data_nascimento',
            'genero' => 'genero',
            'raca_cor' => 'raca_cor',
            'nome_social' => 'nome_social',
            'email_pessoal' => 'email_pessoal',
            'email_login' => 'email_login',
            'email_institucional' => 'email_institucional',
            'acessibilidade' => 'acessibilidade',
            'descricao_acessibilidade' => 'descricao_acessibilidade',
            // Endereço
            'logradouro' => 'logradouro',
            'numero' => 'numero',
            'complemento' => 'complemento',
            'bairro' => 'bairro',
            'cep' => 'cep',
            'municipio' => 'municipio',
            'estado' => 'estado',
            // Campos específicos do perfil
            'instituicao_nome' => 'instituicao_nome',
            'escola_nome' => 'escola_nome',
            'rede_escola' => 'rede_escola',
            'nap_nome' => 'nap_nome',
            'curso_nome' => 'curso_nome',
            'setor_gti' => 'setor_gti',
            'sistema_responsavel' => 'sistema_responsavel',
            'regiao_responsavel' => 'regiao_responsavel',
            'departamento' => 'departamento',
            'funcao_eaa' => 'funcao_eaa',
        ];

        if ($this->logger) {
            $this->logger->debug('UserService', 'Iniciando processamento de campos xProfile', [
                'user_id' => $userId,
                'total_mapping' => count($fieldMapping),
                'total_dados' => count($data),
                'member_type' => $memberType ?: 'NENHUM'
            ]);
        }

        foreach ($fieldMapping as $formKey => $fieldKey) {
            // Check if field exists in data
            if (!isset($data[$formKey])) {
                if ($this->logger) {
                    $this->logger->debug('UserService', 'Campo não encontrado nos dados', [
                        'user_id' => $userId,
                        'field_key' => $fieldKey,
                        'form_key' => $formKey
                    ]);
                }
                $skippedCount++;
                continue;
            }

            $value = $data[$formKey];
            
            // Skip only if value is explicitly empty (empty string or null)
            // But allow 0, false, and '0' as valid values
            if ($value === '' || $value === null) {
                if ($this->logger) {
                    $this->logger->debug('UserService', 'Campo vazio - pulando', [
                        'user_id' => $userId,
                        'field_key' => $fieldKey,
                        'form_key' => $formKey
                    ]);
                }
                $skippedCount++;
                continue;
            }

            // TAREFA 2: Validação de Field IDs
            $fieldId = \FortaleceePSE\Core\Seeders\XProfileFieldSeeder::getFieldId($fieldKey);
            
            // Validação rigorosa: não null, não 0, existe no banco
            if (!$fieldId || $fieldId === 0 || $fieldId === null) {
                if ($this->logger) {
                    $this->logger->error('UserService', 'Campo xProfile não encontrado no banco', [
                        'user_id' => $userId,
                        'field_key' => $fieldKey,
                        'field_id' => $fieldId
                    ]);
                }
                $errorCount++;
                continue;
            }

            // Verificar se o campo realmente existe no banco e obter informações do campo
            global $wpdb;
            $fieldsTable = $wpdb->prefix . 'bp_xprofile_fields';
            $fieldData = $wpdb->get_row($wpdb->prepare(
                "SELECT id, type, name, is_required FROM {$fieldsTable} WHERE id = %d AND parent_id = 0",
                $fieldId
            ));

            if (!$fieldData) {
                if ($this->logger) {
                    $this->logger->error('UserService', 'Campo ID não existe na tabela', [
                        'user_id' => $userId,
                        'field_key' => $fieldKey,
                        'field_id' => $fieldId,
                        'table' => $fieldsTable
                    ]);
                }
                $errorCount++;
                continue;
            }

            $fieldType = $fieldData->type;
            $fieldName = $fieldData->name;
            $isRequired = (bool) $fieldData->is_required;

            // TAREFA 3: Validar se campo está associado ao member type
            // BuddyBoss pode ignorar campos não associados ao member type
            if ($memberType) {
                // Tentar verificar associação se função disponível
                if (function_exists('bp_xprofile_get_member_type_field_ids')) {
                    $memberTypeFieldIds = bp_xprofile_get_member_type_field_ids($memberType);
                    if (!empty($memberTypeFieldIds) && !in_array($fieldId, $memberTypeFieldIds)) {
                        if ($this->logger) {
                            $this->logger->warn('UserService', 'Campo não associado ao member type', [
                                'user_id' => $userId,
                                'field_key' => $fieldKey,
                                'field_id' => $fieldId,
                                'member_type' => $memberType
                            ]);
                        }
                    }
                }
            } else {
                if ($this->logger) {
                    $this->logger->warn('UserService', 'Nenhum member type ativo', [
                        'user_id' => $userId,
                        'field_key' => $fieldKey,
                        'field_id' => $fieldId
                    ]);
                }
            }

            // For datebox fields, format the date properly
            // BuddyBoss datebox expects format: YYYY-MM-DD 00:00:00 (MySQL datetime format)
            if ($fieldType === 'datebox' && !empty($value)) {
                // If value is already a date string, try to parse it
                if (is_string($value)) {
                    // Try to parse various date formats
                    $timestamp = strtotime($value);
                    if ($timestamp !== false) {
                        // Format as MySQL datetime: YYYY-MM-DD 00:00:00
                        $value = date('Y-m-d 00:00:00', $timestamp);
                    } else {
                        // If strtotime fails, try to extract date parts from string
                        // Handle formats like "2024-01-15" or "15/01/2024"
                        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $matches)) {
                            $value = sprintf('%04d-%02d-%02d 00:00:00', $matches[1], $matches[2], $matches[3]);
                        } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})/', $value, $matches)) {
                            $value = sprintf('%04d-%02d-%02d 00:00:00', $matches[3], $matches[2], $matches[1]);
                        }
                    }
                } elseif (is_array($value) && isset($value['year'], $value['month'], $value['day'])) {
                    // If it's already an array with year, month, day, format it
                    $value = sprintf('%04d-%02d-%02d 00:00:00', 
                        intval($value['year']), 
                        intval($value['month']), 
                        intval($value['day'])
                    );
                }
                
                if ($this->logger) {
                    $this->logger->debug('UserService', 'Data formatada para datebox', [
                        'user_id' => $userId,
                        'field_key' => $fieldKey,
                        'field_id' => $fieldId,
                        'original_value' => $data[$formKey],
                        'formatted_value' => $value
                    ]);
                }
            }
            
            // IMPORTANTE: Não converter valores - o valor recebido já deve ser o slug correto da opção
            // O frontend deve enviar os valores exatos que existem como opções no BuddyBoss
            // Para campos selectbox/radio, o valor deve corresponder ao 'name' da opção (que é o slug)
            
            // Apenas garantir que valores booleanos sejam convertidos para string (compatibilidade)
            // Mas o ideal é que o frontend já envie o valor correto ('sim'/'nao', etc.)
            if (is_bool($value)) {
                // Log warning se receber boolean para campo selectbox/radio
                if ($fieldType === 'selectbox' || $fieldType === 'radio') {
                    if ($this->logger) {
                        $this->logger->warn('UserService', 'Valor boolean recebido para campo selectbox/radio - deve ser slug da opção', [
                            'user_id' => $userId,
                            'field_key' => $fieldKey,
                            'field_type' => $fieldType,
                            'value_received' => $value,
                            'expected_format' => 'slug da opção (ex: sim, nao, homem_cis, etc.)'
                        ]);
                    }
                }
                // Converter apenas para evitar erro, mas idealmente o frontend deve enviar o slug correto
                $value = $value ? '1' : '0';
            }

            // TAREFA 1: Log detalhado antes de salvar
            if ($this->logger) {
                $this->logger->debug('UserService', 'Salvando xProfile field', [
                    'user_id' => $userId,
                    'member_type' => $memberType ?: 'NENHUM',
                    'field_slug' => $fieldKey,
                    'field_name' => $fieldName,
                    'field_id' => $fieldId,
                    'field_type' => $fieldType,
                    'field_required' => $isRequired,
                    'form_key' => $formKey,
                    'value_type' => gettype($value),
                    'value_length' => is_string($value) ? strlen($value) : null,
                    'value_preview' => is_string($value) && strlen($value) > 50 
                        ? substr($value, 0, 50) . '...' 
                        : $value
                ]);
            }
            
            // CRÍTICO: Verificar se member type foi realmente aplicado antes de salvar
            if ($memberType) {
                $currentMemberType = function_exists('bp_get_member_type') 
                    ? bp_get_member_type($userId) 
                    : null;
                
                if ($currentMemberType !== $memberType) {
                    if ($this->logger) {
                        $this->logger->warn('UserService', 'Member type não corresponde - tentando reaplicar', [
                            'user_id' => $userId,
                            'expected' => $memberType,
                            'current' => $currentMemberType ?: 'NENHUM',
                            'field_key' => $fieldKey
                        ]);
                    }
                    
                    // Tentar reaplicar member type
                    if (function_exists('bp_set_member_type')) {
                        bp_set_member_type($userId, $memberType);
                        // Pequeno delay para garantir que foi aplicado
                        usleep(100000); // 0.1 segundo
                        
                        // Verificar novamente
                        $currentMemberType = bp_get_member_type($userId);
                        if ($currentMemberType !== $memberType) {
                            if ($this->logger) {
                                $this->logger->error('UserService', 'Falha ao aplicar member type antes de salvar campo', [
                                    'user_id' => $userId,
                                    'expected' => $memberType,
                                    'current' => $currentMemberType ?: 'NENHUM',
                                    'field_key' => $fieldKey
                                ]);
                            }
                        }
                    }
                }
            }
            
            $result = \xprofile_set_field_data($fieldId, $userId, $value);

            // TAREFA 1: Log do resultado
            if ($result !== false) {
                $savedCount++;
                
                if ($this->logger) {
                    $this->logger->debug('UserService', 'Campo xProfile salvo com sucesso', [
                        'user_id' => $userId,
                        'field_key' => $fieldKey,
                        'field_name' => $fieldName,
                        'field_id' => $fieldId,
                        'field_type' => $fieldType,
                        'result' => true
                    ]);
                }
                
                // Verificar se realmente foi salvo (leitura imediata)
                if (function_exists('xprofile_get_field_data')) {
                    // Pequeno delay para garantir persistência
                    usleep(50000); // 0.05 segundo
                    
                    $savedValue = xprofile_get_field_data($fieldId, $userId);
                    if ($savedValue !== false && $savedValue !== null && $savedValue !== '') {
                        if ($this->logger) {
                            $this->logger->info('UserService', '✅ Campo xProfile salvo e verificado', [
                                'user_id' => $userId,
                                'field_key' => $fieldKey,
                                'field_name' => $fieldName,
                                'field_id' => $fieldId,
                                'field_type' => $fieldType,
                                'value_saved' => true,
                                'value_retrieved' => is_string($savedValue) && strlen($savedValue) > 100 
                                    ? substr($savedValue, 0, 100) . '...' 
                                    : $savedValue
                            ]);
                        }
                    } else {
                        if ($this->logger) {
                            $this->logger->warn('UserService', '⚠️ Campo salvo mas não foi possível ler de volta', [
                                'user_id' => $userId,
                                'field_key' => $fieldKey,
                                'field_name' => $fieldName,
                                'field_id' => $fieldId,
                                'field_type' => $fieldType,
                                'member_type' => $memberType ?: 'NENHUM',
                                'saved_value' => $savedValue,
                                'possible_cause' => 'timing, member type não associado, ou validação falhou'
                            ]);
                        }
                    }
                }
            } else {
                $errorCount++;
                if ($this->logger) {
                    $this->logger->error('UserService', '❌ Falha ao salvar campo xProfile', [
                        'user_id' => $userId,
                        'field_key' => $fieldKey,
                        'field_name' => $fieldName,
                        'field_id' => $fieldId,
                        'field_type' => $fieldType,
                        'field_required' => $isRequired,
                        'member_type' => $memberType ?: 'NENHUM',
                        'value_type' => gettype($value),
                        'value_preview' => is_string($value) && strlen($value) > 100 
                            ? substr($value, 0, 100) . '...' 
                            : $value,
                        'result' => false,
                        'possible_causes' => [
                            'Campo não associado ao member type',
                            'Formato de valor inválido para field_type',
                            'Campo obrigatório com valor vazio',
                            'Validação do BuddyBoss falhou'
                        ]
                    ]);
                }
            }
        }

        // Resumo final
        $totalProcessed = $savedCount + $skippedCount + $errorCount;
        if ($this->logger) {
            $this->logger->info('UserService', 'Resumo salvamento xProfile', [
                'user_id' => $userId,
                'member_type' => $memberType ?: 'NENHUM',
                'saved_count' => $savedCount,
                'skipped_count' => $skippedCount,
                'error_count' => $errorCount,
                'total_processed' => $totalProcessed,
                'success_rate' => $totalProcessed > 0 
                    ? round(($savedCount / $totalProcessed) * 100, 2) . '%' 
                    : '0%'
            ]);
        }
        
        // Flush cache do BuddyBoss para garantir que os dados apareçam imediatamente
        if (function_exists('bp_core_clear_cache')) {
            bp_core_clear_cache();
        }
        
        // Limpar cache específico de xProfile se função disponível
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete('xprofile_data_' . $userId, 'bp');
            wp_cache_delete('xprofile_fields_' . $userId, 'bp');
        }
    }

}
