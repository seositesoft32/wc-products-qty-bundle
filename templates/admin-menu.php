<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = isset($settings) && is_array($settings) ? $settings : wpqb_plugin_settings();
$positions = isset($positions) && is_array($positions) ? $positions : wpqb_plugin_get_display_positions();
$shortcode_example = isset($shortcode_example) ? $shortcode_example : '[wpqb_bundles product_id="123"]';
?>
<div class="wrap wpqb-settings-page">
    <h1><?php esc_html_e('WC Products Qty Bundle', 'wpqb'); ?></h1>
    <p class="description">
        <?php esc_html_e('Configure storefront behavior, display options, and cleanup preferences for quantity bundle pricing.', 'wpqb'); ?>
    </p>

    <div class="wpqb-settings-layout">
        <div class="wpqb-settings-main">
            <form method="post" action="options.php">
                <?php settings_fields('wpqb_plugin_settings_group'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="wpqb_table_title"><?php esc_html_e('Table title', 'wpqb'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="wpqb_table_title" class="regular-text" name="wpqb_plugin_setting[table_title]" value="<?php echo esc_attr($settings['table_title']); ?>" />
                                <p class="description"><?php esc_html_e('Displayed above the bundle pricing table on product pages.', 'wpqb'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="wpqb_display_position"><?php esc_html_e('Display position', 'wpqb'); ?></label>
                            </th>
                            <td>
                                <select id="wpqb_display_position" name="wpqb_plugin_setting[display_position]">
                                    <?php foreach ($positions as $position_key => $position_label) : ?>
                                        <option value="<?php echo esc_attr($position_key); ?>" <?php selected($settings['display_position'], $position_key); ?>>
                                            <?php echo esc_html($position_label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description"><?php esc_html_e('Choose where the bundle table appears on the single product page.', 'wpqb'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="wpqb_selection_mode"><?php esc_html_e('Selection mode', 'wpqb'); ?></label>
                            </th>
                            <td>
                                <select id="wpqb_selection_mode" name="wpqb_plugin_setting[selection_mode]">
                                    <option value="auto" <?php selected($settings['selection_mode'], 'auto'); ?>><?php esc_html_e('Automatic by quantity', 'wpqb'); ?></option>
                                    <option value="manual" <?php selected($settings['selection_mode'], 'manual'); ?>><?php esc_html_e('Manual tier selection', 'wpqb'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Automatic mode applies the best matching tier based on the chosen quantity, similar to tiered pricing plugins.', 'wpqb'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Product types', 'wpqb'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="wpqb_plugin_setting[enable_simple_products]" value="yes" <?php checked($settings['enable_simple_products'], 'yes'); ?> />
                                    <?php esc_html_e('Enable on simple products', 'wpqb'); ?>
                                </label>
                                <br />
                                <label>
                                    <input type="checkbox" name="wpqb_plugin_setting[enable_variable_products]" value="yes" <?php checked($settings['enable_variable_products'], 'yes'); ?> />
                                    <?php esc_html_e('Enable on variable products', 'wpqb'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Storefront options', 'wpqb'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="wpqb_plugin_setting[show_savings]" value="yes" <?php checked($settings['show_savings'], 'yes'); ?> />
                                    <?php esc_html_e('Show savings text when a sale price exists', 'wpqb'); ?>
                                </label>
                                <br />
                                <label>
                                    <input type="checkbox" name="wpqb_plugin_setting[show_selected_total]" value="yes" <?php checked($settings['show_selected_total'], 'yes'); ?> />
                                    <?php esc_html_e('Show the live selected total below the table', 'wpqb'); ?>
                                </label>
                                <br />
                                <label>
                                    <input type="checkbox" name="wpqb_plugin_setting[require_bundle_selection]" value="yes" <?php checked($settings['require_bundle_selection'], 'yes'); ?> />
                                    <?php esc_html_e('Require a bundle selection before add to cart', 'wpqb'); ?>
                                </label>
                                <br />
                                <label>
                                    <input type="checkbox" name="wpqb_plugin_setting[shortcode_enabled]" value="yes" <?php checked($settings['shortcode_enabled'], 'yes'); ?> />
                                    <?php esc_html_e('Enable the shortcode renderer', 'wpqb'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Data cleanup', 'wpqb'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="wpqb_plugin_setting[cleanup_on_uninstall]" value="yes" <?php checked($settings['cleanup_on_uninstall'], 'yes'); ?> />
                                    <?php esc_html_e('Delete plugin settings and bundle metadata on uninstall', 'wpqb'); ?>
                                </label>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Save Changes', 'wpqb')); ?>
            </form>
        </div>

        <aside class="wpqb-settings-sidebar">
            <div class="postbox">
                <h2 class="hndle"><?php esc_html_e('Usage', 'wpqb'); ?></h2>
                <div class="inside">
                    <p><?php esc_html_e('Add bundles in the Pricing tab for simple products or inside each variation panel for variable products.', 'wpqb'); ?></p>
                    <p><strong><?php esc_html_e('Shortcode', 'wpqb'); ?></strong></p>
                    <code><?php echo esc_html($shortcode_example); ?></code>
                </div>
            </div>

            <div class="postbox">
                <h2 class="hndle"><?php esc_html_e('Marketplace checklist', 'wpqb'); ?></h2>
                <div class="inside">
                    <p><?php esc_html_e('This build now includes settings sanitization, validated pricing data, uninstall cleanup control, WooCommerce compatibility declarations, and standardized plugin/readme metadata.', 'wpqb'); ?></p>
                </div>
            </div>
        </aside>
    </div>
</div>