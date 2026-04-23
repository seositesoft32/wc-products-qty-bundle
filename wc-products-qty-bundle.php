<?php
/**
 * Plugin Name:       WC Products Qty Bundle
 * Plugin URI:        https://myspecialtyflooring.com/
 * Description:       Add WooCommerce quantity bundle pricing with selectable bundle tiers for simple and variable products.
 * Version:           2.4.0
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
 *
 * @package WC_Products_Qty_Bundle
 *
 * This plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * This plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit();
}

if ( ! function_exists( 'is_plugin_active' ) || ! function_exists( 'get_plugin_data' ) ) {
    include_once ABSPATH . 'wp-admin/includes/plugin.php';
}

/** Plugin version. */
define( 'WPQB_PLUGIN_VERSION', '2.4.0' );

/** Absolute path to the main plugin file. */
define( 'WPQB_PLUGIN_FILE', __FILE__ );

/** Absolute path to the plugin directory (with trailing slash). */
define( 'WPQB_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/** URL to the plugin directory (with trailing slash). */
define( 'WPQB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/** Plugin basename, e.g. `wc-products-qty-bundle/wc-products-qty-bundle.php`. */
define( 'WPQB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/** Absolute path to the plugin templates directory (with trailing slash). */
define( 'WPQB_PLUGIN_TEMPLATE_PATH', WPQB_PLUGIN_PATH . 'templates/' );

// Legacy constant aliases — kept for backward compatibility with any external
// code that may reference the older naming convention.
if ( ! defined( 'wpqb_plugin_plugin_PATH' ) ) {
    define( 'wpqb_plugin_plugin_PATH', WPQB_PLUGIN_PATH );
}

if ( ! defined( 'wpqb_plugin_plugin_URL' ) ) {
    define( 'wpqb_plugin_plugin_URL', WPQB_PLUGIN_URL );
}

if ( ! defined( 'wpqb_plugin_plugin_basename' ) ) {
    define( 'wpqb_plugin_plugin_basename', WPQB_PLUGIN_BASENAME );
}

if ( ! defined( 'wpqb_plugin_template_path' ) ) {
    define( 'wpqb_plugin_template_path', WPQB_PLUGIN_TEMPLATE_PATH );
}

// Build and store parsed plugin header data as a constant for use across the
// codebase (e.g. slug resolution, version access).
if ( ! defined( 'wpqb_plugin_info' ) ) {
    $plugin_data          = get_plugin_data( __FILE__ );
    $plugin_data['v']     = WPQB_PLUGIN_VERSION;
    $plugin_data['base_name'] = WPQB_PLUGIN_BASENAME;
    $base_name            = explode( '/', WPQB_PLUGIN_BASENAME );
    $plugin_data['index'] = isset( $base_name[1] ) ? $base_name[1] : basename( __FILE__ );
    $plugin_data['slug']  = isset( $base_name[0] ) ? $base_name[0] : 'wc-products-qty-bundle';
    define( 'wpqb_plugin_info', $plugin_data );
}

require_once WPQB_PLUGIN_PATH . 'helper.php';

/**
 * Run on plugin activation.
 *
 * Ensures the plugin settings option exists in the database by writing the
 * current (or default) settings on first activation.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wpqb_plugin_activate() {
    wpqb_plugin_settings( wpqb_plugin_settings() );
}

/**
 * Declare compatibility with WooCommerce feature flags.
 *
 * Signals to WooCommerce that this plugin is compatible with High-Performance
 * Order Storage (HPOS / custom_order_tables) and the Cart & Checkout Blocks
 * (cart_checkout_blocks). Called on the `before_woocommerce_init` hook so the
 * declaration is registered before WooCommerce reads it.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wpqb_plugin_declare_wc_compatibility() {
    if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        return;
    }

    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WPQB_PLUGIN_FILE, true );
    \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WPQB_PLUGIN_FILE, true );
}

/**
 * Load the plugin text domain for translation.
 *
 * Called on `plugins_loaded` via `wpqb_plugin_bootstrap()`. Translation files
 * should be placed in `/languages/wpqb-{locale}.mo`.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wpqb_plugin_load_textdomain() {
    load_plugin_textdomain( 'wpqb', false, dirname( WPQB_PLUGIN_BASENAME ) . '/languages' );
}

/**
 * Require the plugin's class files in dependency order.
 *
 * Loads the four core classes in the correct order (Base → Admin → Frontend →
 * Init) so that parent classes exist before their children. Any additional
 * files found in `inc/` are loaded afterwards.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wpqb_plugin_load_inc_files() {
    $ordered_files = [
        WPQB_PLUGIN_PATH . 'inc/class-plugin-base.php',
        WPQB_PLUGIN_PATH . 'inc/class-plugin-admin.php',
        WPQB_PLUGIN_PATH . 'inc/class-plugin-frontend.php',
        WPQB_PLUGIN_PATH . 'inc/class-plugin-init.php',
    ];

    foreach ( $ordered_files as $file ) {
        if ( file_exists( $file ) ) {
            require_once $file;
        }
    }

    // Load any additional files added to inc/ that are not in the ordered list.
    $files = glob( WPQB_PLUGIN_PATH . 'inc/*.php' );
    if ( empty( $files ) ) {
        return;
    }

    foreach ( $files as $file ) {
        if ( ! in_array( $file, $ordered_files, true ) ) {
            require_once $file;
        }
    }
}

/**
 * Bootstrap the plugin.
 *
 * Loads the text domain and, if all required plugins are active, loads the
 * plugin's class files. Called on the `plugins_loaded` hook to ensure
 * WooCommerce is fully available.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wpqb_plugin_bootstrap() {
    wpqb_plugin_load_textdomain();

    if ( ! wpqb_plugin_has_active_required_plugins() ) {
        return;
    }

    wpqb_plugin_load_inc_files();
}

register_activation_hook(WPQB_PLUGIN_FILE, 'wpqb_plugin_activate');

add_action('before_woocommerce_init', 'wpqb_plugin_declare_wc_compatibility');
add_action('plugins_loaded', 'wpqb_plugin_bootstrap');
add_action('admin_notices', 'wpqb_plugin_plugin_admin_notce');
