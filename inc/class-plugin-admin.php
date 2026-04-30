<?php
/**
 * Plugin admin class.
 *
 * Handles all WordPress admin-side functionality for the WC Products Qty Bundle
 * plugin, including the settings page, product/variation meta fields, AJAX save
 * endpoint, and admin asset enqueueing.
 *
 * @package   WC_Products_Qty_Bundle
 * @subpackage Inc
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WPQB_Plugin_Admin
 *
 * Extends the base plugin class to register admin hooks, render the settings
 * page, persist bundle data for simple and variable products, and handle
 * AJAX-driven settings saves.
 *
 * @since 1.0.0
 */
class WPQB_Plugin_Admin extends WPQB_Plugin_Base {
    /**
     * Register all admin-side WordPress hooks.
     *
     * Wires up settings registration, admin menu creation, AJAX save, plugin
     * action links, WooCommerce product/variation meta hooks, and admin asset
     * enqueueing.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hooks() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_menu', [ $this, 'admin_menus' ] );
        add_action( 'wp_ajax_wpqb_save_settings', [ $this, 'ajax_save_settings' ] );
        add_filter( 'plugin_action_links_' . WPQB_PLUGIN_BASENAME, [ $this, 'add_plugin_action_links' ] );

        add_action( 'woocommerce_product_options_pricing', [ $this, 'add_qty_bundle_fields' ] );
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_qty_bundle_fields' ] );
        add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'add_variation_bundle_fields' ], 10, 3 );
        add_action( 'woocommerce_save_product_variation', [ $this, 'save_variation_bundle_fields' ], 10, 2 );

        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Register the plugin settings with the WordPress Settings API.
     *
     * Registers `wpqb_plugin_setting` under `wpqb_plugin_settings_group` with
     * type validation, a sanitization callback, and plugin defaults. This is
     * required for `settings_fields()` / `options.php` to work and also acts
     * as the authority for REST-API schema validation of this option.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register_settings() {
        register_setting(
            'wpqb_plugin_settings_group',
            'wpqb_plugin_setting',
            [
                'type'              => 'array',
                'sanitize_callback' => 'wpqb_plugin_sanitize_settings',
                'default'           => wpqb_plugin_get_default_settings(),
            ]
        );
    }

    /**
     * Prepend a "Settings" link to the plugin row on the Plugins screen.
     *
     * Gives administrators a one-click path from the plugin list table to the
     * plugin settings page.
     *
     * @since 1.0.0
     *
     * @param string[] $links Existing plugin action link HTML strings.
     * @return string[]       Modified array with the Settings link prepended.
     */
    public function add_plugin_action_links( $links ) {
        $settings_url = admin_url( 'admin.php?page=wpqb-plugin-settings' );

        array_unshift(
            $links,
            sprintf( '<a href="%s">%s</a>', esc_url( $settings_url ), esc_html__( 'Settings', 'wpqb' ) )
        );

        return $links;
    }

    /**
     * Register the plugin's admin sub-menu page under WooCommerce.
     *
     * Adds a "Qty Bundles" submenu item to the WooCommerce admin menu. The page
     * is restricted to users with the `manage_woocommerce` capability so that
     * only store managers and administrators can access it.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function admin_menus() {
        add_submenu_page(
            'woocommerce',
            __( 'Qty Bundles', 'wpqb' ),
            __( 'Qty Bundles', 'wpqb' ),
            'manage_woocommerce',
            'wpqb-plugin-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Render the plugin settings page.
     *
     * Builds the data array expected by the `admin-menu` template and delegates
     * rendering to `wpqb_plugin_get_template()`. Separating data preparation
     * from display keeps the template logic clean and testable.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function render_settings_page() {
        $data = [
            'settings'          => wpqb_plugin_settings(),
            'positions'         => wpqb_plugin_get_display_positions(),
            'shortcode_example' => '[wpqb_bundles product_id="123"]',
        ];

        /*
         * Filters the data passed to the plugin settings page template.
         *
         * Since: 2.3.0
         * Arguments: $data, $this
         */
        $data = apply_filters( 'wpqb_admin_settings_page_data', $data, $this );

