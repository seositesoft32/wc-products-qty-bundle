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

    public function init() {}
    
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
                    <input type="text" 
                           name="wpqb_bundles[<?php echo $index; ?>][name]" 
                           value="<?php echo esc_attr($bundle_name); ?>" 
                           placeholder="<?php _e('e.g., Starter Pack, Family Bundle', 'wpqb'); ?>" />
                </p>
                <p class="form-field">
                    <label><?php _e('Regular Price', 'wpqb'); ?></label>
                    <input type="text" 
                           name="wpqb_bundles[<?php echo $index; ?>][regular_price]" 
                           value="<?php echo esc_attr($regular_price); ?>" 
                           placeholder="<?php _e('0.00', 'wpqb'); ?>"
                           class="short wc_input_price" />
                </p>
                <p class="form-field">
                    <label><?php _e('Sale Price', 'wpqb'); ?></label>
                    <input type="text" 
                           name="wpqb_bundles[<?php echo $index; ?>][sale_price]" 
                           value="<?php echo esc_attr($sale_price); ?>" 
                           placeholder="<?php _e('0.00', 'wpqb'); ?>"
                           class="short wc_input_price" />
                </p>
                <p class="form-field">
                    <label><?php _e('Quantity', 'wpqb'); ?></label>
                    <input type="number" 
                           name="wpqb_bundles[<?php echo $index; ?>][qty]" 
                           value="<?php echo esc_attr($qty); ?>" 
                           placeholder="<?php _e('e.g., 10', 'wpqb'); ?>"
                           min="1"
                           step="1" />
                </p>
                <p class="form-field wpqb-image-field">
                    <label><?php _e('Bundle Image', 'wpqb'); ?></label>
                    <div class="wpqb-image-preview">
                        <?php if ($image_url): ?>
                            <img src="<?php echo esc_url($image_url); ?>" alt="" style="max-width: 100px; max-height: 100px;" />
                        <?php endif; ?>
                    </div>
                    <input type="hidden" 
                           name="wpqb_bundles[<?php echo $index; ?>][image_id]" 
                           class="wpqb-image-id"
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
        
        echo '<div class="wpqb-bundles-frontend">';
        echo '<h3 class="wpqb-bundles-title">' . __('Quantity Bundles', 'wpqb') . '</h3>';
        echo '<input type="hidden" name="wpqb_selected_bundle" id="wpqb-selected-bundle" value="" />';
        echo '<div class="wpqb-bundles-list">';
        
        foreach ($bundles as $index => $bundle) {
            $bundle_name = isset($bundle['name']) ? $bundle['name'] : '';
            $qty = isset($bundle['qty']) ? $bundle['qty'] : 0;
            $regular_price = isset($bundle['regular_price']) ? $bundle['regular_price'] : 0;
            $sale_price = isset($bundle['sale_price']) ? $bundle['sale_price'] : 0;
            $image_id = isset($bundle['image_id']) ? $bundle['image_id'] : 0;
            
            if ($qty <= 0) {
                continue;
            }
            
            $display_price = $sale_price > 0 ? $sale_price : $regular_price;
            $has_sale = $sale_price > 0 && $sale_price < $regular_price;
            
            echo '<div class="wpqb-bundle-option" 
                       data-bundle-index="' . esc_attr($index) . '"
                       data-bundle-name="' . esc_attr($bundle_name) . '"
                       data-qty="' . esc_attr($qty) . '" 
                       data-price="' . esc_attr($display_price) . '"
                       data-regular-price="' . esc_attr($regular_price) . '"
                       data-sale-price="' . esc_attr($sale_price) . '">';
            
            if ($image_id) {
                $image_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                if ($image_url) {
                    echo '<div class="wpqb-bundle-image">';
                    echo '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($bundle_name ? $bundle_name : sprintf(__('Bundle of %d', 'wpqb'), $qty)) . '" />';
                    echo '</div>';
                }
            }
            
            echo '<div class="wpqb-bundle-details">';
            
            // Display bundle name if available
            if (!empty($bundle_name)) {
                echo '<div class="wpqb-bundle-name">' . esc_html($bundle_name) . '</div>';
            }
            
            echo '<div class="wpqb-bundle-qty">' . sprintf(__('Bundle: %d items', 'wpqb'), $qty) . '</div>';
            
            // Calculate per-item price for display
            $per_item_price = $qty > 0 ? $display_price / $qty : 0;
            $per_item_regular = $qty > 0 ? $regular_price / $qty : 0;
            
            echo '<div class="wpqb-bundle-price">';
            
            if ($has_sale) {
                echo '<del>' . wc_price($regular_price) . '</del> ';
                echo '<ins>' . wc_price($sale_price) . '</ins>';
            } else {
                echo wc_price($regular_price);
            }
            
            echo '</div>';
            
            // Show per-item price
            if ($qty > 1) {
                echo '<div class="wpqb-per-item-price">' . sprintf(__('%s per item', 'wpqb'), wc_price($per_item_price)) . '</div>';
            }
            
            // Calculate savings
            if ($has_sale) {
                $savings = $regular_price - $sale_price;
                $savings_percent = round(($savings / $regular_price) * 100);
                echo '<div class="wpqb-bundle-savings">' . sprintf(__('Save %s (%d%%)', 'wpqb'), wc_price($savings), $savings_percent) . '</div>';
            }
            
            echo '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
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
                
                $cart_item_data['wpqb_bundle'] = [
                    'bundle_index' => intval($bundle_data['bundle_index']),
                    'bundle_name' => sanitize_text_field($bundle_data['bundle_name']),
                    'qty' => $qty,
                    'per_item_price' => $per_item_price,
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
