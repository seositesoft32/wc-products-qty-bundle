# WC Products Qty Bundle

WC Products Qty Bundle adds quantity-based bundle pricing to WooCommerce products and variations. Merchants can define bundle tiers per product, display them as selectable options on the product page, and have the correct tier pricing applied in cart and checkout.

## Features

- Quantity bundle pricing for simple and variable products
- Automatic or manual tier selection on the storefront
- Savings display and live selected-total summary
- WooCommerce settings page for display and cleanup options
- Shortcode support with `[wpqb_bundles product_id="123"]`
- Cart and order metadata for applied pricing tiers
- HPOS and Cart/Checkout Blocks compatibility declarations

## Setup

1. Activate WooCommerce.
2. Activate WC Products Qty Bundle.
3. Go to WooCommerce > Qty Bundles to configure storefront behavior.
4. Edit a product and add bundle rows in the Pricing section.
5. For variable products, add bundle rows inside each variation panel.

## Notes

- Bundle prices are validated server-side against saved product metadata.
- Uninstall cleanup is opt-in and can be enabled from WooCommerce > Qty Bundles.
- Translation loading expects language files in the `languages` directory.

## Developer Hooks

The plugin now exposes filters and actions for:

- default settings and runtime settings
- template resolution and before/after template rendering
- sanitized bundle rows and resolved pricing data
- admin settings saves and product or variation bundle saves
- frontend localized script settings and rendered bundle markup
- template-level hooks for titles, headings, placeholders, classes, and before/after markup blocks
- cart item bundle payloads, cart recalculation, and order item persistence

See `docs/hooks.md` for the full hook reference and argument list.