        wpqb_plugin_get_template( 'admin-menu', $data );
    }

    /**
     * Enqueue admin-side CSS and JavaScript assets.
     *
     * Assets are only loaded on the product edit screen and the plugin settings
     * page to keep the overall admin footprint minimal. The media uploader is
     * additionally enqueued on product screens to support bundle image pickers.
     *
     * @since 1.0.0
     *
     * @param string $hook The current admin page hook suffix (e.g. `post.php`).
     * @return void
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load assets on product edit screens and the plugin settings page.
        $allowed_hooks = [ 'post.php', 'post-new.php', 'woocommerce_page_wpqb-plugin-settings' ];
        if ( ! in_array( $hook, $allowed_hooks, true ) ) {
            return;
        }

        $is_settings_page = ( 'woocommerce_page_wpqb-plugin-settings' === $hook );

        wp_enqueue_script( 'wpqb-admin-js', WPQB_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery' ], WPQB_PLUGIN_VERSION, true );
        wp_localize_script(
            'wpqb-admin-js',
            'wpqbAdmin',
            [
                'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
                'saveNonce'     => wp_create_nonce( 'wpqb_save_settings' ),
                'isSettingsPage' => $is_settings_page,
                'confirmRemove' => __( 'Are you sure you want to remove this bundle?', 'wpqb' ),
                'mediaTitle'    => __( 'Select Bundle Image', 'wpqb' ),
                'mediaButton'   => __( 'Use this image', 'wpqb' ),
                'saveButton'    => __( 'Save Settings', 'wpqb' ),
                'savingButton'  => __( 'Saving...', 'wpqb' ),
                'savedMessage'  => __( 'Settings saved successfully.', 'wpqb' ),
                'errorMessage'  => __( 'Unable to save settings. Please try again.', 'wpqb' ),
            ]
        );

        // Enqueue the WP media uploader on product edit screens (used by image pickers).
        if ( in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            global $post;

            if ( ! $post || 'product' !== $post->post_type ) {
                return;
            }

            wp_enqueue_media();
        }

        wp_enqueue_style( 'wpqb-admin-css', WPQB_PLUGIN_URL . 'assets/css/admin.css', [], WPQB_PLUGIN_VERSION );
    }

    /**
     * Handle the AJAX request to save plugin settings.
     *
     * Validates the current user's capability and verifies the nonce before
     * parsing, sanitizing, and persisting the settings payload. Responds with
     * a JSON success or error object consumed by `admin.js`.
     *
     * Expected POST parameters:
     *   - `nonce`     {string} Nonce created with action `wpqb_save_settings`.
     *   - `form_data` {string} URL-encoded form data produced by jQuery `.serialize()`.
     *
     * @since 1.0.0
     *
     * @return void Outputs JSON and terminates via `wp_send_json_*`.
     */
    public function ajax_save_settings() {
        // Authorization check must come before nonce verification to return a
        // meaningful 403 rather than a generic nonce failure message.
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( [
                'message' => __( 'You are not allowed to save these settings.', 'wpqb' ),
            ], 403 );
        }

        // Nonce verification prevents CSRF attacks.
        check_ajax_referer( 'wpqb_save_settings', 'nonce' );

        // Require a non-empty serialized form payload.
        $form_data_raw = isset( $_POST['form_data'] ) ? wp_unslash( $_POST['form_data'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized downstream via wpqb_plugin_sanitize_settings().
        if ( empty( $form_data_raw ) || ! is_string( $form_data_raw ) ) {
            wp_send_json_error( [
                'message' => __( 'No settings payload received.', 'wpqb' ),
            ], 400 );
        }

        // Parse the URL-encoded form data and extract the plugin option array.
        $parsed = [];
        parse_str( $form_data_raw, $parsed );
        $settings = isset( $parsed['wpqb_plugin_setting'] ) && is_array( $parsed['wpqb_plugin_setting'] )
            ? $parsed['wpqb_plugin_setting']
            : [];

        // Sanitize, persist, and refresh the in-memory settings property.
        $sanitized      = wpqb_plugin_sanitize_settings( $settings );
        update_option( 'wpqb_plugin_setting', $sanitized );
        $this->settings = wpqb_plugin_settings();

        /*
         * Fires after plugin settings are saved from the admin AJAX endpoint.
         *
         * Since: 2.3.0
         * Arguments: $sanitized, $settings, $this
         */
        do_action( 'wpqb_admin_settings_saved', $sanitized, $settings, $this );

        wp_send_json_success( [
            'message'  => __( 'Settings saved successfully.', 'wpqb' ),
            'settings' => $sanitized,
        ] );
    }

    /**
     * Render quantity bundle fields in the WooCommerce "General" pricing tab.
     *
     * Hooked into `woocommerce_product_options_pricing`. For variable products
     * this outputs an informational notice directing the user to the Variations
     * tab instead. For simple (and all other) product types it renders the full
     * bundle editor template.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function add_qty_bundle_fields() {
        global $post;

        $product = wc_get_product( $post->ID );

        // Variable products manage bundles per-variation; show a redirect notice.
        if ( $product && $product->is_type( 'variable' ) ) {
            wpqb_plugin_get_template( 'admin-variable-bundles-message' );
            return;
        }

        $data = [
            'bundles'     => $this->get_product_bundles( $post->ID ),
            'name_prefix' => 'wpqb_bundles',
            'renderer'    => $this,
        ];

        wpqb_plugin_get_template( 'admin-product-bundles', $data );
    }

    /**
     * Render the repeatable bundle row fields template for a single bundle entry.
     *
     * Called by the `admin-product-bundles` and `admin-variation-bundles`
     * templates via the `$renderer` reference so that both simple and variable
     * product contexts share identical field markup.
     *
     * @since 1.0.0
     *
     * @param int    $index       Zero-based index of this bundle row.
     * @param array  $bundle      Associative array of existing bundle data.
     *                            Accepted keys: `name`, `regular_price`,
     *                            `sale_price`, `qty`, `image_id`.
     * @param string $name_prefix HTML input name prefix (e.g. `wpqb_bundles`
     *                            or `wpqb_variation_bundles[{id}]`).
     * @return void
     */
    public function render_bundle_fields( $index, $bundle = [], $name_prefix = 'wpqb_bundles' ) {
        $data = [
            'index'         => $index,
            'name_prefix'   => $name_prefix,
            'bundle_name'   => isset( $bundle['name'] )          ? $bundle['name']                : '',
            'regular_price' => isset( $bundle['regular_price'] ) ? $bundle['regular_price']       : '',
            'sale_price'    => isset( $bundle['sale_price'] )    ? $bundle['sale_price']          : '',
            'qty'           => isset( $bundle['qty'] )           ? $bundle['qty']                 : '',
            'image_id'      => isset( $bundle['image_id'] )      ? absint( $bundle['image_id'] )  : 0,
        ];

        // Resolve the thumbnail URL from the media library if an image is attached.
        $data['image_url'] = $data['image_id'] ? wp_get_attachment_image_url( $data['image_id'], 'thumbnail' ) : '';

        /*
         * Filters admin bundle row template data before rendering.
         *
         * Since: 2.3.0
         * Arguments: $data, $index, $bundle, $name_prefix, $this
         */
        $data = apply_filters( 'wpqb_admin_bundle_field_data', $data, $index, $bundle, $name_prefix, $this );

        wpqb_plugin_get_template( 'admin-bundle-fields', $data );
    }

    /**
     * Save quantity bundle meta when a WooCommerce product is saved.
     *
     * Hooked into `woocommerce_process_product_meta`. Handles both simple
     * products (stores bundles on the parent post) and variable products
     * (iterates rendered variation IDs and stores bundles on each child).
     *
     * For variable products, a hidden `wpqb_variation_bundle_rendered` input
     * identifies which variation panels were actually present in the form,
     * preventing accidental deletion of bundles belonging to tabs not rendered.
     *
     * @since 1.0.0
     *
     * @param int $post_id The product post ID being saved.
     * @return void
     */
    public function save_qty_bundle_fields( $post_id ) {
        // Capability and nonce checks are centralised in the helper.
        if ( ! $this->can_save_product_bundles( $post_id ) ) {
            return;
        }

        $product = wc_get_product( $post_id );

        if ( $product && $product->is_type( 'variable' ) ) {
            // Nothing to do if no variation panels were rendered in the form.
            if ( empty( $_POST['wpqb_variation_bundle_rendered'] ) || ! is_array( $_POST['wpqb_variation_bundle_rendered'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in can_save_product_bundles().
                return;
            }

            // Restrict saves to IDs that genuinely belong to this product to
            // prevent IDOR (Insecure Direct Object Reference) attacks.
            $child_ids    = array_map( 'absint', $product->get_children() );
            $rendered_raw = wp_unslash( $_POST['wpqb_variation_bundle_rendered'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $rendered_ids = array_map( 'absint', array_keys( $rendered_raw ) );
            $valid_ids    = array_intersect( $rendered_ids, $child_ids );

            $raw_variations = isset( $_POST['wpqb_variation_bundles'] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
                ? wp_unslash( $_POST['wpqb_variation_bundles'] )
                : [];

            foreach ( $valid_ids as $variation_id ) {
                $raw_bundles = isset( $raw_variations[ $variation_id ] ) ? $raw_variations[ $variation_id ] : [];
                $bundles     = $this->sanitize_bundles( $raw_bundles );

                if ( empty( $bundles ) ) {
                    delete_post_meta( $variation_id, '_wpqb_qty_bundles' );
                } else {
                    update_post_meta( $variation_id, '_wpqb_qty_bundles', $bundles );
                }

                /*
                 * Fires after bundle rows are saved for a variation during parent product save.
                 *
                 * Since: 2.3.0
                 * Arguments: $variation_id, $bundles, $raw_bundles, $this
                 */
                do_action( 'wpqb_saved_product_bundles', $variation_id, $bundles, $raw_bundles, $this );
            }

            return;
        }

        // Simple product path.
        $bundles = isset( $_POST['wpqb_bundles'] ) ? $this->sanitize_bundles( wp_unslash( $_POST['wpqb_bundles'] ) ) : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing

        if ( empty( $bundles ) ) {
            delete_post_meta( $post_id, '_wpqb_qty_bundles' );

            /*
             * Fires after bundle rows are cleared for a product.
             *
             * Since: 2.3.0
             * Arguments: $post_id, $bundles, $raw_bundles, $this
             */
            do_action( 'wpqb_saved_product_bundles', $post_id, $bundles, [], $this );
            return;
        }

        update_post_meta( $post_id, '_wpqb_qty_bundles', $bundles );

        /*
         * Fires after bundle rows are saved for a simple product.
         *
         * Since: 2.3.0
         * Arguments: $post_id, $bundles, $raw_bundles, $this
         */
        do_action( 'wpqb_saved_product_bundles', $post_id, $bundles, wp_unslash( $_POST['wpqb_bundles'] ), $this ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in can_save_product_bundles().
    }

    /**
     * Render the bundle fields panel inside a WooCommerce variation accordion row.
     *
     * Hooked into `woocommerce_product_after_variable_attributes` with priority 10.
     * Each variation gets its own bundle editor below the standard WooCommerce
     * variation fields.
     *
     * @since 1.0.0
     *
     * @param int     $loop           The current variation loop index (unused).
     * @param array   $variation_data Array of variation meta data (unused).
     * @param WP_Post $variation      The variation post object.
     * @return void
     */
    public function add_variation_bundle_fields( $loop, $variation_data, $variation ) {
        unset( $loop, $variation_data ); // Not needed; parameters required by hook signature.

        $variation_id = $variation->ID;
        $data = [
            'variation_id' => $variation_id,
            'bundles'      => $this->get_product_bundles( $variation_id ),
            'renderer'     => $this,
        ];

        /*
         * Filters variation bundle panel data before rendering.
         *
         * Since: 2.3.0
         * Arguments: $data, $variation_id, $variation, $this
         */
        $data = apply_filters( 'wpqb_admin_variation_bundle_data', $data, $variation_id, $variation, $this );

        wpqb_plugin_get_template( 'admin-variation-bundles', $data );
    }

    /**
     * Save bundle meta for a single product variation.
     *
     * Hooked into `woocommerce_save_product_variation` with priority 10. Fires
     * once per variation during both the AJAX bulk-edit save and the standard
     * product save flow. Nonce verification is delegated to
     * `can_save_variation_bundles()`, which accepts either the AJAX
     * `save-variations` nonce or the classic `woocommerce_save_data` nonce.
     *
     * @since 1.0.0
     *
     * @param int $variation_id The variation post ID to save bundles for.
     * @param int $index        The loop index of the current variation (unused).
     * @return void
     */
    public function save_variation_bundle_fields( $variation_id, $index ) {
        unset( $index ); // Not needed; parameter required by hook signature.

        if ( ! $this->can_save_variation_bundles( $variation_id ) ) {
            return;
        }

        $raw_variations = isset( $_POST['wpqb_variation_bundles'] ) ? wp_unslash( $_POST['wpqb_variation_bundles'] ) : []; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified in can_save_variation_bundles().
        $raw_bundles    = isset( $raw_variations[ $variation_id ] ) ? $raw_variations[ $variation_id ] : [];
        $bundles        = $this->sanitize_bundles( $raw_bundles );

        if ( empty( $bundles ) ) {
            delete_post_meta( $variation_id, '_wpqb_qty_bundles' );

            /*
             * Fires after bundle rows are cleared for a variation save request.
             *
             * Since: 2.3.0
             * Arguments: $variation_id, $bundles, $raw_bundles, $this
             */
            do_action( 'wpqb_saved_variation_bundles', $variation_id, $bundles, $raw_bundles, $this );
            return;
        }

        update_post_meta( $variation_id, '_wpqb_qty_bundles', $bundles );

        /*
         * Fires after bundle rows are saved for an individual variation request.
         *
         * Since: 2.3.0
         * Arguments: $variation_id, $bundles, $raw_bundles, $this
         */
        do_action( 'wpqb_saved_variation_bundles', $variation_id, $bundles, $raw_bundles, $this );
    }

    /**
     * Determine whether it is safe to save product bundle data.
     *
     * Combines several security checks that must all pass before any product
     * meta is written:
     *  1. Skip autosaves to avoid race conditions with revision creation.
     *  2. Verify the current user can edit the specific post.
     *  3. Validate WooCommerce's own `woocommerce_meta_nonce` to confirm the
     *     request originated from the product edit form.
     *
     * @since 1.0.0
     *
     * @param int $post_id The product post ID whose meta we intend to save.
     * @return bool True if safe to proceed, false otherwise.
     */
    private function can_save_product_bundles( $post_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return false;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return false;
        }

        if ( empty( $_POST['woocommerce_meta_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified on next line.
            return false;
        }

        return (bool) wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' );
    }

    /**
     * Determine whether it is safe to save variation bundle data.
     *
     * Variation saves can arrive via two distinct code paths in WooCommerce:
     *  - The AJAX bulk-save action uses the `security` field with the
     *    `save-variations` nonce.
     *  - The classic form-submit path uses `woocommerce_meta_nonce` with the
     *    `woocommerce_save_data` nonce.
     *
     * Either nonce being valid is sufficient. Autosaves are still blocked and
     * a per-post capability check is enforced in both cases.
     *
     * @since 1.0.0
     *
     * @param int $variation_id The variation post ID whose meta we intend to save.
     * @return bool True if at least one valid nonce is present and the user can
     *              edit the variation, false otherwise.
     */
    private function can_save_variation_bundles( $variation_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return false;
        }

        if ( ! current_user_can( 'edit_post', $variation_id ) ) {
            return false;
        }

        // AJAX variation save path (WooCommerce "Save variations" button).
        $security_nonce_valid = false;
        if ( ! empty( $_POST['security'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified on next line.
            $security_nonce_valid = (bool) wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['security'] ) ),
                'save-variations'
            );
        }

        // Classic product-form save path.
        $meta_nonce_valid = false;
        if ( ! empty( $_POST['woocommerce_meta_nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified on next line.
            $meta_nonce_valid = (bool) wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ),
                'woocommerce_save_data'
            );
        }

        return ( $security_nonce_valid || $meta_nonce_valid );
    }
}
