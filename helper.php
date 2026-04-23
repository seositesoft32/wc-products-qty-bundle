<?php
/**
 * Plugin helper functions.
 *
 * Global utility functions used across the plugin for settings retrieval,
 * sanitization, template loading, dependency checking, and logging. This file
 * is loaded by the main plugin file before any class files so every function
 * is available throughout the plugin's bootstrap sequence.
 *
 * @package   WC_Products_Qty_Bundle
 * @since     1.0.0
 */

/**
 * Check whether one or all required plugins are active.
 *
 * When `$plugin_name` is supplied only that specific plugin is checked.
 * When omitted, every plugin in the required list must be active for the
 * function to return true.
 *
 * @since 1.0.0
 *
 * @param string $plugin_name Optional. A key from the internal `$plugins` map
 *                            (e.g. `'woocommerce'`) or a plugin basename
 *                            (e.g. `'woocommerce/woocommerce.php'`).
 *                            Pass an empty string (default) to check all.
 * @return bool True if the requested plugin(s) are active, false otherwise.
 */
function wpqb_plugin_has_active_required_plugins( $plugin_name = '' ) {
    $plugins = [
        'woocommerce' => 'woocommerce/woocommerce.php',
    ];

    if ( ! empty( $plugin_name ) ) {
        $plugin_file = isset( $plugins[ $plugin_name ] ) ? $plugins[ $plugin_name ] : $plugin_name;

        return is_plugin_active( $plugin_file );
    }

    foreach ( $plugins as $plugin ) {
        if ( ! is_plugin_active( $plugin ) ) {
            return false;
        }
    }

    return true;
}

/**
 * Return the plugin's default settings array.
 *
 * Provides the canonical default value for every plugin option. Used as the
 * fallback in `wpqb_plugin_settings()` and as the base in
 * `wpqb_plugin_sanitize_settings()` to guarantee every key is always present.
 *
 * @since 1.0.0
 *
 * @return array<string, mixed> Associative array of setting keys and their defaults.
 */
function wpqb_plugin_get_default_settings() {
    return [
        'table_title' => __('Quantity Bundles', 'wpqb'),
        'variable_placeholder_text' => __('Select product options to view bundles.', 'wpqb'),
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
        'enable_bundle_sorting' => 'yes',
        'auto_select_by_qty_change' => 'yes',
        'cleanup_on_uninstall' => 'no',
        'table_heading_bundle' => __('Bundle', 'wpqb'),
        'table_heading_per_item' => __('Per Item', 'wpqb'),
        'table_heading_total_price' => __('Total Price', 'wpqb'),
        'table_title_bg_color' => '#0a5998',
        'table_title_text_color' => '#ffffff',
        'table_head_bg_color' => '#084e86',
        'table_head_text_color' => '#ffffff',
        'table_body_bg_color' => '#ffffff',
        'table_body_text_color' => '#1d2327',
        'table_border_color' => '#e3e3e3',
        'table_cell_border_color' => '#ececec',
        'table_hover_bg_color' => '#f7fbff',
        'table_selected_bg_color' => '#edf5fd',
        'table_selected_border_color' => '#005284',
        'card_bg_color' => '#ffffff',
        'card_text_color' => '#1d2327',
        'card_border_color' => '#e1e3e5',
        'card_hover_border_color' => '#84b7df',
        'card_selected_border_color' => '#005284',
        'card_media_bg_color' => '#eff3f7',
        'card_radius' => '10',
        'discount_bg_color' => '#e9f9ef',
        'discount_text_color' => '#0f7a38',
        'regular_price_color' => '#8a8a8a',
        'sale_price_color' => '#d63638',
        'strikethrough_price_color' => '#8a8a8a',
    ];
}

/**
 * Return the allowed display hook positions.
 *
 * Maps WooCommerce action hook names (and the special `shortcode_only` value)
 * to human-readable labels used in the settings page select field.
 *
 * @since 1.0.0
 *
 * @return array<string, string> Associative array of hook name => label.
 */
function wpqb_plugin_get_display_positions() {
    return [
        'woocommerce_before_add_to_cart_button' => __( 'Before add to cart button', 'wpqb' ),
        'woocommerce_after_add_to_cart_button'  => __( 'After add to cart button', 'wpqb' ),
        'woocommerce_single_product_summary'    => __( 'Inside product summary', 'wpqb' ),
        'shortcode_only'                        => __( 'Shortcode only (no WooCommerce hook output)', 'wpqb' ),
    ];
}

