<?php

class WPQB_Plugin_Init
{
    /**
     * Plugin settings.
     *
     * @var array<string, string>
     */
    private $settings = [];

    public function __construct()
    {
        $this->settings = wpqb_plugin_settings();

        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'admin_menus']);
        add_action('wp_ajax_wpqb_save_settings', [$this, 'ajax_save_settings']);
        add_filter('plugin_action_links_' . WPQB_PLUGIN_BASENAME, [$this, 'add_plugin_action_links']);

        add_action('woocommerce_product_options_pricing', [$this, 'add_qty_bundle_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_qty_bundle_fields']);
        add_action('woocommerce_product_after_variable_attributes', [$this, 'add_variation_bundle_fields'], 10, 3);
        add_action('woocommerce_save_product_variation', [$this, 'save_variation_bundle_fields'], 10, 2);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

        add_action($this->get_display_hook(), [$this, 'display_qty_bundles']);
        add_shortcode('wpqb_bundles', [$this, 'render_bundles_shortcode']);

        add_filter('woocommerce_available_variation', [$this, 'append_variation_bundle_data'], 10, 3);
        add_filter('woocommerce_add_to_cart_validation', [$this, 'validate_bundle_selection'], 10, 5);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_bundle_to_cart_item'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_bundle_in_cart'], 10, 2);
        add_action('woocommerce_before_calculate_totals', [$this, 'update_cart_item_price'], 20);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_bundle_to_order'], 10, 4);
    }

    public function init()
    {
    }

    public function register_settings()
    {
        register_setting(
            'wpqb_plugin_settings_group',
            'wpqb_plugin_setting',
            [
                'type' => 'array',
                'sanitize_callback' => 'wpqb_plugin_sanitize_settings',
                'default' => wpqb_plugin_get_default_settings(),
            ]
        );
    }

    public function add_plugin_action_links($links)
    {
        $settings_url = admin_url('admin.php?page=wpqb-plugin-settings');

        array_unshift(
            $links,
            sprintf('<a href="%s">%s</a>', esc_url($settings_url), esc_html__('Settings', 'wpqb'))
        );

        return $links;
    }

    public function admin_menus()
    {
        add_submenu_page(
            'woocommerce',
            __('Qty Bundles', 'wpqb'),
            __('Qty Bundles', 'wpqb'),
            'manage_woocommerce',
            'wpqb-plugin-settings',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page()
    {
        $data = [
            'settings' => wpqb_plugin_settings(),
            'positions' => wpqb_plugin_get_display_positions(),
            'shortcode_example' => '[wpqb_bundles product_id="123"]',
        ];

        wpqb_plugin_get_template('admin-menu', $data);
    }

    public function enqueue_admin_assets($hook)
    {
        $allowed_hooks = ['post.php', 'post-new.php', 'woocommerce_page_wpqb-plugin-settings'];
        if (!in_array($hook, $allowed_hooks, true)) {
            return;
        }

        $is_settings_page = ('woocommerce_page_wpqb-plugin-settings' === $hook);

        wp_enqueue_script('wpqb-admin-js', WPQB_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], WPQB_PLUGIN_VERSION, true);
        wp_localize_script(
            'wpqb-admin-js',
            'wpqbAdmin',
            [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'saveNonce' => wp_create_nonce('wpqb_save_settings'),
                'isSettingsPage' => $is_settings_page,
                'confirmRemove' => __('Are you sure you want to remove this bundle?', 'wpqb'),
                'mediaTitle' => __('Select Bundle Image', 'wpqb'),
                'mediaButton' => __('Use this image', 'wpqb'),
                'saveButton' => __('Save Settings', 'wpqb'),
                'savingButton' => __('Saving...', 'wpqb'),
                'savedMessage' => __('Settings saved successfully.', 'wpqb'),
                'errorMessage' => __('Unable to save settings. Please try again.', 'wpqb'),
            ]
        );

        if (in_array($hook, ['post.php', 'post-new.php'], true)) {
            global $post;

            if (!$post || 'product' !== $post->post_type) {
                return;
            }

            wp_enqueue_media();
        }

        wp_enqueue_style('wpqb-admin-css', WPQB_PLUGIN_URL . 'assets/css/admin.css', [], WPQB_PLUGIN_VERSION);
    }

    public function ajax_save_settings()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error([
                'message' => __('You are not allowed to save these settings.', 'wpqb'),
            ], 403);
        }

        check_ajax_referer('wpqb_save_settings', 'nonce');

        $form_data_raw = isset($_POST['form_data']) ? wp_unslash($_POST['form_data']) : '';
        if (empty($form_data_raw) || !is_string($form_data_raw)) {
            wp_send_json_error([
                'message' => __('No settings payload received.', 'wpqb'),
            ], 400);
        }

        $parsed = [];
        parse_str($form_data_raw, $parsed);
        $settings = isset($parsed['wpqb_plugin_setting']) && is_array($parsed['wpqb_plugin_setting'])
            ? $parsed['wpqb_plugin_setting']
            : [];

        $sanitized = wpqb_plugin_sanitize_settings($settings);
        update_option('wpqb_plugin_setting', $sanitized);

        $this->settings = wpqb_plugin_settings();

        wp_send_json_success([
            'message' => __('Settings saved successfully.', 'wpqb'),
            'settings' => $sanitized,
        ]);
    }

    public function enqueue_frontend_assets()
    {
        if (!is_product()) {
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
                'headings' => [
                    'bundle' => $this->settings['table_heading_bundle'],
                    'perItem' => $this->settings['table_heading_per_item'],
                    'totalPrice' => $this->settings['table_heading_total_price'],
                ],
                'i18n' => [
                    'selectVariation' => __('Select product options to view bundles.', 'wpqb'),
                    'noBundles' => __('No bundles found for this variation.', 'wpqb'),
                    'chooseBundle' => __('Please select a bundle before adding this product to your cart.', 'wpqb'),
                    'savePrefix' => __('Save', 'wpqb'),
                    'bundleFallback' => __('Bundle', 'wpqb'),
                ],
            ]
        );
    }

    public function add_qty_bundle_fields()
    {
        global $post;

        $product = wc_get_product($post->ID);
        if ($product && $product->is_type('variable')) {
            echo '<div class="options_group wpqb-qty-bundles show_if_variable">';
            echo '<p style="padding: 12px; margin: 0;">' . esc_html__('Set quantity bundles per variation in each variation panel below.', 'wpqb') . '</p>';
            echo '</div>';

            return;
        }

        echo '<div class="options_group wpqb-qty-bundles">';
        echo '<h3 style="padding-left: 12px;">' . esc_html__('Quantity Price Bundles', 'wpqb') . '</h3>';

        $bundles = $this->get_product_bundles($post->ID);

        echo '<div id="wpqb-bundles-container" class="wpqb-bundles-container" data-name-prefix="wpqb_bundles">';

        foreach ($bundles as $index => $bundle) {
            $this->render_bundle_fields($index, $bundle);
        }

        echo '</div>';
        echo '<p class="wpqb-form-field" style="padding-left: 12px;">';
        echo '<button type="button" class="button wpqb-add-bundle">' . esc_html__('Add Bundle', 'wpqb') . '</button>';
        echo '</p>';
        echo '</div>';
    }

    private function render_bundle_fields($index, $bundle = [], $name_prefix = 'wpqb_bundles')
    {
        $bundle_name = isset($bundle['name']) ? $bundle['name'] : '';
        $regular_price = isset($bundle['regular_price']) ? $bundle['regular_price'] : '';
        $sale_price = isset($bundle['sale_price']) ? $bundle['sale_price'] : '';
        $qty = isset($bundle['qty']) ? $bundle['qty'] : '';
        $image_id = isset($bundle['image_id']) ? absint($bundle['image_id']) : 0;
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
        ?>
        <div class="wpqb-bundle-item" data-index="<?php echo esc_attr($index); ?>">
            <div class="wpqb-bundle-header">
                <h4><?php echo esc_html(sprintf(__('Bundle #%d', 'wpqb'), $index + 1)); ?></h4>
                <span class="wpqb-bundle-total-price"></span>
                <button type="button" class="button wpqb-remove-bundle"><?php esc_html_e('Remove', 'wpqb'); ?></button>
            </div>
            <div class="wpqb-bundle-fields">
                <p class="wpqb-form-field wpqb-name-field">
                    <label><?php esc_html_e('Bundle Name', 'wpqb'); ?></label>
                    <input type="text" name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][name]"
                        value="<?php echo esc_attr($bundle_name); ?>"
                        placeholder="<?php echo esc_attr__('e.g., Starter Pack, Family Bundle', 'wpqb'); ?>" />
                </p>
                <p class="wpqb-form-field">
                    <label><?php esc_html_e('Quantity', 'wpqb'); ?></label>
                    <input type="number" name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][qty]"
                        value="<?php echo esc_attr($qty); ?>" placeholder="<?php echo esc_attr__('e.g., 10', 'wpqb'); ?>"
                        min="1" step="1" />
                </p>
                <p class="wpqb-form-field">
                    <label><?php esc_html_e('Regular Price', 'wpqb'); ?></label>
                    <input type="text"
                        name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][regular_price]"
                        value="<?php echo esc_attr($regular_price); ?>" placeholder="<?php echo esc_attr__('0.00', 'wpqb'); ?>"
                        class="short wc_input_price" />
                </p>
                <p class="wpqb-form-field">
                    <label><?php esc_html_e('Sale Price', 'wpqb'); ?></label>
                    <input type="text" name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][sale_price]"
                        value="<?php echo esc_attr($sale_price); ?>" placeholder="<?php echo esc_attr__('0.00', 'wpqb'); ?>"
                        class="short wc_input_price" />
                </p>
                <p class="wpqb-form-field wpqb-image-field">
                    <label><?php esc_html_e('Bundle Image', 'wpqb'); ?></label>
                    <span class="wpqb-image-preview">
                        <?php if ($image_url): ?>
                            <img src="<?php echo esc_url($image_url); ?>" alt="" style="max-width: 100px; max-height: 100px;" />
                        <?php endif; ?>
                    </span>
                    <input type="hidden" name="<?php echo esc_attr($name_prefix); ?>[<?php echo esc_attr($index); ?>][image_id]"
                        class="wpqb-image-id" value="<?php echo esc_attr($image_id); ?>" />
                    <button type="button" class="button wpqb-upload-image"><?php esc_html_e('Upload Image', 'wpqb'); ?></button>
                    <button type="button" class="button wpqb-remove-image" <?php echo $image_url ? '' : 'style="display:none;"'; ?>><?php esc_html_e('Remove Image', 'wpqb'); ?></button>
                </p>
            </div>
        </div>
        <?php
    }

    public function save_qty_bundle_fields($post_id)
    {
        if (!$this->can_save_product_bundles($post_id)) {
            return;
        }

        $bundles = isset($_POST['wpqb_bundles']) ? $this->sanitize_bundles(wp_unslash($_POST['wpqb_bundles'])) : [];

        if (empty($bundles)) {
            delete_post_meta($post_id, '_wpqb_qty_bundles');

            return;
        }

        update_post_meta($post_id, '_wpqb_qty_bundles', $bundles);
    }

    public function add_variation_bundle_fields($loop, $variation_data, $variation)
    {
        unset($loop, $variation_data);

        $variation_id = $variation->ID;
        $bundles = $this->get_product_bundles($variation_id);

        echo '<div class="wpqb-variation-bundles">';
        echo '<h4>' . esc_html__('Quantity Bundles', 'wpqb') . '</h4>';
        echo '<div class="wpqb-bundles-container" data-name-prefix="wpqb_variation_bundles[' . esc_attr($variation_id) . ']">';

        foreach ($bundles as $index => $bundle) {
            $this->render_bundle_fields($index, $bundle, 'wpqb_variation_bundles[' . $variation_id . ']');
        }

        echo '</div>';
        echo '<p><button type="button" class="button wpqb-add-variation-bundle" data-variation-id="' . esc_attr($variation_id) . '">' . esc_html__('Add Bundle', 'wpqb') . '</button></p>';
        echo '</div>';
    }

    public function save_variation_bundle_fields($variation_id, $index)
    {
        unset($index);

        if (!$this->can_save_product_bundles($variation_id)) {
            return;
        }

        $raw_variations = isset($_POST['wpqb_variation_bundles']) ? wp_unslash($_POST['wpqb_variation_bundles']) : [];
        $raw_bundles = isset($raw_variations[$variation_id]) ? $raw_variations[$variation_id] : [];
        $bundles = $this->sanitize_bundles($raw_bundles);

        if (empty($bundles)) {
            delete_post_meta($variation_id, '_wpqb_qty_bundles');

            return;
        }

        update_post_meta($variation_id, '_wpqb_qty_bundles', $bundles);
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
        if ('yes' !== $this->settings['shortcode_enabled']) {
            return '';
        }

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

    private function get_bundles_markup($product, $is_primary_product_form)
    {
        $is_variable = $product->is_type('variable');
        $bundles = $is_variable ? [] : $this->get_product_bundles($product->get_id());
        $is_table = ('table' === $this->settings['design_type']);
        $inline_style = $this->get_frontend_inline_style();

        if (!$is_variable && empty($bundles)) {
            return '';
        }

        if ($is_variable && !$this->product_or_variations_have_bundles($product)) {
            return '';
        }

        ob_start();
        ?>
        <div class="wpqb-bundles-frontend wpqb-design-<?php echo esc_attr($this->settings['design_type']); ?><?php echo $is_primary_product_form ? '' : ' wpqb-bundles-shortcode'; ?>"
            style="<?php echo esc_attr($inline_style); ?>">
            <h3 class="wpqb-bundles-title"><?php echo esc_html($this->settings['table_title']); ?></h3>
            <input type="hidden" name="wpqb_selected_bundle" id="wpqb-selected-bundle" value="" />
            <?php if ($is_variable): ?>
                <p class="wpqb-bundles-placeholder"><?php esc_html_e('Select product options to view bundles.', 'wpqb'); ?></p>
            <?php endif; ?>
            <div class="wpqb-bundles-list">
                <div class="wpqb-bundles-table-wrap<?php echo $is_table ? '' : ' wpqb-hidden'; ?>">
                    <table class="wpqb-bundles-table">
                        <thead>
                            <tr>
                                <th><?php echo esc_html($this->settings['table_heading_bundle']); ?></th>
                                <?php if ('yes' === $this->settings['show_per_item_price']): ?>
                                    <th><?php echo esc_html($this->settings['table_heading_per_item']); ?></th>
                                <?php endif; ?>
                                <th><?php echo esc_html($this->settings['table_heading_total_price']); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (!$is_variable) {
                                foreach ($bundles as $index => $bundle) {
                                    $pricing = $this->build_bundle_pricing_data($product, $bundle, $index, 0);
                                    if (empty($pricing)) {
                                        continue;
                                    }

                                    echo $this->get_bundle_row_html($pricing, $index);
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
                <div class="wpqb-bundles-cards<?php echo $is_table ? ' wpqb-hidden' : ''; ?>">
                    <?php
                    if (!$is_variable) {
                        foreach ($bundles as $index => $bundle) {
                            $pricing = $this->build_bundle_pricing_data($product, $bundle, $index, 0);
                            if (empty($pricing)) {
                                continue;
                            }

                            echo $this->get_bundle_card_html($pricing, $index);
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        <p class="wpqb-selected-total" id="wpqb-selected-total" <?php echo ('yes' === $this->settings['show_selected_total']) ? '' : ' style="display:none;"'; ?>></p>
        <?php

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
        ?>
        <tr class="wpqb-bundle-option" data-bundle-index="<?php echo esc_attr($pricing['bundle_index']); ?>"
            data-bundle-name="<?php echo esc_attr($pricing['bundle_name']); ?>"
            data-qty="<?php echo esc_attr($pricing['tier_qty']); ?>"
            data-price="<?php echo esc_attr($pricing['total_price']); ?>"
            data-regular-price="<?php echo esc_attr($pricing['total_regular_price']); ?>"
            data-sale-price="<?php echo esc_attr($pricing['total_sale_price']); ?>">
            <td class="wpqb-col-name">
                <span class="wpqb-bundle-name"><?php echo esc_html($bundle_name); ?></span>
                <?php echo wp_kses_post($savings_html); ?>
            </td>
            <?php if ('yes' === $this->settings['show_per_item_price']): ?>
                <td class="wpqb-col-per-item">
                    <?php echo esc_html($per_item_text); ?>
                </td>
            <?php endif; ?>
            <td class="wpqb-col-price wpqb-bundle-price">
                <?php echo wp_kses_post($price_html); ?>
            </td>
        </tr>
        <?php

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
        ?>
        <div class="wpqb-bundle-card wpqb-bundle-option" data-bundle-index="<?php echo esc_attr($pricing['bundle_index']); ?>"
            data-bundle-name="<?php echo esc_attr($pricing['bundle_name']); ?>"
            data-qty="<?php echo esc_attr($pricing['tier_qty']); ?>"
            data-price="<?php echo esc_attr($pricing['total_price']); ?>"
            data-regular-price="<?php echo esc_attr($pricing['total_regular_price']); ?>"
            data-sale-price="<?php echo esc_attr($pricing['total_sale_price']); ?>">
            <?php echo wp_kses_post($image_markup); ?>
            <div class="wpqb-card-content">
                <div class="wpqb-card-title-row">
                    <span class="wpqb-bundle-name"><?php echo esc_html($bundle_name); ?></span>
                    <?php echo wp_kses_post($discount_html); ?>
                </div>
                <?php if ('yes' === $this->settings['show_per_item_price']): ?>
                    <div class="wpqb-card-per-item"><?php echo esc_html($per_item_text); ?></div>
                <?php endif; ?>
                <div class="wpqb-card-total wpqb-bundle-price"><?php echo wp_kses_post($price_html); ?></div>
            </div>
        </div>
        <?php

        return ob_get_clean();
    }

    public function append_variation_bundle_data($variation_data, $product, $variation)
    {
        unset($product);

        $bundles = $this->get_product_bundles($variation->get_id());
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

    public function validate_bundle_selection($passed, $product_id, $quantity, $variation_id, $variations)
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

    private function can_save_product_bundles($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return false;
        }

        if (empty($_POST['woocommerce_meta_nonce'])) {
            return false;
        }

        return (bool) wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['woocommerce_meta_nonce'])), 'woocommerce_save_data');
    }

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

        usort(
            $bundles,
            static function ($left, $right) {
                return $left['qty'] <=> $right['qty'];
            }
        );

        return array_values($bundles);
    }

    private function get_product_regular_price_value($product)
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

    private function get_product_bundles($product_id)
    {
        $bundles = get_post_meta($product_id, '_wpqb_qty_bundles', true);

        return is_array($bundles) ? $this->sanitize_bundles($bundles) : [];
    }

    private function product_has_bundles($product_id)
    {
        return !empty($this->get_product_bundles($product_id));
    }

    private function product_or_variations_have_bundles($product)
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

    private function is_product_type_enabled($product)
    {
        if ($product->is_type('variable')) {
            return 'yes' === $this->settings['enable_variable_products'];
        }

        return 'yes' === $this->settings['enable_simple_products'];
    }

    private function get_display_hook()
    {
        $positions = wpqb_plugin_get_display_positions();
        $position = isset($this->settings['display_position']) ? $this->settings['display_position'] : '';

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

    private function find_matching_bundle_for_quantity($bundles, $quantity)
    {
        $match = [];

        foreach ($bundles as $index => $bundle) {
            $tier_qty = isset($bundle['qty']) ? absint($bundle['qty']) : 0;
            if ($tier_qty > 0 && $quantity >= $tier_qty) {
                $match = [
                    'index' => $index,
                    'bundle' => $bundle,
                ];
            }
        }

        return $match;
    }

    private function build_bundle_pricing_data($product, $bundle, $bundle_index, $cart_qty)
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

    private function get_frontend_inline_style()
    {
        $map = [
            '--wpqb-table-head-bg' => 'table_head_bg_color',
            '--wpqb-table-head-text' => 'table_head_text_color',
            '--wpqb-table-body-bg' => 'table_body_bg_color',
            '--wpqb-table-body-text' => 'table_body_text_color',
            '--wpqb-card-bg' => 'card_bg_color',
            '--wpqb-card-text' => 'card_text_color',
            '--wpqb-card-border' => 'card_border_color',
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

        return implode(';', $parts);
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

if (!isset($GLOBALS['wpqb_plugin_init']) && class_exists('WPQB_Plugin_Init')) {
    $GLOBALS['wpqb_plugin_init'] = new WPQB_Plugin_Init();
    add_action('init', [$GLOBALS['wpqb_plugin_init'], 'init']);
}
