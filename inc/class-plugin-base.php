<?php
/**
 * Base plugin class.
 *
 * Provides shared properties and methods inherited by both the admin and
 * frontend classes. Centralises bundle data retrieval, sanitization, pricing
 * calculations, and product-type guards so they are not duplicated across
 * subclasses.
 *
 * @package   WC_Products_Qty_Bundle
 * @subpackage Inc
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class WPQB_Plugin_Base
 *
 * Abstract-style base class extended by WPQB_Plugin_Admin and
 * WPQB_Plugin_Frontend. Contains no hook registrations of its own.
 *
 * @since 1.0.0
 */
class WPQB_Plugin_Base {

    /**
     * Cached plugin settings.
     *
     * Populated in the constructor from `wpqb_plugin_settings()` and refreshed
     * after any settings save. Subclasses read from this property directly.
     *
     * @since 1.0.0
     *
     * @var array<string, mixed>
     */
    protected $settings = [];

    /**
     * Constructor.
     *
     * Loads the current plugin settings into `$this->settings` so every
     * subclass has access to the full configuration on instantiation.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->settings = wpqb_plugin_settings();
    }

    /**
     * Sanitize a raw bundle array submitted from a product meta form.
     *
     * Iterates the raw array, validates required fields (`qty` > 0), formats
     * decimal prices via `wc_format_decimal()`, and sanitizes text fields.
     * Entries with no valid quantity are silently dropped.
     *
     * @since 1.0.0
     *
     * @param array $raw_bundles Raw bundle data from `$_POST`, typically
     *                           `$_POST['wpqb_bundles']` or a variation sub-array.
     * @return array[] Re-indexed array of sanitized bundle arrays, each
     *                 containing: `name`, `qty`, `regular_price`, `sale_price`,
     *                 `image_id`.
     */
    protected function sanitize_bundles( $raw_bundles ) {
        $bundles = [];

        if ( ! is_array( $raw_bundles ) ) {
            return $bundles;
        }

        foreach ( $raw_bundles as $bundle ) {
            if ( empty( $bundle['qty'] ) ) {
                continue;
            }

            $qty = absint( $bundle['qty'] );
            if ( $qty <= 0 ) {
                continue;
            }

            $regular_price = isset( $bundle['regular_price'] ) ? wc_format_decimal( $bundle['regular_price'] ) : '';
            $sale_price    = isset( $bundle['sale_price'] )    ? wc_format_decimal( $bundle['sale_price'] )    : '';

            $bundles[] = [
                'name'          => sanitize_text_field( isset( $bundle['name'] ) ? $bundle['name'] : '' ),
                'qty'           => $qty,
                'regular_price' => '' !== $regular_price ? $regular_price : '',
                'sale_price'    => '' !== $sale_price    ? $sale_price    : '',
                'image_id'      => absint( isset( $bundle['image_id'] ) ? $bundle['image_id'] : 0 ),
            ];
        }

        return array_values( $bundles );
    }

    /**
     * Get the effective regular (non-sale) price of a product.
     *
     * Prefers `get_regular_price()`. Falls back to `get_price()` if the
     * regular price is not set (e.g. for certain external product types).
     *
     * @since 1.0.0
     *
     * @param WC_Product $product The product object to read pricing from.
     * @return float The regular price as a float, or 0.0 on failure.
     */
    protected function get_product_regular_price_value( $product ) {
        if ( ! $product instanceof WC_Product ) {
            return 0.0;
        }

        $regular_price = (float) $product->get_regular_price();
        if ( $regular_price > 0 ) {
            return $regular_price;
        }

        return (float) $product->get_price();
    }

    /**
     * Retrieve and sanitize the saved bundles for a product or variation.
     *
     * Reads the `_wpqb_qty_bundles` post meta and passes the result through
     * `sanitize_bundles()` to guarantee a clean array is always returned.
     *
     * @since 1.0.0
     *
     * @param int $product_id The product or variation post ID.
     * @return array[] Array of sanitized bundle arrays (may be empty).
     */
    protected function get_product_bundles( $product_id ) {
        $bundles = get_post_meta( $product_id, '_wpqb_qty_bundles', true );

        return is_array( $bundles ) ? $this->sanitize_bundles( $bundles ) : [];
    }

    /**
     * Check whether a product or variation has at least one bundle configured.
     *
     * @since 1.0.0
     *
     * @param int $product_id The product or variation post ID.
     * @return bool True if one or more bundles exist, false otherwise.
     */
    protected function product_has_bundles( $product_id ) {
        return ! empty( $this->get_product_bundles( $product_id ) );
    }