/**
 * Sanitize and validate the raw plugin settings array.
 *
 * Merges the supplied `$settings` with plugin defaults, then sanitizes every
 * value to its expected type and range. Guarantees that the persisted option
 * is always a fully-populated, safe array regardless of what was submitted.
 *
 * Sanitization rules:
 *  - Text fields: `sanitize_text_field()`.
 *  - Boolean-style yes/no flags: must be exactly `'yes'`, otherwise `'no'`.
 *  - Color fields: validated hex color with fallback to the default value.
 *  - `card_radius`: integer clamped to 0–40.
 *  - `design_type`: only `'cards'` or `'table'`.
 *  - `selection_mode`: only `'manual'` or `'auto'`.
 *  - `display_position`: must be a key in `wpqb_plugin_get_display_positions()`.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $settings Raw (unsanitized) settings array, e.g.
 *                                       from a form submission or the database.
 * @return array<string, mixed> Fully sanitized settings array.
 */
function wpqb_plugin_sanitize_settings( $settings ) {
    $defaults   = wpqb_plugin_get_default_settings();
    $sanitized  = $defaults;
    $settings   = is_array( $settings ) ? $settings : [];
    $positions  = wpqb_plugin_get_display_positions();

    // -- Text fields.
    $sanitized['table_title']              = isset( $settings['table_title'] ) ? sanitize_text_field( wp_unslash( $settings['table_title'] ) ) : $defaults['table_title'];
    $sanitized['variable_placeholder_text'] = isset( $settings['variable_placeholder_text'] ) ? sanitize_text_field( wp_unslash( $settings['variable_placeholder_text'] ) ) : $defaults['variable_placeholder_text'];
    $sanitized['table_heading_bundle']     = isset( $settings['table_heading_bundle'] ) ? sanitize_text_field( wp_unslash( $settings['table_heading_bundle'] ) ) : $defaults['table_heading_bundle'];
    $sanitized['table_heading_per_item']   = isset( $settings['table_heading_per_item'] ) ? sanitize_text_field( wp_unslash( $settings['table_heading_per_item'] ) ) : $defaults['table_heading_per_item'];
    $sanitized['table_heading_total_price'] = isset( $settings['table_heading_total_price'] ) ? sanitize_text_field( wp_unslash( $settings['table_heading_total_price'] ) ) : $defaults['table_heading_total_price'];

    // -- Enumerated string fields.
    $sanitized['design_type']     = ( isset( $settings['design_type'] ) && 'cards' === $settings['design_type'] ) ? 'cards' : 'table';
    $sanitized['selection_mode']  = ( isset( $settings['selection_mode'] ) && 'manual' === $settings['selection_mode'] ) ? 'manual' : 'auto';

    $sanitized['display_position'] = isset( $settings['display_position'], $positions[ $settings['display_position'] ] )
        ? $settings['display_position']
        : $defaults['display_position'];

    // -- Yes/no boolean fields.
    foreach ( [ 'enable_simple_products', 'enable_variable_products', 'show_savings', 'show_discount_after_title', 'show_selected_total', 'show_per_item_price', 'show_regular_price_when_sale', 'show_qty_after_per_item', 'require_bundle_selection', 'enable_bundle_sorting', 'auto_select_by_qty_change', 'cleanup_on_uninstall' ] as $key ) {
        $sanitized[ $key ] = ( ! empty( $settings[ $key ] ) && 'yes' === $settings[ $key ] ) ? 'yes' : 'no';
    }

    // -- Hex color fields.
    foreach ( [ 'table_title_bg_color', 'table_title_text_color', 'table_head_bg_color', 'table_head_text_color', 'table_body_bg_color', 'table_body_text_color', 'table_border_color', 'table_cell_border_color', 'table_hover_bg_color', 'table_selected_bg_color', 'table_selected_border_color', 'card_bg_color', 'card_text_color', 'card_border_color', 'card_hover_border_color', 'card_selected_border_color', 'card_media_bg_color', 'discount_bg_color', 'discount_text_color', 'regular_price_color', 'sale_price_color', 'strikethrough_price_color' ] as $color_key ) {
        $sanitized[ $color_key ] = wpqb_plugin_sanitize_hex_color( isset( $settings[ $color_key ] ) ? $settings[ $color_key ] : '', $defaults[ $color_key ] );
    }

    // -- Numeric fields.
    $sanitized['card_radius'] = isset( $settings['card_radius'] ) ? max( 0, min( 40, absint( $settings['card_radius'] ) ) ) : absint( $defaults['card_radius'] );

    return $sanitized;
}

/**
 * Sanitize a hex color value, falling back to a default.
 *
 * Wraps WordPress's `sanitize_hex_color()` to provide an explicit fallback
 * rather than returning an empty string on failure. Accepts both 3-digit and
 * 6-digit hex colors.
 *
 * @since 1.0.0
 *
 * @param string $value    The raw hex color string to sanitize (e.g. `'#abc'`,
 *                          `'#aabbcc'`).
 * @param string $fallback The hex color to return when `$value` is invalid.
 * @return string A valid hex color string.
 */
function wpqb_plugin_sanitize_hex_color( $value, $fallback ) {
    $value = sanitize_hex_color( $value );

    if ( empty( $value ) ) {
        return $fallback;
    }

    return $value;
}

