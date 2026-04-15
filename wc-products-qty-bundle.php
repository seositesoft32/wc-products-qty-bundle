<?php

/**
 * Plugin Name:       WC Products Qty Bundle
 * Plugin URI:        #
 * Description:       Adds quantity-based bundle pricing for WooCommerce products and variations, with bundle selection on product pages and automatic cart/order pricing.
 * Version:           2.0
 * Author:            SeoSiteSoft
 * Author URI:        #
 * Requires at least: 5.9
 * Tested up to:      6.0
 * Requires PHP:      7.4
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpqb
 */


if (!defined('ABSPATH')) {
    exit();
}

if (!function_exists('is_plugin_active') || !function_exists('get_plugin_data')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

if (!defined('wpqb_plugin_plugin_PATH')) {
    define('wpqb_plugin_plugin_PATH', plugin_dir_path(__FILE__));
}

if (!defined('wpqb_plugin_plugin_URL')) {
    define('wpqb_plugin_plugin_URL', plugin_dir_url(__FILE__));
}

if (!defined('wpqb_plugin_plugin_basename')) {
    define('wpqb_plugin_plugin_basename', plugin_basename(__FILE__));
}

if (!defined('wpqb_plugin_template_path')) {
    define('wpqb_plugin_template_path', wpqb_plugin_plugin_PATH . 'templates/');
}

if (!defined('wpqb_plugin_info')) {
    $plugin_data = get_plugin_data(__FILE__);
    // $plugin_data['v'] = $plugin_data['Version'];
    $plugin_data['v'] = strtotime('now');
    $base_name = plugin_basename(__FILE__);
    $plugin_data['base_name'] = $base_name;
    $base_name = explode('/', $base_name);
    $plugin_data['index'] = $base_name[1];
    $plugin_data['slug'] = $base_name[0];
    define('wpqb_plugin_info', $plugin_data);
}

require_once wpqb_plugin_plugin_PATH . "helper.php";

add_action('admin_notices', 'wpqb_plugin_plugin_admin_notce');

if (wpqb_plugin_has_active_required_plugins()):

    function wpqb_plugin_load_admin_assets()
    {
        wp_register_script('wpqb_plugin_admin_scripts', wpqb_plugin_plugin_URL . 'assets/js/admin-scripts.js', [], wpqb_plugin_info['v'], true);
        wp_register_style('wpqb_plugin_admin_style', wpqb_plugin_plugin_URL . 'assets/css/admin-style.css', [], wpqb_plugin_info['v']);

        $localize_object = [];
        $localize_object['ajax_url'] = admin_url('admin-ajax.php');
        $localize_object['wpqb_plugin_plugin_URL'] = wpqb_plugin_plugin_URL;
        $localize_object['timeNow'] = strtotime('now');

        wp_localize_script('wpqb_plugin_admin_scripts', 'wpqb_plugina', $localize_object);

        wp_enqueue_script('wpqb_plugin_admin_scripts');
        wp_enqueue_style('wpqb_plugin_admin_style');
    }

    add_action('admin_enqueue_scripts', 'wpqb_plugin_load_admin_assets', 10);

    function wpqb_plugin_load_plugin_assets()
    {
        wp_register_style('wpqb_plugin_plugin_style', wpqb_plugin_plugin_URL . 'assets/css/style.css', [], wpqb_plugin_info['v']);
        wp_register_script('wpqb_plugin_plugin_scripts', wpqb_plugin_plugin_URL . 'assets/js/scripts.js', [], wpqb_plugin_info['v'], true);

        $localize_object = [];
        $localize_object['ajax_url'] = admin_url('admin-ajax.php');

        wp_localize_script('wpqb_plugin_plugin_scripts', 'wpqb_plugin', $localize_object);

        wp_enqueue_style('wpqb_plugin_plugin_style');
        wp_enqueue_script('wpqb_plugin_plugin_scripts');
    }

    add_action('wp_enqueue_scripts', 'wpqb_plugin_load_plugin_assets');

    function wpqb_plugin_load_inc_files()
    {
        $dir = wpqb_plugin_plugin_PATH . 'inc';
        $files = glob($dir . '/**.php');
        foreach ($files as $file) {
            require_once $file;
        }
    }
    wpqb_plugin_load_inc_files();

endif;
