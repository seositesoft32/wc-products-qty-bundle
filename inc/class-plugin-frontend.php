<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPQB_Plugin_Frontend extends WPQB_Plugin_Base
{
    public function hooks()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        $display_hook = $this->get_display_hook();
        if (!empty($display_hook)) {
            add_action($display_hook, [$this, 'display_qty_bundles']);
        }

        add_shortcode('wpqb_bundles', [$this, 'render_bundles_shortcode']);

        add_filter('woocommerce_available_variation', [$this, 'append_variation_bundle_data'], 10, 3);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_bundle_selection'], 10, 5);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_bundle_to_cart_item'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_bundle_in_cart'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'update_cart_item_price'], 20);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_bundle_to_order'], 10, 4);
    }

    public function enqueue_frontend_assets()
    {
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

    public function display_qty_bundles()
    {
        global $product;

        if (!$product instanceof WC_Product || !$this->is_product_type_enabled($product)) {
            return;
        }

        if (!$this->product_or_variations_have_bundles($product)) {
            return;
        }

        echo $this->get_bundles_markup($product, true);
    }

    public function render_bundles_shortcode($atts)
    {
        $atts = shortcode_atts(
            [
                'product_id' => get_the_ID(),
            ],
            $atts,
            'wpqb_bundles'
        );

        $product = wc_get_product(absint($atts['product_id']));
        if (!$product instanceof WC_Product || !$this->is_product_type_enabled($product)) {
            return '';
        }

        return $this->get_bundles_markup($product, false);
    }

    public function append_variation_bundle_data($variation_data, $product, $variation)
    {
        unset($product);

        $bundles = $this->get_product_bundles($variation->get_id());
        if ('yes' === $this->settings['enable_bundle_sorting']) {
            $bundles = $this->sort_bundles_by_qty($bundles);
        }

        $prepared_bundles = [];

        foreach ($bundles as $index => $bundle) {
            $pricing = $this->build_bundle_pricing_data($variation, $bundle, $index, 0);
            if (empty($pricing)) {
                continue;
            }

            $prepared_bundles[] = [
                'bundle_index' => $pricing['bundle_index'],
                'bundle_name' => $pricing['bundle_name'],
                'qty' => $pricing['tier_qty'],
                'per_item_price' => $pricing['per_item_price'],
                'price' => $pricing['total_price'],
                'regular_price' => $pricing['total_regular_price'],
                'sale_price' => $pricing['total_sale_price'],
                'image_id' => $pricing['image_id'],
                'image_url' => $pricing['image_id'] ? wp_get_attachment_image_url($pricing['image_id'], 'woocommerce_thumbnail') : '',
            ];
        }

        $variation_data['wpqb_bundles'] = $prepared_bundles;

        return $variation_data;
    }

    public function validate_bundle_selection($passed, $product_id, $quantity, $variation_id = 0, $variations = [])
    {
        unset($variations);

        if (!$passed) {
            return $passed;
        }

        $bundle_data = $this->resolve_requested_bundle_data($product_id, $variation_id, $quantity);

        if ('yes' === $this->settings['require_bundle_selection'] && $this->product_has_bundles($variation_id ?: $product_id) && empty($bundle_data)) {
            wc_add_notice(__('Please select a quantity bundle before adding this product to your cart.', 'wpqb'), 'error');

            return false;
        }

        return $passed;
    }

    public function add_bundle_to_cart_item($cart_item_data, $product_id, $variation_id)
    {
        $quantity = isset($_POST['quantity']) ? wc_stock_amount(wp_unslash($_POST['quantity'])) : 1;
        $bundle_data = $this->resolve_requested_bundle_data($product_id, $variation_id, $quantity);

        if (empty($bundle_data)) {
            return $cart_item_data;
        }

        $cart_item_data['wpqb_bundle'] = $bundle_data;
        $cart_item_data['unique_key'] = md5(wp_json_encode($bundle_data) . '|' . microtime(true));

        return $cart_item_data;
    }

    public function display_bundle_in_cart($item_data, $cart_item)
    {
        if (empty($cart_item['wpqb_bundle']) || !is_array($cart_item['wpqb_bundle'])) {
            return $item_data;
        }

        $bundle = $cart_item['wpqb_bundle'];
        $applied_qty = isset($cart_item['quantity']) ? absint($cart_item['quantity']) : absint($bundle['applied_qty']);

        if (!empty($bundle['bundle_name'])) {
            $item_data[] = [
                'name' => __('Bundle', 'wpqb'),
                'value' => esc_html($bundle['bundle_name']),
            ];
        }

        $item_data[] = [
            'name' => __('Pricing Tier', 'wpqb'),
            'value' => esc_html(sprintf(__('Applies from %d items', 'wpqb'), absint($bundle['tier_qty']))),
        ];

        $item_data[] = [
            'name' => __('Applied Quantity', 'wpqb'),
            'value' => esc_html(sprintf(__('%d items', 'wpqb'), $applied_qty)),
        ];

        $item_data[] = [
            'name' => __('Per Item Price', 'wpqb'),
            'value' => wp_kses_post(wc_price($bundle['per_item_price'])),
        ];

        if ('yes' === $this->settings['show_savings'] && !empty($bundle['total_sale_price']) && $bundle['total_sale_price'] < $bundle['total_regular_price']) {
            $savings = $bundle['total_regular_price'] - $bundle['total_sale_price'];

            $item_data[] = [
                'name' => __('Bundle Savings', 'wpqb'),
                'value' => wp_kses_post(wc_price($savings)),
            ];
        }

        return $item_data;
    }

    public function update_cart_item_price($cart)
    {
        if (!is_a($cart, 'WC_Cart')) {
            return;
        }

        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (empty($cart_item['wpqb_bundle']) || empty($cart_item['data'])) {
                continue;
            }

            $bundle = $this->refresh_cart_bundle_data($cart_item, $cart_item['quantity']);
            if (empty($bundle)) {
                $source_product_id = !empty($cart_item['wpqb_bundle']['source_product_id']) ? absint($cart_item['wpqb_bundle']['source_product_id']) : 0;
                $source_product = $source_product_id ? wc_get_product($source_product_id) : null;

                if ($source_product instanceof WC_Product) {
                    $cart->cart_contents[$cart_item_key]['data']->set_price((float) $source_product->get_price());
                }

                unset($cart->cart_contents[$cart_item_key]['wpqb_bundle']);
                continue;
            }

            $cart->cart_contents[$cart_item_key]['wpqb_bundle'] = $bundle;
            $cart->cart_contents[$cart_item_key]['data']->set_price((float) $bundle['per_item_price']);
        }
    }

    public function save_bundle_to_order($item, $cart_item_key, $values, $order)
    {
        unset($cart_item_key, $order);

        if (empty($values['wpqb_bundle']) || !is_array($values['wpqb_bundle'])) {
            return;
        }

        $bundle = $values['wpqb_bundle'];

        if (!empty($bundle['bundle_name'])) {
            $item->add_meta_data(__('Bundle', 'wpqb'), $bundle['bundle_name'], true);
        }

        $item->add_meta_data(__('Pricing Tier', 'wpqb'), sprintf(__('Applies from %d items', 'wpqb'), absint($bundle['tier_qty'])), true);
        $item->add_meta_data(__('Applied Quantity', 'wpqb'), sprintf(__('%d items', 'wpqb'), absint($bundle['applied_qty'])), true);
        $item->add_meta_data(__('Per Item Price', 'wpqb'), wp_strip_all_tags(wc_price($bundle['per_item_price'])), true);

        if ('yes' === $this->settings['show_savings'] && !empty($bundle['total_sale_price']) && $bundle['total_sale_price'] < $bundle['total_regular_price']) {
            $savings = $bundle['total_regular_price'] - $bundle['total_sale_price'];
            $item->add_meta_data(__('Bundle Savings', 'wpqb'), wp_strip_all_tags(wc_price($savings)), true);
        }

        $item->add_meta_data('_wpqb_bundle_data', $bundle, false);
    }

    private function get_display_hook()
    {
        $positions = wpqb_plugin_get_display_positions();
        $position = isset($this->settings['display_position']) ? $this->settings['display_position'] : '';

        if ('shortcode_only' === $position) {
            return '';
        }

        return isset($positions[$position]) ? $position : 'woocommerce_before_add_to_cart_button';
    }

    private function resolve_requested_bundle_data($product_id, $variation_id, $quantity)
    {
        $requested_index = null;
        if (!empty($_POST['wpqb_selected_bundle'])) {
            $bundle_data = json_decode(wp_unslash($_POST['wpqb_selected_bundle']), true);
            if (is_array($bundle_data) && isset($bundle_data['bundle_index'])) {
                $requested_index = absint($bundle_data['bundle_index']);
            }
        }

        $source_product_id = $variation_id > 0 ? $variation_id : $product_id;
        $product = wc_get_product($source_product_id);
        if (!$product instanceof WC_Product || !$this->is_product_type_enabled($product)) {
            return [];
        }

        $bundles = $this->get_product_bundles($source_product_id);
        if (empty($bundles)) {
            return [];
        }

        $quantity = max(1, absint($quantity));

        if (null !== $requested_index && isset($bundles[$requested_index])) {
            return $this->build_bundle_pricing_data($product, $bundles[$requested_index], $requested_index, $quantity);
        }

        if ('auto' === $this->settings['selection_mode']) {
            $matched_bundle = $this->find_matching_bundle_for_quantity($bundles, $quantity);
            if (!empty($matched_bundle)) {
                return $this->build_bundle_pricing_data($product, $matched_bundle['bundle'], $matched_bundle['index'], $quantity);
            }
        }

        return [];
    }

    private function get_bundles_markup($product, $is_primary_product_form)
    {
        $is_variable = $product->is_type('variable');
        $bundles = $is_variable ? [] : $this->get_product_bundles($product->get_id());

        if (!$is_variable && 'yes' === $this->settings['enable_bundle_sorting']) {
            $bundles = $this->sort_bundles_by_qty($bundles);
        }

        if (!$is_variable && empty($bundles)) {
            return '';
        }

        if ($is_variable && !$this->product_or_variations_have_bundles($product)) {
            return '';
        }

        $rows_html = [];
        $cards_html = [];

        if (!$is_variable) {
            foreach ($bundles as $index => $bundle) {
                $pricing = $this->build_bundle_pricing_data($product, $bundle, $index, 0);
                if (empty($pricing)) {
                    continue;
                }

                $rows_html[] = $this->get_bundle_row_html($pricing, $index);
                $cards_html[] = $this->get_bundle_card_html($pricing, $index);
            }
        }

        ob_start();
        wpqb_plugin_get_template(
            'frontend-bundles',
            [
                'settings' => $this->settings,
                'is_variable' => $is_variable,
                'is_table' => ('table' === $this->settings['design_type']),
                'is_primary_product_form' => $is_primary_product_form,
                'inline_style' => $this->get_frontend_inline_style(),
                'rows_html' => implode('', $rows_html),
                'cards_html' => implode('', $cards_html),
            ]
        );

        return ob_get_clean();
    }

    private function get_bundle_row_html($pricing, $fallback_index = 0)
    {
        $bundle_name = !empty($pricing['bundle_name']) ? $pricing['bundle_name'] : sprintf(__('Bundle %d', 'wpqb'), $fallback_index + 1);
        $price_html = '<span class="wpqb-price-regular">' . wc_price($pricing['total_regular_price']) . '</span>';
        $savings_html = '';
        $has_sale = !empty($pricing['total_sale_price']) && $pricing['total_sale_price'] < $pricing['total_regular_price'];

        if ($has_sale) {
            if ('yes' === $this->settings['show_regular_price_when_sale']) {
                $price_html = '<del class="wpqb-price-cutoff">' . wc_price($pricing['total_regular_price']) . '</del><ins class="wpqb-price-sale">' . wc_price($pricing['total_sale_price']) . '</ins>';
            } else {
                $price_html = '<span class="wpqb-price-sale">' . wc_price($pricing['total_sale_price']) . '</span>';
            }

            if ('yes' === $this->settings['show_savings'] && 'yes' === $this->settings['show_discount_after_title'] && $pricing['total_regular_price'] > 0) {
                $savings = $pricing['total_regular_price'] - $pricing['total_sale_price'];
                $savings_percent = (int) round(($savings / $pricing['total_regular_price']) * 100);
                $savings_html = sprintf(
                    '<br><span class="wpqb-bundle-savings">%s</span>',
                    esc_html(sprintf(__('Save %1$s (%2$d%%)', 'wpqb'), wp_strip_all_tags(wc_price($savings)), $savings_percent))
                );
            }
        }

        $per_item_text = wp_strip_all_tags(wc_price($pricing['per_item_price']));
        if ('yes' === $this->settings['show_qty_after_per_item']) {
            $per_item_text = sprintf(__('%1$s x %2$d', 'wpqb'), $per_item_text, $pricing['tier_qty']);
        }

        ob_start();
        wpqb_plugin_get_template(
            'frontend-bundle-row',
            [
                'settings' => $this->settings,
                'pricing' => $pricing,
                'bundle_name' => $bundle_name,
                'price_html' => $price_html,
                'savings_html' => $savings_html,
                'per_item_text' => $per_item_text,
            ]
        );

        return ob_get_clean();
    }

    private function get_bundle_card_html($pricing, $fallback_index = 0)
    {
        $bundle_name = !empty($pricing['bundle_name']) ? $pricing['bundle_name'] : sprintf(__('Bundle %d', 'wpqb'), $fallback_index + 1);
        $has_sale = !empty($pricing['total_sale_price']) && $pricing['total_sale_price'] < $pricing['total_regular_price'];
        $price_html = '<span class="wpqb-price-regular">' . wc_price($pricing['total_regular_price']) . '</span>';
        $discount_html = '';

        if ($has_sale) {
            if ('yes' === $this->settings['show_regular_price_when_sale']) {
                $price_html = '<del class="wpqb-price-cutoff">' . wc_price($pricing['total_regular_price']) . '</del><ins class="wpqb-price-sale">' . wc_price($pricing['total_sale_price']) . '</ins>';
            } else {
                $price_html = '<span class="wpqb-price-sale">' . wc_price($pricing['total_sale_price']) . '</span>';
            }

            if ('yes' === $this->settings['show_savings'] && 'yes' === $this->settings['show_discount_after_title'] && $pricing['total_regular_price'] > 0) {
                $savings = $pricing['total_regular_price'] - $pricing['total_sale_price'];
                $savings_percent = (int) round(($savings / $pricing['total_regular_price']) * 100);
                $discount_html = sprintf(
                    '<span class="wpqb-bundle-savings">%s</span>',
                    esc_html(sprintf(__('Save %1$s (%2$d%%)', 'wpqb'), wp_strip_all_tags(wc_price($savings)), $savings_percent))
                );
            }
        }

        $per_item_text = wp_strip_all_tags(wc_price($pricing['per_item_price']));
        if ('yes' === $this->settings['show_qty_after_per_item']) {
            $per_item_text = sprintf(__('%1$s x %2$d', 'wpqb'), $per_item_text, $pricing['tier_qty']);
        }

        $image_markup = '<div class="wpqb-card-media wpqb-card-media-empty" aria-hidden="true"></div>';
        if (!empty($pricing['image_id'])) {
            $image_src = wp_get_attachment_image_url($pricing['image_id'], 'woocommerce_thumbnail');
            if ($image_src) {
                $image_markup = '<div class="wpqb-card-media"><img src="' . esc_url($image_src) . '" alt="' . esc_attr($bundle_name) . '" /></div>';
            }
        }

        ob_start();
        wpqb_plugin_get_template(
            'frontend-bundle-card',
            [
                'settings' => $this->settings,
                'pricing' => $pricing,
                'bundle_name' => $bundle_name,
                'price_html' => $price_html,
                'discount_html' => $discount_html,
                'per_item_text' => $per_item_text,
                'image_markup' => $image_markup,
            ]
        );

        return ob_get_clean();
    }

    private function refresh_cart_bundle_data($cart_item, $quantity)
    {
        if (empty($cart_item['wpqb_bundle']['source_product_id'])) {
            return [];
        }

        $source_product_id = absint($cart_item['wpqb_bundle']['source_product_id']);
        $bundle_index = isset($cart_item['wpqb_bundle']['bundle_index']) ? absint($cart_item['wpqb_bundle']['bundle_index']) : 0;
        $product = wc_get_product($source_product_id);
        $bundles = $this->get_product_bundles($source_product_id);

        if (!$product instanceof WC_Product || !isset($bundles[$bundle_index])) {
            return [];
        }

        if ('auto' === $this->settings['selection_mode']) {
            $matched_bundle = $this->find_matching_bundle_for_quantity($bundles, $quantity);
            if (!empty($matched_bundle)) {
                return $this->build_bundle_pricing_data($product, $matched_bundle['bundle'], $matched_bundle['index'], $quantity);
            }

            return [];
        }

        return $this->build_bundle_pricing_data($product, $bundles[$bundle_index], $bundle_index, $quantity);
    }
}
