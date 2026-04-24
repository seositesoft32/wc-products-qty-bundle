<?php

/**
 * Plugin Name:       WC Products Qty Bundle
 * Plugin URI:        https://myspecialtyflooring.com/
 * Description:       Add WooCommerce quantity bundle pricing with selectable bundle tiers for simple and variable products.
 * Version:           2.3.4
 * Author:            SeoSiteSoft
 * Author URI:        https://myspecialtyflooring.com/
 * Requires Plugins:  woocommerce
 * Requires at least: 6.4
 * Tested up to:      6.8
 * Requires PHP:      7.4
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpqb
 * Domain Path:       /languages/
 * WC requires at least: 7.8
 * WC tested up to:   10.0
 */

if (!defined('ABSPATH')) {
    exit();
}

if (!function_exists('is_plugin_active') || !function_exists('get_plugin_data')) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

define('WPQB_PLUGIN_VERSION', '2.3.4');
define('WPQB_PLUGIN_FILE', __FILE__);
define('WPQB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WPQB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPQB_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WPQB_PLUGIN_TEMPLATE_PATH', WPQB_PLUGIN_PATH . 'templates/');

if (!defined('wpqb_plugin_plugin_PATH')) {
    define('wpqb_plugin_plugin_PATH', WPQB_PLUGIN_PATH);
}

if (!defined('wpqb_plugin_plugin_URL')) {
    define('wpqb_plugin_plugin_URL', WPQB_PLUGIN_URL);
}

if (!defined('wpqb_plugin_plugin_basename')) {
    define('wpqb_plugin_plugin_basename', WPQB_PLUGIN_BASENAME);
}

if (!defined('wpqb_plugin_template_path')) {
    define('wpqb_plugin_template_path', WPQB_PLUGIN_TEMPLATE_PATH);
}

if (!defined('wpqb_plugin_info')) {
    $plugin_data = get_plugin_data(__FILE__);
    $plugin_data['v'] = WPQB_PLUGIN_VERSION;
    $plugin_data['base_name'] = WPQB_PLUGIN_BASENAME;
    $base_name = explode('/', WPQB_PLUGIN_BASENAME);
    $plugin_data['index'] = isset($base_name[1]) ? $base_name[1] : basename(__FILE__);
    $plugin_data['slug'] = isset($base_name[0]) ? $base_name[0] : 'wc-products-qty-bundle';
    define('wpqb_plugin_info', $plugin_data);
}

require_once WPQB_PLUGIN_PATH . 'helper.php';

function wpqb_plugin_activate()
{
    wpqb_plugin_settings(wpqb_plugin_settings());
}

function wpqb_plugin_declare_wc_compatibility()
{
    if (!class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        return;
    }

    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', WPQB_PLUGIN_FILE, true);
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', WPQB_PLUGIN_FILE, true);
}

function wpqb_plugin_load_textdomain()
{
    load_plugin_textdomain('wpqb', false, dirname(WPQB_PLUGIN_BASENAME) . '/languages');
}

function wpqb_plugin_load_inc_files()
{
    $files = glob(WPQB_PLUGIN_PATH . 'inc/*.php');

    if (empty($files)) {
        return;
    }

    foreach ($files as $file) {
        require_once $file;
    }
}

function wpqb_plugin_bootstrap()
{
    wpqb_plugin_load_textdomain();

    if (!wpqb_plugin_has_active_required_plugins()) {
        return;
    }

    wpqb_plugin_load_inc_files();
}

register_activation_hook(WPQB_PLUGIN_FILE, 'wpqb_plugin_activate');

add_action('before_woocommerce_init', 'wpqb_plugin_declare_wc_compatibility');
add_action('plugins_loaded', 'wpqb_plugin_bootstrap');
add_action('admin_notices', 'wpqb_plugin_plugin_admin_notce');
