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

        // Enqueue admin scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // Frontend display
        add_action('woocommerce_before_add_to_cart_button', [$this, 'display_qty_bundles']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

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

        echo '<div class="options_group wpqb-qty-bundles">';
        echo '<h3 style="padding-left: 12px;">' . __('Quantity Price Bundles', 'wpqb') . '</h3>';

        // Get existing bundles
        $bundles = get_post_meta($post->ID, '_wpqb_qty_bundles', true);
        if (!is_array($bundles)) {
            $bundles = [];
        }

        echo '<div id="wpqb-bundles-container">';

        // Display existing bundles only
        if (!empty($bundles)) {
            foreach ($bundles as $index => $bundle) {
                $this->render_bundle_fields($index, $bundle);
            }
        }

        echo '</div>';

        echo '<p class="form-field" style="padding-left: 12px;">';
        echo '<button type="button" class="button wpqb-add-bundle">' . __('Add Bundle', 'wpqb') . '</button>';
        echo '</p>';

        echo '</div>';
    }

    /**
     * Render individual bundle fields
     */
    private function render_bundle_fields($index, $bundle = [])
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
                <button type="button" class="button wpqb-remove-bundle"><?php _e('Remove', 'wpqb'); ?></button>
            </div>
            <div class="wpqb-bundle-fields">
                <p class="form-field wpqb-name-field">
                    <label><?php _e('Bundle Name', 'wpqb'); ?></label>
                    <input type="text" name="wpqb_bundles[<?php echo $index; ?>][name]"
                        value="<?php echo esc_attr($bundle_name); ?>"
                        placeholder="<?php _e('e.g., Starter Pack, Family Bundle', 'wpqb'); ?>" />
                </p>
                <p class="form-field">
                    <label><?php _e('Regular Price', 'wpqb'); ?></label>
                    <input type="text" name="wpqb_bundles[<?php echo $index; ?>][regular_price]"
                        value="<?php echo esc_attr($regular_price); ?>" placeholder="<?php _e('0.00', 'wpqb'); ?>"
                        class="short wc_input_price" />
                </p>
                <p class="form-field">
                    <label><?php _e('Sale Price', 'wpqb'); ?></label>
                    <input type="text" name="wpqb_bundles[<?php echo $index; ?>][sale_price]"
                        value="<?php echo esc_attr($sale_price); ?>" placeholder="<?php _e('0.00', 'wpqb'); ?>"
                        class="short wc_input_price" />
                </p>
                <p class="form-field">
                    <label><?php _e('Quantity', 'wpqb'); ?></label>
                    <input type="number" name="wpqb_bundles[<?php echo $index; ?>][qty]" value="<?php echo esc_attr($qty); ?>"
                        placeholder="<?php _e('e.g., 10', 'wpqb'); ?>" min="1" step="1" />
                </p>
                <p class="form-field wpqb-image-field">
                    <label><?php _e('Bundle Image', 'wpqb'); ?></label>
                <div class="wpqb-image-preview">
                    <?php if ($image_url): ?>
                        <img src="<?php echo esc_url($image_url); ?>" alt="" style="max-width: 100px; max-height: 100px;" />
                    <?php endif; ?>
                </div>
                <input type="hidden" name="wpqb_bundles[<?php echo $index; ?>][image_id]" class="wpqb-image-id"
                    value="<?php echo esc_attr($image_id); ?>" />
                <button type="button" class="button wpqb-upload-image"><?php _e('Upload Image', 'wpqb'); ?></button>
                <button type="button" class="button wpqb-remove-image" <?php echo !$image_url ? 'style="display:none;"' : ''; ?>><?php _e('Remove Image', 'wpqb'); ?></button>
                </p>
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
            $bundles = [];

            foreach ($_POST['wpqb_bundles'] as $bundle) {
                // Only save bundles that have at least a quantity
                if (!empty($bundle['qty'])) {
                    $bundles[] = [
                        'name' => sanitize_text_field($bundle['name']),
                        'qty' => absint($bundle['qty']),
                        'regular_price' => wc_format_decimal($bundle['regular_price']),
                        'sale_price' => wc_format_decimal($bundle['sale_price']),
                        'image_id' => absint($bundle['image_id'])
                    ];
                }
            }

            update_post_meta($post_id, '_wpqb_qty_bundles', $bundles);
        } else {
            delete_post_meta($post_id, '_wpqb_qty_bundles');
        }
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

        $bundles = get_post_meta($product->get_id(), '_wpqb_qty_bundles', true);

        if (!is_array($bundles) || empty($bundles)) {
            return;
        }
        ?>
        <div class="wpqb-bundles-frontend">
            <h3 class="wpqb-bundles-title"><?php _e('Quantity Bundles', 'wpqb'); ?></h3>
            <input type="hidden" name="wpqb_selected_bundle" id="wpqb-selected-bundle" value="" />
            <div class="wpqb-bundles-list">
                <div class="wpqb-bundles-table-wrap">
                    <table class="wpqb-bundles-table">
                        <thead>
                            <tr>
                                <!-- <th><?php //_e('Image', 'wpqb'); ?></th> -->
                                <th><?php _e('Bundle', 'wpqb'); ?></th>
                                <th><?php _e('Quantity', 'wpqb'); ?></th>
                                <th><?php _e('Per Item', 'wpqb'); ?></th>
                                <th><?php _e('Total Price', 'wpqb'); ?></th>
                                <th><?php _e('Savings', 'wpqb'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bundles as $index => $bundle): ?>
                                <?php $bundle_name = isset($bundle['name']) ? $bundle['name'] : ''; ?>
                                <?php $qty = isset($bundle['qty']) ? $bundle['qty'] : 0; ?>
                                <?php $regular_price = isset($bundle['regular_price']) ? $bundle['regular_price'] : 0; ?>
                                <?php $sale_price = isset($bundle['sale_price']) ? $bundle['sale_price'] : 0; ?>
                                <?php $image_id = isset($bundle['image_id']) ? $bundle['image_id'] : 0; ?>
                                <?php if ($qty <= 0): ?>
                                    <?php continue; ?>
                                <?php endif; ?>

                                <?php $per_item_price = $sale_price > 0 ? $sale_price : $regular_price; ?>
                                <?php $total_price = $qty > 0 ? $per_item_price * $qty : 0; ?>
                                <?php $total_reg_price = $qty > 0 ? $regular_price * $qty : 0; ?>
                                <?php $total_sale_price = $qty > 0 ? $sale_price * $qty : 0; ?>
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
                                <?php if ($has_sale): ?>
                                    <?php $bundle_total_price_h = '<del>' . wc_price($total_reg_price) . '</del>'; ?>
                                    <?php $bundle_total_price_h .= '<ins>' . wc_price($total_sale_price) . '</ins>'; ?>
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
                                    </td>

                                    <td class="wpqb-col-qty">
                                        <?php echo sprintf(__('%d items', 'wpqb'), $qty); ?>
                                    </td>

                                    <td class="wpqb-col-per-item">
                                        <?php echo wc_price($per_item_price); ?>
                                    </td>

                                    <td class="wpqb-col-price wpqb-bundle-price">
                                        <?php echo $bundle_total_price_h; ?>
                                    </td>
                                    <td class="wpqb-col-savings">
                                        <?php if ($has_sale): ?>
                                            <?php $savings = $total_reg_price - $total_sale_price; ?>
                                            <?php $savings_percent = round(($savings / $total_reg_price) * 100); ?>
                                            <span class="wpqb-bundle-savings">
                                                <?php echo sprintf(__('Save %s (%d%%)', 'wpqb'), wc_price($savings), $savings_percent); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="wpqb-empty">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets()
    {
        if (is_product()) {
            wp_enqueue_style('wpqb-frontend-css', wpqb_plugin_plugin_URL . 'assets/css/frontend.css', [], wpqb_plugin_info['v']);
            wp_enqueue_script('wpqb-frontend-js', wpqb_plugin_plugin_URL . 'assets/js/frontend.js', ['jquery'], wpqb_plugin_info['v'], true);
        }
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

                // Calculate per-item price (divide total bundle price by quantity)
                $per_item_price = $qty > 0 ? $total_price / $qty : $total_price;
                $per_item_regular_price = $qty > 0 ? $total_regular_price / $qty : $total_regular_price;
                $per_item_sale_price = $qty > 0 ? $total_sale_price / $qty : $total_sale_price;

                $cart_item_data['wpqb_bundle'] = [
                    'bundle_index' => intval($bundle_data['bundle_index']),
                    'bundle_name' => sanitize_text_field($bundle_data['bundle_name']),
                    'qty' => $qty,
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
