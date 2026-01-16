<?php
/**
 * BuddyBoss xProfile Field Seeder
 *
 * Creates custom xProfile fields for storing registration form data
 *
 * @package FortaleceePSE
 * @subpackage Seeders
 */

namespace FortaleceePSE\Core\Seeders;

class XProfileFieldSeeder {
    /**
     * Field definitions mapping form fields to xProfile fields
     *
     * @var array
     */
    private $fieldDefinitions = [
        // Dados Pessoais
        'cpf' => [
            'name' => 'CPF',
            'description' => 'CPF do usuário',
            'type' => 'textbox',
            'is_required' => true,
            'can_delete' => false,
        ],
        'telefone' => [
            'name' => 'Telefone',
            'description' => 'Telefone com DDD',
            'type' => 'textbox',
            'is_required' => true,
            'can_delete' => false,
        ],
        'data_nascimento' => [
            'name' => 'Data de Nascimento',
            'description' => 'Data de nascimento do usuário',
            'type' => 'datebox',
            'is_required' => true,
            'can_delete' => false,
        ],
        'genero' => [
            'name' => 'Gênero',
            'description' => 'Gênero do usuário',
            'type' => 'selectbox',
            'is_required' => true,
            'can_delete' => false,
            'options' => [
                'mulher' => 'Mulher',
                'homem' => 'Homem',
                'nao-binario' => 'Não-binário',
                'trans-travesti' => 'Trans/Travesti',
                'outro' => 'Outro',
                'prefiro-nao-informar' => 'Prefiro não informar',
            ],
        ],
        'raca_cor' => [
            'name' => 'Raça/Cor',
            'description' => 'Raça ou cor do usuário',
            'type' => 'selectbox',
            'is_required' => true,
            'can_delete' => false,
            'options' => [
                'branca' => 'Branca',
                'preta' => 'Preta',
                'parda' => 'Parda',
                'amarela' => 'Amarela',
                'indigena' => 'Indígena',
            ],
        ],
        'nome_social' => [
            'name' => 'Nome Social',
            'description' => 'Nome social do usuário',
            'type' => 'textbox',
            'is_required' => false,
            'can_delete' => false,
        ],
        
        // Endereço
        'logradouro' => [
            'name' => 'Logradouro',
            'description' => 'Rua, avenida, etc.',
            'type' => 'textbox',
            'is_required' => true,
            'can_delete' => false,
        ],
        'numero' => [
            'name' => 'Número',
            'description' => 'Número do endereço',
            'type' => 'textbox',
            'is_required' => true,
            'can_delete' => false,
        ],
        'complemento' => [
            'name' => 'Complemento',
            'description' => 'Complemento do endereço',
            'type' => 'textbox',
            'is_required' => false,
            'can_delete' => false,
        ],
        'bairro' => [
            'name' => 'Bairro',
            'description' => 'Bairro',
            'type' => 'textbox',
            'is_required' => true,
            'can_delete' => false,
        ],
        'cep' => [
            'name' => 'CEP',
            'description' => 'CEP do endereço',
            'type' => 'textbox',
            'is_required' => true,
            'can_delete' => false,
        ],
        'municipio' => [
            'name' => 'Município',
            'description' => 'Município',
            'type' => 'textbox',
            'is_required' => true,
            'can_delete' => false,
        ],
        'estado' => [
            'name' => 'Estado',
            'description' => 'Estado (UF)',
            'type' => 'textbox',
            'is_required' => true,
            'can_delete' => false,
        ],
        
        // Campos específicos do perfil
        'instituicao_nome' => [
            'name' => 'Nome da IES',
            'description' => 'Nome da Instituição de Ensino Superior',
            'type' => 'textbox',
            'is_required' => false,
            'can_delete' => false,
        ],
        'escola_nome' => [
            'name' => 'Nome da Escola',
            'description' => 'Nome da escola',
            'type' => 'textbox',
            'is_required' => false,
            'can_delete' => false,
        ],
        'rede_escola' => [
            'name' => 'Rede da Escola',
            'description' => 'Rede da escola (municipal, estadual, federal, privada)',
            'type' => 'selectbox',
            'is_required' => false,
            'can_delete' => false,
            'options' => [
                'municipal' => 'Municipal',
                'estadual' => 'Estadual',
                'federal' => 'Federal',
                'privada' => 'Privada',
            ],
        ],
        'nap_nome' => [
            'name' => 'Número do NAP',
            'description' => 'Número do Núcleo de Acessibilidade Pedagógica',
            'type' => 'textbox',
            'is_required' => false,
            'can_delete' => false,
        ],
        'curso_nome' => [
            'name' => 'Curso',
            'description' => 'Nome do curso',
            'type' => 'textbox',
            'is_required' => false,
            'can_delete' => false,
        ],
        'setor_gti' => [
            'name' => 'Setor GTI',
            'description' => 'Setor de Gestão Tecnológica Inclusiva',
            'type' => 'textbox',
            'is_required' => false,
            'can_delete' => false,
        ],
        'sistema_responsavel' => [
            'name' => 'Sistema Responsável',
            'description' => 'Sistema pelo qual é responsável',
            'type' => 'textbox',
            'is_required' => false,
            'can_delete' => false,
        ],
        'regiao_responsavel' => [
            'name' => 'Região Responsável',
            'description' => 'Região pela qual é responsável',
            'type' => 'selectbox',
            'is_required' => false,
            'can_delete' => false,
            'options' => [
                'saude' => 'Saúde',
                'educacao' => 'Educação',
            ],
        ],
        'departamento' => [
            'name' => 'Departamento',
            'description' => 'Departamento MS/MEC',
            'type' => 'textbox',
            'is_required' => false,
            'can_delete' => false,
        ],
        'funcao_eaa' => [
            'name' => 'Função na EAA',
            'description' => 'Função na Educação de Adolescentes e Adultos',
            'type' => 'textbox',
            'is_required' => false,
            'can_delete' => false,
        ],
    ];

