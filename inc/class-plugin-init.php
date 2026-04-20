<?php
class WPQB_Plugin_init
{
    // define properties here

    /**
     *
     * @since    1.0
     * 
     */
    public function __construct($args = [])
    {
        add_action('admin_menu', [$this, 'admin_menus']);

        // Add custom product fields
        add_action('woocommerce_product_options_pricing', [$this, 'add_qty_bundle_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_qty_bundle_fields']);
        add_action('woocommerce_product_after_variable_attributes', [$this, 'add_variation_bundle_fields'], 10, 3);
        add_action('woocommerce_save_product_variation', [$this, 'save_variation_bundle_fields'], 10, 2);

        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Frontend display
        add_action('woocommerce_before_add_to_cart_button', [$this, 'display_qty_bundles']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        add_filter('woocommerce_available_variation', [$this, 'append_variation_bundle_data'], 10, 3);

        // Cart and Order functionality
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_bundle_to_cart_item'], 10, 2);
        add_filter('woocommerce_get_item_data', [$this, 'display_bundle_in_cart'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'update_cart_item_price'], 10, 1);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_bundle_to_order'], 10, 4);
    }

    public function init()
    {
    }

    /**
     * Add quantity bundle fields to product pricing tab
     */
    public function add_qty_bundle_fields()
    {
        global $post;

        $product = wc_get_product($post->ID);
        if ($product && $product->is_type('variable')) {
            echo '<div class="options_group wpqb-qty-bundles show_if_variable">';
            echo '<p style="padding: 12px; margin: 0;">' . __('Set quantity bundles per variation in each variation panel below.', 'wpqb') . '</p>';
            echo '</div>';
            return;
        }

        echo '<div class="options_group wpqb-qty-bundles">';
        echo '<h3 style="padding-left: 12px;">' . __('Quantity Price Bundles', 'wpqb') . '</h3>';

        // Get existing bundles
        $bundles = get_post_meta($post->ID, '_wpqb_qty_bundles', true);
        if (!is_array($bundles)) {
            $bundles = [];
        }

        echo '<div id="wpqb-bundles-container" class="wpqb-bundles-container" data-name-prefix="wpqb_bundles">';

        // Display existing bundles only
        if (!empty($bundles)) {
            foreach ($bundles as $index => $bundle) {
                $this->render_bundle_fields($index, $bundle);
            }
        }

        echo '</div>';

        echo '<p class="wpqb-form-field" style="padding-left: 12px;">';
        echo '<button type="button" class="button wpqb-add-bundle">' . __('Add Bundle', 'wpqb') . '</button>';
        echo '</p>';

        echo '</div>';
    }

    /**
     * Render individual bundle fields
     */
    private function render_bundle_fields($index, $bundle = [], $name_prefix = 'wpqb_bundles')
    {
        $bundle_name = isset($bundle['name']) ? $bundle['name'] : '';
        $regular_price = isset($bundle['regular_price']) ? $bundle['regular_price'] : '';
        $sale_price = isset($bundle['sale_price']) ? $bundle['sale_price'] : '';
        $qty = isset($bundle['qty']) ? $bundle['qty'] : '';
        $image_id = isset($bundle['image_id']) ? $bundle['image_id'] : '';
        $image_url = $image_id ? wp_get_attachment_url($image_id) : '';

        ?>
        <div class="wpqb-bundle-item" data-index="<?php echo $index; ?>">
            <div class="wpqb-bundle-header">
                <h4><?php echo sprintf(__('Bundle #%d', 'wpqb'), $index + 1); ?></h4>
                <span class="wpqb-bundle-total-price"></span>
                <button type="button" class="button wpqb-remove-bundle"><?php _e('Remove', 'wpqb'); ?></button>
            </div>
            <div class="wpqb-bundle-fields">
                <p class="wpqb-form-field wpqb-name-field">
                    <label><?php _e('Bundle Name', 'wpqb'); ?></label>
                    <input type="text" name="<?php echo esc_attr($name_prefix); ?>[<?php echo $index; ?>][name]"
                        value="<?php echo esc_attr($bundle_name); ?>"
                        placeholder="<?php _e('e.g., Starter Pack, Family Bundle', 'wpqb'); ?>" />
                </p>
                <p class="wpqb-form-field">
                    <label>
                        <?php _e('Quantity', 'wpqb'); ?>
                    </label>
                    <input type="number" name="<?php echo esc_attr($name_prefix); ?>[<?php echo $index; ?>][qty]"
                        value="<?php echo esc_attr($qty); ?>" placeholder="<?php _e('e.g., 10', 'wpqb'); ?>" min="1" step="1" />
                </p>
                <p class="wpqb-form-field">
                    <label><?php _e('Regular Price', 'wpqb'); ?></label>
                    <input type="text" name="<?php echo esc_attr($name_prefix); ?>[<?php echo $index; ?>][regular_price]"
                        value="<?php echo esc_attr($regular_price); ?>" placeholder="<?php _e('0.00', 'wpqb'); ?>"
                        class="short wc_input_price" />
                </p>
                <p class="wpqb-form-field">
                    <label><?php _e('Sale Price', 'wpqb'); ?></label>
                    <input type="text" name="<?php echo esc_attr($name_prefix); ?>[<?php echo $index; ?>][sale_price]"
                        value="<?php echo esc_attr($sale_price); ?>" placeholder="<?php _e('0.00', 'wpqb'); ?>"
                        class="short wc_input_price" />
                </p>
                <!-- <p class="wpqb-form-field wpqb-image-field">
                    <label><?php //_e('Bundle Image', 'wpqb'); ?></label>
                <div class="wpqb-image-preview">
                    <?php //if ($image_url): ?>
                        <img src="<?php //echo esc_url($image_url); ?>" alt="" style="max-width: 100px; max-height: 100px;" />
                    <?php //endif; ?>
                </div>
                <input type="hidden" name="<?php //echo esc_attr($name_prefix); ?>[<?php //echo $index; ?>][image_id]"
                    class="wpqb-image-id" value="<?php //echo esc_attr($image_id); ?>" />
                <button type="button" class="button wpqb-upload-image"><?php //_e('Upload Image', 'wpqb'); ?></button>
                <button type="button" class="button wpqb-remove-image" <?php //echo !$image_url ? 'style="display:none;"' : ''; ?>><?php _e('Remove Image', 'wpqb'); ?></button>
                </p> -->
            </div>
        </div>
        <?php
    }

    /**
     * Save quantity bundle fields
     */
    public function save_qty_bundle_fields($post_id)
    {
        if (isset($_POST['wpqb_bundles']) && is_array($_POST['wpqb_bundles'])) {
            $bundles = $this->sanitize_bundles($_POST['wpqb_bundles']);

            update_post_meta($post_id, '_wpqb_qty_bundles', $bundles);
        } else {
            delete_post_meta($post_id, '_wpqb_qty_bundles');
        }
    }

    /**
     * Add variation-level bundle fields
     */
    public function add_variation_bundle_fields($loop, $variation_data, $variation)
    {
        $variation_id = $variation->ID;
        $bundles = get_post_meta($variation_id, '_wpqb_qty_bundles', true);
        if (!is_array($bundles)) {
            $bundles = [];
        }

        echo '<div class="wpqb-variation-bundles">';
        echo '<h4>' . __('Quantity Bundles', 'wpqb') . '</h4>';
        echo '<div class="wpqb-bundles-container" data-name-prefix="wpqb_variation_bundles[' . esc_attr($variation_id) . ']">';

        if (!empty($bundles)) {
            foreach ($bundles as $index => $bundle) {
                $this->render_bundle_fields($index, $bundle, 'wpqb_variation_bundles[' . $variation_id . ']');
            }
        }

        echo '</div>';
        echo '<p><button type="button" class="button wpqb-add-variation-bundle" data-variation-id="' . esc_attr($variation_id) . '">' . __('Add Bundle', 'wpqb') . '</button></p>';
        echo '</div>';
    }

    /**
     * Save variation-level bundle fields
     */
    public function save_variation_bundle_fields($variation_id, $i)
    {
        if (isset($_POST['wpqb_variation_bundles']) && isset($_POST['wpqb_variation_bundles'][$variation_id])) {
            $bundles = $this->sanitize_bundles($_POST['wpqb_variation_bundles'][$variation_id]);
            update_post_meta($variation_id, '_wpqb_qty_bundles', $bundles);
        } else {
            delete_post_meta($variation_id, '_wpqb_qty_bundles');
        }
    }

    /**
     * Normalize and sanitize bundle list
     */
    private function sanitize_bundles($raw_bundles)
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

            $bundles[] = [
                'name' => sanitize_text_field(isset($bundle['name']) ? $bundle['name'] : ''),
                'qty' => $qty,
                'regular_price' => wc_format_decimal(isset($bundle['regular_price']) ? $bundle['regular_price'] : ''),
                'sale_price' => wc_format_decimal(isset($bundle['sale_price']) ? $bundle['sale_price'] : ''),
                'image_id' => absint(isset($bundle['image_id']) ? $bundle['image_id'] : 0),
            ];
        }

