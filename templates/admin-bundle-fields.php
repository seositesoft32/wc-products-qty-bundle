<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wpqb-bundle-item" data-index="<?php echo esc_attr($index); ?>">
    <div class="wpqb-bundle-header">
        <h4><?php echo esc_html(sprintf(__('Bundle #%d', 'wpqb'), $index + 1)); ?></h4>
        <span class="wpqb-bundle-total-price"></span>
        <button type="button" class="button wpqb-remove-bundle"><?php esc_html_e('Remove', 'wpqb'); ?></button>
    </div>
    <div class="wpqb-bundle-fields">
        <p class="wpqb-form-field wpqb-name-field">
            <label><?php esc_html_e('Bundle Name', 'wpqb'); ?></label>
            <input type="text" name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][name]"
                value="<?php echo esc_attr($bundle_name); ?>"
                placeholder="<?php echo esc_attr__('e.g., Starter Pack, Family Bundle', 'wpqb'); ?>" />
        </p>
        <p class="wpqb-form-field">
            <label><?php esc_html_e('Quantity', 'wpqb'); ?></label>
            <input type="number" name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][qty]"
                value="<?php echo esc_attr($qty); ?>" placeholder="<?php echo esc_attr__('e.g., 10', 'wpqb'); ?>"
                min="1" step="1" />
        </p>
        <p class="wpqb-form-field">
            <label><?php esc_html_e('Regular Price', 'wpqb'); ?></label>
            <input type="text"
                name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][regular_price]"
                value="<?php echo esc_attr($regular_price); ?>" placeholder="<?php echo esc_attr__('0.00', 'wpqb'); ?>"
                class="short wc_input_price" />
        </p>
        <p class="wpqb-form-field">
            <label><?php esc_html_e('Sale Price', 'wpqb'); ?></label>
            <input type="text" name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][sale_price]"
                value="<?php echo esc_attr($sale_price); ?>" placeholder="<?php echo esc_attr__('0.00', 'wpqb'); ?>"
                class="short wc_input_price" />
        </p>
        <p class="wpqb-form-field wpqb-image-field">
            <label><?php esc_html_e('Bundle Image', 'wpqb'); ?></label>
            <span class="wpqb-image-preview">
                <?php if ($image_url) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="" style="max-width: 100px; max-height: 100px;" />
                <?php endif; ?>
            </span>
            <input type="hidden" name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][image_id]"
                class="wpqb-image-id" value="<?php echo esc_attr($image_id); ?>" />
            <button type="button" class="button wpqb-upload-image"><?php esc_html_e('Upload Image', 'wpqb'); ?></button>
            <button type="button" class="button wpqb-remove-image" <?php echo $image_url ? '' : 'style="display:none;"'; ?>><?php esc_html_e('Remove Image', 'wpqb'); ?></button>
        </p>
    </div>
</div>
