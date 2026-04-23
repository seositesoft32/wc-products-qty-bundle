<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPQB_Plugin_Base
{
    /**
     * Plugin settings.
     *
     * @var array<string, string>
     */
    protected $settings = [];

    public function __construct()
    {
        $this->settings = wpqb_plugin_settings();
    }

    protected function sanitize_bundles($raw_bundles)
    {
        $bundles = [];

        if (!is_array($raw_bundles)) {
            return $bundles;
        }

        foreach ($raw_bundles as $bundle) {
            if (empty($bundle['qty'])) {
                continue;
            }

            $qty = absint($bundle['qty']);
            if ($qty <= 0) {
                continue;
            }

            $regular_price = isset($bundle['regular_price']) ? wc_format_decimal($bundle['regular_price']) : '';
            $sale_price = isset($bundle['sale_price']) ? wc_format_decimal($bundle['sale_price']) : '';

            $bundles[] = [
                'name' => sanitize_text_field(isset($bundle['name']) ? $bundle['name'] : ''),
                'qty' => $qty,
                'regular_price' => '' !== $regular_price ? $regular_price : '',
                'sale_price' => '' !== $sale_price ? $sale_price : '',
                'image_id' => absint(isset($bundle['image_id']) ? $bundle['image_id'] : 0),
            ];
        }

        return array_values($bundles);
    }

    protected function get_product_regular_price_value($product)
    {
        if (!$product instanceof WC_Product) {
            return 0.0;
        }

        $regular_price = (float) $product->get_regular_price();
        if ($regular_price > 0) {
            return $regular_price;
        }

        return (float) $product->get_price();
    }

    protected function get_product_bundles($product_id)
    {
        $bundles = get_post_meta($product_id, '_wpqb_qty_bundles', true);

        return is_array($bundles) ? $this->sanitize_bundles($bundles) : [];
    }

    protected function product_has_bundles($product_id)
    {
        return !empty($this->get_product_bundles($product_id));
    }

    protected function product_or_variations_have_bundles($product)
    {
        if (!$product instanceof WC_Product) {
            return false;
        }

        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $variation_id) {
                if ($this->product_has_bundles($variation_id)) {
                    return true;
                }
            }

            return false;
        }

        return $this->product_has_bundles($product->get_id());
    }

    protected function is_product_type_enabled($product)
    {
        if ($product->is_type('variable')) {
            return 'yes' === $this->settings['enable_variable_products'];
        }

        return 'yes' === $this->settings['enable_simple_products'];
    }

    protected function sort_bundles_by_qty($bundles)
    {
        $bundles = is_array($bundles) ? $bundles : [];

        usort(
            $bundles,
            static function ($left, $right) {
                $left_qty = isset($left['qty']) ? absint($left['qty']) : 0;
                $right_qty = isset($right['qty']) ? absint($right['qty']) : 0;

                return $left_qty <=> $right_qty;
            }
        );

        return array_values($bundles);
    }

    protected function build_bundle_pricing_data($product, $bundle, $bundle_index, $cart_qty)
    {
        if (!$product instanceof WC_Product) {
            return [];
        }

        $tier_qty = isset($bundle['qty']) ? absint($bundle['qty']) : 0;
        if ($tier_qty <= 0) {
            return [];
        }

        $regular_price = isset($bundle['regular_price']) ? (float) $bundle['regular_price'] : 0.0;
        if ($regular_price <= 0) {
            $regular_price = $this->get_product_regular_price_value($product);
        }

        $sale_price = isset($bundle['sale_price']) ? (float) $bundle['sale_price'] : 0.0;
        $per_item_price = ($sale_price > 0 && $sale_price < $regular_price) ? $sale_price : $regular_price;
        $applied_qty = $cart_qty > 0 ? absint($cart_qty) : $tier_qty;
        $total_regular_price = $regular_price * $applied_qty;
        $total_sale_price = ($sale_price > 0 && $sale_price < $regular_price) ? ($sale_price * $applied_qty) : 0.0;

        return [
            'bundle_index' => absint($bundle_index),
            'bundle_name' => isset($bundle['name']) ? sanitize_text_field($bundle['name']) : '',
            'tier_qty' => $tier_qty,
            'applied_qty' => $applied_qty,
            'per_item_price' => $per_item_price,
            'per_item_regular_price' => $regular_price,
            'per_item_sale_price' => ($total_sale_price > 0) ? $sale_price : 0.0,
            'total_price' => $per_item_price * $applied_qty,
            'total_regular_price' => $total_regular_price,
            'total_sale_price' => $total_sale_price,
            'image_id' => isset($bundle['image_id']) ? absint($bundle['image_id']) : 0,
            'source_product_id' => $product->get_id(),
        ];
    }

    protected function find_matching_bundle_for_quantity($bundles, $quantity)
    {
        $match = [];
        $best_tier_qty = 0;

        foreach ($bundles as $index => $bundle) {
            $tier_qty = isset($bundle['qty']) ? absint($bundle['qty']) : 0;
            if ($tier_qty > 0 && $quantity >= $tier_qty && $tier_qty >= $best_tier_qty) {
                $best_tier_qty = $tier_qty;
                $match = [
                    'index' => $index,
                    'bundle' => $bundle,
                ];
            }
        }

        return $match;
    }

    protected function get_frontend_inline_style()
    {
        $map = [
            '--wpqb-table-head-bg' => 'table_head_bg_color',
            '--wpqb-table-head-text' => 'table_head_text_color',
            '--wpqb-table-body-bg' => 'table_body_bg_color',
            '--wpqb-table-body-text' => 'table_body_text_color',
            '--wpqb-table-border' => 'table_border_color',
            '--wpqb-table-cell-border' => 'table_cell_border_color',
            '--wpqb-table-hover-bg' => 'table_hover_bg_color',
            '--wpqb-table-selected-bg' => 'table_selected_bg_color',
            '--wpqb-table-selected-border' => 'table_selected_border_color',
            '--wpqb-title-bg' => 'table_title_bg_color',
            '--wpqb-title-text' => 'table_title_text_color',
            '--wpqb-card-bg' => 'card_bg_color',
            '--wpqb-card-text' => 'card_text_color',
            '--wpqb-card-border' => 'card_border_color',
            '--wpqb-card-hover-border' => 'card_hover_border_color',
            '--wpqb-card-selected-border' => 'card_selected_border_color',
            '--wpqb-card-media-bg' => 'card_media_bg_color',
            '--wpqb-discount-bg' => 'discount_bg_color',
            '--wpqb-discount-text' => 'discount_text_color',
            '--wpqb-regular-price' => 'regular_price_color',
            '--wpqb-sale-price' => 'sale_price_color',
            '--wpqb-cutoff-price' => 'strikethrough_price_color',
        ];

        $parts = [];

        foreach ($map as $css_var => $setting_key) {
            if (!empty($this->settings[$setting_key])) {
                $parts[] = sprintf('%s:%s', $css_var, sanitize_hex_color($this->settings[$setting_key]));
            }
        }

        $parts[] = '--wpqb-card-radius:' . max(0, min(40, absint($this->settings['card_radius']))) . 'px';

        return implode(';', $parts);
    }
}
