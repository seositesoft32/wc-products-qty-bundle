<?php
if (!defined('ABSPATH')) {
    exit;
}

$pricing = is_array($pricing) ? $pricing : (array) $pricing;
?>
<tr class="wpqb-bundle-option" data-bundle-index="<?php echo esc_attr($pricing['bundle_index']); ?>"
    data-bundle-name="<?php echo esc_attr($pricing['bundle_name']); ?>"
    data-qty="<?php echo esc_attr($pricing['tier_qty']); ?>"
    data-price="<?php echo esc_attr($pricing['total_price']); ?>"
    data-regular-price="<?php echo esc_attr($pricing['total_regular_price']); ?>"
    data-sale-price="<?php echo esc_attr($pricing['total_sale_price']); ?>">
    <td class="wpqb-col-name">
        <span class="wpqb-bundle-name"><?php echo esc_html($bundle_name); ?></span>
        <?php echo wp_kses_post($savings_html); ?>
    </td>
    <?php if ('yes' === $settings['show_per_item_price']) : ?>
        <td class="wpqb-col-per-item">
            <?php echo esc_html($per_item_text); ?>
        </td>
    <?php endif; ?>
    <td class="wpqb-col-price wpqb-bundle-price">
        <?php echo wp_kses_post($price_html); ?>
    </td>
</tr>
