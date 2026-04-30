# Developer Hooks Reference

WC Products Qty Bundle now exposes filters and actions around settings, template loading, bundle pricing, storefront rendering, and cart or order persistence.

## Settings And Templates

### `wpqb_plugin_default_settings`
Filter the plugin default settings array before it is used.

### `wpqb_plugin_display_positions`
Filter the supported WooCommerce display hook positions shown in settings.

### `wpqb_plugin_sanitized_settings`
Filter sanitized settings before they are saved.

Arguments: `$sanitized`, `$settings`, `$defaults`

### `wpqb_plugin_settings`
Filter the final runtime settings array returned by `wpqb_plugin_settings()`.

Arguments: `$settings`, `$data`

### `wpqb_located_template`
Filter the resolved template file path before inclusion.

Arguments: `$template`, `$template_name`, `$args`, `$template_path`, `$default_path`

### `wpqb_before_template_part`
Action fired before a plugin template is included.

Arguments: `$template`, `$template_name`, `$args`

### `wpqb_after_template_part`
Action fired after a plugin template is included.

Arguments: `$template`, `$template_name`, `$args`

## Bundle Data And Pricing

### `wpqb_sanitized_bundles`
Filter sanitized bundle rows before they are saved or consumed.

Arguments: `$bundles`, `$raw_bundles`, `$instance`

### `wpqb_product_bundles`
Filter bundle rows after they are loaded from product or variation meta.

Arguments: `$bundles`, `$product_id`, `$instance`

### `wpqb_is_product_type_enabled`
Filter whether bundle behavior is enabled for the current product.

Arguments: `$enabled`, `$product`, `$instance`

### `wpqb_sorted_bundles`
Filter bundle rows after quantity sorting.

Arguments: `$bundles`, `$instance`

### `wpqb_bundle_pricing_data`
Filter the resolved pricing payload for a bundle tier.

Arguments: `$pricing_data`, `$product`, `$bundle`, `$bundle_index`, `$cart_qty`, `$instance`

### `wpqb_matching_bundle_for_quantity`
Filter the best matching tier returned for a quantity.

Arguments: `$match`, `$bundles`, `$quantity`, `$instance`

### `wpqb_frontend_inline_style`
Filter the frontend wrapper CSS variable string.

Arguments: `$inline_style`, `$parts`, `$settings`, `$instance`

## Admin Hooks

### `wpqb_admin_settings_page_data`
Filter the data passed into the settings page template.

Arguments: `$data`, `$instance`

### `wpqb_admin_settings_saved`
Action fired after settings are saved from the AJAX endpoint.

Arguments: `$sanitized`, `$settings`, `$instance`

### `wpqb_admin_bundle_field_data`
Filter the admin bundle row template data.

Arguments: `$data`, `$index`, `$bundle`, `$name_prefix`, `$instance`

### `wpqb_admin_variation_bundle_data`
Filter the variation bundle editor panel data.

Arguments: `$data`, `$variation_id`, `$variation`, `$instance`

### `wpqb_saved_product_bundles`
Action fired after bundle rows are saved or cleared for a product or variation during the parent save flow.

Arguments: `$post_id`, `$bundles`, `$raw_bundles`, `$instance`

### `wpqb_saved_variation_bundles`
Action fired after bundle rows are saved or cleared in the dedicated variation save flow.

Arguments: `$variation_id`, `$bundles`, `$raw_bundles`, `$instance`

## Frontend And Shortcode Hooks

### `wpqb_should_load_frontend_assets`
Filter whether frontend CSS and JS should load for the current request.

Arguments: `$load_assets`, `$instance`

### `wpqb_frontend_script_settings`
Filter the localized settings payload sent to `frontend.js`.

Arguments: `$script_settings`, `$product`, `$instance`

### `wpqb_before_display_bundles`
Action fired before product-page bundle markup is echoed.

Arguments: `$markup`, `$product`, `$instance`

### `wpqb_after_display_bundles`
Action fired after product-page bundle markup is echoed.

Arguments: `$markup`, `$product`, `$instance`

