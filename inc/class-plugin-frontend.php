<?php
/**
 * Plugin frontend class.
 *
 * Handles all customer-facing functionality: asset enqueueing, bundle display
 * on product pages (both hook-based and shortcode), variation data injection,
 * cart/order line-item handling, and price overrides.
 *
 * @package   WC_Products_Qty_Bundle
 * @subpackage Inc
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WPQB_Plugin_Frontend
 *
 * Extends WPQB_Plugin_Base to register all customer-facing WordPress and
 * WooCommerce hooks. Contains no direct output — all markup is delegated to
 * template files via `wpqb_plugin_get_template()`.
 *
 * @since 1.0.0
 */
class WPQB_Plugin_Frontend extends WPQB_Plugin_Base {

    /**
     * Register all frontend WordPress and WooCommerce hooks.
     *
     * Wires up asset enqueueing, the product-page display action, the shortcode,
     * variation data filtering, cart validation, cart item data, cart price
     * recalculation, and order line-item persistence.
     *
     * The display action is only registered when the configured position is not
     * `shortcode_only`; in that case the bundle is exclusively rendered via
     * the `[wpqb_bundles]` shortcode.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function hooks() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

        $display_hook = $this->get_display_hook();
        if ( ! empty( $display_hook ) ) {
            add_action( $display_hook, [ $this, 'display_qty_bundles' ] );
        }

        add_shortcode( 'wpqb_bundles', [ $this, 'render_bundles_shortcode' ] );

        add_filter( 'woocommerce_available_variation',     [ $this, 'append_variation_bundle_data' ], 10, 3 );
        add_filter( 'woocommerce_add_to_cart_validation',  [ $this, 'validate_bundle_selection' ],    10, 5 );
        add_filter( 'woocommerce_add_cart_item_data',      [ $this, 'add_bundle_to_cart_item' ],      10, 3 );
        add_filter( 'woocommerce_get_item_data',           [ $this, 'display_bundle_in_cart' ],       10, 2 );
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'update_cart_item_price' ],       20 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_bundle_to_order' ], 10, 4 );
    }

    /**
     * Enqueue frontend CSS and JavaScript assets.
     *
     * Assets are conditionally loaded:
     *  - On single product pages the queried product is checked.
     *  - On other singular pages the post content is scanned for the
     *    `[wpqb_bundles]` shortcode.
     *
     * Assets are not enqueued if the product (or none of its variations) has
     * any configured bundles, avoiding unnecessary HTTP requests.
     *
     * Plugin settings are passed to `frontend.js` via `wp_localize_script()`
     * so the JavaScript layer has access to the same configuration as PHP.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function enqueue_frontend_assets() {
        $load_assets = false;

        if (is_product()) {
            $load_assets = true;
        } elseif (is_singular()) {
            global $post;
            if ($post instanceof WP_Post && has_shortcode($post->post_content, 'wpqb_bundles')) {
                $load_assets = true;
            }
        }

        if (!$load_assets) {
            return;
        }

        $product = wc_get_product(get_queried_object_id());
        if (!$product instanceof WC_Product || !$this->product_or_variations_have_bundles($product)) {
            return;
        }

        wp_enqueue_style('wpqb-frontend-css', WPQB_PLUGIN_URL . 'assets/css/frontend.css', [], WPQB_PLUGIN_VERSION);
        wp_enqueue_script('wpqb-frontend-js', WPQB_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], WPQB_PLUGIN_VERSION, true);
        wp_localize_script(
            'wpqb-frontend-js',
            'wpqbPluginSettings',
            [
                'designType' => $this->settings['design_type'],
                'selectionMode' => $this->settings['selection_mode'],
                'requireBundleSelection' => ('yes' === $this->settings['require_bundle_selection']),
                'showSelectedTotal' => ('yes' === $this->settings['show_selected_total']),
                'showSavings' => ('yes' === $this->settings['show_savings']),
                'showDiscountAfterTitle' => ('yes' === $this->settings['show_discount_after_title']),
                'showPerItemPrice' => ('yes' === $this->settings['show_per_item_price']),
                'showRegularPriceWhenSale' => ('yes' === $this->settings['show_regular_price_when_sale']),
                'showQtyAfterPerItem' => ('yes' === $this->settings['show_qty_after_per_item']),
                'enableBundleSorting' => ('yes' === $this->settings['enable_bundle_sorting']),
                'autoSelectByQtyChange' => ('yes' === $this->settings['auto_select_by_qty_change']),
                'headings' => [
                    'bundle' => !empty($this->settings['table_heading_bundle']) ? $this->settings['table_heading_bundle'] : __('Bundle', 'wpqb'),
                    'perItem' => !empty($this->settings['table_heading_per_item']) ? $this->settings['table_heading_per_item'] : __('Per Item', 'wpqb'),
                    'totalPrice' => !empty($this->settings['table_heading_total_price']) ? $this->settings['table_heading_total_price'] : __('Total Price', 'wpqb'),
                ],
                'i18n' => [
                    'selectVariation' => !empty($this->settings['variable_placeholder_text']) ? $this->settings['variable_placeholder_text'] : __('Select product options to view bundles.', 'wpqb'),
                    'noBundles' => __('No bundles found for this variation.', 'wpqb'),
                    'chooseBundle' => __('Please select a bundle before adding this product to your cart.', 'wpqb'),
                    'savePrefix' => __('Save', 'wpqb'),
                    'bundleFallback' => __('Bundle', 'wpqb'),
                ],
            ]
        );
    }

    /**
     * Output the bundle widget on the product page via a WooCommerce hook.
     *
     * Called by whichever WooCommerce action hook is configured in
     * `display_position` settings. Silently returns when the global `$product`
     * is not a WC_Product, when the product type is disabled in settings, or
     * when the product has no bundles.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function display_qty_bundles() {
        global $product;

        if ( ! $product instanceof WC_Product || ! $this->is_product_type_enabled( $product ) ) {
            return;
        }

        if ( ! $this->product_or_variations_have_bundles( $product ) ) {
            return;
        }

        echo $this->get_bundles_markup( $product, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup is escaped inside get_bundles_markup() and its templates.
    }

    /**
     * Shortcode handler for `[wpqb_bundles]`.
     *
     * Renders the bundle widget for any product, identified by the
     * `product_id` attribute (defaults to the current post ID). Returns an
     * empty string when the product is invalid, the product type is disabled,
     * or no bundles are configured.
     *
     * Usage: `[wpqb_bundles product_id="123"]`
     *
     * @since 1.0.0
     *
     * @param array $atts Shortcode attributes. Accepted: `product_id` (int).
     * @return string Bundle widget HTML, or an empty string on failure.
     */
    public function render_bundles_shortcode( $atts ) {
        $atts = shortcode_atts(
            [
                'product_id' => get_the_ID(),
            ],
            $atts,
            'wpqb_bundles'
        );

        $product = wc_get_product( absint( $atts['product_id'] ) );
        if ( ! $product instanceof WC_Product || ! $this->is_product_type_enabled( $product ) ) {
            return '';
        }

        return $this->get_bundles_markup( $product, false );
    }

