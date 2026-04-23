<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wpqb-bundles-frontend wpqb-design-<?php echo esc_attr($settings['design_type']); ?><?php echo $is_primary_product_form ? '' : ' wpqb-bundles-shortcode'; ?>"
    style="<?php echo esc_attr($inline_style); ?>">
    <h3 class="wpqb-bundles-title"><?php echo esc_html(!empty($settings['table_title']) ? $settings['table_title'] : __('Quantity Bundles', 'wpqb')); ?></h3>
    <input type="hidden" name="wpqb_selected_bundle" id="wpqb-selected-bundle" value="" />
    <?php if ($is_variable) : ?>
        <p class="wpqb-bundles-placeholder"><?php echo esc_html(!empty($settings['variable_placeholder_text']) ? $settings['variable_placeholder_text'] : __('Select product options to view bundles.', 'wpqb')); ?></p>
    <?php endif; ?>
    <div class="wpqb-bundles-list">
        <div class="wpqb-bundles-table-wrap<?php echo $is_table ? '' : ' wpqb-hidden'; ?>">
            <table class="wpqb-bundles-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html(!empty($settings['table_heading_bundle']) ? $settings['table_heading_bundle'] : __('Bundle', 'wpqb')); ?></th>
                        <?php if ('yes' === $settings['show_per_item_price']) : ?>
                            <th><?php echo esc_html(!empty($settings['table_heading_per_item']) ? $settings['table_heading_per_item'] : __('Per Item', 'wpqb')); ?></th>
                        <?php endif; ?>
                        <th><?php echo esc_html(!empty($settings['table_heading_total_price']) ? $settings['table_heading_total_price'] : __('Total Price', 'wpqb')); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php echo wp_kses_post($rows_html); ?>
                </tbody>
            </table>
        </div>
        <div class="wpqb-bundles-cards<?php echo $is_table ? ' wpqb-hidden' : ''; ?>">
            <?php echo wp_kses_post($cards_html); ?>
        </div>
    </div>
</div>
<p class="wpqb-selected-total" id="wpqb-selected-total" <?php echo ('yes' === $settings['show_selected_total']) ? '' : ' style="display:none;"'; ?>></p>
