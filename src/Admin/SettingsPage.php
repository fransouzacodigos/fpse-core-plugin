<?php
/**
 * Admin Settings Page
 *
 * Provides WordPress Admin interface for plugin configuration
 *
 * @package FortaleceePSE
 * @subpackage Admin
 */

namespace FortaleceePSE\Core\Admin;

class SettingsPage {
    /**
     * @var string
     */
    private $optionGroup = 'fpse_settings';

    /**
     * @var string
     */
    private $optionName = 'fpse_cors_origins';

    /**
     * @var string
     */
    private $pageSlug = 'fpse-settings';

    /**
     * Initialize settings page
     *
     * @return void
     */
    public function init() {
        add_action('admin_menu', [$this, 'addSettingsPage']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_init', [$this, 'handleSeedersActions']);
        add_action('admin_init', [$this, 'handleRateLimitReset']);
        // Hook para processar o textarea após submit do formulário
        add_filter('pre_update_option_' . $this->optionName, [$this, 'processTextareaInput'], 10, 2);
    }

    /**
     * Process textarea input before WordPress sanitizes it
     *
     * WordPress Settings API doesn't handle textarea well for array options,
     * so we intercept and process manually
     *
     * @param mixed $value New value
     * @param mixed $oldValue Old value
     * @return array Processed array
     */
    public function processTextareaInput($value, $oldValue) {
        // Se estiver vindo do POST e for string (textarea), processar
        if (isset($_POST[$this->optionName]) && is_string($_POST[$this->optionName])) {
            return $this->sanitizeCorsOrigins($_POST[$this->optionName]);
        }
        
        // Se já for array válido, usar
        if (is_array($value)) {
            return $this->sanitizeCorsOrigins($value);
        }
        
        // Caso contrário, retornar valor antigo
        return $oldValue;
    }

    /**
     * Add settings page to WordPress admin menu
     *
     * Action: admin_menu
     *
     * @return void
     */
    public function addSettingsPage() {
        add_submenu_page(
            'fpse-dashboard',
            __('Configurações FPSE Core', 'fpse-core'),
            __('Configurações', 'fpse-core'),
            'manage_options',
            $this->pageSlug,
            [$this, 'renderSettingsPage']
        );
    }

    /**
     * Register settings
     *
     * Action: admin_init
     *
     * @return void
     */
    public function registerSettings() {
        // Register setting with custom sanitize callback
        register_setting(
            $this->optionGroup,
            $this->optionName,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeCorsOrigins'],
                'default' => [],
            ]
        );

        add_settings_section(
            'fpse_cors_section',
            __('Configuração de CORS', 'fpse-core'),
            [$this, 'renderCorsSectionDescription'],
            $this->pageSlug
        );

        add_settings_field(
            'fpse_cors_origins',
            __('Origens Permitidas (CORS)', 'fpse-core'),
            [$this, 'renderCorsOriginsField'],
            $this->pageSlug,
            'fpse_cors_section'
        );
    }