/**
 * Output an admin notice when WooCommerce is not active.
 *
 * Hooked into `admin_notices`. Silently returns when WooCommerce is already
 * active so no notice is shown during normal operation.
 *
 * @since 1.0.0
 *
 * @return void
 */
function wpqb_plugin_plugin_admin_notce() {
    if ( wpqb_plugin_has_active_required_plugins( 'woocommerce' ) ) {
        return;
    }

    $plugin_name = isset( wpqb_plugin_info['Name'] ) ? wpqb_plugin_info['Name'] : __( 'WC Products Qty Bundle', 'wpqb' );
    ?>
    <div class="notice notice-error">
        <p>
            <strong><?php esc_html_e( 'Error:', 'wpqb' ); ?></strong>
            <?php echo esc_html( sprintf( __( '%s requires WooCommerce to be installed and active.', 'wpqb' ), $plugin_name ) ); ?>
        </p>
    </div>
    <?php
}

/**
 * Locate and load a plugin template file.
 *
 * Searches the active theme directory first (allowing theme overrides), then
 * falls back to the plugin's own `templates/` directory. Variables passed via
 * `$args` are extracted into the template scope.
 *
 * Theme override path: `{theme}/wc-products-qty-bundle/{template_name}.php`
 * Plugin default path: `{plugin}/templates/{template_name}.php`
 *
 * @since 1.0.0
 *
 * @param string $template_name  Template file name without or with `.php` extension.
 * @param array  $args           Optional. Associative array of variables to
 *                               extract into the template scope. Default empty array.
 * @param string $template_path  Optional. Sub-path appended to the theme directory
 *                               when searching for overrides. Defaults to the plugin slug.
 * @param string $default_path   Optional. Absolute path to the fallback template directory.
 *                               Defaults to `WPQB_PLUGIN_TEMPLATE_PATH`.
 * @return void
 */
function wpqb_plugin_get_template( $template_name, $args = [], $template_path = '', $default_path = '' ) {
    if ( ! empty( $args ) && is_array( $args ) ) {
        extract( $args, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- intentional template pattern.
    }

    if ( ! $template_path ) {
        $template_path = wpqb_plugin_info['slug'] . '/';
    }

    if ( ! $default_path ) {
        $default_path = wpqb_plugin_template_path;
    }

    // Normalise: ensure the template name ends with .php.
    $template_name = ( false !== strpos( $template_name, '.php' ) ) ? $template_name : $template_name . '.php';

    // Check for a theme override before falling back to the plugin default.
    $template = locate_template( [ $template_path . $template_name ] );

    if ( ! $template ) {
        $template = $default_path . $template_name;
    }

    if ( file_exists( $template ) ) {
        include $template;
    }
}

/**
 * Get or update the plugin settings option.
 *
 * Acts as both a getter and a setter:
 *  - When `$data` is `null` (default), retrieves the current settings from the
 *    database, merged with plugin defaults so every key is always present.
 *  - When `$data` is an array, sanitizes and persists it first, then returns
 *    the freshly merged value.
 *
 * @since 1.0.0
 *
 * @param array|null $data Optional. Raw settings to sanitize and save.
 *                         Pass `null` to retrieve without modifying. Default null.
 * @return array<string, mixed> The current plugin settings.
 */
function wpqb_plugin_settings( $data = null ) {
    if ( null !== $data ) {
        $data = wpqb_plugin_sanitize_settings( $data );
        update_option( 'wpqb_plugin_setting', $data );
    }

    $settings = get_option( 'wpqb_plugin_setting', [] );

    return wp_parse_args( is_array( $settings ) ? $settings : [], wpqb_plugin_get_default_settings() );
}

/**
 * Append a timestamped line to a plugin-specific log file.
 *
 * Writes to `{ABSPATH}/wpqb-plugin-{$log}.log`. Intended for development
 * debugging and should not be called in production code paths without a
 * conditional guard.
 *
 * @since 1.0.0
 *
 * @param mixed  $data The data to log. Non-string values are cast to string;
 *                     HTML tags are stripped via `wp_strip_all_tags()`.
 * @param string $log  Optional. Log file identifier used in the filename.
 *                     Sanitized with `sanitize_key()`. Default `'reports'`.
 * @return void
 */
function wpqb_plugin_logs( $data, $log = 'reports' ) {
    if ( ! function_exists( 'get_home_path' ) ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $file_path = trailingslashit( get_home_path() ) . 'wpqb-plugin-' . sanitize_key( $log ) . '.log';
    $handle    = fopen( $file_path, 'a+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

    if ( false === $handle ) {
        return;
    }

    fwrite( $handle, gmdate( 'Y-m-d H:i:s' ) . ' ' . wp_strip_all_tags( (string) $data ) . PHP_EOL ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
    fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
}