    /**
     * Inject bundle data into the WooCommerce variation payload.
     *
     * Hooked into `woocommerce_available_variation` (filter). Appends a
     * `wpqb_bundles` key to each variation's data array so the frontend
     * JavaScript can render bundle options when a specific variation is
     * selected by the customer.
     *
     * @since 1.0.0
     *
     * @param array      $variation_data The existing variation data array.
     * @param WC_Product $product        The parent variable product (unused).
     * @param WC_Product $variation      The variation product object.
     * @return array Modified variation data array with `wpqb_bundles` key added.
     */
    public function append_variation_bundle_data( $variation_data, $product, $variation ) {
        unset( $product ); // Parent product is not needed; parameter required by hook signature.

        $bundles = $this->get_product_bundles( $variation->get_id() );
        if ( 'yes' === $this->settings['enable_bundle_sorting'] ) {
            $bundles = $this->sort_bundles_by_qty( $bundles );
        }

        $prepared_bundles = [];

        foreach ( $bundles as $index => $bundle ) {
            $pricing = $this->build_bundle_pricing_data( $variation, $bundle, $index, 0 );
            if ( empty( $pricing ) ) {
                continue;
            }

            $prepared_bundles[] = [
                'bundle_index'  => $pricing['bundle_index'],
                'bundle_name'   => $pricing['bundle_name'],
                'qty'           => $pricing['tier_qty'],
                'per_item_price' => $pricing['per_item_price'],
                'price'         => $pricing['total_price'],
                'regular_price' => $pricing['total_regular_price'],
                'sale_price'    => $pricing['total_sale_price'],
                'image_id'      => $pricing['image_id'],
                'image_url'     => $pricing['image_id'] ? wp_get_attachment_image_url( $pricing['image_id'], 'woocommerce_thumbnail' ) : '',
            ];
        }

        $variation_data['wpqb_bundles'] = $prepared_bundles;

        return $variation_data;
    }