### `wpqb_shortcode_atts`
Filter shortcode attributes before product lookup.

Arguments: `$atts`, `$instance`

### `wpqb_shortcode_markup`
Filter final shortcode output.

Arguments: `$markup`, `$product`, `$atts`, `$instance`

### `wpqb_variation_prepared_bundle`
Filter each bundle payload prepared for variation JSON.

Arguments: `$prepared_bundle`, `$pricing`, `$variation`, `$bundle`, `$index`, `$instance`

### `wpqb_variation_bundle_data`
Filter full variation data after `wpqb_bundles` has been appended.

Arguments: `$variation_data`, `$variation`, `$instance`

### `wpqb_resolved_bundle_data`
Filter the bundle payload resolved from request data and quantity.

Arguments: `$resolved_bundle`, `$product_id`, `$variation_id`, `$quantity`, `$requested_index`, `$instance`

### `wpqb_bundles_template_args`
Filter the arguments passed to the main `frontend-bundles` template.

Arguments: `$args`, `$product`, `$is_primary_product_form`, `$instance`

### `wpqb_bundles_markup`
Filter the complete bundle widget markup.

Arguments: `$markup`, `$product`, `$is_primary_product_form`, `$instance`

### `wpqb_bundles_rows_html`
Filter the rendered table rows array before the main bundles template is rendered.

Arguments: `$rows_html`, `$product`, `$is_primary_product_form`, `$instance`

### `wpqb_bundles_cards_html`
Filter the rendered card items array before the main bundles template is rendered.

Arguments: `$cards_html`, `$product`, `$is_primary_product_form`, `$instance`

### `wpqb_before_render_bundles_template`
Action fired before `frontend-bundles.php` is rendered.

Arguments: `$product`, `$rows_html`, `$cards_html`, `$is_primary_product_form`, `$instance`

### `wpqb_after_render_bundles_template`
Action fired after `frontend-bundles.php` is rendered.

Arguments: `$product`, `$rows_html`, `$cards_html`, `$is_primary_product_form`, `$instance`

### `wpqb_bundle_row_html`
Filter one rendered table-row bundle option.

Arguments: `$markup`, `$pricing`, `$fallback_index`, `$instance`

### `wpqb_bundle_card_html`
Filter one rendered card bundle option.

Arguments: `$markup`, `$pricing`, `$fallback_index`, `$instance`

## Cart And Order Hooks

### `wpqb_validate_bundle_selection`
Filter the final bundle add-to-cart validation result.

Arguments: `$passed`, `$bundle_data`, `$product_id`, `$quantity`, `$variation_id`, `$instance`

### `wpqb_required_bundle_notice_text`
Filter the required-bundle validation notice text shown before add-to-cart is blocked.

Arguments: `$message`, `$product_id`, `$variation_id`, `$quantity`, `$instance`

### `wpqb_cart_item_bundle_data`
Filter bundle data before it is stored on the cart item.

Arguments: `$bundle_data`, `$cart_item_data`, `$product_id`, `$variation_id`, `$quantity`, `$instance`

### `wpqb_add_bundle_to_cart_item`
Filter final cart item data after bundle information has been attached.

Arguments: `$cart_item_data`, `$bundle_data`, `$product_id`, `$variation_id`, `$instance`

### `wpqb_cart_item_display_data`
Filter bundle metadata rows shown in cart and checkout.

Arguments: `$item_data`, `$cart_item`, `$bundle`, `$instance`

### `wpqb_cart_display_labels`
Filter cart and checkout display labels and format strings for bundle item metadata.

Arguments: `$labels`, `$bundle`, `$cart_item`, `$instance`

### `wpqb_refreshed_cart_bundle_data`
Filter the refreshed bundle pricing payload used during cart recalculation.

Arguments: `$bundle_data`, `$cart_item`, `$quantity`, `$instance`

### `wpqb_before_update_cart_item_price`
Action fired at the beginning of bundle-driven cart repricing.

Arguments: `$cart`, `$instance`

### `wpqb_removed_invalid_cart_bundle`
Action fired when stale or invalid bundle data is removed from a cart item.

