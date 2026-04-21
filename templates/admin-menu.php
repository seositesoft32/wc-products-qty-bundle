<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = isset($settings) && is_array($settings) ? $settings : wpqb_plugin_settings();
$positions = isset($positions) && is_array($positions) ? $positions : wpqb_plugin_get_display_positions();
$shortcode_example = isset($shortcode_example) ? $shortcode_example : '[wpqb_bundles product_id="123"]';
?>
<div class="wrap wpqb-settings-page">
    <div class="wpqb-settings-hero">
        <div>
            <h1><?php esc_html_e('WC Products Qty Bundle', 'wpqb'); ?></h1>
            <p><?php esc_html_e('A premium-style control center for layout, pricing, and storefront behavior.', 'wpqb'); ?></p>
        </div>
        <div class="wpqb-settings-actions">
            <button type="button" class="button button-primary button-hero" id="wpqb-save-settings">
                <?php esc_html_e('Save Settings', 'wpqb'); ?>
            </button>
        </div>
    </div>

    <div class="wpqb-settings-notice" id="wpqb-settings-notice" aria-live="polite"></div>

    <div class="wpqb-settings-layout">
        <div class="wpqb-settings-main">
            <div class="wpqb-settings-tabs" role="tablist" aria-label="<?php esc_attr_e('Settings tabs', 'wpqb'); ?>">
                <button type="button" class="wpqb-tab is-active" data-tab="general"><?php esc_html_e('General', 'wpqb'); ?></button>
                <button type="button" class="wpqb-tab" data-tab="table-style"><?php esc_html_e('Table Style', 'wpqb'); ?></button>
                <button type="button" class="wpqb-tab" data-tab="cards-style"><?php esc_html_e('Cards Style', 'wpqb'); ?></button>
                <button type="button" class="wpqb-tab" data-tab="advanced"><?php esc_html_e('Advanced', 'wpqb'); ?></button>
            </div>

            <form method="post" action="options.php" id="wpqb-settings-form">
                <input type="hidden" name="action" value="wpqb_save_settings" />

                <section class="wpqb-tab-panel is-active" data-panel="general">
                    <h2><?php esc_html_e('General Settings', 'wpqb'); ?></h2>
                    <p class="description"><?php esc_html_e('Core storefront behavior, display mode, and product coverage.', 'wpqb'); ?></p>

                    <div class="wpqb-field-grid">
                        <div class="wpqb-field-card">
                            <label for="wpqb_design_type"><?php esc_html_e('Design', 'wpqb'); ?></label>
                            <select id="wpqb_design_type" name="wpqb_plugin_setting[design_type]">
                                <option value="table" <?php selected($settings['design_type'], 'table'); ?>><?php esc_html_e('Default table', 'wpqb'); ?></option>
                                <option value="cards" <?php selected($settings['design_type'], 'cards'); ?>><?php esc_html_e('Cards with image', 'wpqb'); ?></option>
                            </select>
                        </div>

                        <div class="wpqb-field-card">
                            <label for="wpqb_display_position"><?php esc_html_e('Display position', 'wpqb'); ?></label>
                            <select id="wpqb_display_position" name="wpqb_plugin_setting[display_position]">
                                <?php foreach ($positions as $position_key => $position_label) : ?>
                                    <option value="<?php echo esc_attr($position_key); ?>" <?php selected($settings['display_position'], $position_key); ?>>
                                        <?php echo esc_html($position_label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="wpqb-field-card">
                            <label for="wpqb_selection_mode"><?php esc_html_e('Selection mode', 'wpqb'); ?></label>
                            <select id="wpqb_selection_mode" name="wpqb_plugin_setting[selection_mode]">
                                <option value="auto" <?php selected($settings['selection_mode'], 'auto'); ?>><?php esc_html_e('Automatic by quantity', 'wpqb'); ?></option>
                                <option value="manual" <?php selected($settings['selection_mode'], 'manual'); ?>><?php esc_html_e('Manual tier selection', 'wpqb'); ?></option>
                            </select>
                        </div>

                        <div class="wpqb-field-card">
                            <label for="wpqb_table_title"><?php esc_html_e('Main title', 'wpqb'); ?></label>
                            <input type="text" id="wpqb_table_title" name="wpqb_plugin_setting[table_title]" value="<?php echo esc_attr($settings['table_title']); ?>" />
                        </div>

                        <div class="wpqb-field-card">
                            <label for="wpqb_variable_placeholder_text"><?php esc_html_e('Variation placeholder text', 'wpqb'); ?></label>
                            <input type="text" id="wpqb_variable_placeholder_text" name="wpqb_plugin_setting[variable_placeholder_text]" value="<?php echo esc_attr($settings['variable_placeholder_text']); ?>" />
                        </div>
                    </div>

                    <div class="wpqb-checklist-grid">
                        <fieldset class="wpqb-check-card">
                            <legend><?php esc_html_e('Product Types', 'wpqb'); ?></legend>
                            <label><input type="checkbox" name="wpqb_plugin_setting[enable_simple_products]" value="yes" <?php checked($settings['enable_simple_products'], 'yes'); ?> /> <?php esc_html_e('Enable on simple products', 'wpqb'); ?></label>
                            <label><input type="checkbox" name="wpqb_plugin_setting[enable_variable_products]" value="yes" <?php checked($settings['enable_variable_products'], 'yes'); ?> /> <?php esc_html_e('Enable on variable products', 'wpqb'); ?></label>
                        </fieldset>

                        <fieldset class="wpqb-check-card">
                            <legend><?php esc_html_e('Storefront Visibility', 'wpqb'); ?></legend>
                            <label><input type="checkbox" name="wpqb_plugin_setting[show_savings]" value="yes" <?php checked($settings['show_savings'], 'yes'); ?> /> <?php esc_html_e('Show savings text', 'wpqb'); ?></label>
                            <label><input type="checkbox" name="wpqb_plugin_setting[show_discount_after_title]" value="yes" <?php checked($settings['show_discount_after_title'], 'yes'); ?> /> <?php esc_html_e('Show discount after title', 'wpqb'); ?></label>
                            <label><input type="checkbox" name="wpqb_plugin_setting[show_per_item_price]" value="yes" <?php checked($settings['show_per_item_price'], 'yes'); ?> /> <?php esc_html_e('Show per item price', 'wpqb'); ?></label>
                            <label><input type="checkbox" name="wpqb_plugin_setting[show_qty_after_per_item]" value="yes" <?php checked($settings['show_qty_after_per_item'], 'yes'); ?> /> <?php esc_html_e('Show qty after per-item value', 'wpqb'); ?></label>
                            <label><input type="checkbox" name="wpqb_plugin_setting[show_regular_price_when_sale]" value="yes" <?php checked($settings['show_regular_price_when_sale'], 'yes'); ?> /> <?php esc_html_e('Show regular total when sale exists', 'wpqb'); ?></label>
                            <label><input type="checkbox" name="wpqb_plugin_setting[show_selected_total]" value="yes" <?php checked($settings['show_selected_total'], 'yes'); ?> /> <?php esc_html_e('Show selected total', 'wpqb'); ?></label>
                        </fieldset>
                    </div>
                </section>

                <section class="wpqb-tab-panel" data-panel="table-style">
                    <h2><?php esc_html_e('Table Style', 'wpqb'); ?></h2>
                    <p class="description"><?php esc_html_e('Appearance controls for table-based presentation.', 'wpqb'); ?></p>

                    <div class="wpqb-field-grid">
                        <div class="wpqb-field-card">
                            <label for="wpqb_table_heading_bundle"><?php esc_html_e('Bundle heading', 'wpqb'); ?></label>
                            <input type="text" id="wpqb_table_heading_bundle" name="wpqb_plugin_setting[table_heading_bundle]" value="<?php echo esc_attr($settings['table_heading_bundle']); ?>" />
                        </div>
                        <div class="wpqb-field-card">
                            <label for="wpqb_table_heading_per_item"><?php esc_html_e('Per item heading', 'wpqb'); ?></label>
                            <input type="text" id="wpqb_table_heading_per_item" name="wpqb_plugin_setting[table_heading_per_item]" value="<?php echo esc_attr($settings['table_heading_per_item']); ?>" />
                        </div>
                        <div class="wpqb-field-card">
                            <label for="wpqb_table_heading_total_price"><?php esc_html_e('Total heading', 'wpqb'); ?></label>
                            <input type="text" id="wpqb_table_heading_total_price" name="wpqb_plugin_setting[table_heading_total_price]" value="<?php echo esc_attr($settings['table_heading_total_price']); ?>" />
                        </div>
                    </div>

                    <div class="wpqb-color-grid">
                        <label><?php esc_html_e('Title background', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[table_title_bg_color]" value="<?php echo esc_attr($settings['table_title_bg_color']); ?>" /></label>
                        <label><?php esc_html_e('Title text', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[table_title_text_color]" value="<?php echo esc_attr($settings['table_title_text_color']); ?>" /></label>
                        <label><?php esc_html_e('Table head background', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[table_head_bg_color]" value="<?php echo esc_attr($settings['table_head_bg_color']); ?>" /></label>
                        <label><?php esc_html_e('Table head text', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[table_head_text_color]" value="<?php echo esc_attr($settings['table_head_text_color']); ?>" /></label>
                        <label><?php esc_html_e('Table body background', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[table_body_bg_color]" value="<?php echo esc_attr($settings['table_body_bg_color']); ?>" /></label>
                        <label><?php esc_html_e('Table body text', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[table_body_text_color]" value="<?php echo esc_attr($settings['table_body_text_color']); ?>" /></label>
                        <label><?php esc_html_e('Table border', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[table_border_color]" value="<?php echo esc_attr($settings['table_border_color']); ?>" /></label>
                        <label><?php esc_html_e('Cell border', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[table_cell_border_color]" value="<?php echo esc_attr($settings['table_cell_border_color']); ?>" /></label>
                        <label><?php esc_html_e('Row hover background', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[table_hover_bg_color]" value="<?php echo esc_attr($settings['table_hover_bg_color']); ?>" /></label>
                        <label><?php esc_html_e('Selected row background', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[table_selected_bg_color]" value="<?php echo esc_attr($settings['table_selected_bg_color']); ?>" /></label>
                        <label><?php esc_html_e('Selected row border', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[table_selected_border_color]" value="<?php echo esc_attr($settings['table_selected_border_color']); ?>" /></label>
                    </div>
                </section>

                <section class="wpqb-tab-panel" data-panel="cards-style">
                    <h2><?php esc_html_e('Cards Style', 'wpqb'); ?></h2>
                    <p class="description"><?php esc_html_e('Appearance controls dedicated to cards layout.', 'wpqb'); ?></p>

                    <div class="wpqb-color-grid">
                        <label><?php esc_html_e('Card background', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[card_bg_color]" value="<?php echo esc_attr($settings['card_bg_color']); ?>" /></label>
                        <label><?php esc_html_e('Card text', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[card_text_color]" value="<?php echo esc_attr($settings['card_text_color']); ?>" /></label>
                        <label><?php esc_html_e('Card border', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[card_border_color]" value="<?php echo esc_attr($settings['card_border_color']); ?>" /></label>
                        <label><?php esc_html_e('Card hover border', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[card_hover_border_color]" value="<?php echo esc_attr($settings['card_hover_border_color']); ?>" /></label>
                        <label><?php esc_html_e('Card selected border', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[card_selected_border_color]" value="<?php echo esc_attr($settings['card_selected_border_color']); ?>" /></label>
                        <label><?php esc_html_e('Card media background', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[card_media_bg_color]" value="<?php echo esc_attr($settings['card_media_bg_color']); ?>" /></label>
                        <label><?php esc_html_e('Card radius (px)', 'wpqb'); ?><input type="number" min="0" max="40" step="1" name="wpqb_plugin_setting[card_radius]" value="<?php echo esc_attr($settings['card_radius']); ?>" /></label>
                        <label><?php esc_html_e('Discount background', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[discount_bg_color]" value="<?php echo esc_attr($settings['discount_bg_color']); ?>" /></label>
                        <label><?php esc_html_e('Discount text', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[discount_text_color]" value="<?php echo esc_attr($settings['discount_text_color']); ?>" /></label>
                    </div>

                    <div class="wpqb-color-grid">
                        <label><?php esc_html_e('Regular price', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[regular_price_color]" value="<?php echo esc_attr($settings['regular_price_color']); ?>" /></label>
                        <label><?php esc_html_e('Sale price', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[sale_price_color]" value="<?php echo esc_attr($settings['sale_price_color']); ?>" /></label>
                        <label><?php esc_html_e('Cutoff price', 'wpqb'); ?><input type="color" name="wpqb_plugin_setting[strikethrough_price_color]" value="<?php echo esc_attr($settings['strikethrough_price_color']); ?>" /></label>
                    </div>
                </section>

                <section class="wpqb-tab-panel" data-panel="advanced">
                    <h2><?php esc_html_e('Advanced', 'wpqb'); ?></h2>
                    <p class="description"><?php esc_html_e('Operational controls and maintenance preferences.', 'wpqb'); ?></p>

                    <div class="wpqb-checklist-grid">
                        <fieldset class="wpqb-check-card">
                            <legend><?php esc_html_e('Behavior', 'wpqb'); ?></legend>
                            <label><input type="checkbox" name="wpqb_plugin_setting[require_bundle_selection]" value="yes" <?php checked($settings['require_bundle_selection'], 'yes'); ?> /> <?php esc_html_e('Require bundle selection before add to cart', 'wpqb'); ?></label>
                            <label><input type="checkbox" name="wpqb_plugin_setting[enable_bundle_sorting]" value="yes" <?php checked($settings['enable_bundle_sorting'], 'yes'); ?> /> <?php esc_html_e('Sort bundles by quantity', 'wpqb'); ?></label>
                            <label><input type="checkbox" name="wpqb_plugin_setting[auto_select_by_qty_change]" value="yes" <?php checked($settings['auto_select_by_qty_change'], 'yes'); ?> /> <?php esc_html_e('Auto-select matching bundle on qty change', 'wpqb'); ?></label>
                        </fieldset>

                        <fieldset class="wpqb-check-card">
                            <legend><?php esc_html_e('Maintenance', 'wpqb'); ?></legend>
                            <label><input type="checkbox" name="wpqb_plugin_setting[cleanup_on_uninstall]" value="yes" <?php checked($settings['cleanup_on_uninstall'], 'yes'); ?> /> <?php esc_html_e('Delete plugin data on uninstall', 'wpqb'); ?></label>
                        </fieldset>
                    </div>
                </section>
            </form>
        </div>

        <aside class="wpqb-settings-sidebar">
            <div class="postbox">
                <h2 class="hndle"><?php esc_html_e('Quick Usage', 'wpqb'); ?></h2>
                <div class="inside">
                    <p><?php esc_html_e('Create bundles in WooCommerce product pricing blocks for simple or variation products.', 'wpqb'); ?></p>
                    <p><strong><?php esc_html_e('Shortcode', 'wpqb'); ?></strong></p>
                    <code><?php echo esc_html($shortcode_example); ?></code>
                </div>
            </div>

            <div class="postbox">
                <h2 class="hndle"><?php esc_html_e('Tip', 'wpqb'); ?></h2>
                <div class="inside">
                    <p><?php esc_html_e('Use Table Style for dense pricing grids and Cards Style for visual merchandising with bundle images.', 'wpqb'); ?></p>
                </div>
            </div>
        </aside>
    </div>
</div>
