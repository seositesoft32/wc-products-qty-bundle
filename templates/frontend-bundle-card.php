<?php
if (!defined('ABSPATH')) {
    exit;
}

$pricing = is_array($pricing) ? $pricing : (array) $pricing;
?>
<div class="wpqb-bundle-card wpqb-bundle-option" data-bundle-index="<?php echo esc_attr($pricing['bundle_index']); ?>"
    data-bundle-name="<?php echo esc_attr($pricing['bundle_name']); ?>"
    data-qty="<?php echo esc_attr($pricing['tier_qty']); ?>"
    data-price="<?php echo esc_attr($pricing['total_price']); ?>"
    data-regular-price="<?php echo esc_attr($pricing['total_regular_price']); ?>"
    data-sale-price="<?php echo esc_attr($pricing['total_sale_price']); ?>">
    <?php echo wp_kses_post($image_markup); ?>
    <div class="wpqb-card-content">
        <div class="wpqb-card-title-row">
            <span class="wpqb-bundle-name"><?php echo esc_html($bundle_name); ?></span>
            <?php echo wp_kses_post($discount_html); ?>
        </div>
        <?php if ('yes' === $settings['show_per_item_price']) : ?>
            <div class="wpqb-card-per-item"><?php echo esc_html($per_item_text); ?></div>
        <?php endif; ?>
        <div class="wpqb-card-total wpqb-bundle-price"><?php echo wp_kses_post($price_html); ?></div>
    </div>
</div>
