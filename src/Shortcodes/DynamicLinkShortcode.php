<?php
/**
 * Shortcodes for contextual dynamic links.
 *
 * @package FortaleceePSE
 * @subpackage Shortcodes
 */

namespace FortaleceePSE\Core\Shortcodes;

use FortaleceePSE\Core\Plugin;
use FortaleceePSE\Core\Services\DynamicLinkResolver;

class DynamicLinkShortcode {
    /**
     * @var Plugin
     */
    private $plugin;

    /**
     * @var DynamicLinkResolver
     */
    private $resolver;

    /**
     * Constructor.
     *
     * @param Plugin $plugin Main plugin instance.
     */
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
        $this->resolver = new DynamicLinkResolver($plugin);
    }

    /**
     * Register shortcodes.
     *
     * @return void
     */
    public function register() {
        add_shortcode('fpse_dynamic_link', [$this, 'renderLink']);
        add_shortcode('fpse_dynamic_url', [$this, 'renderUrl']);
    }

    /**
     * Render full anchor tag.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function renderLink($atts = []) {
        $atts = shortcode_atts([
            'target' => '',
            'label' => '',
            'class' => '',
            'fallback_label' => '',
            'fallback_url' => '',
            'fallback' => 'home',
        ], $atts, 'fpse_dynamic_link');

        $resolution = $this->resolver->resolve($atts['target'], null, [
            'fallback_url' => $atts['fallback_url'],
            'fallback' => $atts['fallback'],
        ]);

        $label = $this->resolveLinkLabel($atts, $resolution);
        if ($label === '') {
            return '';
        }

        $classes = $this->sanitizeCssClassList($atts['class']);
        if (!empty($resolution['used_fallback'])) {
            $classes[] = 'fpse-dynamic-link--fallback';
        }

        $attributes = [
            'href' => esc_url($resolution['url']),
            'class' => !empty($classes) ? esc_attr(implode(' ', $classes)) : null,
            'data-fpse-target' => esc_attr((string) ($resolution['alias'] ?? '')),
            'data-fpse-status' => esc_attr(!empty($resolution['used_fallback']) ? 'fallback' : 'resolved'),
        ];

        $parts = [];
        foreach ($attributes as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $parts[] = sprintf('%s="%s"', $name, $value);
        }

        return sprintf('<a %s>%s</a>', implode(' ', $parts), esc_html($label));
    }

    /**
     * Render only the resolved URL.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public function renderUrl($atts = []) {
        $atts = shortcode_atts([
            'target' => '',
            'fallback_url' => '',
            'fallback' => 'home',
        ], $atts, 'fpse_dynamic_url');

        $resolution = $this->resolver->resolve($atts['target'], null, [
            'fallback_url' => $atts['fallback_url'],
            'fallback' => $atts['fallback'],
        ]);

        return esc_url($resolution['url']);
    }

    /**
     * Resolve link label.
     *
     * @param array $atts Shortcode attributes.
     * @param array $resolution Resolved destination.
     * @return string
     */
    private function resolveLinkLabel(array $atts, array $resolution) {
        if (!empty($resolution['used_fallback']) && $atts['fallback_label'] !== '') {
            return (string) $atts['fallback_label'];
        }

        if ($atts['label'] !== '') {
            return (string) $atts['label'];
        }

        if (!empty($resolution['definition']['label'])) {
            return (string) $resolution['definition']['label'];
        }

        if (!empty($resolution['used_fallback']) && $atts['fallback_label'] === '') {
            return 'Acessar';
        }

        return (string) ($resolution['alias'] ?? '');
    }

    /**
     * Sanitize optional CSS class list.
     *
     * @param string $classList Raw class list.
     * @return array
     */
    private function sanitizeCssClassList($classList) {
        $classes = preg_split('/\s+/', trim((string) $classList));
        $classes = is_array($classes) ? $classes : [];

        $sanitized = [];
        foreach ($classes as $className) {
            $className = sanitize_html_class($className);
            if ($className !== '') {
                $sanitized[] = $className;
            }
        }

        return array_values(array_unique($sanitized));
    }
}