    /**
     * Create or update all xProfile fields
     *
     * @return array Result with 'created', 'updated', 'errors' keys
     */
    public function seed() {
        if (!function_exists('xprofile_insert_field_group')) {
            error_log('FPSE: BuddyBoss xProfile API not available');
            return [
                'created' => [],
                'updated' => [],
                'errors' => ['BuddyBoss xProfile não está disponível'],
            ];
        }

        // Create field group for FPSE fields
        $groupId = $this->getOrCreateFieldGroup();
        
        if (!$groupId) {
            return [
                'created' => [],
                'updated' => [],
                'errors' => ['Não foi possível criar o grupo de campos'],
            ];
        }

        $created = [];
        $updated = [];
        $errors = [];

        foreach ($this->fieldDefinitions as $fieldKey => $fieldData) {
            $result = $this->createOrUpdateField($groupId, $fieldKey, $fieldData);
            
            if ($result['success']) {
                if ($result['created']) {
                    $created[] = $fieldKey;
                } else {
                    $updated[] = $fieldKey;
                }
            } else {
                $errors[] = "{$fieldKey}: {$result['error']}";
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    /**
     * Get or create the FPSE field group
     *
     * @return int|false Group ID or false on failure
     */
    private function getOrCreateFieldGroup() {
        $groupName = 'Dados do Cadastro FPSE';
        
        // Use direct database access (more reliable than API functions)
        global $wpdb;
        $table = $wpdb->prefix . 'bp_xprofile_groups';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            error_log('FPSE: BuddyBoss xProfile groups table does not exist');
            return false;
        }
        
        // Check if group exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE name = %s",
            $groupName
        ));
        
        if ($existing) {
            return (int) $existing;
        }
        
        // Create new group using database directly
        $result = $wpdb->insert(
            $table,
            [
                'name' => $groupName,
                'description' => 'Campos do formulário de cadastro do Fortalece PSE',
                'group_order' => 1,
                'can_delete' => 0,
            ],
            ['%s', '%s', '%d', '%d']
        );
        
        if ($result === false) {
            error_log('FPSE: Failed to create xProfile group: ' . $wpdb->last_error);
            return false;
        }
        
        return $wpdb->insert_id;
    }

