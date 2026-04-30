<?php
if (!defined('ABSPATH')) {
    exit;
}

$pricing = is_array($pricing) ? $pricing : (array) $pricing;

$row_classes = apply_filters('wpqb_template_bundle_row_classes', ['wpqb-bundle-option'], $pricing, $settings, $bundle_name);
$row_class_attr = implode(' ', array_map('sanitize_html_class', (array) $row_classes));

$row_data_attributes = [
    'data-bundle-index'  => isset($pricing['bundle_index']) ? $pricing['bundle_index'] : 0,
    'data-bundle-name'   => isset($pricing['bundle_name']) ? $pricing['bundle_name'] : '',
    'data-qty'           => isset($pricing['tier_qty']) ? $pricing['tier_qty'] : 0,
    'data-price'         => isset($pricing['total_price']) ? $pricing['total_price'] : 0,
    'data-regular-price' => isset($pricing['total_regular_price']) ? $pricing['total_regular_price'] : 0,
    'data-sale-price'    => isset($pricing['total_sale_price']) ? $pricing['total_sale_price'] : 0,
];

$row_data_attributes = apply_filters('wpqb_template_bundle_row_data_attributes', $row_data_attributes, $pricing, $settings, $bundle_name);

$bundle_name_text = apply_filters('wpqb_template_bundle_row_name_text', $bundle_name, $pricing, $settings);
$per_item_text_value = apply_filters('wpqb_template_bundle_row_per_item_text', $per_item_text, $pricing, $settings);
$price_html_value = apply_filters('wpqb_template_bundle_row_price_html', $price_html, $pricing, $settings);
$savings_html_value = apply_filters('wpqb_template_bundle_row_savings_html', $savings_html, $pricing, $settings);
?>
<?php do_action('wpqb_template_before_bundle_row', $pricing, $settings, $bundle_name_text); ?>
<tr class="<?php echo esc_attr($row_class_attr); ?>"
    <?php foreach ((array) $row_data_attributes as $attribute_key => $attribute_value) : ?>
        <?php echo esc_attr($attribute_key); ?>="<?php echo esc_attr($attribute_value); ?>"
    <?php endforeach; ?>>
    <td class="wpqb-col-name">
        <?php do_action('wpqb_template_bundle_row_name_before', $pricing, $settings, $bundle_name_text); ?>
        <span class="wpqb-bundle-name"><?php echo esc_html($bundle_name_text); ?></span>
        <?php echo wp_kses_post($savings_html_value); ?>
        <?php do_action('wpqb_template_bundle_row_name_after', $pricing, $settings, $bundle_name_text); ?>
    </td>
    <?php if ('yes' === $settings['show_per_item_price']) : ?>
        <td class="wpqb-col-per-item">
            <?php do_action('wpqb_template_bundle_row_per_item_before', $pricing, $settings, $per_item_text_value); ?>
            <?php echo esc_html($per_item_text_value); ?>
            <?php do_action('wpqb_template_bundle_row_per_item_after', $pricing, $settings, $per_item_text_value); ?>
        </td>
    <?php endif; ?>
    <td class="wpqb-col-price wpqb-bundle-price">
        <?php do_action('wpqb_template_bundle_row_price_before', $pricing, $settings, $price_html_value); ?>
        <?php echo wp_kses_post($price_html_value); ?>
        <?php do_action('wpqb_template_bundle_row_price_after', $pricing, $settings, $price_html_value); ?>
    </td>
</tr>
<?php do_action('wpqb_template_after_bundle_row', $pricing, $settings, $bundle_name_text); ?>
