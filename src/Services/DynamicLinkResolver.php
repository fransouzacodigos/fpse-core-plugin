<?php
/**
 * Resolves contextual dynamic destinations by alias.
 *
 * @package FortaleceePSE
 * @subpackage Services
 */

namespace FortaleceePSE\Core\Services;

use FortaleceePSE\Core\Plugin;

class DynamicLinkResolver {
    /**
     * @var Plugin
     */
    private $plugin;

    /**
     * @var UserTerritorialContextResolver
     */
    private $contextResolver;

    /**
     * @var \FortaleceePSE\Core\Utils\Logger
     */
    private $logger;

    /**
     * Constructor.
     *
     * @param Plugin $plugin Main plugin instance.
     * @param UserTerritorialContextResolver|null $contextResolver Optional injected context resolver.
     */
    public function __construct(Plugin $plugin, ?UserTerritorialContextResolver $contextResolver = null) {
        $this->plugin = $plugin;
        $this->contextResolver = $contextResolver ?: new UserTerritorialContextResolver($plugin);
        $this->logger = $plugin->getLogger();
    }

    /**
     * Resolve final URL for an alias.
     *
     * @param string $alias Alias name.
     * @param int|null $userId User ID, defaults to current user.
     * @param array $options Optional resolution parameters.
     * @return array
     */
    public function resolve($alias, $userId = null, array $options = []) {
        $alias = sanitize_key((string) $alias);
        $fallbackUrl = $this->resolveFallbackUrl($options);
        $context = $this->contextResolver->resolve($userId);

        $result = [
            'success' => false,
            'alias' => $alias,
            'url' => $fallbackUrl,
            'used_fallback' => true,
            'fallback_url' => $fallbackUrl,
            'context' => $context,
            'definition' => null,
            'errors' => [],
        ];

        if ($alias === '') {
            $result['errors'][] = 'missing_alias';
            return $result;
        }

        $aliases = $this->getAliasMap();
        $definition = $aliases[$alias] ?? null;
        if (!$definition) {
            $result['errors'][] = 'unknown_alias';
            $this->logDiagnostic('Alias dinâmico não mapeado', ['alias' => $alias]);
            return $result;
        }

        $result['definition'] = $definition;

        if (empty($context['success'])) {
            $result['errors'] = array_merge($result['errors'], $context['errors']);
            return $result;
        }

        $url = $this->resolveUrlFromDefinition($definition, $context, $options);
        if (!$url) {
            $result['errors'][] = 'url_not_resolved';
            return $result;
        }

        $result['success'] = true;
        $result['url'] = $url;
        $result['used_fallback'] = false;

        return apply_filters('fpse_dynamic_link_resolution', $result, $alias, $options);
    }

    /**
     * Get centralized alias map.
     *
     * @return array
     */
    public function getAliasMap() {
        $aliases = $this->plugin->getConfig('link_aliases', []);

        return apply_filters('fpse_dynamic_link_alias_map', $aliases);
    }

    /**
     * Resolve URL from alias definition.
     *
     * @param array $definition Alias configuration.
     * @param array $context Resolved territorial context.
     * @param array $options Resolution options.
     * @return string|null
     */
    private function resolveUrlFromDefinition(array $definition, array $context, array $options) {
        $tokens = array_merge($this->buildTokens($context), $options['tokens'] ?? []);

        if (!empty($definition['url_pattern'])) {
            return $this->replaceTokens((string) $definition['url_pattern'], $tokens);
        }

        $type = $definition['type'] ?? 'group';
        if ($type === 'group') {
            $path = isset($definition['path']) ? (string) $definition['path'] : '';
            return $this->appendPathToBaseUrl($context['group_base_url'], $path);
        }

        if ($type === 'external' && !empty($definition['url'])) {
            return $this->replaceTokens((string) $definition['url'], $tokens);
        }

        return null;
    }

    /**
     * Build token bag for URL interpolation.
     *
     * @param array $context Resolved context.
     * @return array
     */
    private function buildTokens(array $context) {
        return [
            'uf' => $context['uf'] ?? '',
            'group_slug' => $context['group_slug'] ?? '',
            'group_id' => (string) ($context['group_id'] ?? ''),
            'group_base_url' => $context['group_base_url'] ?? '',
            'group_directory_url' => $context['group_directory_url'] ?? '',
        ];
    }

    /**
     * Replace {tokens} in a string.
     *
     * @param string $pattern Pattern with tokens.
     * @param array $tokens Token bag.
     * @return string
     */
    private function replaceTokens($pattern, array $tokens) {
        $replacements = [];
        foreach ($tokens as $key => $value) {
            $replacements['{' . $key . '}'] = (string) $value;
        }

        return strtr($pattern, $replacements);
    }

    /**
     * Append relative path to a base URL.
     *
     * @param string $baseUrl Base URL.
     * @param string $path Relative path.
     * @return string
     */
    private function appendPathToBaseUrl($baseUrl, $path) {
        $baseUrl = trailingslashit((string) $baseUrl);
        $path = trim((string) $path, '/');

        if ($path === '') {
            return $baseUrl;
        }

        return $baseUrl . $path . '/';
    }

    /**
     * Resolve safe fallback URL.
     *
     * @param array $options Resolution options.
     * @return string
     */
    private function resolveFallbackUrl(array $options) {
        if (!empty($options['fallback_url'])) {
            return esc_url_raw((string) $options['fallback_url']);
        }

        if (!empty($options['fallback']) && $options['fallback'] === 'groups_directory') {
            if (function_exists('bp_get_groups_directory_permalink')) {
                return trailingslashit(bp_get_groups_directory_permalink());
            }

            return trailingslashit(home_url('/groups/'));
        }

        return trailingslashit(home_url('/'));
    }

    /**
     * Conditional diagnostic logging.
     *
     * @param string $message Message.
     * @param array $context Context data.
     * @return void
     */
    private function logDiagnostic($message, array $context = []) {
        $debug = $this->plugin->getConfig('debug', []);
        if (empty($debug['enable_debug'])) {
            return;
        }

        $this->logger->warn('DynamicLinkResolver', $message, $context);
    }
}
