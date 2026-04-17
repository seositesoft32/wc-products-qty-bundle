<?php


function wpqb_plugin_has_active_required_plugins($plugin_name = '')
{
    $plugins = [
        'woocommerce' => 'woocommerce/woocommerce.php',
    ];

    if (!empty($plugin_name)) {
        $plugin_file = isset($plugins[$plugin_name]) ? $plugins[$plugin_name] : $plugin_name;

        return is_plugin_active($plugin_file);
    }

    foreach ($plugins as $plugin) {
        if (!is_plugin_active($plugin)) {
            return false;
        }
    }

    return true;
}

function wpqb_plugin_get_default_settings()
{
    return [
        'table_title' => __('Quantity Bundles', 'wpqb'),
        'design_type' => 'table',
        'display_position' => 'woocommerce_before_add_to_cart_button',
        'selection_mode' => 'auto',
        'enable_simple_products' => 'yes',
        'enable_variable_products' => 'yes',
        'show_savings' => 'yes',
        'show_discount_after_title' => 'yes',
        'show_selected_total' => 'yes',
        'show_per_item_price' => 'yes',
        'show_regular_price_when_sale' => 'yes',
        'show_qty_after_per_item' => 'yes',
        'require_bundle_selection' => 'no',
        'cleanup_on_uninstall' => 'no',
        'shortcode_enabled' => 'yes',
        'table_heading_bundle' => __('Bundle', 'wpqb'),
        'table_heading_per_item' => __('Per Item', 'wpqb'),
        'table_heading_total_price' => __('Total Price', 'wpqb'),
        'table_head_bg_color' => '#084e86',
        'table_head_text_color' => '#ffffff',
        'table_body_bg_color' => '#ffffff',
        'table_body_text_color' => '#1d2327',
        'discount_bg_color' => '#e9f9ef',
        'discount_text_color' => '#0f7a38',
        'regular_price_color' => '#8a8a8a',
        'sale_price_color' => '#d63638',
        'strikethrough_price_color' => '#8a8a8a',
    ];
}

function wpqb_plugin_get_display_positions()
{
    return [
        'woocommerce_before_add_to_cart_button' => __('Before add to cart button', 'wpqb'),
        'woocommerce_after_add_to_cart_button' => __('After add to cart button', 'wpqb'),
        'woocommerce_single_product_summary' => __('Inside product summary', 'wpqb'),
    ];
}

function wpqb_plugin_sanitize_settings($settings)
{
    $defaults = wpqb_plugin_get_default_settings();
    $sanitized = $defaults;
    $settings = is_array($settings) ? $settings : [];
    $positions = wpqb_plugin_get_display_positions();

    $sanitized['table_title'] = isset($settings['table_title']) ? sanitize_text_field(wp_unslash($settings['table_title'])) : $defaults['table_title'];
    $sanitized['table_heading_bundle'] = isset($settings['table_heading_bundle']) ? sanitize_text_field(wp_unslash($settings['table_heading_bundle'])) : $defaults['table_heading_bundle'];
    $sanitized['table_heading_per_item'] = isset($settings['table_heading_per_item']) ? sanitize_text_field(wp_unslash($settings['table_heading_per_item'])) : $defaults['table_heading_per_item'];
    $sanitized['table_heading_total_price'] = isset($settings['table_heading_total_price']) ? sanitize_text_field(wp_unslash($settings['table_heading_total_price'])) : $defaults['table_heading_total_price'];

    $sanitized['design_type'] = (isset($settings['design_type']) && 'cards' === $settings['design_type']) ? 'cards' : 'table';

    $sanitized['display_position'] = isset($settings['display_position'], $positions[$settings['display_position']])
        ? $settings['display_position']
        : $defaults['display_position'];

    $sanitized['selection_mode'] = (isset($settings['selection_mode']) && 'manual' === $settings['selection_mode']) ? 'manual' : 'auto';

    foreach (['enable_simple_products', 'enable_variable_products', 'show_savings', 'show_discount_after_title', 'show_selected_total', 'show_per_item_price', 'show_regular_price_when_sale', 'show_qty_after_per_item', 'require_bundle_selection', 'cleanup_on_uninstall', 'shortcode_enabled'] as $key) {
        $sanitized[$key] = (!empty($settings[$key]) && 'yes' === $settings[$key]) ? 'yes' : 'no';
    }

    foreach (['table_head_bg_color', 'table_head_text_color', 'table_body_bg_color', 'table_body_text_color', 'discount_bg_color', 'discount_text_color', 'regular_price_color', 'sale_price_color', 'strikethrough_price_color'] as $color_key) {
        $sanitized[$color_key] = wpqb_plugin_sanitize_hex_color(isset($settings[$color_key]) ? $settings[$color_key] : '', $defaults[$color_key]);
    }

    return $sanitized;
}

function wpqb_plugin_sanitize_hex_color($value, $fallback)
{
    $value = sanitize_hex_color($value);

    if (empty($value)) {
        return $fallback;
    }

    return $value;
}

function wpqb_plugin_plugin_admin_notce()
{
    if (wpqb_plugin_has_active_required_plugins('woocommerce')) {
        return;
    }

    $plugin_name = isset(wpqb_plugin_info['Name']) ? wpqb_plugin_info['Name'] : __('WC Products Qty Bundle', 'wpqb');
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e('Error:', 'wpqb'); ?></strong>
            <?php echo esc_html(sprintf(__('%s requires WooCommerce to be installed and active.', 'wpqb'), $plugin_name)); ?>
        </p>
    </div>
    <?php
}

function wpqb_plugin_get_template($template_name, $args = [], $template_path = '', $default_path = '')
{
    if (!empty($args) && is_array($args)) {
        extract($args, EXTR_SKIP);
    }

    if (!$template_path) {
        $template_path = wpqb_plugin_info['slug'] . '/';
    }

    if (!$default_path) {
        $default_path = wpqb_plugin_template_path;
    }

    $template_name = (false !== strpos($template_name, '.php')) ? $template_name : $template_name . '.php';

    $template = locate_template([$template_path . $template_name]);

    if (!$template) {
        $template = $default_path . $template_name;
    }

    if (file_exists($template)) {
        include $template;
    }
}

function wpqb_plugin_settings($data = null)
{
    if (null !== $data) {
        $data = wpqb_plugin_sanitize_settings($data);
        update_option('wpqb_plugin_setting', $data);
    }

    $settings = get_option('wpqb_plugin_setting', []);

    return wp_parse_args(is_array($settings) ? $settings : [], wpqb_plugin_get_default_settings());
}

function wpqb_plugin_logs($data, $log = 'reports')
{
    if (!function_exists('get_home_path')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $file_path = trailingslashit(get_home_path()) . 'wpqb-plugin-' . sanitize_key($log) . '.log';
    $handle = fopen($file_path, 'a+');

    if (false === $handle) {
        return;
    }

    fwrite($handle, gmdate('Y-m-d H:i:s') . ' ' . wp_strip_all_tags((string) $data) . PHP_EOL);
    fclose($handle);
}
