<?php
/**
 * Admin Dashboard Template
 *
 * Modern dashboard with shadcn/ui look & feel using Tailwind CSS v4 and Chart.js
 *
 * @package FortaleceePSE
 * @subpackage Admin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get settings data
$corsOrigins = get_option('fpse_cors_origins', []);
$corsOriginsText = is_array($corsOrigins) ? implode("\n", $corsOrigins) : '';

// Get REST API URL for stats
$restUrl = rest_url('fpse/v1/stats');
$nonce = wp_create_nonce('wp_rest');
?>

<?php
// This template is included within WordPress admin, so we don't need full HTML structure
// We'll wrap it in a div and add styles inline
?>
<div class="fpse-dashboard-wrapper">
    <!-- Tailwind CSS v4 via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Chart.js via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        .fpse-dashboard-wrapper * {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .fpse-dashboard-wrapper {
            margin: -20px -20px 0 -2px;
            background: #fafafa;
            min-height: calc(100vh - 32px);
        }
        
        /* Custom scrollbar */
        .fpse-dashboard-wrapper ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        .fpse-dashboard-wrapper ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .fpse-dashboard-wrapper ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        
        .fpse-dashboard-wrapper ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Input focus styles */
        .fpse-dashboard-wrapper input:focus,
        .fpse-dashboard-wrapper textarea:focus {
            outline: none;
            ring: 2px;
            ring-color: #6366f1;
            border-color: #6366f1;
        }
        
        /* Button hover effects */
        .fpse-dashboard-wrapper button:hover {
            transition: all 0.2s;
        }
        
        /* Override WordPress admin styles */
        .fpse-dashboard-wrapper .wrap {
            margin: 0;
            padding: 0;
        }
    </style>
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white border-b border-zinc-200 sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <h1 class="text-2xl font-semibold text-zinc-900">
                    <?php echo esc_html(get_admin_page_title()); ?>
                </h1>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Settings Messages -->
            <?php if (isset($_GET['settings-updated'])): ?>
                <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-lg text-emerald-800">
                    <?php _e('Configurações salvas com sucesso!', 'fpse-core'); ?>
                </div>
            <?php endif; ?>

            <?php
            // Show seeder action results
            $seederResult = get_transient('fpse_seeder_result');
            if ($seederResult !== false):
                delete_transient('fpse_seeder_result');
                $bgColor = !empty($seederResult['success']) ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800';
            ?>
                <div class="mb-6 p-4 <?php echo esc_attr($bgColor); ?> border rounded-lg">
                    <?php echo esc_html($seederResult['message']); ?>
                </div>
            <?php endif; ?>

            <!-- Grid Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column: Settings Card -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Configuration Card -->
                    <div class="bg-white rounded-lg border border-zinc-200 shadow-sm">
                        <div class="p-6 border-b border-zinc-200">
                            <h2 class="text-lg font-semibold text-zinc-900">
                                <?php _e('Configurações', 'fpse-core'); ?>
                            </h2>
                            <p class="mt-1 text-sm text-zinc-500">
                                <?php _e('Configure os domínios permitidos para CORS', 'fpse-core'); ?>
                            </p>
                        </div>
                        
                        <form action="options.php" method="post" class="p-6 space-y-6">
                            <?php
                            settings_fields('fpse_settings');
                            // Note: do_settings_sections removed to avoid duplicate CORS field
                            ?>
                            
                            <div>
                                <label for="fpse_cors_origins" class="block text-sm font-medium text-zinc-700 mb-2">
                                    <?php _e('Origens CORS Permitidas', 'fpse-core'); ?>
                                </label>
                                <textarea
                                    id="fpse_cors_origins"
                                    name="fpse_cors_origins"
                                    rows="6"
                                    class="w-full px-3 py-2 border border-zinc-300 rounded-md shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm text-zinc-900 bg-white"
                                    placeholder="https://exemplo.com&#10;http://localhost:5173"
                                ><?php echo esc_textarea($corsOriginsText); ?></textarea>
                                <p class="mt-2 text-sm text-zinc-500">
                                    <?php _e('Um domínio por linha. Use protocolo completo (http:// ou https://)', 'fpse-core'); ?>
                                </p>
                            </div>

                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 font-medium text-sm transition-colors"
                                >
                                    <?php _e('Salvar Configurações', 'fpse-core'); ?>
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- BuddyBoss Actions Card -->
                    <div class="bg-white rounded-lg border border-zinc-200 shadow-sm">
                        <div class="p-6 border-b border-zinc-200">
                            <h2 class="text-lg font-semibold text-zinc-900">
                                <?php _e('BuddyBoss - Grupos e Member Types', 'fpse-core'); ?>
                            </h2>
                            <p class="mt-1 text-sm text-zinc-500">
                                <?php _e('Gerencie grupos estaduais, member types e campos xProfile', 'fpse-core'); ?>
                            </p>
                        </div>
                        
                        <div class="p-6 space-y-6">
                            <!-- State Groups -->
                            <div>
                                <h3 class="text-sm font-medium text-zinc-900 mb-2">
                                    <?php _e('Grupos Estaduais', 'fpse-core'); ?>
                                </h3>
                                <p class="text-sm text-zinc-500 mb-3">
                                    <?php _e('Cria 27 grupos estaduais (26 estados + DF) no BuddyBoss.', 'fpse-core'); ?>
                                </p>
                                <form method="post" action="" class="inline-block">
                                    <?php wp_nonce_field('fpse_create_state_groups', 'fpse_seeder_nonce'); ?>
                                    <input type="hidden" name="fpse_action" value="create_state_groups">
                                    <button
                                        type="submit"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 font-medium text-sm transition-colors"
                                    >
                                        <?php _e('Criar Grupos Estaduais', 'fpse-core'); ?>
                                    </button>
                                </form>
                                <?php
                                if (function_exists('groups_get_groups')) {
                                    $groups = groups_get_groups([
                                        'per_page' => 100,
                                        'search_terms' => 'estado-',
                                    ]);
                                    $count = count($groups['groups'] ?? []);
                                    if ($count > 0) {
                                        echo '<p class="mt-2 text-sm text-zinc-600"><strong>' . esc_html($count) . '</strong> ' . __('grupos estaduais já existem.', 'fpse-core') . '</p>';
                                    }
                                }
                                ?>
                            </div>

                            <!-- Member Types -->
                            <div>
                                <h3 class="text-sm font-medium text-zinc-900 mb-2">
                                    <?php _e('Member Types', 'fpse-core'); ?>
                                </h3>
                                <p class="text-sm text-zinc-500 mb-3">
                                    <?php _e('Cria member types como termos de taxonomia no banco de dados (persistem).', 'fpse-core'); ?>
                                </p>
                                <form method="post" action="" class="inline-block">
                                    <?php wp_nonce_field('fpse_register_member_types', 'fpse_seeder_nonce'); ?>
                                    <input type="hidden" name="fpse_action" value="register_member_types">
                                    <button
                                        type="submit"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 font-medium text-sm transition-colors"
                                    >
                                        <?php _e('Criar Member Types', 'fpse-core'); ?>
                                    </button>
                                </form>
                                <?php
                                if (post_type_exists('bp-member-type')) {
                                    $posts = get_posts([
                                        'post_type' => 'bp-member-type',
                                        'post_status' => 'any',
                                        'posts_per_page' => -1,
                                    ]);
                                    $count = count($posts);
                                    if ($count > 0) {
                                        echo '<p class="mt-2 text-sm text-zinc-600"><strong>' . esc_html($count) . '</strong> ' . __('member types FPSE já existem.', 'fpse-core') . '</p>';
                                    }
                                }
                                ?>
                            </div>

                            <!-- xProfile Fields -->
                            <div>
                                <h3 class="text-sm font-medium text-zinc-900 mb-2">
                                    <?php _e('Campos xProfile', 'fpse-core'); ?>
                                </h3>
                                <p class="text-sm text-zinc-500 mb-3">
                                    <?php _e('Cria campos customizados do BuddyBoss (xProfile) para armazenar dados do formulário de cadastro.', 'fpse-core'); ?>
                                </p>
                                <div class="flex gap-3">
                                    <form method="post" action="" class="inline-block">
                                        <?php wp_nonce_field('fpse_remove_xprofile_fields', 'fpse_seeder_nonce'); ?>
                                        <input type="hidden" name="fpse_action" value="remove_xprofile_fields">
                                        <button
                                            type="submit"
                                            onclick="return confirm('<?php _e('Tem certeza? Isso removerá todos os campos xProfile do FPSE (mas manterá os campos nativos do BuddyBoss).', 'fpse-core'); ?>');"
                                            class="px-4 py-2 bg-zinc-100 text-zinc-700 rounded-md hover:bg-zinc-200 focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 font-medium text-sm transition-colors"
                                        >
                                            <?php _e('Remover Campos FPSE', 'fpse-core'); ?>
                                        </button>
                                    </form>
                                    <form method="post" action="" class="inline-block">
                                        <?php wp_nonce_field('fpse_create_xprofile_fields', 'fpse_seeder_nonce'); ?>
                                        <input type="hidden" name="fpse_action" value="create_xprofile_fields">
                                        <button
                                            type="submit"
                                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 font-medium text-sm transition-colors"
                                        >
                                            <?php _e('Criar Campos xProfile', 'fpse-core'); ?>
                                        </button>
                                    </form>
                                </div>
                                <?php
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
                                            echo '<p class="mt-2 text-sm text-zinc-600"><strong>' . esc_html($count) . '</strong> ' . __('campos xProfile FPSE já existem.', 'fpse-core') . '</p>';
                                        }
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    </div>

                    <!-- Rate Limit Card -->
                    <div class="bg-white rounded-lg border border-zinc-200 shadow-sm">
                        <div class="p-6 border-b border-zinc-200">
                            <h2 class="text-lg font-semibold text-zinc-900">
                                <?php _e('Rate Limiting', 'fpse-core'); ?>
                            </h2>
                            <p class="mt-1 text-sm text-zinc-500">
                                <?php _e('Gerencie o rate limiting do sistema. Útil para testes e desenvolvimento.', 'fpse-core'); ?>
                            </p>
                        </div>
                        
                        <div class="p-6">
                            <?php
                            $rateLimitReset = get_transient('fpse_rate_limit_reset');
                            if ($rateLimitReset !== false):
                                delete_transient('fpse_rate_limit_reset');
                                $bgColor = $rateLimitReset['success'] ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800';
                            ?>
                                <div class="mb-4 p-4 <?php echo esc_attr($bgColor); ?> border rounded-lg">
                                    <?php echo esc_html($rateLimitReset['message']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <form method="post" action="">
                                <?php wp_nonce_field('fpse_reset_rate_limit', 'fpse_rate_limit_nonce'); ?>
                                <input type="hidden" name="fpse_reset_rate_limit" value="1">
                                <button
                                    type="submit"
                                    class="px-4 py-2 bg-zinc-100 text-zinc-700 rounded-md hover:bg-zinc-200 focus:outline-none focus:ring-2 focus:ring-zinc-500 focus:ring-offset-2 font-medium text-sm transition-colors"
                                >
                                    <?php _e('Resetar Rate Limit', 'fpse-core'); ?>
                                </button>
                                <p class="mt-2 text-sm text-zinc-500">
                                    <?php _e('Remove todos os contadores de rate limit. Use apenas para testes.', 'fpse-core'); ?>
                                </p>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Analytics Card -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg border border-zinc-200 shadow-sm sticky top-20">
                        <div class="p-6 border-b border-zinc-200">
                            <h2 class="text-lg font-semibold text-zinc-900">
                                <?php _e('Estatísticas', 'fpse-core'); ?>
                            </h2>
                            <p class="mt-1 text-sm text-zinc-500">
                                <?php _e('Dados do sistema em tempo real', 'fpse-core'); ?>
                        </div>
                        
                        <div class="p-6">
                            <!-- Chart Container -->
                            <div class="mb-6">
                                <canvas id="statsChart" class="w-full"></canvas>
                            </div>
                            
                            <!-- Stats Cards -->
                            <div id="statsCards" class="space-y-4">
                                <!-- Stats will be loaded here via JavaScript -->
                                <div class="animate-pulse space-y-4">
                                    <div class="h-20 bg-zinc-100 rounded-lg"></div>
                                    <div class="h-20 bg-zinc-100 rounded-lg"></div>
                                    <div class="h-20 bg-zinc-100 rounded-lg"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Configuration
        const REST_URL = <?php echo wp_json_encode($restUrl); ?>;
        const REST_NONCE = <?php echo wp_json_encode($nonce); ?>;

        // Fetch stats and render chart
        async function loadStats() {
            try {
                const response = await fetch(REST_URL, {
                    method: 'GET',
                    headers: {
                        'X-WP-Nonce': REST_NONCE,
                        'Content-Type': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to fetch stats');
                }

                const data = await response.json();

                // Render chart
                renderChart(data);

                // Render stats cards
                renderStatsCards(data);

            } catch (error) {
                console.error('Error loading stats:', error);
                document.getElementById('statsCards').innerHTML = `
                    <div class="p-4 bg-red-50 border border-red-200 rounded-lg text-red-800 text-sm">
                        Erro ao carregar estatísticas. Verifique o console para mais detalhes.
                    </div>
                `;
            }
        }

        // Render Chart.js chart
        function renderChart(data) {
            const ctx = document.getElementById('statsChart');
            if (!ctx) return;

            // Destroy existing chart if it exists
            if (window.statsChartInstance) {
                window.statsChartInstance.destroy();
            }

            // Prepare chart data
            const labels = ['Usuários', 'Grupos', 'Member Types', 'xProfile Fields', 'Eventos'];
            const values = [
                data.users?.total || 0,
                data.buddyboss?.groups || 0,
                data.buddyboss?.member_types || 0,
                data.buddyboss?.xprofile_fields || 0,
                data.events?.total || 0,
            ];

            window.statsChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Total',
                        data: values,
                        backgroundColor: 'rgba(99, 102, 241, 0.6)',
                        borderColor: 'rgba(99, 102, 241, 1)',
                        borderWidth: 1,
                        borderRadius: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: {
                                family: 'Inter',
                                size: 12,
                            },
                            bodyFont: {
                                family: 'Inter',
                                size: 11,
                            },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                font: {
                                    family: 'Inter',
                                    size: 11,
                                },
                                color: '#71717a',
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)',
                            },
                        },
                        x: {
                            ticks: {
                                font: {
                                    family: 'Inter',
                                    size: 11,
                                },
                                color: '#71717a',
                            },
                            grid: {
                                display: false,
                            },
                        },
                    },
                },
            });
        }

        // Render stats cards
        function renderStatsCards(data) {
            const container = document.getElementById('statsCards');
            if (!container) return;

            const cards = [
                {
                    title: 'Total de Usuários',
                    value: data.users?.total || 0,
                    subtitle: `${data.users?.fpse_users || 0} com perfil FPSE`,
                    bgColor: 'bg-indigo-50',
                    borderColor: 'border-indigo-200',
                    textColor: 'text-indigo-600',
                    textDarkColor: 'text-indigo-900',
                },
                {
                    title: 'Registros (30 dias)',
                    value: data.users?.recent_30_days || 0,
                    subtitle: 'Últimos 30 dias',
                    bgColor: 'bg-emerald-50',
                    borderColor: 'border-emerald-200',
                    textColor: 'text-emerald-600',
                    textDarkColor: 'text-emerald-900',
                },
                {
                    title: 'Eventos Totais',
                    value: data.events?.total || 0,
                    subtitle: `${data.events?.recent_30_days || 0} nos últimos 30 dias`,
                    bgColor: 'bg-violet-50',
                    borderColor: 'border-violet-200',
                    textColor: 'text-violet-600',
                    textDarkColor: 'text-violet-900',
                },
                {
                    title: 'Grupos BuddyBoss',
                    value: data.buddyboss?.groups || 0,
                    subtitle: `${data.buddyboss?.member_types || 0} member types`,
                    bgColor: 'bg-blue-50',
                    borderColor: 'border-blue-200',
                    textColor: 'text-blue-600',
                    textDarkColor: 'text-blue-900',
                },
            ];

            container.innerHTML = cards.map(card => `
                <div class="p-4 ${card.bgColor} border ${card.borderColor} rounded-lg">
                    <div class="text-sm font-medium ${card.textColor} mb-1">${card.title}</div>
                    <div class="text-2xl font-semibold ${card.textDarkColor}">${card.value.toLocaleString('pt-BR')}</div>
                    <div class="text-xs ${card.textColor} mt-1">${card.subtitle}</div>
                </div>
            `).join('');
        }

        // Load stats on page load
        document.addEventListener('DOMContentLoaded', loadStats);
    </script>
</div>