    /**
     * Create or update an xProfile field
     *
     * @param int $groupId Field group ID
     * @param string $fieldKey Field key (internal identifier)
     * @param array $fieldData Field configuration
     * @return array Result with 'success', 'created', 'error' keys
     */
    private function createOrUpdateField($groupId, $fieldKey, $fieldData) {
        // Check if field exists first
        $fieldId = get_option("fpse_xprofile_field_{$fieldKey}", false);
        
        // Try to find by name using direct database access if not found in options
        if (!$fieldId) {
            global $wpdb;
            $fieldsTable = $wpdb->prefix . 'bp_xprofile_fields';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '{$fieldsTable}'") === $fieldsTable) {
                $fieldId = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$fieldsTable} WHERE name = %s AND group_id = %d AND parent_id = 0 LIMIT 1",
                    $fieldData['name'],
                    $groupId
                ));
                
                if ($fieldId) {
                    update_option("fpse_xprofile_field_{$fieldKey}", $fieldId);
                }
            }
        }
        
        // Try to use BuddyBoss API if available
        if (function_exists('xprofile_insert_field')) {
            
            // Prepare field data for BuddyBoss API
            $fieldArgs = [
                'field_group_id' => $groupId,
                'name' => $fieldData['name'],
                'description' => $fieldData['description'] ?? '',
                'is_required' => $fieldData['is_required'],
                'type' => $fieldData['type'],
                'can_delete' => $fieldData['can_delete'],
            ];
            
            if ($fieldId) {
                $fieldArgs['field_id'] = $fieldId;
            }
            
            // Create or update field (use global namespace prefix)
            $newFieldId = \xprofile_insert_field($fieldArgs);
            
            if (is_wp_error($newFieldId)) {
                return [
                    'success' => false,
                    'created' => false,
                    'error' => $newFieldId->get_error_message(),
                ];
            }
            
            $fieldId = (int) $newFieldId;
            
            // Create options if selectbox
            if ($fieldData['type'] === 'selectbox' && isset($fieldData['options'])) {
                $this->createFieldOptions($fieldId, $fieldData['options']);
            }
            
            // Store field key as meta for reference
            update_option("fpse_xprofile_field_{$fieldKey}", $fieldId);
            
            return [
                'success' => true,
                'created' => !$fieldId || $fieldId != $newFieldId,
            ];
        }
        
        // Fallback: direct database access
        global $wpdb;
        $table = $wpdb->prefix . 'bp_xprofile_fields';
        
        // Check if field exists by name
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table} WHERE name = %s AND group_id = %d",
            $fieldData['name'],
            $groupId
        ));
        
        $fieldId = $existing ? (int) $existing->id : null;
        
        // Prepare field data
        $data = [
            'group_id' => $groupId,
            'parent_id' => 0,
            'type' => $fieldData['type'],
            'name' => $fieldData['name'],
            'description' => $fieldData['description'] ?? '',
            'is_required' => $fieldData['is_required'] ? 1 : 0,
            'is_default_option' => 0,
            'field_order' => 0,
            'option_order' => 0,
            'can_delete' => $fieldData['can_delete'] ? 1 : 0,
            'order_by' => 'default',
        ];
        
        $formats = ['%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s'];
        
        if ($fieldId) {
            // Update existing field
            $result = $wpdb->update(
                $table,
                $data,
                ['id' => $fieldId],
                $formats,
                ['%d']
            );
            
            if ($result === false) {
                return [
                    'success' => false,
                    'created' => false,
                    'error' => $wpdb->last_error,
                ];
            }
            
            // Update options if selectbox
            if ($fieldData['type'] === 'selectbox' && isset($fieldData['options'])) {
                $this->updateFieldOptions($fieldId, $fieldData['options']);
            }
            
            return [
                'success' => true,
                'created' => false,
            ];
        } else {
            // Create new field
            $result = $wpdb->insert($table, $data, $formats);
            
            if ($result === false) {
                return [
                    'success' => false,
                    'created' => false,
                    'error' => $wpdb->last_error,
                ];
            }
            
            $fieldId = $wpdb->insert_id;
            
            // Create options if selectbox
            if ($fieldData['type'] === 'selectbox' && isset($fieldData['options'])) {
                $this->createFieldOptions($fieldId, $fieldData['options']);
            }
            
            // Store field key as meta for reference
            update_option("fpse_xprofile_field_{$fieldKey}", $fieldId);
            
            return [
                'success' => true,
                'created' => true,
            ];
        }
    }

    /**
     * Create options for a selectbox field
     *
     * @param int $fieldId Field ID
     * @param array $options Options array (key => label)
     * @return void
     */
    private function createFieldOptions($fieldId, $options) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'bp_xprofile_fields';
        $order = 0;
        
        foreach ($options as $value => $label) {
            $order++;
            $wpdb->insert(
                $table,
                [
                    'group_id' => 0,
                    'parent_id' => $fieldId,
                    'type' => 'option',
                    'name' => $label,
                    'description' => '',
                    'is_required' => 0,
                    'is_default_option' => 0,
                    'field_order' => $order,
                    'option_order' => $order,
                    'can_delete' => 1,
                    'order_by' => 'custom',
                ],
                ['%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s']
            );
        }
    }

    /**
     * Update options for a selectbox field
     *
     * @param int $fieldId Field ID
     * @param array $options Options array (key => label)
     * @return void
     */
    private function updateFieldOptions($fieldId, $options) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'bp_xprofile_fields';
        
        // Delete existing options
        $wpdb->delete($table, ['parent_id' => $fieldId, 'type' => 'option'], ['%d', '%s']);
        
        // Create new options
        $this->createFieldOptions($fieldId, $options);
    }

    /**
     * Get field ID by field key
     *
     * @param string $fieldKey Field key
     * @return int|false Field ID or false if not found
     */
    public static function getFieldId($fieldKey) {
        $fieldId = get_option("fpse_xprofile_field_{$fieldKey}", false);
        
        if ($fieldId) {
            return (int) $fieldId;
        }
        
        // Fallback: search by name
        global $wpdb;
        $table = $wpdb->prefix . 'bp_xprofile_fields';
        
        // Map field key to field name
        $seeder = new self();
        if (!isset($seeder->fieldDefinitions[$fieldKey])) {
            return false;
        }
        
        $fieldName = $seeder->fieldDefinitions[$fieldKey]['name'];
        $fieldId = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE name = %s LIMIT 1",
            $fieldName
        ));
        
        if ($fieldId) {
            update_option("fpse_xprofile_field_{$fieldKey}", $fieldId);
            return (int) $fieldId;
        }
        
        return false;
    }

    /**
     * Remove existing FPSE xProfile fields (but keep native BuddyBoss fields)
     *
     * This method removes fields from the "Dados do Cadastro FPSE" group
     * and any fields that match FPSE field names, but preserves native BuddyBoss fields
     *
     * @return array Result with 'removed', 'errors' keys
     */
    public function removeExistingFields() {
        global $wpdb;
        $fieldsTable = $wpdb->prefix . 'bp_xprofile_fields';
        $groupsTable = $wpdb->prefix . 'bp_xprofile_groups';
        
        // Check if tables exist
        if ($wpdb->get_var("SHOW TABLES LIKE '{$groupsTable}'") !== $groupsTable ||
            $wpdb->get_var("SHOW TABLES LIKE '{$fieldsTable}'") !== $fieldsTable) {
            return [
                'removed' => [],
                'errors' => ['BuddyBoss xProfile não está disponível'],
            ];
        }
        
        $removed = [];
        $errors = [];

        // Find the FPSE field group
        $groupId = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$groupsTable} WHERE name = %s",
            'Dados do Cadastro FPSE'
        ));

        if ($groupId) {
            // Remove all fields from FPSE group
            $fields = $wpdb->get_results($wpdb->prepare(
                "SELECT id, name, type FROM {$fieldsTable} WHERE group_id = %d AND parent_id = 0",
                $groupId
            ));

            foreach ($fields as $field) {
                // Remove field options first (if any)
                $wpdb->delete($fieldsTable, ['parent_id' => $field->id], ['%d']);
                
                // Remove field data for all users
                $dataTable = $wpdb->prefix . 'bp_xprofile_data';
                $wpdb->delete($dataTable, ['field_id' => $field->id], ['%d']);
                
                // Remove the field itself
                $deleted = $wpdb->delete($fieldsTable, ['id' => $field->id], ['%d']);
                
                if ($deleted) {
                    $removed[] = $field->name;
                    // Remove option reference
                    delete_option("fpse_xprofile_field_" . array_search($field->name, array_column($this->fieldDefinitions, 'name')));
                } else {
                    $errors[] = "Falha ao remover campo: {$field->name}";
                }
            }

            // Remove the group itself
            $wpdb->delete($groupsTable, ['id' => $groupId], ['%d']);
            
            if (!empty($removed)) {
                error_log(sprintf(
                    'FPSE: Removidos %d campos xProfile do grupo FPSE',
                    count($removed)
                ));
            }
        }

        // Also remove any FPSE fields that might be in other groups (by name matching)
        // Only remove if they match FPSE field names AND are not in native BuddyBoss groups
        $fpseFieldNames = array_column($this->fieldDefinitions, 'name');
        
        // Get list of native BuddyBoss groups to preserve
        $nativeGroups = ['Base', 'Extended', 'Essenciais', 'Dados Complementares', 'Endereço Completo', 'Dados Bancários'];
        
        foreach ($fpseFieldNames as $fieldName) {
            $field = $wpdb->get_row($wpdb->prepare(
                "SELECT id, group_id FROM {$fieldsTable} WHERE name = %s AND parent_id = 0",
                $fieldName
            ));

            if ($field) {
                // Check which group this field belongs to
                $group = $wpdb->get_row($wpdb->prepare(
                    "SELECT name FROM {$groupsTable} WHERE id = %d",
                    $field->group_id
                ));

                // Only remove if it's NOT in a native BuddyBoss group
                // This ensures we preserve native BuddyBoss fields even if they have the same name
                if ($group && !in_array($group->name, $nativeGroups)) {
                    // Double-check: make sure this is actually an FPSE field by checking if it's in our definitions
                    $isFpseField = false;
                    foreach ($this->fieldDefinitions as $def) {
                        if ($def['name'] === $fieldName) {
                            $isFpseField = true;
                            break;
                        }
                    }
                    
                    if ($isFpseField) {
                        // Remove field options first (if any)
                        $wpdb->delete($fieldsTable, ['parent_id' => $field->id], ['%d']);
                        
                        // Remove field data for all users
                        $dataTable = $wpdb->prefix . 'bp_xprofile_data';
                        $wpdb->delete($dataTable, ['field_id' => $field->id], ['%d']);
                        
                        // Remove the field itself
                        $deleted = $wpdb->delete($fieldsTable, ['id' => $field->id], ['%d']);
                        
                        if ($deleted) {
                            $removed[] = $fieldName;
                            // Remove option reference if exists
                            $fieldKey = array_search($fieldName, array_column($this->fieldDefinitions, 'name'));
                            if ($fieldKey !== false) {
                                delete_option("fpse_xprofile_field_{$fieldKey}");
                            }
                        } else {
                            $errors[] = "Falha ao remover campo: {$fieldName}";
                        }
                    }
                }
            }
        }

        return [
            'removed' => $removed,
            'errors' => $errors,
        ];
    }
}