    /**
     * Check whether a product or any of its variations have bundles.
     *
     * For variable products, iterates all child variation IDs. For all other
     * product types the parent product is checked directly.
     *
     * @since 1.0.0
     *
     * @param WC_Product $product The product object to check.
     * @return bool True if bundles exist on the product or any variation.
     */
    protected function product_or_variations_have_bundles( $product ) {
        if ( ! $product instanceof WC_Product ) {
            return false;
        }

        if ( $product->is_type( 'variable' ) ) {
            foreach ( $product->get_children() as $variation_id ) {
                if ( $this->product_has_bundles( $variation_id ) ) {
                    return true;
                }
            }

            return false;
        }

        return $this->product_has_bundles( $product->get_id() );
    }

    /**
     * Check whether the plugin is enabled for a given product type.
     *
     * Reads the `enable_variable_products` or `enable_simple_products` setting
     * depending on whether the product is variable.
     *
     * @since 1.0.0
     *
     * @param WC_Product $product The product to check.
     * @return bool True if the product type is enabled in plugin settings.
     */
    protected function is_product_type_enabled( $product ) {
        if ( $product->is_type( 'variable' ) ) {
            return 'yes' === $this->settings['enable_variable_products'];
        }

        return 'yes' === $this->settings['enable_simple_products'];
    }

    /**
     * Sort a bundle array ascending by quantity tier.
     *
     * Returns a re-indexed copy of the input array ordered from the lowest
     * quantity tier to the highest. Bundles without a `qty` key sort to the
     * front (treated as 0).
     *
     * @since 1.0.0
     *
     * @param array[] $bundles Array of bundle arrays to sort.
     * @return array[] Re-indexed array of bundles sorted by `qty` ascending.
     */
    protected function sort_bundles_by_qty( $bundles ) {
        $bundles = is_array( $bundles ) ? $bundles : [];

        usort(
            $bundles,
            static function ( $left, $right ) {
                $left_qty  = isset( $left['qty'] )  ? absint( $left['qty'] )  : 0;
                $right_qty = isset( $right['qty'] ) ? absint( $right['qty'] ) : 0;

                return $left_qty <=> $right_qty;
            }
        );

        return array_values( $bundles );
    }

    /**
     * Build the pricing data array for a single bundle at a given cart quantity.
     *
     * Calculates per-item and total prices (regular and sale) for the bundle
     * tier, taking the actual cart quantity into account when provided. Used
     * by both the frontend display and the cart pricing hooks.
     *
     * @since 1.0.0
     *
     * @param WC_Product $product      The product or variation the bundle belongs to.
     * @param array      $bundle       The raw bundle array (keys: `qty`, `name`,
     *                                 `regular_price`, `sale_price`, `image_id`).
     * @param int        $bundle_index Zero-based index of the bundle in its parent array.
     * @param int        $cart_qty     The actual cart quantity. Pass `0` to use the
     *                                 bundle's own `qty` as the applied quantity.
     * @return array Pricing data array, or an empty array if `$product` is invalid
     *               or the tier qty is zero. Keys: `bundle_index`, `bundle_name`,
     *               `tier_qty`, `applied_qty`, `per_item_price`,
     *               `per_item_regular_price`, `per_item_sale_price`, `total_price`,
     *               `total_regular_price`, `total_sale_price`, `image_id`,
     *               `source_product_id`.
     */
    protected function build_bundle_pricing_data( $product, $bundle, $bundle_index, $cart_qty ) {
        if ( ! $product instanceof WC_Product ) {
            return [];
        }

        $tier_qty = isset( $bundle['qty'] ) ? absint( $bundle['qty'] ) : 0;
        if ( $tier_qty <= 0 ) {
            return [];
        }

        $regular_price = isset( $bundle['regular_price'] ) ? (float) $bundle['regular_price'] : 0.0;
        if ( $regular_price <= 0 ) {
            // Fall back to the product's own regular price when none is set on the bundle.
            $regular_price = $this->get_product_regular_price_value( $product );
        }

        $sale_price      = isset( $bundle['sale_price'] ) ? (float) $bundle['sale_price'] : 0.0;
        $per_item_price  = ( $sale_price > 0 && $sale_price < $regular_price ) ? $sale_price : $regular_price;
        $applied_qty     = $cart_qty > 0 ? absint( $cart_qty ) : $tier_qty;

        $total_regular_price = $regular_price * $applied_qty;
        $total_sale_price    = ( $sale_price > 0 && $sale_price < $regular_price ) ? ( $sale_price * $applied_qty ) : 0.0;

        return [
            'bundle_index'           => absint( $bundle_index ),
            'bundle_name'            => isset( $bundle['name'] ) ? sanitize_text_field( $bundle['name'] ) : '',
            'tier_qty'               => $tier_qty,
            'applied_qty'            => $applied_qty,
            'per_item_price'         => $per_item_price,
            'per_item_regular_price' => $regular_price,
            'per_item_sale_price'    => ( $total_sale_price > 0 ) ? $sale_price : 0.0,
            'total_price'            => $per_item_price * $applied_qty,
            'total_regular_price'    => $total_regular_price,
            'total_sale_price'       => $total_sale_price,
            'image_id'               => isset( $bundle['image_id'] ) ? absint( $bundle['image_id'] ) : 0,
            'source_product_id'      => $product->get_id(),
        ];
    }

