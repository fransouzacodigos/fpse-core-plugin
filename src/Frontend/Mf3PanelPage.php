<?php
/**
 * Virtual WordPress page for the MF3 panel frontend.
 *
 * Publishes /painel-mf3 inside the MF3 domain and mounts the React build
 * generated from the shared frontend workspace.
 *
 * @package FortaleceePSE
 * @subpackage Frontend
 */

namespace FortaleceePSE\Core\Frontend;

class Mf3PanelPage {
    /**
     * Query var used by the virtual route.
     */
    const QUERY_VAR = 'fpse_mf3_panel';

    /**
     * Semantic path published in WordPress.
     */
    const PATH = 'painel-mf3';

    /**
     * Rewrite version used for one-shot flushes after deploys.
     */
    const REWRITE_VERSION = '2026-04-01-mf3-panel-page-v2';

    /**
     * Manifest entry point for the Vite app.
     */
    const MANIFEST_ENTRY = 'index.html';

    /**
     * Register hooks.
     *
     * @return void
     */
    public function register() {
        add_action('init', [$this, 'registerRewrite'], 20);
        add_filter('query_vars', [$this, 'registerQueryVar']);
        add_action('template_redirect', [$this, 'maybeRenderPage'], 0);
        add_filter('document_title_parts', [$this, 'filterDocumentTitle']);
        add_filter('body_class', [$this, 'addBodyClass']);
        add_action('init', [$this, 'maybeFlushRewriteRules'], 99);
    }

    /**
     * Register the semantic route /painel-mf3.
     *
     * @return void
     */
    public function registerRewrite() {
        add_rewrite_rule(
            '^' . self::PATH . '/?$',
            'index.php?' . self::QUERY_VAR . '=1',
            'top'
        );
    }

    /**
     * Register custom query var.
     *
     * @param array $vars
     * @return array
     */
    public function registerQueryVar($vars) {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    /**
     * Flush rewrite rules once after a route version change.
     *
     * @return void
     */
    public function maybeFlushRewriteRules() {
        $storedVersion = (string) get_option('fpse_mf3_panel_rewrite_version', '');
        if ($storedVersion === self::REWRITE_VERSION && $this->hasExpectedRewriteRule()) {
            return;
        }

        flush_rewrite_rules(false);
        update_option('fpse_mf3_panel_rewrite_version', self::REWRITE_VERSION, false);
    }

    /**
     * Intercept the virtual route and render the panel page.
     *
     * @return void
     */
    public function maybeRenderPage() {
        if (!$this->isPanelRequest()) {
            return;
        }

        if (!is_user_logged_in()) {
            auth_redirect();
        }

        $assets = $this->resolveAssets();
        if (empty($assets['entry_js'])) {
            wp_die(
                esc_html__('Os assets do painel MF3 ainda não foram publicados no plugin.', 'fpse-core'),
                esc_html__('Painel MF3 indisponível', 'fpse-core'),
                ['response' => 500]
            );
        }

        global $wp_query;

        if ($wp_query instanceof \WP_Query) {
            $wp_query->is_404 = false;
            $wp_query->is_page = true;
            $wp_query->is_singular = true;
            $wp_query->is_home = false;
        }

        status_header(200);
        nocache_headers();

        $panelConfig = [
            'restBase' => esc_url_raw(rest_url()),
            'panelPath' => esc_url_raw(home_url('/' . self::PATH . '/')),
        ];

        $template = FPSE_CORE_PATH . 'templates/mf3-panel-page.php';
        if (!file_exists($template)) {
            wp_die(
                esc_html__('Template do painel MF3 não encontrado.', 'fpse-core'),
                esc_html__('Painel MF3 indisponível', 'fpse-core'),
                ['response' => 500]
            );
        }

        require $template;
        exit;
    }

    /**
     * Customize title only for the virtual panel page.
     *
     * @param array $parts
     * @return array
     */
    public function filterDocumentTitle($parts) {
        if ($this->isPanelRequest()) {
            $parts['title'] = 'Painel MF3';
        }

        return $parts;
    }

    /**
     * Add body classes for styling/debugging.
     *
     * @param array $classes
     * @return array
     */
    public function addBodyClass($classes) {
        if ($this->isPanelRequest()) {
            $classes[] = 'fpse-mf3-panel-page';
            $classes[] = 'fpse-mf3-panel';
        }

        return $classes;
    }

    /**
     * Resolve current build assets from the copied Vite manifest.
     *
     * @return array
     */
    private function resolveAssets() {
        $manifestPath = FPSE_CORE_PATH . 'assets/panel-mf3/.vite/manifest.json';
        if (!file_exists($manifestPath)) {
            return [
                'entry_js' => null,
                'css' => [],
            ];
        }

        $manifestJson = file_get_contents($manifestPath);
        if (!is_string($manifestJson) || $manifestJson === '') {
            return [
                'entry_js' => null,
                'css' => [],
            ];
        }

        $manifest = json_decode($manifestJson, true);
        if (!is_array($manifest) || empty($manifest[self::MANIFEST_ENTRY]['file'])) {
            return [
                'entry_js' => null,
                'css' => [],
            ];
        }

        $entry = $manifest[self::MANIFEST_ENTRY];
        $css = [];
        $this->collectCssFromManifest($manifest, self::MANIFEST_ENTRY, $css);

        return [
            'entry_js' => FPSE_CORE_URL . 'assets/panel-mf3/' . ltrim($entry['file'], '/'),
            'css' => array_values(array_unique(array_map(function ($file) {
                return FPSE_CORE_URL . 'assets/panel-mf3/' . ltrim($file, '/');
            }, $css))),
        ];
    }

    /**
     * Recursively collect CSS files required by the entry and static imports.
     *
     * @param array $manifest
     * @param string $key
     * @param array $css
     * @param array $visited
     * @return void
     */
    private function collectCssFromManifest(array $manifest, $key, array &$css, array &$visited = []) {
        if (isset($visited[$key]) || empty($manifest[$key]) || !is_array($manifest[$key])) {
            return;
        }

        $visited[$key] = true;
        $entry = $manifest[$key];

        foreach ((array) ($entry['css'] ?? []) as $cssFile) {
            if (is_string($cssFile) && $cssFile !== '') {
                $css[] = $cssFile;
            }
        }

        foreach ((array) ($entry['imports'] ?? []) as $importKey) {
            if (is_string($importKey) && $importKey !== '') {
                $this->collectCssFromManifest($manifest, $importKey, $css, $visited);
            }
        }
    }

    /**
     * Check whether the current request is the MF3 panel route.
     *
     * @return bool
     */
    private function isPanelRequest() {
        return (int) get_query_var(self::QUERY_VAR) === 1;
    }

    /**
     * Check whether the persisted rewrite map still contains the panel route.
     *
     * @return bool
     */
    private function hasExpectedRewriteRule() {
        $rules = get_option('rewrite_rules');

        if (!is_array($rules)) {
            return false;
        }

        $pattern = '^' . self::PATH . '/?$';
        return isset($rules[$pattern]) && $rules[$pattern] === 'index.php?' . self::QUERY_VAR . '=1';
    }
}
