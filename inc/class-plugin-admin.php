<?php

if (!defined('ABSPATH')) {
    exit;
}

class WPQB_Plugin_Admin extends WPQB_Plugin_Base
{
    public function hooks()
    {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_menu', [$this, 'admin_menus']);
        add_action('wp_ajax_wpqb_save_settings', [$this, 'ajax_save_settings']);
        add_filter('plugin_action_links_' . WPQB_PLUGIN_BASENAME, [$this, 'add_plugin_action_links']);

        add_action('woocommerce_product_options_pricing', [$this, 'add_qty_bundle_fields']);
        add_action('woocommerce_process_product_meta', [$this, 'save_qty_bundle_fields']);
        add_action('woocommerce_product_after_variable_attributes', [$this, 'add_variation_bundle_fields'], 10, 3);
        add_action('woocommerce_save_product_variation', [$this, 'save_variation_bundle_fields'], 10, 2);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
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

    public function add_qty_bundle_fields()
    {
        global $post;

        $product = wc_get_product($post->ID);
        if ($product && $product->is_type('variable')) {
            wpqb_plugin_get_template('admin-variable-bundles-message');
            return;
        }

        $data = [
            'bundles' => $this->get_product_bundles($post->ID),
            'name_prefix' => 'wpqb_bundles',
            'renderer' => $this,
        ];

        wpqb_plugin_get_template('admin-product-bundles', $data);
    }

    public function render_bundle_fields($index, $bundle = [], $name_prefix = 'wpqb_bundles')
    {
        $data = [
            'index' => $index,
            'name_prefix' => $name_prefix,
            'bundle_name' => isset($bundle['name']) ? $bundle['name'] : '',
            'regular_price' => isset($bundle['regular_price']) ? $bundle['regular_price'] : '',
            'sale_price' => isset($bundle['sale_price']) ? $bundle['sale_price'] : '',
            'qty' => isset($bundle['qty']) ? $bundle['qty'] : '',
            'image_id' => isset($bundle['image_id']) ? absint($bundle['image_id']) : 0,
        ];

        $data['image_url'] = $data['image_id'] ? wp_get_attachment_image_url($data['image_id'], 'thumbnail') : '';

        wpqb_plugin_get_template('admin-bundle-fields', $data);
    }

    public function save_qty_bundle_fields($post_id)
    {
        if (!$this->can_save_product_bundles($post_id)) {
            return;
        }

        $product = wc_get_product($post_id);

        if ($product && $product->is_type('variable')) {
            if (empty($_POST['wpqb_variation_bundle_rendered']) || !is_array($_POST['wpqb_variation_bundle_rendered'])) {
                return;
            }

            $child_ids = array_map('absint', $product->get_children());
            $rendered_raw = wp_unslash($_POST['wpqb_variation_bundle_rendered']);
            $rendered_ids = array_map('absint', array_keys($rendered_raw));
            $valid_ids = array_intersect($rendered_ids, $child_ids);

            $raw_variations = isset($_POST['wpqb_variation_bundles'])
                ? wp_unslash($_POST['wpqb_variation_bundles'])
                : [];

            foreach ($valid_ids as $variation_id) {
                $raw_bundles = isset($raw_variations[$variation_id]) ? $raw_variations[$variation_id] : [];
                $bundles = $this->sanitize_bundles($raw_bundles);

                if (empty($bundles)) {
                    delete_post_meta($variation_id, '_wpqb_qty_bundles');
                } else {
                    update_post_meta($variation_id, '_wpqb_qty_bundles', $bundles);
                }
            }

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
        $data = [
            'variation_id' => $variation_id,
            'bundles' => $this->get_product_bundles($variation_id),
            'renderer' => $this,
        ];

        wpqb_plugin_get_template('admin-variation-bundles', $data);
    }

    public function save_variation_bundle_fields($variation_id, $index)
    {
        unset($index);

        if (!$this->can_save_variation_bundles($variation_id)) {
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

    private function can_save_variation_bundles($variation_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }

        if (!current_user_can('edit_post', $variation_id)) {
            return false;
        }

        $security_nonce_valid = false;
        if (!empty($_POST['security'])) {
            $security_nonce_valid = (bool) wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['security'])),
                'save-variations'
            );
        }

        $meta_nonce_valid = false;
        if (!empty($_POST['woocommerce_meta_nonce'])) {
            $meta_nonce_valid = (bool) wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['woocommerce_meta_nonce'])),
                'woocommerce_save_data'
            );
        }

        return ($security_nonce_valid || $meta_nonce_valid);
    }
}
