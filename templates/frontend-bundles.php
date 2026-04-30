<?php
if (!defined('ABSPATH')) {
    exit;
}

$wrapper_classes = [
    'wpqb-bundles-frontend',
    'wpqb-design-' . $settings['design_type'],
];

if (!$is_primary_product_form) {
    $wrapper_classes[] = 'wpqb-bundles-shortcode';
}

$wrapper_classes = apply_filters('wpqb_template_bundles_wrapper_classes', $wrapper_classes, $settings, $is_primary_product_form, $is_variable, $is_table);
$wrapper_class_attr = implode(' ', array_map('sanitize_html_class', (array) $wrapper_classes));

$title_text = !empty($settings['table_title']) ? $settings['table_title'] : __('Quantity Bundles', 'wpqb');
$title_text = apply_filters('wpqb_template_bundles_title_text', $title_text, $settings, $is_primary_product_form, $is_variable);

$placeholder_text = !empty($settings['variable_placeholder_text']) ? $settings['variable_placeholder_text'] : __('Select product options to view bundles.', 'wpqb');
$placeholder_text = apply_filters('wpqb_template_bundles_placeholder_text', $placeholder_text, $settings, $is_primary_product_form);

$heading_bundle = !empty($settings['table_heading_bundle']) ? $settings['table_heading_bundle'] : __('Bundle', 'wpqb');
$heading_bundle = apply_filters('wpqb_template_heading_bundle_text', $heading_bundle, $settings);

$heading_per_item = !empty($settings['table_heading_per_item']) ? $settings['table_heading_per_item'] : __('Per Item', 'wpqb');
$heading_per_item = apply_filters('wpqb_template_heading_per_item_text', $heading_per_item, $settings);

$heading_total = !empty($settings['table_heading_total_price']) ? $settings['table_heading_total_price'] : __('Total Price', 'wpqb');
$heading_total = apply_filters('wpqb_template_heading_total_price_text', $heading_total, $settings);

$selected_total_text = apply_filters('wpqb_template_selected_total_text', '', $settings);
$selected_total_classes = apply_filters('wpqb_template_selected_total_classes', ['wpqb-selected-total'], $settings);
$selected_total_class_attr = implode(' ', array_map('sanitize_html_class', (array) $selected_total_classes));
?>
<?php do_action('wpqb_template_before_bundles', $settings, $is_primary_product_form, $is_variable, $is_table); ?>
<div class="<?php echo esc_attr($wrapper_class_attr); ?>"
    style="<?php echo esc_attr($inline_style); ?>">
    <?php do_action('wpqb_template_bundles_top', $settings, $is_primary_product_form, $is_variable, $is_table); ?>
    <h3 class="wpqb-bundles-title"><?php echo esc_html($title_text); ?></h3>
    <input type="hidden" name="wpqb_selected_bundle" id="wpqb-selected-bundle" value="" />
    <?php if ($is_variable) : ?>
        <p class="wpqb-bundles-placeholder"><?php echo esc_html($placeholder_text); ?></p>
    <?php endif; ?>
    <?php do_action('wpqb_template_before_bundles_list', $settings, $is_primary_product_form, $is_variable, $is_table); ?>
    <div class="wpqb-bundles-list">
        <div class="wpqb-bundles-table-wrap<?php echo $is_table ? '' : ' wpqb-hidden'; ?>">
            <?php do_action('wpqb_template_before_bundles_table', $settings, $is_primary_product_form, $is_variable); ?>
            <table class="wpqb-bundles-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html($heading_bundle); ?></th>
                        <?php if ('yes' === $settings['show_per_item_price']) : ?>
                            <th><?php echo esc_html($heading_per_item); ?></th>
                        <?php endif; ?>
                        <th><?php echo esc_html($heading_total); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php echo wp_kses_post($rows_html); ?>
                </tbody>
            </table>
            <?php do_action('wpqb_template_after_bundles_table', $settings, $is_primary_product_form, $is_variable); ?>
        </div>
        <?php do_action('wpqb_template_before_bundles_cards', $settings, $is_primary_product_form, $is_variable); ?>
        <div class="wpqb-bundles-cards<?php echo $is_table ? ' wpqb-hidden' : ''; ?>">
            <?php echo wp_kses_post($cards_html); ?>
        </div>
        <?php do_action('wpqb_template_after_bundles_cards', $settings, $is_primary_product_form, $is_variable); ?>
    </div>
    <?php do_action('wpqb_template_bundles_bottom', $settings, $is_primary_product_form, $is_variable, $is_table); ?>
</div>
<?php do_action('wpqb_template_before_selected_total', $settings, $is_primary_product_form, $is_variable, $is_table); ?>
<p class="<?php echo esc_attr($selected_total_class_attr); ?>" id="wpqb-selected-total" <?php echo ('yes' === $settings['show_selected_total']) ? '' : ' style="display:none;"'; ?>><?php echo esc_html($selected_total_text); ?></p>
<?php do_action('wpqb_template_after_selected_total', $settings, $is_primary_product_form, $is_variable, $is_table); ?>
<?php do_action('wpqb_template_after_bundles', $settings, $is_primary_product_form, $is_variable, $is_table); ?>
