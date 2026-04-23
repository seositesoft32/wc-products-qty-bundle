<?php
if (!defined('ABSPATH')) {
    exit;
}

$bundles = isset($bundles) && is_array($bundles) ? $bundles : [];
$name_prefix = isset($name_prefix) ? $name_prefix : 'wpqb_bundles';
$renderer = (isset($renderer) && is_object($renderer) && method_exists($renderer, 'render_bundle_fields')) ? $renderer : null;
?>
<div class="options_group wpqb-qty-bundles">
    <h3 style="padding-left: 12px;"><?php esc_html_e('Quantity Price Bundles', 'wpqb'); ?></h3>

    <div id="wpqb-bundles-container" class="wpqb-bundles-container" data-name-prefix="<?php echo esc_attr($name_prefix); ?>">
        <?php foreach ($bundles as $index => $bundle) : ?>
            <?php if ($renderer) : ?>
                <?php $renderer->render_bundle_fields($index, $bundle, $name_prefix); ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <p class="wpqb-form-field" style="padding-left: 12px;">
        <button type="button" class="button wpqb-add-bundle"><?php esc_html_e('Add Bundle', 'wpqb'); ?></button>
    </p>
</div>
