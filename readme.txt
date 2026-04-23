=== WC Products Qty Bundle ===
Contributors: seositesoft
Tags: woocommerce, quantity pricing, bundle pricing, tiered pricing, variable products
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add quantity-based bundle pricing to WooCommerce products and variations with selectable tiers on the product page.

== Description ==

WC Products Qty Bundle helps store owners create bundle-style quantity pricing directly on WooCommerce products.

Key features:

* Quantity bundle pricing for simple products
* Variation-level quantity bundle pricing for variable products
* Automatic tier matching by quantity or manual bundle selection
* Savings display and live selected total on product pages
* Server-side pricing validation to prevent manipulated frontend prices
* WooCommerce settings page for layout and behavior options
* Shortcode support: [wpqb_bundles product_id="123"]
* Cart and order line item metadata for applied pricing tiers
* Optional uninstall cleanup

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/wc-products-qty-bundle/` directory.
2. Activate the plugin through the Plugins screen in WordPress.
3. Make sure WooCommerce is installed and active.
4. Go to WooCommerce > Qty Bundles to configure the plugin.
5. Edit products to add quantity bundle rows in the Pricing tab.

== Frequently Asked Questions ==

= How do I add bundles to variable products? =

Edit the product, open each variation panel, and add bundle rows inside that variation.

= Can I place the bundle table with a shortcode? =

Yes. Use `[wpqb_bundles product_id="123"]` on a page, template, or builder that supports shortcodes.

= Does the plugin trust frontend bundle pricing data? =

No. The plugin recalculates pricing from saved product or variation metadata on the server.

= Will uninstall remove plugin data? =

Only if you enable the cleanup option in WooCommerce > Qty Bundles.

== Changelog ==

= 2.3.0 =
* Added WooCommerce settings page for storefront and cleanup options.
* Added server-side validation for bundle pricing data.
* Added automatic tier recalculation in cart based on quantity.
* Added shortcode support and plugin action link.
* Added HPOS and Cart/Checkout Blocks compatibility declarations.
* Improved uninstall cleanup, readme metadata, and marketplace readiness.