    /**
     * Handle seeder actions (create groups, register member types)
     *
     * Action: admin_init
     *
     * @return void
     */
    /**
     * Handle rate limit reset action
     *
     * Action: admin_init
     *
     * @return void
     */
    public function handleRateLimitReset() {
        if (!isset($_POST['fpse_reset_rate_limit']) || !isset($_POST['fpse_rate_limit_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['fpse_rate_limit_nonce'], 'fpse_reset_rate_limit')) {
            wp_die(__('Ação não autorizada.', 'fpse-core'));
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para executar esta ação.', 'fpse-core'));
        }

        // Reset all rate limit transients
        global $wpdb;
        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_fpse_rate_%' OR option_name LIKE '_transient_timeout_fpse_rate_%'"
        );

        // Set success message
        set_transient('fpse_rate_limit_reset', [
            'success' => true,
            'message' => sprintf(__('Rate limit resetado com sucesso! %d entradas removidas.', 'fpse-core'), $deleted),
        ], 30);

        // Redirect
        wp_redirect(add_query_arg(['settings-updated' => 'true'], admin_url('admin.php?page=' . $this->pageSlug)));
        exit;
    }

    /**
     * Create xProfile fields via admin action
     *
     * @return void
     */
    private function createXProfileFields() {
        if (!function_exists('xprofile_insert_field_group')) {
            set_transient('fpse_seeder_result', [
                'success' => false,
                'message' => __('Erro: BuddyBoss xProfile não está disponível. Certifique-se de que o BuddyBoss está ativo.', 'fpse-core'),
            ], 30);
            return;
        }

        $seeder = new \FortaleceePSE\Core\Seeders\XProfileFieldSeeder();
        $result = $seeder->seed();

        if (!empty($result['errors'])) {
            set_transient('fpse_seeder_result', [
                'success' => false,
                'message' => sprintf(
                    __('Erro ao criar campos xProfile: %s', 'fpse-core'),
                    implode(', ', $result['errors'])
                ),
            ], 30);
        } else {
            $created = count($result['created']);
            $updated = count($result['updated']);
            set_transient('fpse_seeder_result', [
                'success' => true,
                'message' => sprintf(
                    __('Campos xProfile criados com sucesso! %d criados, %d atualizados.', 'fpse-core'),
                    $created,
                    $updated
                ),
            ], 30);
        }

        // Redirect
        wp_redirect(add_query_arg(['settings-updated' => 'true'], admin_url('admin.php?page=' . $this->pageSlug)));
        exit;
    }

    /**
     * Remove existing FPSE xProfile fields via admin action
     *
     * @return void
     */
    private function removeXProfileFields() {
        if (!function_exists('xprofile_get_field_groups')) {
            set_transient('fpse_seeder_result', [
                'success' => false,
                'message' => __('Erro: BuddyBoss xProfile não está disponível.', 'fpse-core'),
            ], 30);
            return;
        }

        $seeder = new \FortaleceePSE\Core\Seeders\XProfileFieldSeeder();
        $result = $seeder->removeExistingFields();

        if (!empty($result['errors'])) {
            set_transient('fpse_seeder_result', [
                'success' => false,
                'message' => sprintf(
                    __('Erro ao remover campos: %s', 'fpse-core'),
                    implode(', ', $result['errors'])
                ),
            ], 30);
        } else {
            $removed = count($result['removed']);
            set_transient('fpse_seeder_result', [
                'success' => true,
                'message' => sprintf(
                    __('%d campos xProfile FPSE removidos com sucesso! Campos nativos do BuddyBoss foram preservados.', 'fpse-core'),
                    $removed
                ),
            ], 30);
        }

        // Redirect
        wp_redirect(add_query_arg(['settings-updated' => 'true'], admin_url('admin.php?page=' . $this->pageSlug)));
        exit;
    }

    public function handleSeedersActions() {
        // Check if action was submitted
        if (!isset($_POST['fpse_action']) || !isset($_POST['fpse_seeder_nonce'])) {
            return;
        }

        // Verify nonce
        $action = sanitize_text_field($_POST['fpse_action']);
        
        if ($action === 'create_state_groups') {
            if (!wp_verify_nonce($_POST['fpse_seeder_nonce'], 'fpse_create_state_groups')) {
                return;
            }
            $this->createStateGroups();
        } elseif ($action === 'register_member_types') {
            if (!wp_verify_nonce($_POST['fpse_seeder_nonce'], 'fpse_register_member_types')) {
                return;
            }
            $this->registerMemberTypes();
        } elseif ($action === 'create_xprofile_fields') {
            if (!wp_verify_nonce($_POST['fpse_seeder_nonce'], 'fpse_create_xprofile_fields')) {
                return;
            }
            $this->createXProfileFields();
        } elseif ($action === 'remove_xprofile_fields') {
            if (!wp_verify_nonce($_POST['fpse_seeder_nonce'], 'fpse_remove_xprofile_fields')) {
                return;
            }
            $this->removeXProfileFields();
        }
    }

    /**
     * Create state groups via admin action
     *
     * @return void
     */
    private function createStateGroups() {
        if (!function_exists('groups_create_group')) {
            set_transient('fpse_seeder_result', [
                'success' => false,
                'message' => __('Erro: BuddyBoss não está ativo ou não está carregado.', 'fpse-core'),
            ], 30);
            return;
        }

        $plugin = \FortaleceePSE\Core\Plugin::getInstance();
        $states = $plugin->getConfig('states', []);

        if (empty($states)) {
            set_transient('fpse_seeder_result', [
                'success' => false,
                'message' => __('Erro: Nenhum estado configurado.', 'fpse-core'),
            ], 30);
            return;
        }

        $seeder = new \FortaleceePSE\Core\Seeders\StateGroupSeeder($states);
        $result = $seeder->seed();

        $created = count($result['created'] ?? []);
        $updated = count($result['updated'] ?? []);
        $errors = count($result['errors'] ?? []);

        if ($created > 0 || $updated > 0) {
            $message = sprintf(
                __('✅ Criados %d grupos. Atualizados %d grupos.', 'fpse-core'),
                $created,
                $updated
            );
            
            if ($errors > 0) {
                $message .= ' ' . sprintf(__('⚠️ %d erros.', 'fpse-core'), $errors);
            }

            set_transient('fpse_seeder_result', [
                'success' => true,
                'message' => $message,
            ], 30);
        } else {
            set_transient('fpse_seeder_result', [
                'success' => false,
                'message' => __('Nenhum grupo foi criado ou atualizado. Verifique se o BuddyBoss está ativo.', 'fpse-core'),
            ], 30);
        }

        // Redirect to prevent resubmission
        wp_redirect(add_query_arg(['settings-updated' => 'true'], admin_url('admin.php?page=' . $this->pageSlug)));
        exit;
    }

    /**
     * Register member types via admin action
     *
     * @return void
     */
    private function registerMemberTypes() {
        if (!function_exists('bp_register_member_type')) {
            set_transient('fpse_seeder_result', [
                'success' => false,
                'message' => __('Erro: BuddyBoss não está ativo ou não está carregado.', 'fpse-core'),
            ], 30);
            return;
        }

        $plugin = \FortaleceePSE\Core\Plugin::getInstance();
        $profiles = $plugin->getConfig('profiles', []);

        if (empty($profiles)) {
            set_transient('fpse_seeder_result', [
                'success' => false,
                'message' => __('Erro: Nenhum perfil configurado.', 'fpse-core'),
            ], 30);
            return;
        }

        $seeder = new \FortaleceePSE\Core\Seeders\MemberTypeSeeder($profiles);
        $result = $seeder->register();

        $created = count($result['created'] ?? []);
        $registered = count($result['registered'] ?? []);
        $errors = count($result['errors'] ?? []);

        if ($created > 0 || $registered > 0) {
            $message = sprintf(
                __('✅ Criados %d member types. Registrados %d member types.', 'fpse-core'),
                $created,
                $registered
            );
            
            if ($errors > 0) {
                $message .= ' ' . sprintf(__('⚠️ %d erros.', 'fpse-core'), $errors);
            }

            set_transient('fpse_seeder_result', [
                'success' => true,
                'message' => $message,
            ], 30);
        } else {
            set_transient('fpse_seeder_result', [
                'success' => false,
                'message' => __('Nenhum member type foi criado. Verifique se o BuddyBoss está ativo.', 'fpse-core'),
            ], 30);
        }

        // Redirect to prevent resubmission
        wp_redirect(add_query_arg(['settings-updated' => 'true'], admin_url('admin.php?page=' . $this->pageSlug)));
        exit;
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function renderSettingsPage() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'fpse-core'));
        }

        // Load modern dashboard template
        $templatePath = FPSE_CORE_PATH . '/templates/admin-dashboard.php';
        
        if (file_exists($templatePath)) {
            include $templatePath;
            return;
        }

        // Fallback to old template if new one doesn't exist
        // Check if settings were saved
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'fpse_messages',
                'fpse_message',
                __('Configurações salvas com sucesso!', 'fpse-core'),
                'success'
            );
        }

        settings_errors('fpse_messages');
        
        // Show seeder action results
        $seederResult = get_transient('fpse_seeder_result');
        if ($seederResult !== false) {
            delete_transient('fpse_seeder_result');
            if (!empty($seederResult['success'])) {
                add_settings_error(
                    'fpse_messages',
                    'fpse_seeder_success',
                    $seederResult['message'],
                    'success'
                );
            } else {
                add_settings_error(
                    'fpse_messages',
                    'fpse_seeder_error',
                    $seederResult['message'],
                    'error'
                );
            }
            settings_errors('fpse_messages');
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <!-- BuddyBoss Seeders Section -->
            <div class="card" style="margin-bottom: 20px; padding: 20px;">
                <h2><?php _e('BuddyBoss - Grupos e Member Types', 'fpse-core'); ?></h2>
                <p><?php _e('Crie grupos estaduais e member types do BuddyBoss manualmente.', 'fpse-core'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Grupos Estaduais', 'fpse-core'); ?></th>
                        <td>
                            <p><?php _e('Cria 27 grupos estaduais (26 estados + DF) no BuddyBoss.', 'fpse-core'); ?></p>
                            <form method="post" action="">
                                <?php wp_nonce_field('fpse_create_state_groups', 'fpse_seeder_nonce'); ?>
                                <input type="hidden" name="fpse_action" value="create_state_groups">
                                <button type="submit" class="button button-primary">
                                    <?php _e('Criar Grupos Estaduais', 'fpse-core'); ?>
                                </button>
                            </form>
                            <?php
                            // Show existing groups count
                            if (function_exists('groups_get_groups')) {
                                $groups = groups_get_groups([
                                    'per_page' => 100,
                                    'search_terms' => 'estado-',
                                ]);
                                $count = count($groups['groups'] ?? []);
                                if ($count > 0) {
                                    echo '<p class="description" style="margin-top: 10px;">';
                                    printf(
                                        __('<strong>%d grupos estaduais</strong> já existem.', 'fpse-core'),
                                        $count
                                    );
                                    echo '</p>';
                                }
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Member Types', 'fpse-core'); ?></th>
                        <td>
                            <p><?php _e('Cria member types como termos de taxonomia no banco de dados (persistem).', 'fpse-core'); ?></p>
                            <form method="post" action="">
                                <?php wp_nonce_field('fpse_register_member_types', 'fpse_seeder_nonce'); ?>
                                <input type="hidden" name="fpse_action" value="register_member_types">
                                <button type="submit" class="button button-primary">
                                    <?php _e('Criar Member Types', 'fpse-core'); ?>
                                </button>
                            </form>
                            <?php
                            // Show existing member types count (check posts)
                            if (post_type_exists('bp-member-type')) {
                                $posts = get_posts([
                                    'post_type' => 'bp-member-type',
                                    'post_status' => 'any',
                                    'meta_key' => '_bp_member_type_key',
                                    'meta_compare' => 'LIKE',
                                    'meta_value' => 'fpse_',
                                    'posts_per_page' => -1,
                                ]);
                                $count = count($posts);
                                if ($count > 0) {
                                    echo '<p class="description" style="margin-top: 10px;">';
                                    printf(
                                        __('<strong>%d member types FPSE</strong> já existem.', 'fpse-core'),
                                        $count
                                    );
                                    echo '</p>';
                                }
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Campos xProfile', 'fpse-core'); ?></th>
                        <td>
                            <p><?php _e('Cria campos customizados do BuddyBoss (xProfile) para armazenar dados do formulário de cadastro.', 'fpse-core'); ?></p>
                            <div style="margin-bottom: 10px;">
                                <form method="post" action="" style="display: inline-block; margin-right: 10px;">
                                    <?php wp_nonce_field('fpse_remove_xprofile_fields', 'fpse_seeder_nonce'); ?>
                                    <input type="hidden" name="fpse_action" value="remove_xprofile_fields">
                                    <button type="submit" class="button button-secondary" onclick="return confirm('<?php _e('Tem certeza? Isso removerá todos os campos xProfile do FPSE (mas manterá os campos nativos do BuddyBoss).', 'fpse-core'); ?>');">
                                        <?php _e('Remover Campos FPSE Existentes', 'fpse-core'); ?>
                                    </button>
                                </form>
                                <form method="post" action="" style="display: inline-block;">
                                    <?php wp_nonce_field('fpse_create_xprofile_fields', 'fpse_seeder_nonce'); ?>
                                    <input type="hidden" name="fpse_action" value="create_xprofile_fields">
                                    <button type="submit" class="button button-primary">
                                        <?php _e('Criar Campos xProfile', 'fpse-core'); ?>
                                    </button>
                                </form>
                            </div>
                            <?php
                            // Show existing xProfile fields count
                            if (function_exists('xprofile_get_field_groups')) {
                                global $wpdb;
                                $table = $wpdb->prefix . 'bp_xprofile_groups';
                                $groupId = $wpdb->get_var($wpdb->prepare(
                                    "SELECT id FROM {$table} WHERE name = %s",
                                    'Dados do Cadastro FPSE'
                                ));
                                
                                if ($groupId) {
                                    $fieldsTable = $wpdb->prefix . 'bp_xprofile_fields';
                                    $count = $wpdb->get_var($wpdb->prepare(
                                        "SELECT COUNT(*) FROM {$fieldsTable} WHERE group_id = %d AND parent_id = 0",
                                        $groupId
                                    ));
                                    
                                    if ($count > 0) {
                                        echo '<p class="description" style="margin-top: 10px;">';
                                        printf(
                                            __('<strong>%d campos xProfile FPSE</strong> já existem.', 'fpse-core'),
                                            $count
                                        );
                                        echo '</p>';
                                    }
                                }
                            }
                            ?>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Rate Limit Section -->
            <div class="card" style="margin-bottom: 20px; padding: 20px;">
                <h2><?php _e('Rate Limiting', 'fpse-core'); ?></h2>
                <p><?php _e('Gerencie o rate limiting do sistema. Útil para testes e desenvolvimento.', 'fpse-core'); ?></p>
                
                <?php
                $rateLimitReset = get_transient('fpse_rate_limit_reset');
                if ($rateLimitReset !== false) {
                    delete_transient('fpse_rate_limit_reset');
                    $class = $rateLimitReset['success'] ? 'notice-success' : 'notice-error';
                    echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($rateLimitReset['message']) . '</p></div>';
                }
                ?>
                
                <form method="post" action="">
                    <?php wp_nonce_field('fpse_reset_rate_limit', 'fpse_rate_limit_nonce'); ?>
                    <input type="hidden" name="fpse_reset_rate_limit" value="1">
                    <button type="submit" class="button button-secondary">
                        <?php _e('Resetar Rate Limit', 'fpse-core'); ?>
                    </button>
                    <p class="description">
                        <?php _e('Remove todos os contadores de rate limit. Use apenas para testes.', 'fpse-core'); ?>
                    </p>
                </form>
            </div>
            
            <!-- CORS Settings Section -->
            <form action="options.php" method="post">
                <?php
                settings_fields($this->optionGroup);
                do_settings_sections($this->pageSlug);
                submit_button(__('Salvar Configurações', 'fpse-core'));
                ?>
            </form>

            <div class="fpse-settings-help" style="margin-top: 30px; padding: 20px; background: #f0f0f1; border-left: 4px solid #2271b1;">
                <h2><?php _e('Como Usar', 'fpse-core'); ?></h2>
                <p><?php _e('Configure os domínios que podem fazer requisições para a API do WordPress.', 'fpse-core'); ?></p>
                
                <h3><?php _e('Formato:', 'fpse-core'); ?></h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php _e('Use protocolo completo: <code>https://exemplo.com</code> ou <code>http://localhost:5173</code>', 'fpse-core'); ?></li>
                    <li><?php _e('Um domínio por linha', 'fpse-core'); ?></li>
                    <li><?php _e('Para desenvolvimento local, use: <code>http://localhost:5173</code> ou <code>http://127.0.0.1:5173</code>', 'fpse-core'); ?></li>
                    <li><?php _e('Para produção, use: <code>https://seu-dominio.com</code>', 'fpse-core'); ?></li>
                </ul>

                <h3><?php _e('Exemplos:', 'fpse-core'); ?></h3>
                <pre style="background: #fff; padding: 10px; border: 1px solid #ddd;">http://localhost:5173
http://localhost:3000
https://cadastro.fortalecepse.com.br
https://app.exemplo.com</pre>

                <h3><?php _e('Segurança:', 'fpse-core'); ?></h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <li><?php _e('Liste apenas domínios que você controla', 'fpse-core'); ?></li>
                    <li><?php _e('Não use <code>*</code> (wildcard) em produção', 'fpse-core'); ?></li>
                    <li><?php _e('Use <code>https://</code> em produção', 'fpse-core'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    /**
     * Render CORS section description
     *
     * @return void
     */
    public function renderCorsSectionDescription() {
        echo '<p>' . esc_html__('Configure os domínios que podem acessar a API REST do plugin via CORS.', 'fpse-core') . '</p>';
    }

    /**
     * Render CORS origins field
     *
     * @return void
     */
    public function renderCorsOriginsField() {
        $origins = get_option($this->optionName, []);
        $originsString = is_array($origins) ? implode("\n", $origins) : '';
        
        // Se estiver vazio, mostrar origens padrão de desenvolvimento
        if (empty($originsString)) {
            $originsString = "http://localhost:5173\nhttp://localhost:3000\nhttp://127.0.0.1:5173\nhttp://127.0.0.1:3000";
        }
        ?>
        <textarea 
            name="<?php echo esc_attr($this->optionName); ?>" 
            id="fpse_cors_origins" 
            rows="10" 
            cols="60" 
            class="large-text code"
            placeholder="https://exemplo.com&#10;http://localhost:5173"
        ><?php echo esc_textarea($originsString); ?></textarea>
        <p class="description">
            <?php _e('Digite um domínio por linha. Use protocolo completo (<code>https://</code> ou <code>http://</code>).', 'fpse-core'); ?>
        </p>
        <?php
    }

    /**
     * Sanitize CORS origins input
     *
     * Converts textarea input (one per line) to array
     * This is called by WordPress settings API or by processTextareaInput
     *
     * @param mixed $input Raw input from form (string from textarea or array)
     * @return array Sanitized array of origins
     */
    public function sanitizeCorsOrigins($input) {
        // Se for string (do textarea), quebrar em linhas
        if (is_string($input)) {
            $origins = explode("\n", $input);
        } elseif (is_array($input)) {
            // Se for array com um elemento string (WordPress às vezes faz isso)
            if (count($input) === 1 && is_string($input[0])) {
                $origins = explode("\n", $input[0]);
            } else {
                // Se já for array de strings, usar diretamente
                $origins = $input;
            }
        } else {
            $origins = [];
        }

        $sanitized = [];

        foreach ($origins as $origin) {
            // Remover espaços e quebras de linha
            $origin = trim($origin);
            
            // Pular linhas vazias
            if (empty($origin)) {
                continue;
            }

            // Validar formato básico
            // Deve começar com http:// ou https://
            if (!preg_match('/^https?:\/\//', $origin)) {
                // Tentar corrigir se usuário esqueceu protocolo
                if (preg_match('/^[a-zA-Z0-9.-]+/', $origin)) {
                    // Adicionar https:// por padrão
                    $origin = 'https://' . $origin;
                } else {
                    // Formato inválido, pular
                    continue;
                }
            }

            // Remover barras finais
            $origin = rtrim($origin, '/');

            // Sanitizar (mas manter URL válida)
            $sanitizedOrigin = esc_url_raw($origin, ['http', 'https']);
            
            // Se sanitização manteve o formato, adicionar
            if (!empty($sanitizedOrigin) && filter_var($sanitizedOrigin, FILTER_VALIDATE_URL)) {
                $sanitized[] = $sanitizedOrigin;
            }
        }

        // Remover duplicatas
        $sanitized = array_unique($sanitized);

        // Reindexar array
        return array_values($sanitized);
    }

    /**
     * Get configured CORS origins
     *
     * Returns configured origins from database, or defaults if not set
     *
     * @return array Array of allowed origins
     */
    public static function getCorsOrigins() {
        $optionName = 'fpse_cors_origins';
        $origins = get_option($optionName, []);

        // Se não houver configuração salva, usar padrões de desenvolvimento
        if (empty($origins) || !is_array($origins)) {
            return [
                'http://localhost:5173',
                'http://localhost:3000',
                'http://127.0.0.1:5173',
                'http://127.0.0.1:3000',
            ];
        }

        return $origins;
    }
}
