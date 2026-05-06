<?php
if (!defined('ABSPATH')) {
    exit;
}

$pricing = is_array($pricing) ? $pricing : (array) $pricing;

$card_classes = apply_filters('wpqb_template_bundle_card_classes', ['wpqb-bundle-card', 'wpqb-bundle-option'], $pricing, $settings, $bundle_name);
$card_class_attr = implode(' ', array_map('sanitize_html_class', (array) $card_classes));

$card_data_attributes = [
    'data-bundle-index'  => isset($pricing['bundle_index']) ? $pricing['bundle_index'] : 0,
    'data-bundle-name'   => isset($pricing['bundle_name']) ? $pricing['bundle_name'] : '',
    'data-qty'           => isset($pricing['tier_qty']) ? $pricing['tier_qty'] : 0,
    'data-price'         => isset($pricing['total_price']) ? $pricing['total_price'] : 0,
    'data-regular-price' => isset($pricing['total_regular_price']) ? $pricing['total_regular_price'] : 0,
    'data-sale-price'    => isset($pricing['total_sale_price']) ? $pricing['total_sale_price'] : 0,
];

$card_data_attributes = apply_filters('wpqb_template_bundle_card_data_attributes', $card_data_attributes, $pricing, $settings, $bundle_name);

$bundle_name_text = apply_filters('wpqb_template_bundle_card_name_text', $bundle_name, $pricing, $settings);
$per_item_text_value = apply_filters('wpqb_template_bundle_card_per_item_text', $per_item_text, $pricing, $settings);
$price_html_value = apply_filters('wpqb_template_bundle_card_price_html', $price_html, $pricing, $settings);
$discount_html_value = apply_filters('wpqb_template_bundle_card_discount_html', $discount_html, $pricing, $settings);
$image_markup_value = apply_filters('wpqb_template_bundle_card_image_markup', $image_markup, $pricing, $settings, $bundle_name_text);
?>
<?php do_action('wpqb_template_before_bundle_card', $pricing, $settings, $bundle_name_text); ?>
<div class="<?php echo esc_attr($card_class_attr); ?>"
    <?php foreach ((array) $card_data_attributes as $attribute_key => $attribute_value) : ?>
        <?php echo esc_attr($attribute_key); ?>="<?php echo esc_attr($attribute_value); ?>"
    <?php endforeach; ?>>
    <?php do_action('wpqb_template_bundle_card_media_before', $pricing, $settings, $image_markup_value); ?>
    <?php echo wp_kses_post($image_markup_value); ?>
    <?php do_action('wpqb_template_bundle_card_media_after', $pricing, $settings, $image_markup_value); ?>
    <div class="wpqb-card-content">
        <div class="wpqb-card-title-row">
            <?php do_action('wpqb_template_bundle_card_name_before', $pricing, $settings, $bundle_name_text); ?>
            <span class="wpqb-bundle-name"><?php echo esc_html($bundle_name_text); ?></span>
            <?php echo wp_kses_post($discount_html_value); ?>
            <?php do_action('wpqb_template_bundle_card_name_after', $pricing, $settings, $bundle_name_text); ?>
        </div>
        <?php if ('yes' === $settings['show_per_item_price']) : ?>
            <div class="wpqb-card-per-item">
                <?php do_action('wpqb_template_bundle_card_per_item_before', $pricing, $settings, $per_item_text_value); ?>
                <?php echo esc_html($per_item_text_value); ?>
                <?php do_action('wpqb_template_bundle_card_per_item_after', $pricing, $settings, $per_item_text_value); ?>
            </div>
        <?php endif; ?>
        <div class="wpqb-card-total wpqb-bundle-price">
            <?php do_action('wpqb_template_bundle_card_price_before', $pricing, $settings, $price_html_value); ?>
            <?php echo wp_kses_post($price_html_value); ?>
            <?php do_action('wpqb_template_bundle_card_price_after', $pricing, $settings, $price_html_value); ?>
        </div>
    </div>
</div>
<?php do_action('wpqb_template_after_bundle_card', $pricing, $settings, $bundle_name_text); ?>
