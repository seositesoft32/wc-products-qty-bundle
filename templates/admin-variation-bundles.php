<?php
if (!defined('ABSPATH')) {
    exit;
}

$renderer = (isset($renderer) && is_object($renderer) && method_exists($renderer, 'render_bundle_fields')) ? $renderer : null;
?>
<div class="wpqb-variation-bundles">
    <input type="hidden"
        name="wpqb_variation_bundle_rendered[<?php echo esc_attr($variation_id); ?>]"
        value="1"
        class="wpqb-variation-dirty no-var-save" />
    <h4><?php esc_html_e('Quantity Bundles', 'wpqb'); ?></h4>
    <div class="wpqb-bundles-container" data-name-prefix="wpqb_variation_bundles[<?php echo esc_attr($variation_id); ?>]">
        <?php foreach ($bundles as $index => $bundle) : ?>
            <?php if ($renderer) : ?>
                <?php $renderer->render_bundle_fields($index, $bundle, 'wpqb_variation_bundles[' . $variation_id . ']'); ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <p><button type="button" class="button wpqb-add-variation-bundle" data-variation-id="<?php echo esc_attr($variation_id); ?>"><?php esc_html_e('Add Bundle', 'wpqb'); ?></button></p>
</div>
