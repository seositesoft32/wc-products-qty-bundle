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
        'display_position' => 'woocommerce_before_add_to_cart_button',
        'selection_mode' => 'auto',
        'enable_simple_products' => 'yes',
        'enable_variable_products' => 'yes',
        'show_savings' => 'yes',
        'show_selected_total' => 'yes',
        'require_bundle_selection' => 'no',
        'cleanup_on_uninstall' => 'no',
        'shortcode_enabled' => 'yes',
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

    $sanitized['display_position'] = isset($settings['display_position'], $positions[$settings['display_position']])
        ? $settings['display_position']
        : $defaults['display_position'];

    $sanitized['selection_mode'] = (isset($settings['selection_mode']) && 'manual' === $settings['selection_mode']) ? 'manual' : 'auto';

    foreach (['enable_simple_products', 'enable_variable_products', 'show_savings', 'show_selected_total', 'require_bundle_selection', 'cleanup_on_uninstall', 'shortcode_enabled'] as $key) {
        $sanitized[$key] = (!empty($settings[$key]) && 'yes' === $settings[$key]) ? 'yes' : 'no';
    }

    return $sanitized;
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
