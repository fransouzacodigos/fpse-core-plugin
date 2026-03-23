<?php
/**
 * MF3 Panel virtual page template.
 *
 * Variables expected from the controller:
 * - $assets
 * - $panelConfig
 *
 * @package FortaleceePSE
 * @subpackage Templates
 */

if (!defined('ABSPATH')) {
    exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php foreach ((array) ($assets['css'] ?? []) as $styleUrl) : ?>
        <link rel="stylesheet" href="<?php echo esc_url($styleUrl); ?>">
    <?php endforeach; ?>
    <script>
        window.FPSE_PANEL_CONFIG = <?php echo wp_json_encode($panelConfig); ?>;
    </script>
    <?php wp_head(); ?>
</head>
<body <?php body_class('fpse-mf3-panel-shell'); ?>>
<?php
if (function_exists('wp_body_open')) {
    wp_body_open();
}
?>
<div id="root"></div>
<?php if (!empty($assets['entry_js'])) : ?>
    <script type="module" src="<?php echo esc_url($assets['entry_js']); ?>"></script>
<?php endif; ?>
<?php wp_footer(); ?>
</body>
</html>