    /**
     * Validate that a bundle has been selected before the customer adds to cart.
     *
     * Hooked into `woocommerce_add_to_cart_validation`. When the
     * `require_bundle_selection` setting is enabled and the product has
     * configured bundles but none is selected, a WooCommerce error notice is
     * added and `false` is returned to prevent the add-to-cart action.
     *
     * @since 1.0.0
     *
     * @param bool  $passed      Whether validation has passed so far.
     * @param int   $product_id  The product being added to the cart.
     * @param int   $quantity    The quantity being added.
     * @param int   $variation_id Optional. The variation ID, if applicable. Default 0.
     * @param array $variations  Optional. Variation attribute key-value pairs (unused).
     * @return bool True to allow add-to-cart, false to block it.
     */
    public function validate_bundle_selection( $passed, $product_id, $quantity, $variation_id = 0, $variations = [] ) {
        unset( $variations ); // Not needed; parameter required by hook signature.

        if ( ! $passed ) {
            return $passed;
        }

        $bundle_data = $this->resolve_requested_bundle_data( $product_id, $variation_id, $quantity );

        if ( 'yes' === $this->settings['require_bundle_selection'] && $this->product_has_bundles( $variation_id ?: $product_id ) && empty( $bundle_data ) ) {
            wc_add_notice( __( 'Please select a quantity bundle before adding this product to your cart.', 'wpqb' ), 'error' );

            return false;
        }

        return $passed;
    }

