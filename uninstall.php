<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$settings = get_option('wpqb_plugin_setting', []);
$cleanup_enabled = is_array($settings) && isset($settings['cleanup_on_uninstall']) && 'yes' === $settings['cleanup_on_uninstall'];

if (!$cleanup_enabled) {
    return;
}

delete_option('wpqb_plugin_setting');

global $wpdb;

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
        '_wpqb_qty_bundles'
    )
);