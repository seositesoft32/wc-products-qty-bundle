<?php
/**
 * Plugin uninstall routine.
 *
 * Executed by WordPress when the plugin is deleted from the Plugins screen.
 * All cleanup is conditional on the `cleanup_on_uninstall` setting being
 * enabled so site owners can choose to keep their data.
 *
 * WordPress only runs this file when:
 *  - The request originates from the WordPress uninstall process.
 *  - The `WP_UNINSTALL_PLUGIN` constant is defined.
 *
 * Direct execution is blocked by the constant check below.
 *
 * @package WC_Products_Qty_Bundle
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Read settings before deletion so we can check the cleanup preference.
$settings        = get_option( 'wpqb_plugin_setting', [] );
$cleanup_enabled = is_array( $settings ) && isset( $settings['cleanup_on_uninstall'] ) && 'yes' === $settings['cleanup_on_uninstall'];

// Bail out early if the site owner has not opted in to data removal.
if ( ! $cleanup_enabled ) {
    return;
}

// Remove the plugin settings option.
delete_option( 'wpqb_plugin_setting' );

global $wpdb;

// Remove all bundle meta from every product and variation in the database.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
        '_wpqb_qty_bundles'
    )
);