Arguments: `$cart_item_key`, `$cart_item`, `$cart`, `$instance`

### `wpqb_after_update_cart_item_price`
Action fired at the end of bundle-driven cart repricing.

Arguments: `$cart`, `$instance`

### `wpqb_after_cart_item_bundle_refresh`
Action fired after a cart line item's bundle pricing has been recalculated.

Arguments: `$cart_item_key`, `$bundle`, `$cart_item`, `$cart`, `$instance`

### `wpqb_after_save_bundle_to_order`
Action fired after bundle data has been written to the order item.

Arguments: `$item`, `$bundle`, `$values`, `$instance`

### `wpqb_order_item_meta_labels`
Filter order line-item labels and format strings used when writing bundle metadata.

Arguments: `$labels`, `$bundle`, `$values`, `$instance`

### `wpqb_display_hook`
Filter the resolved WooCommerce display hook used to output frontend bundles.

Arguments: `$display_hook`, `$position`, `$positions`, `$instance`

## Template-Level Hooks

These hooks live directly inside frontend template files and are useful for changing text, classes, data attributes, and inserting custom blocks.

### Main bundles template (`frontend-bundles.php`)

- `wpqb_template_bundles_wrapper_classes`
- `wpqb_template_bundles_title_text`
- `wpqb_template_bundles_placeholder_text`
- `wpqb_template_heading_bundle_text`
- `wpqb_template_heading_per_item_text`
- `wpqb_template_heading_total_price_text`
- `wpqb_template_selected_total_text`
- `wpqb_template_selected_total_classes`
- `wpqb_template_before_bundles`
- `wpqb_template_bundles_top`
- `wpqb_template_before_bundles_list`
- `wpqb_template_before_bundles_table`
- `wpqb_template_after_bundles_table`
- `wpqb_template_before_bundles_cards`
- `wpqb_template_after_bundles_cards`
- `wpqb_template_bundles_bottom`
- `wpqb_template_before_selected_total`
- `wpqb_template_after_selected_total`
- `wpqb_template_after_bundles`

### Table row template (`frontend-bundle-row.php`)

- `wpqb_template_bundle_row_classes`
- `wpqb_template_bundle_row_data_attributes`
- `wpqb_template_bundle_row_name_text`
- `wpqb_template_bundle_row_per_item_text`
- `wpqb_template_bundle_row_price_html`
- `wpqb_template_bundle_row_savings_html`
- `wpqb_template_before_bundle_row`
- `wpqb_template_bundle_row_name_before`
- `wpqb_template_bundle_row_name_after`
- `wpqb_template_bundle_row_per_item_before`
- `wpqb_template_bundle_row_per_item_after`
- `wpqb_template_bundle_row_price_before`
- `wpqb_template_bundle_row_price_after`
- `wpqb_template_after_bundle_row`

### Card template (`frontend-bundle-card.php`)

- `wpqb_template_bundle_card_classes`
- `wpqb_template_bundle_card_data_attributes`
- `wpqb_template_bundle_card_name_text`
- `wpqb_template_bundle_card_per_item_text`
- `wpqb_template_bundle_card_price_html`
- `wpqb_template_bundle_card_discount_html`
- `wpqb_template_bundle_card_image_markup`
- `wpqb_template_before_bundle_card`
- `wpqb_template_bundle_card_media_before`
- `wpqb_template_bundle_card_media_after`
- `wpqb_template_bundle_card_name_before`
- `wpqb_template_bundle_card_name_after`
- `wpqb_template_bundle_card_per_item_before`
- `wpqb_template_bundle_card_per_item_after`
- `wpqb_template_bundle_card_price_before`
- `wpqb_template_bundle_card_price_after`
- `wpqb_template_after_bundle_card`

## Example

```php
add_filter( 'wpqb_bundle_pricing_data', function ( $pricing_data, $product ) {
    if ( $product->get_id() === 123 ) {
        $pricing_data['bundle_name'] = 'Wholesale Tier';
    }

    return $pricing_data;
}, 10, 2 );
```