        return $bundles;
    }

    /**
     * Check if a bundle list contains at least one usable bundle row.
     */
    private function has_valid_bundles($bundles)
    {
        if (!is_array($bundles) || empty($bundles)) {
            return false;
        }

        foreach ($bundles as $bundle) {
            $qty = isset($bundle['qty']) ? absint($bundle['qty']) : 0;
            if ($qty > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if product (simple or variable) has at least one valid bundle configured.
     */
    private function product_has_any_valid_bundles($product)
    {
        if (!$product || !is_a($product, 'WC_Product')) {
            return false;
        }

        if ($product->is_type('variable')) {
            $variation_ids = $product->get_children();
            if (empty($variation_ids)) {
                return false;
            }

            foreach ($variation_ids as $variation_id) {
                $variation_bundles = get_post_meta($variation_id, '_wpqb_qty_bundles', true);
                if ($this->has_valid_bundles($variation_bundles)) {
                    return true;
                }
            }

            return false;
        }

        $bundles = get_post_meta($product->get_id(), '_wpqb_qty_bundles', true);
        return $this->has_valid_bundles($bundles);
    }

    /**
     * Validate posted bundle payload against configured product/variation bundles.
     */
    private function is_posted_bundle_valid_for_product($bundle_data, $product_id, $variation_id = 0)
    {
        if (!is_array($bundle_data)) {
            return false;
        }

        $posted_index = isset($bundle_data['bundle_index']) ? intval($bundle_data['bundle_index']) : -1;
        $posted_qty = isset($bundle_data['qty']) ? absint($bundle_data['qty']) : 0;

        if ($posted_index < 0 || $posted_qty <= 0) {
            return false;
        }

        $source_id = $variation_id > 0 ? $variation_id : $product_id;
        $configured_bundles = get_post_meta($source_id, '_wpqb_qty_bundles', true);

        if (!$this->has_valid_bundles($configured_bundles)) {
            return false;
        }

        if (!isset($configured_bundles[$posted_index])) {
            return false;
        }

        $configured_qty = isset($configured_bundles[$posted_index]['qty']) ? absint($configured_bundles[$posted_index]['qty']) : 0;
        if ($configured_qty <= 0) {
            return false;
        }

        return $configured_qty === $posted_qty;
    }

    /**
     * Resolve a product regular price with safe fallback to current price.
     */
    private function get_product_regular_price_value($product)
    {
        if (!$product || !is_a($product, 'WC_Product')) {
            return 0;
        }

        $regular_price = floatval($product->get_regular_price());
        if ($regular_price > 0) {
            return $regular_price;
        }

        return floatval($product->get_price());
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on product edit pages
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        global $post;
        if (!$post || $post->post_type !== 'product') {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style('wpqb-admin-css', wpqb_plugin_plugin_URL . 'assets/css/admin.css', [], wpqb_plugin_info['v']);
        wp_enqueue_script('wpqb-admin-js', wpqb_plugin_plugin_URL . 'assets/js/admin.js', ['jquery'], wpqb_plugin_info['v'], true);
    }

    /**
     * Display quantity bundles on product page
     */
    public function display_qty_bundles()
    {
        global $product;

        if (!$product) {
            return;
        }

        if (!$this->product_has_any_valid_bundles($product)) {
            return;
        }

        $is_variable = $product->is_type('variable');
        $bundles = $is_variable ? [] : get_post_meta($product->get_id(), '_wpqb_qty_bundles', true);

        if (!$is_variable && !$this->has_valid_bundles($bundles)) {
            return;
        }
        ?>
        <div class="wpqb-bundles-frontend">
            <h3 class="wpqb-bundles-title"><?php _e('Quantity Bundles', 'wpqb'); ?></h3>
            <input type="hidden" name="wpqb_selected_bundle" id="wpqb-selected-bundle" value="" />
            <?php if ($is_variable): ?>
                <p class="wpqb-bundles-placeholder"><?php _e('Select product options to view bundles.', 'wpqb'); ?></p>
            <?php endif; ?>
            <div class="wpqb-bundles-list">
                <div class="wpqb-bundles-table-wrap">
                    <table class="wpqb-bundles-table">
                        <thead>
                            <tr>
                                <!-- <th><?php //_e('Image', 'wpqb'); ?></th> -->
                                <th><?php _e('Bundle', 'wpqb'); ?></th>
                                <!-- <th><?php //_e('Qty', 'wpqb'); ?></th> -->
                                <th><?php _e('Per Item', 'wpqb'); ?></th>
                                <th><?php _e('Total Price', 'wpqb'); ?></th>
                                <!-- <th><?php //_e('Savings', 'wpqb'); ?></th> -->
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$is_variable): ?>
                                <?php foreach ($bundles as $index => $bundle): ?>
                                    <?php $bundle_name = isset($bundle['name']) ? $bundle['name'] : ''; ?>
                                    <?php $qty = isset($bundle['qty']) ? $bundle['qty'] : 0; ?>
                                    <?php $regular_price = isset($bundle['regular_price']) ? floatval($bundle['regular_price']) : 0; ?>
                                    <?php if ($regular_price <= 0): ?>
                                        <?php $regular_price = $this->get_product_regular_price_value($product); ?>
                                    <?php endif; ?>
                                    <?php $sale_price = isset($bundle['sale_price']) ? $bundle['sale_price'] : 0; ?>
                                    <?php $image_id = isset($bundle['image_id']) ? $bundle['image_id'] : 0; ?>
                                    <?php if ($qty <= 0): ?>
                                        <?php continue; ?>
                                    <?php endif; ?>

                                    <?php $per_item_price = ($sale_price > 0 && $sale_price < $regular_price) ? $sale_price : $regular_price; ?>
                                    <?php $total_price = $qty > 0 ? $per_item_price * $qty : 0; ?>
                                    <?php $total_reg_price = $qty > 0 && $regular_price > 0 ? $regular_price * $qty : 0; ?>
                                    <?php $total_sale_price = $qty > 0 && $sale_price > 0 ? $sale_price * $qty : 0; ?>
                                    <?php $has_sale = $total_sale_price > 0 && $total_sale_price < $total_reg_price; ?>

                                    <?php $bundle_name_h = ($bundle_name) ? esc_html($bundle_name) : sprintf(__('Bundle %d', 'wpqb'), $index + 1) ?>

                                    <?php $bundle_img = '<span class="wpqb-empty">-</span>'; ?>
                                    <?php if ($image_id): ?>
                                        <?php $image_url = wp_get_attachment_image_url($image_id, 'thumbnail'); ?>
                                        <?php if ($image_url): ?>
                                            <?php $bundle_img = '<img class="wpqb-bundle-image" src="' . esc_url($image_url) . '" alt="' . esc_attr($bundle_name_h) . '" />'; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>


                                    <?php $bundle_total_price_h = wc_price($total_reg_price); ?>
                                    <?php $bundle_savings_h = '';//'<span class="wpqb-empty">-</span>'; ?>

                                    <?php if ($has_sale): ?>
                                        <?php $bundle_total_price_h = '<del>' . wc_price($total_reg_price) . '</del>'; ?>
                                        <?php $bundle_total_price_h .= '<ins>' . wc_price($total_sale_price) . '</ins>'; ?>

                                        <?php $savings = $total_reg_price - $total_sale_price; ?>
                                        <?php $savings_percent = round(($savings / $total_reg_price) * 100); ?>
                                        <?php $bundle_savings_h = sprintf(__('<br><span class="wpqb-bundle-savings">Save %s (%d%%)</span>', 'wpqb'), wc_price($savings), $savings_percent); ?>
                                    <?php endif; ?>

                                    <tr class="wpqb-bundle-option" data-bundle-index="<?php echo esc_attr($index); ?>"
                                        data-bundle-name="<?php echo esc_attr($bundle_name); ?>"
                                        data-qty="<?php echo esc_attr($qty); ?>" data-price="<?php echo esc_attr($total_price); ?>"
                                        data-regular-price="<?php echo esc_attr($total_reg_price); ?>"
                                        data-sale-price="<?php echo esc_attr($total_sale_price); ?>">

                                        <!-- <td class=" wpqb-col-image">
                                        <?php //echo $bundle_img; ?>
                                    </td> -->

                                        <td class="wpqb-col-name">
                                            <span class="wpqb-bundle-name"><?php echo $bundle_name_h; ?></span>
                                            <?php echo $bundle_savings_h; ?>
                                        </td>

                                        <!-- <td class="wpqb-col-qty">
                                            <?php //echo sprintf(__('%d items', 'wpqb'), $qty); ?>
                                        </td> -->

                                        <td class="wpqb-col-per-item">
                                            <?php echo sprintf(__('%s x %d', 'wpqb'), wc_price($per_item_price), $qty); ?>
                                        </td>

                                        <td class="wpqb-col-price wpqb-bundle-price">
                                            <?php echo $bundle_total_price_h; ?>
                                        </td>
                                        <!-- <td class="wpqb-col-savings">
                                            <?php //echo $bundle_savings_h; ?>
                                        </td> -->
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <p class="wpqb-selected-total" id="wpqb-selected-total" style="display:none;"></p>
        <?php
    }

    /**
     * Add variation bundles to variation payload for frontend JS
     */
    public function append_variation_bundle_data($variation_data, $product, $variation)
    {
        $bundles = get_post_meta($variation->get_id(), '_wpqb_qty_bundles', true);
        $prepared_bundles = [];

        if (is_array($bundles)) {
            foreach ($bundles as $index => $bundle) {
                $qty = isset($bundle['qty']) ? absint($bundle['qty']) : 0;
                if ($qty <= 0) {
                    continue;
                }

                $regular_price = isset($bundle['regular_price']) ? floatval($bundle['regular_price']) : 0;
                if ($regular_price <= 0) {
                    $regular_price = $this->get_product_regular_price_value($variation);
                }
                $sale_price = isset($bundle['sale_price']) ? floatval($bundle['sale_price']) : 0;
                $per_item_price = ($sale_price > 0 && $sale_price < $regular_price) ? $sale_price : $regular_price;

                $total_price = $per_item_price * $qty;
                $total_regular_price = $regular_price * $qty;
                $total_sale_price = $sale_price * $qty;

                $prepared_bundles[] = [
                    'bundle_index' => $index,
                    'bundle_name' => isset($bundle['name']) ? $bundle['name'] : '',
                    'qty' => $qty,
                    'per_item_price' => $per_item_price,
                    'price' => $total_price,
                    'regular_price' => $total_regular_price,
                    'sale_price' => $total_sale_price,
                ];
            }
        }

        $variation_data['wpqb_bundles'] = $prepared_bundles;

        return $variation_data;
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets()
    {
        if (!is_product()) {
            return;
        }

        $product_id = get_the_ID();
        if (!$product_id) {
            $product_id = get_queried_object_id();
        }
        if (!$product_id) {
            return;
        }

        $product = wc_get_product($product_id);
        if (!$this->product_has_any_valid_bundles($product)) {
            return;
        }

        wp_enqueue_style('wpqb-frontend-css', wpqb_plugin_plugin_URL . 'assets/css/frontend.css', [], wpqb_plugin_info['v']);
        wp_enqueue_script('wpqb-frontend-js', wpqb_plugin_plugin_URL . 'assets/js/frontend.js', ['jquery'], wpqb_plugin_info['v'], true);
    }

    /**
     * Add bundle data to cart item
     */
    public function add_bundle_to_cart_item($cart_item_data, $product_id)
    {
        if (isset($_POST['wpqb_selected_bundle']) && !empty($_POST['wpqb_selected_bundle'])) {
            $bundle_data = json_decode(stripslashes($_POST['wpqb_selected_bundle']), true);

            if ($bundle_data && is_array($bundle_data)) {
                $qty = intval($bundle_data['qty']);
                $total_price = floatval($bundle_data['price']);
                $total_regular_price = floatval($bundle_data['regular_price']);
                $total_sale_price = floatval($bundle_data['sale_price']);
                $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;

                if (!$this->is_posted_bundle_valid_for_product($bundle_data, $product_id, $variation_id)) {
                    return $cart_item_data;
                }

                if ($total_regular_price <= 0 && $qty > 0) {
                    $source_product_id = $variation_id > 0 ? $variation_id : $product_id;
                    $source_product = wc_get_product($source_product_id);
                    $fallback_regular = $this->get_product_regular_price_value($source_product);

                    if ($fallback_regular > 0) {
                        $total_regular_price = $fallback_regular * $qty;
                    }
                }

                // Calculate per-item price (divide total bundle price by quantity)
                $per_item_price = $qty > 0 ? $total_price / $qty : $total_price;
                $per_item_regular_price = $qty > 0 ? $total_regular_price / $qty : $total_regular_price;
                $per_item_sale_price = $qty > 0 ? $total_sale_price / $qty : $total_sale_price;

                $cart_item_data['wpqb_bundle'] = [
                    'bundle_index' => intval($bundle_data['bundle_index']),
                    'bundle_name' => sanitize_text_field($bundle_data['bundle_name']),
                    'qty' => $qty,
                    'variation_id' => $variation_id,
                    'per_item_price' => $per_item_price,
                    'per_item_regular_price' => $per_item_regular_price,
                    'per_item_sale_price' => $per_item_sale_price,
                    'total_price' => $total_price,
                    'total_regular_price' => $total_regular_price,
                    'total_sale_price' => $total_sale_price
                ];

                // Make cart item unique
                $cart_item_data['unique_key'] = md5(microtime() . rand());
            }
        }

        return $cart_item_data;
    }

    /**
     * Display bundle info in cart
     */
    public function display_bundle_in_cart($item_data, $cart_item)
    {
        if (isset($cart_item['wpqb_bundle'])) {
            $bundle = $cart_item['wpqb_bundle'];

            if (!empty($bundle['bundle_name'])) {
                $item_data[] = [
                    'name' => __('Bundle', 'wpqb'),
                    'value' => esc_html($bundle['bundle_name'])
                ];
            }

            $item_data[] = [
                'name' => __('Bundle Quantity', 'wpqb'),
                'value' => esc_html($bundle['qty']) . ' ' . __('items', 'wpqb')
            ];

            $item_data[] = [
                'name' => __('Per Item Regular Price', 'wpqb'),
                'value' => wc_price($bundle['per_item_regular_price'])
            ];

            if (!empty($bundle['per_item_sale_price']) && $bundle['per_item_sale_price'] < $bundle['per_item_regular_price']) {
                $item_data[] = [
                    'name' => __('Per Item Sale Price', 'wpqb'),
                    'value' => wc_price($bundle['per_item_sale_price'])
                ];
            }

            // Show total bundle price
            $item_data[] = [
                'name' => __('Bundle Total', 'wpqb'),
                'value' => wc_price($bundle['total_price'])
            ];

            // Show savings if there's a sale price
            if (!empty($bundle['total_sale_price']) && $bundle['total_sale_price'] < $bundle['total_regular_price']) {
                $savings = $bundle['total_regular_price'] - $bundle['total_sale_price'];
                $item_data[] = [
                    'name' => __('Bundle Savings', 'wpqb'),
                    'value' => wc_price($savings)
                ];
            }
        }

        return $item_data;
    }

    /**
     * Update cart item price based on bundle
     */
    public function update_cart_item_price($cart)
    {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['wpqb_bundle'])) {
                // Use per-item price so WooCommerce multiplies it by quantity correctly
                $per_item_price = floatval($cart_item['wpqb_bundle']['per_item_price']);

                if ($per_item_price > 0) {
                    $cart_item['data']->set_price($per_item_price);
                }
            }
        }
    }

    /**
     * Save bundle info to order
     */
    public function save_bundle_to_order($item, $cart_item_key, $values, $order)
    {
        if (isset($values['wpqb_bundle'])) {
            $bundle = $values['wpqb_bundle'];

            if (!empty($bundle['bundle_name'])) {
                $item->add_meta_data(__('Bundle', 'wpqb'), $bundle['bundle_name'], true);
            }

            $item->add_meta_data(__('Bundle Quantity', 'wpqb'), $bundle['qty'] . ' ' . __('items', 'wpqb'), true);

            $item->add_meta_data(__('Per Item Regular Price', 'wpqb'), wc_price($bundle['per_item_regular_price']), true);

            if (!empty($bundle['per_item_sale_price']) && $bundle['per_item_sale_price'] < $bundle['per_item_regular_price']) {
                $item->add_meta_data(__('Per Item Sale Price', 'wpqb'), wc_price($bundle['per_item_sale_price']), true);
            }

            // Show total bundle price
            $item->add_meta_data(__('Bundle Total', 'wpqb'), wc_price($bundle['total_price']), true);

            // Show savings if applicable
            if (!empty($bundle['total_sale_price']) && $bundle['total_sale_price'] < $bundle['total_regular_price']) {
                $savings = $bundle['total_regular_price'] - $bundle['total_sale_price'];
                $item->add_meta_data(__('Bundle Savings', 'wpqb'), wc_price($savings), true);
            }

            // Save full bundle data for reference
            $item->add_meta_data('_wpqb_bundle_data', $bundle, false);
        }
    }

    public function admin_menus()
    {
        add_submenu_page('woocommerce', 'OSC DB to WC Import', 'OSC Import', 'manage_options', 'wpqb_plugin_import', [$this, 'import_data_html']);
    }

    public function import_data_html()
    {
        $data = [
            'get' => $_GET,
        ];
        wpqb_plugin_get_template('admin-menu', $data);
    }
}

if (class_exists('WPQB_Plugin_init')) {
    $loadClass = new WPQB_Plugin_init();
    add_action('init', [$loadClass, 'init']);
}