    /**
     * Attach the selected bundle data to the WooCommerce cart item.
     *
     * Hooked into `woocommerce_add_cart_item_data`. Reads the posted
     * `wpqb_selected_bundle` field, resolves the matching bundle pricing, and
     * stores it in the cart item data array. A `unique_key` is also added to
     * prevent WooCommerce from merging this item with another that lacks a
     * bundle selection.
     *
     * @since 1.0.0
     *
     * @param array $cart_item_data Existing cart item data.
     * @param int   $product_id     The product being added.
     * @param int   $variation_id   The variation ID, or 0 for simple products.
     * @return array Modified cart item data with `wpqb_bundle` key when applicable.
     */
    public function add_bundle_to_cart_item( $cart_item_data, $product_id, $variation_id ) {
        $quantity    = isset( $_POST['quantity'] ) ? wc_stock_amount( wp_unslash( $_POST['quantity'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified upstream by WooCommerce add-to-cart flow.
        $bundle_data = $this->resolve_requested_bundle_data( $product_id, $variation_id, $quantity );

        if ( empty( $bundle_data ) ) {
            return $cart_item_data;
        }

        $cart_item_data['wpqb_bundle']  = $bundle_data;
        // Unique key prevents WooCommerce from merging cart items with different bundle selections.
        $cart_item_data['unique_key']   = md5( wp_json_encode( $bundle_data ) . '|' . microtime( true ) );

        return $cart_item_data;
    }

    /**
     * Display bundle details in the WooCommerce cart and checkout item data.
     *
     * Hooked into `woocommerce_get_item_data`. Appends human-readable bundle
     * information (name, tier, applied quantity, per-item price, and optional
     * savings) to the item data table shown in the cart and checkout pages.
     *
     * @since 1.0.0
     *
     * @param array $item_data Existing array of item data rows.
     * @param array $cart_item The full cart item array.
     * @return array Modified item data array with bundle rows appended.
     */
    public function display_bundle_in_cart( $item_data, $cart_item ) {
        if ( empty( $cart_item['wpqb_bundle'] ) || ! is_array( $cart_item['wpqb_bundle'] ) ) {
            return $item_data;
        }

        $bundle      = $cart_item['wpqb_bundle'];
        $applied_qty = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : absint( $bundle['applied_qty'] );

        if ( ! empty( $bundle['bundle_name'] ) ) {
            $item_data[] = [
                'name'  => __( 'Bundle', 'wpqb' ),
                'value' => esc_html( $bundle['bundle_name'] ),
            ];
        }

        $item_data[] = [
            'name'  => __( 'Pricing Tier', 'wpqb' ),
            'value' => esc_html( sprintf( __( 'Applies from %d items', 'wpqb' ), absint( $bundle['tier_qty'] ) ) ),
        ];

        $item_data[] = [
            'name'  => __( 'Applied Quantity', 'wpqb' ),
            'value' => esc_html( sprintf( __( '%d items', 'wpqb' ), $applied_qty ) ),
        ];

        $item_data[] = [
            'name'  => __( 'Per Item Price', 'wpqb' ),
            'value' => wp_kses_post( wc_price( $bundle['per_item_price'] ) ),
        ];

        if ( 'yes' === $this->settings['show_savings'] && ! empty( $bundle['total_sale_price'] ) && $bundle['total_sale_price'] < $bundle['total_regular_price'] ) {
            $savings = $bundle['total_regular_price'] - $bundle['total_sale_price'];

            $item_data[] = [
                'name'  => __( 'Bundle Savings', 'wpqb' ),
                'value' => wp_kses_post( wc_price( $savings ) ),
            ];
        }

        return $item_data;
    }

    /**
     * Recalculate per-item price for cart items that have a bundle applied.
     *
     * Hooked into `woocommerce_before_calculate_totals` (priority 20). Iterates
     * every cart item: if a `wpqb_bundle` key is present, `refresh_cart_bundle_data()`
     * re-resolves the best tier for the current quantity, then
     * `WC_Product::set_price()` overrides the line-item unit price so
     * WooCommerce uses the bundle per-item rate in all subtotals.
     *
     * If the bundle can no longer be resolved (e.g. tiers were deleted), the
     * `wpqb_bundle` key is removed and the product's current base price is
     * restored.
     *
     * @since 1.0.0
     *
     * @param WC_Cart $cart The active WooCommerce cart object.
     * @return void
     */
    public function update_cart_item_price( $cart ) {
        if ( ! is_a( $cart, 'WC_Cart' ) ) {
            return;
        }

        // Bail on admin non-AJAX requests to avoid modifying prices in order screens.
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( empty( $cart_item['wpqb_bundle'] ) || empty( $cart_item['data'] ) ) {
                continue;
            }

            $bundle = $this->refresh_cart_bundle_data( $cart_item, $cart_item['quantity'] );
            if ( empty( $bundle ) ) {
                // Bundle tier no longer valid — restore the product's base price and clear bundle data.
                $source_product_id = ! empty( $cart_item['wpqb_bundle']['source_product_id'] ) ? absint( $cart_item['wpqb_bundle']['source_product_id'] ) : 0;
                $source_product    = $source_product_id ? wc_get_product( $source_product_id ) : null;

                if ( $source_product instanceof WC_Product ) {
                    $cart->cart_contents[ $cart_item_key ]['data']->set_price( (float) $source_product->get_price() );
                }

                unset( $cart->cart_contents[ $cart_item_key ]['wpqb_bundle'] );
                continue;
            }

            $cart->cart_contents[ $cart_item_key ]['wpqb_bundle'] = $bundle;
            $cart->cart_contents[ $cart_item_key ]['data']->set_price( (float) $bundle['per_item_price'] );
        }
    }

    /**
     * Persist bundle data as order line-item meta when an order is created.
     *
     * Hooked into `woocommerce_checkout_create_order_line_item`. Copies key
     * bundle fields from the cart item's `wpqb_bundle` array into WooCommerce
     * order item meta so the information is preserved on the order detail
     * screen, confirmation emails, and third-party integrations.
     *
     * The raw bundle array is also stored under the private
     * `_wpqb_bundle_data` meta key for programmatic access.
     *
     * @since 1.0.0
     *
     * @param WC_Order_Item_Product $item          The order line-item object.
     * @param string                $cart_item_key The cart item hash key (unused).
     * @param array                 $values        The full cart item data array.
     * @param WC_Order              $order         The order being created (unused).
     * @return void
     */
    public function save_bundle_to_order( $item, $cart_item_key, $values, $order ) {
        unset( $cart_item_key, $order ); // Not needed; parameters required by hook signature.

        if ( empty( $values['wpqb_bundle'] ) || ! is_array( $values['wpqb_bundle'] ) ) {
            return;
        }

        $bundle = $values['wpqb_bundle'];

        if ( ! empty( $bundle['bundle_name'] ) ) {
            $item->add_meta_data( __( 'Bundle', 'wpqb' ), $bundle['bundle_name'], true );
        }

        $item->add_meta_data( __( 'Pricing Tier', 'wpqb' ),    sprintf( __( 'Applies from %d items', 'wpqb' ), absint( $bundle['tier_qty'] ) ),     true );
        $item->add_meta_data( __( 'Applied Quantity', 'wpqb' ), sprintf( __( '%d items', 'wpqb' ),             absint( $bundle['applied_qty'] ) ),    true );
        $item->add_meta_data( __( 'Per Item Price', 'wpqb' ),   wp_strip_all_tags( wc_price( $bundle['per_item_price'] ) ),                         true );

        if ( 'yes' === $this->settings['show_savings'] && ! empty( $bundle['total_sale_price'] ) && $bundle['total_sale_price'] < $bundle['total_regular_price'] ) {
            $savings = $bundle['total_regular_price'] - $bundle['total_sale_price'];
            $item->add_meta_data( __( 'Bundle Savings', 'wpqb' ), wp_strip_all_tags( wc_price( $savings ) ), true );
        }

        // Private meta key stores the full bundle array for programmatic access.
        $item->add_meta_data( '_wpqb_bundle_data', $bundle, false );
    }

    /**
     * Resolve the WooCommerce action hook name for the configured display position.
     *
     * Returns an empty string when the position is `shortcode_only`, signalling
     * to `hooks()` that no display action should be registered.
     *
     * @since 1.0.0
     *
     * @return string Action hook name, or an empty string for shortcode-only mode.
     */
    private function get_display_hook() {
        $positions = wpqb_plugin_get_display_positions();
        $position  = isset( $this->settings['display_position'] ) ? $this->settings['display_position'] : '';

        if ( 'shortcode_only' === $position ) {
            return '';
        }

        // Fall back to a sensible default when the stored position is no longer valid.
        return isset( $positions[ $position ] ) ? $position : 'woocommerce_before_add_to_cart_button';
    }

    /**
     * Read the customer's posted bundle selection and return its pricing data.
     *
     * Reads the `wpqb_selected_bundle` POST field (set by the frontend JS),
     * extracts the `bundle_index`, and returns the fully-resolved pricing array
     * for that bundle at the given quantity.
     *
     * When no explicit selection is posted and `selection_mode` is `auto`,
     * the best matching bundle tier is resolved automatically via
     * `find_matching_bundle_for_quantity()`.
     *
     * Returns an empty array when the product or its bundles cannot be loaded,
     * or when no match is found.
     *
     * @since 1.0.0
     *
     * @param int $product_id   The parent product ID.
     * @param int $variation_id The variation ID (0 for simple products).
     * @param int $quantity     The quantity being added to the cart.
     * @return array Bundle pricing data array, or an empty array on failure.
     */
    private function resolve_requested_bundle_data( $product_id, $variation_id, $quantity ) {
        $requested_index = null;

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified upstream by WooCommerce add-to-cart flow.
        if ( ! empty( $_POST['wpqb_selected_bundle'] ) ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $bundle_data = json_decode( wp_unslash( $_POST['wpqb_selected_bundle'] ), true );
            if ( is_array( $bundle_data ) && isset( $bundle_data['bundle_index'] ) ) {
                $requested_index = absint( $bundle_data['bundle_index'] );
            }
        }

        $source_product_id = $variation_id > 0 ? $variation_id : $product_id;
        $product           = wc_get_product( $source_product_id );
        if ( ! $product instanceof WC_Product || ! $this->is_product_type_enabled( $product ) ) {
            return [];
        }

        $bundles = $this->get_product_bundles( $source_product_id );
        if ( empty( $bundles ) ) {
            return [];
        }

        $quantity = max( 1, absint( $quantity ) );

        // Explicit index: customer selected a specific tier.
        if ( null !== $requested_index && isset( $bundles[ $requested_index ] ) ) {
            return $this->build_bundle_pricing_data( $product, $bundles[ $requested_index ], $requested_index, $quantity );
        }

        // Auto mode: find the best matching tier for the given quantity.
        if ( 'auto' === $this->settings['selection_mode'] ) {
            $matched_bundle = $this->find_matching_bundle_for_quantity( $bundles, $quantity );
            if ( ! empty( $matched_bundle ) ) {
                return $this->build_bundle_pricing_data( $product, $matched_bundle['bundle'], $matched_bundle['index'], $quantity );
            }
        }

        return [];
    }

    /**
     * Build the complete bundle widget HTML for a given product.
     *
     * Generates the rows and cards HTML for all bundles on a simple product,
     * then passes everything to the `frontend-bundles` template. For variable
     * products the rows and cards are empty (JavaScript handles rendering after
     * the customer selects a variation).
     *
     * Returns an empty string when the product has no bundles or is not of an
     * enabled type.
     *
     * @since 1.0.0
     *
     * @param WC_Product $product               The product to render bundles for.
     * @param bool       $is_primary_product_form Whether this is the main add-to-cart form.
     * @return string Complete bundle widget HTML, or an empty string.
     */
    private function get_bundles_markup( $product, $is_primary_product_form ) {
        $is_variable = $product->is_type( 'variable' );
        $bundles     = $is_variable ? [] : $this->get_product_bundles( $product->get_id() );

        if ( ! $is_variable && 'yes' === $this->settings['enable_bundle_sorting'] ) {
            $bundles = $this->sort_bundles_by_qty( $bundles );
        }

        if ( ! $is_variable && empty( $bundles ) ) {
            return '';
        }

        if ( $is_variable && ! $this->product_or_variations_have_bundles( $product ) ) {
            return '';
        }

        $rows_html  = [];
        $cards_html = [];

        if ( ! $is_variable ) {
            foreach ( $bundles as $index => $bundle ) {
                $pricing = $this->build_bundle_pricing_data( $product, $bundle, $index, 0 );
                if ( empty( $pricing ) ) {
                    continue;
                }

                $rows_html[]  = $this->get_bundle_row_html( $pricing, $index );
                $cards_html[] = $this->get_bundle_card_html( $pricing, $index );
            }
        }

        ob_start();
        wpqb_plugin_get_template(
            'frontend-bundles',
            [
                'settings'                => $this->settings,
                'is_variable'             => $is_variable,
                'is_table'                => ( 'table' === $this->settings['design_type'] ),
                'is_primary_product_form' => $is_primary_product_form,
                'inline_style'            => $this->get_frontend_inline_style(),
                'rows_html'               => implode( '', $rows_html ),
                'cards_html'              => implode( '', $cards_html ),
            ]
        );

        return ob_get_clean();
    }

    /**
     * Build the HTML for a single bundle table row.
     *
     * Computes display prices (regular/sale with optional strikethrough),
     * per-item text, and optional savings label, then passes all data to the
     * `frontend-bundle-row` template.
     *
     * @since 1.0.0
     *
     * @param array $pricing        Bundle pricing data array from `build_bundle_pricing_data()`.
     * @param int   $fallback_index Zero-based index used when the bundle has no name.
     * @return string Table row HTML string.
     */
    private function get_bundle_row_html( $pricing, $fallback_index = 0 ) {
        $bundle_name  = ! empty( $pricing['bundle_name'] ) ? $pricing['bundle_name'] : sprintf( __( 'Bundle %d', 'wpqb' ), $fallback_index + 1 );
        $price_html   = '<span class="wpqb-price-regular">' . wc_price( $pricing['total_regular_price'] ) . '</span>';
        $savings_html = '';
        $has_sale     = ! empty( $pricing['total_sale_price'] ) && $pricing['total_sale_price'] < $pricing['total_regular_price'];

        if ( $has_sale ) {
            if ( 'yes' === $this->settings['show_regular_price_when_sale'] ) {
                $price_html = '<del class="wpqb-price-cutoff">' . wc_price( $pricing['total_regular_price'] ) . '</del><ins class="wpqb-price-sale">' . wc_price( $pricing['total_sale_price'] ) . '</ins>';
            } else {
                $price_html = '<span class="wpqb-price-sale">' . wc_price( $pricing['total_sale_price'] ) . '</span>';
            }

            if ( 'yes' === $this->settings['show_savings'] && 'yes' === $this->settings['show_discount_after_title'] && $pricing['total_regular_price'] > 0 ) {
                $savings         = $pricing['total_regular_price'] - $pricing['total_sale_price'];
                $savings_percent = (int) round( ( $savings / $pricing['total_regular_price'] ) * 100 );
                $savings_html    = sprintf(
                    '<br><span class="wpqb-bundle-savings">%s</span>',
                    esc_html( sprintf( __( 'Save %1$s (%2$d%%)', 'wpqb' ), wp_strip_all_tags( wc_price( $savings ) ), $savings_percent ) )
                );
            }
        }

        $per_item_text = wp_strip_all_tags( wc_price( $pricing['per_item_price'] ) );
        if ( 'yes' === $this->settings['show_qty_after_per_item'] ) {
            $per_item_text = sprintf( __( '%1$s x %2$d', 'wpqb' ), $per_item_text, $pricing['tier_qty'] );
        }

        ob_start();
        wpqb_plugin_get_template(
            'frontend-bundle-row',
            [
                'settings'      => $this->settings,
                'pricing'       => $pricing,
                'bundle_name'   => $bundle_name,
                'price_html'    => $price_html,
                'savings_html'  => $savings_html,
                'per_item_text' => $per_item_text,
            ]
        );

        return ob_get_clean();
    }

    /**
     * Build the HTML for a single bundle card.
     *
     * Mirrors the logic of `get_bundle_row_html()` but also resolves an
     * optional card image and passes everything to the `frontend-bundle-card`
     * template.
     *
     * @since 1.0.0
     *
     * @param array $pricing        Bundle pricing data array from `build_bundle_pricing_data()`.
     * @param int   $fallback_index Zero-based index used when the bundle has no name.
     * @return string Card HTML string.
     */
    private function get_bundle_card_html( $pricing, $fallback_index = 0 ) {
        $bundle_name   = ! empty( $pricing['bundle_name'] ) ? $pricing['bundle_name'] : sprintf( __( 'Bundle %d', 'wpqb' ), $fallback_index + 1 );
        $has_sale      = ! empty( $pricing['total_sale_price'] ) && $pricing['total_sale_price'] < $pricing['total_regular_price'];
        $price_html    = '<span class="wpqb-price-regular">' . wc_price( $pricing['total_regular_price'] ) . '</span>';
        $discount_html = '';

        if ( $has_sale ) {
            if ( 'yes' === $this->settings['show_regular_price_when_sale'] ) {
                $price_html = '<del class="wpqb-price-cutoff">' . wc_price( $pricing['total_regular_price'] ) . '</del><ins class="wpqb-price-sale">' . wc_price( $pricing['total_sale_price'] ) . '</ins>';
            } else {
                $price_html = '<span class="wpqb-price-sale">' . wc_price( $pricing['total_sale_price'] ) . '</span>';
            }

            if ( 'yes' === $this->settings['show_savings'] && 'yes' === $this->settings['show_discount_after_title'] && $pricing['total_regular_price'] > 0 ) {
                $savings         = $pricing['total_regular_price'] - $pricing['total_sale_price'];
                $savings_percent = (int) round( ( $savings / $pricing['total_regular_price'] ) * 100 );
                $discount_html   = sprintf(
                    '<span class="wpqb-bundle-savings">%s</span>',
                    esc_html( sprintf( __( 'Save %1$s (%2$d%%)', 'wpqb' ), wp_strip_all_tags( wc_price( $savings ) ), $savings_percent ) )
                );
            }
        }

        $per_item_text = wp_strip_all_tags( wc_price( $pricing['per_item_price'] ) );
        if ( 'yes' === $this->settings['show_qty_after_per_item'] ) {
            $per_item_text = sprintf( __( '%1$s x %2$d', 'wpqb' ), $per_item_text, $pricing['tier_qty'] );
        }

        // Resolve card image markup — uses a placeholder div when no image is set.
        $image_markup = '<div class="wpqb-card-media wpqb-card-media-empty" aria-hidden="true"></div>';
        if ( ! empty( $pricing['image_id'] ) ) {
            $image_src = wp_get_attachment_image_url( $pricing['image_id'], 'woocommerce_thumbnail' );
            if ( $image_src ) {
                $image_markup = '<div class="wpqb-card-media"><img src="' . esc_url( $image_src ) . '" alt="' . esc_attr( $bundle_name ) . '" /></div>';
            }
        }

        ob_start();
        wpqb_plugin_get_template(
            'frontend-bundle-card',
            [
                'settings'      => $this->settings,
                'pricing'       => $pricing,
                'bundle_name'   => $bundle_name,
                'price_html'    => $price_html,
                'discount_html' => $discount_html,
                'per_item_text' => $per_item_text,
                'image_markup'  => $image_markup,
            ]
        );

        return ob_get_clean();
    }

    /**
     * Re-resolve bundle pricing data for a cart item at the current quantity.
     *
     * Called during `woocommerce_before_calculate_totals`. Uses the stored
     * `source_product_id` and `bundle_index` from the existing cart item
     * bundle data to look up the bundle and re-build its pricing.
     *
     * In `auto` selection mode the best tier for the current quantity is
     * returned instead of the originally selected index, allowing price
     * updates when the customer changes quantity in the cart.
     *
     * Returns an empty array when the product or bundle index is no longer valid.
     *
     * @since 1.0.0
     *
     * @param array $cart_item The full cart item data array.
     * @param int   $quantity  The current cart item quantity.
     * @return array Refreshed bundle pricing data array, or empty on failure.
     */
    private function refresh_cart_bundle_data( $cart_item, $quantity ) {
        if ( empty( $cart_item['wpqb_bundle']['source_product_id'] ) ) {
            return [];
        }

        $source_product_id = absint( $cart_item['wpqb_bundle']['source_product_id'] );
        $bundle_index      = isset( $cart_item['wpqb_bundle']['bundle_index'] ) ? absint( $cart_item['wpqb_bundle']['bundle_index'] ) : 0;
        $product           = wc_get_product( $source_product_id );
        $bundles           = $this->get_product_bundles( $source_product_id );

        if ( ! $product instanceof WC_Product || ! isset( $bundles[ $bundle_index ] ) ) {
            return [];
        }

        // Auto mode: re-match the best tier for the updated quantity.
        if ( 'auto' === $this->settings['selection_mode'] ) {
            $matched_bundle = $this->find_matching_bundle_for_quantity( $bundles, $quantity );
            if ( ! empty( $matched_bundle ) ) {
                return $this->build_bundle_pricing_data( $product, $matched_bundle['bundle'], $matched_bundle['index'], $quantity );
            }

            return [];
        }

        // Manual mode: use the previously selected index.
        return $this->build_bundle_pricing_data( $product, $bundles[ $bundle_index ], $bundle_index, $quantity );
    }
}
