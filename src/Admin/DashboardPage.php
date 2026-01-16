<?php
/**
 * Admin Dashboard Page
 *
 * Provides WordPress Admin dashboard with statistics and monitoring
 * Uses Tailwind CDN for styling (no dependencies)
 *
 * @package FortaleceePSE
 * @subpackage Admin
 */

namespace FortaleceePSE\Core\Admin;

class DashboardPage {
    /**
     * @var string
     */
    private $pageSlug = 'fpse-dashboard';

    /**
     * Initialize dashboard page
     *
     * @return void
     */
    public function init() {
        add_action('admin_menu', [$this, 'addDashboardPage']);
    }

    /**
     * Add dashboard page to WordPress admin menu
     *
     * Action: admin_menu
     *
     * @return void
     */
    public function addDashboardPage() {
        add_menu_page(
            __('Dashboard FPSE', 'fpse-core'),
            __('FPSE', 'fpse-core'),
            'manage_options',
            $this->pageSlug,
            [$this, 'renderDashboard'],
            'dashicons-groups',
            30
        );

        // Add Dashboard as submenu (same as main menu)
        add_submenu_page(
            $this->pageSlug,
            __('Dashboard', 'fpse-core'),
            __('Dashboard', 'fpse-core'),
            'manage_options',
            $this->pageSlug,
            [$this, 'renderDashboard']
        );
    }

    /**
     * Render dashboard page
     *
     * @return void
     */
    public function renderDashboard() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'fpse-core'));
        }

        // Get statistics
        $stats = $this->getStatistics();

        // Include Tailwind CDN
        wp_enqueue_script('tailwind-cdn', 'https://cdn.tailwindcss.com', [], null, false);
        ?>
        <div class="wrap fpse-dashboard">
            <!-- Tailwind CSS via CDN -->
            <script src="https://cdn.tailwindcss.com"></script>

            <div class="min-h-screen bg-gray-50 py-8">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <!-- Header -->
                    <div class="mb-8">
                        <h1 class="text-3xl font-bold text-gray-900">Dashboard FPSE</h1>
                        <p class="mt-2 text-gray-600">Visão geral do sistema Fortalece PSE</p>
                    </div>

                    <!-- Status Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <!-- Total Users -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Total de Usuários</dt>
                                        <dd class="text-lg font-medium text-gray-900"><?php echo esc_html($stats['total_users']); ?></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <!-- Total Registrations -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Registros Hoje</dt>
                                        <dd class="text-lg font-medium text-gray-900"><?php echo esc_html($stats['registrations_today']); ?></dd>
                                    </dl>
                                </div>
                            </div>
                        </div>

                        <!-- Infrastructure Status -->
                        <div class="bg-white rounded-lg shadow p-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 <?php echo $stats['infrastructure_ok'] ? 'bg-green-500' : 'bg-red-500'; ?> rounded-md p-3">
                                    <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-5 w-0 flex-1">
                                    <dl>
                                        <dt class="text-sm font-medium text-gray-500 truncate">Status Infraestrutura</dt>
                                        <dd class="text-lg font-medium <?php echo $stats['infrastructure_ok'] ? 'text-green-600' : 'text-red-600'; ?>">
                                            <?php echo $stats['infrastructure_ok'] ? 'OK' : 'Erro'; ?>
                                        </dd>
                                    </dl>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Users by State -->
                    <div class="bg-white rounded-lg shadow mb-8">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-900">Usuários por Estado</h2>
                        </div>
                        <div class="p-6">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($stats['users_by_state'] as $state => $count): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?php echo esc_html($state); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo esc_html($count); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Registrations -->
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200">
                            <h2 class="text-xl font-semibold text-gray-900">Últimos Cadastros</h2>
                        </div>
                        <div class="p-6">
                            <div class="space-y-4">
                                <?php foreach ($stats['recent_registrations'] as $registration): ?>
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            <?php echo esc_html($registration['nome'] ?? 'N/A'); ?>
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            <?php echo esc_html($registration['estado'] ?? 'N/A'); ?> • 
                                            <?php echo esc_html($registration['perfil'] ?? 'N/A'); ?>
                                        </p>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo esc_html($registration['data'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Get dashboard statistics
     *
     * @return array Statistics array
     */
    private function getStatistics() {
        global $wpdb;

        // Total users
        $totalUsers = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");

        // Registrations today
        $today = current_time('Y-m-d');
        $eventsTable = $wpdb->prefix . 'fpse_events';
        $registrationsToday = 0;
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$eventsTable'") === $eventsTable) {
            $registrationsToday = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$eventsTable} 
                WHERE event = 'registered' 
                AND DATE(created_at) = %s",
                $today
            ));
        }

        // Users by state
        $usersByState = [];
        $stateMeta = $wpdb->get_results(
            "SELECT meta_value as estado, COUNT(*) as total 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = 'estado' 
            GROUP BY meta_value 
            ORDER BY total DESC"
        );

        foreach ($stateMeta as $row) {
            $usersByState[$row->estado] = (int) $row->total;
        }

        // Recent registrations
        $recentRegistrations = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '$eventsTable'") === $eventsTable) {
            $recentEvents = $wpdb->get_results(
                "SELECT e.user_id, e.estado, e.perfil, e.created_at,
                       u.display_name
                FROM {$eventsTable} e
                LEFT JOIN {$wpdb->users} u ON e.user_id = u.ID
                WHERE e.event = 'registered'
                ORDER BY e.created_at DESC
                LIMIT 10"
            );

            foreach ($recentEvents as $event) {
                $recentRegistrations[] = [
                    'nome' => $event->display_name ?? 'N/A',
                    'estado' => $event->estado ?? 'N/A',
                    'perfil' => $event->perfil ?? 'N/A',
                    'data' => $event->created_at ? wp_date('d/m/Y H:i', strtotime($event->created_at)) : 'N/A',
                ];
            }
        }

        // Infrastructure status
        $infrastructureOk = true;
        $errors = [];

        // Check if events table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$eventsTable'") !== $eventsTable) {
            $infrastructureOk = false;
            $errors[] = 'Tabela de eventos não encontrada';
        }

        // Check if BuddyBoss is active (optional)
        if (!function_exists('groups_create_group')) {
            // BuddyBoss não é obrigatório, mas logamos
            // $infrastructureOk = false; // Não quebra se não estiver ativo
        }

        return [
            'total_users' => (int) $totalUsers,
            'registrations_today' => (int) $registrationsToday,
            'users_by_state' => $usersByState,
            'recent_registrations' => $recentRegistrations,
            'infrastructure_ok' => $infrastructureOk,
            'infrastructure_errors' => $errors,
        ];
    }
}