    /**
     * Find the best-matching bundle for a given cart quantity.
     *
     * Uses a "best tier" strategy: iterates all bundles and returns the one
     * whose `qty` is the highest value that is still less than or equal to the
     * requested quantity. Returns an empty array when no bundle matches.
     *
     * @since 1.0.0
     *
     * @param array[] $bundles  Array of bundle arrays to search.
     * @param int     $quantity The cart quantity to match against tier thresholds.
     * @return array Associative array with keys `index` (int) and `bundle` (array),
     *               or an empty array if no tier qualifies.
     */
    protected function find_matching_bundle_for_quantity( $bundles, $quantity ) {
        $match         = [];
        $best_tier_qty = 0;

        foreach ( $bundles as $index => $bundle ) {
            $tier_qty = isset( $bundle['qty'] ) ? absint( $bundle['qty'] ) : 0;
            // Accept this tier only if its threshold is met and it is a better
            // (higher) match than any previously found tier.
            if ( $tier_qty > 0 && $quantity >= $tier_qty && $tier_qty >= $best_tier_qty ) {
                $best_tier_qty = $tier_qty;
                $match = [
                    'index'  => $index,
                    'bundle' => $bundle,
                ];
            }
        }

        return $match;
    }

    /**
     * Build the inline style string used on the frontend bundle wrapper element.
     *
     * Maps plugin color settings to CSS custom properties (variables) defined
     * on `.wpqb-bundles-frontend`. The frontend stylesheet consumes these
     * variables so the color theme is entirely driven by the admin settings
     * without requiring dynamic CSS file generation.
     *
     * @since 1.0.0
     *
     * @return string A semicolon-separated `property:value` string suitable for
     *                use in an HTML `style` attribute (not escaped — callers must
     *                use `esc_attr()` when outputting).
     */
    protected function get_frontend_inline_style() {
        $map = [
            '--wpqb-table-head-bg'          => 'table_head_bg_color',
            '--wpqb-table-head-text'         => 'table_head_text_color',
            '--wpqb-table-body-bg'           => 'table_body_bg_color',
            '--wpqb-table-body-text'         => 'table_body_text_color',
            '--wpqb-table-border'            => 'table_border_color',
            '--wpqb-table-cell-border'       => 'table_cell_border_color',
            '--wpqb-table-hover-bg'          => 'table_hover_bg_color',
            '--wpqb-table-selected-bg'       => 'table_selected_bg_color',
            '--wpqb-table-selected-border'   => 'table_selected_border_color',
            '--wpqb-title-bg'                => 'table_title_bg_color',
            '--wpqb-title-text'              => 'table_title_text_color',
            '--wpqb-card-bg'                 => 'card_bg_color',
            '--wpqb-card-text'               => 'card_text_color',
            '--wpqb-card-border'             => 'card_border_color',
            '--wpqb-card-hover-border'       => 'card_hover_border_color',
            '--wpqb-card-selected-border'    => 'card_selected_border_color',
            '--wpqb-card-media-bg'           => 'card_media_bg_color',
            '--wpqb-discount-bg'             => 'discount_bg_color',
            '--wpqb-discount-text'           => 'discount_text_color',
            '--wpqb-regular-price'           => 'regular_price_color',
            '--wpqb-sale-price'              => 'sale_price_color',
            '--wpqb-cutoff-price'            => 'strikethrough_price_color',
        ];

        $parts = [];

        foreach ( $map as $css_var => $setting_key ) {
            if ( ! empty( $this->settings[ $setting_key ] ) ) {
                $parts[] = sprintf( '%s:%s', $css_var, sanitize_hex_color( $this->settings[ $setting_key ] ) );
            }
        }

        // Card border-radius is a numeric value; clamp it to the valid 0–40 px range.
        $parts[] = '--wpqb-card-radius:' . max( 0, min( 40, absint( $this->settings['card_radius'] ) ) ) . 'px';

        return implode( ';', $parts );
    }
}
