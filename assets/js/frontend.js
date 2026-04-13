/**
 * WC Products Qty Bundle - Frontend JavaScript
 */
(function($) {
    'use strict';

    let selectedBundle = null;

    $(document).ready(function() {
        const $priceElement = $('.summary .price');
        const originalPriceHTML = $priceElement.html();
        
        // Handle bundle selection
        $(document).on('click', '.wpqb-bundle-option', function(e) {
            e.preventDefault();
            
            const $this = $(this);
            
            // Check if clicking on already selected bundle (deselect)
            if ($this.hasClass('selected')) {
                deselectBundle();
                return;
            }
            
            // Select new bundle
            selectBundle($this);
        });
        
        /**
         * Select a bundle
         */
        function selectBundle($bundle) {
            // Remove previous selection
            $('.wpqb-bundle-option').removeClass('selected');
            $bundle.addClass('selected');
            
            // Get bundle data
            selectedBundle = {
                bundle_index: $bundle.data('bundle-index'),
                bundle_name: $bundle.data('bundle-name'),
                qty: parseInt($bundle.data('qty')),
                price: parseFloat($bundle.data('price')),
                regular_price: parseFloat($bundle.data('regular-price')),
                sale_price: parseFloat($bundle.data('sale-price'))
            };
            
            // Update quantity field
            const $qtyInput = $('input.qty');
            if ($qtyInput.length) {
                $qtyInput.val(selectedBundle.qty).trigger('change');
            }
            
            // Update price display
            updatePriceDisplay();
            
            // Store bundle data in hidden field
            $('#wpqb-selected-bundle').val(JSON.stringify(selectedBundle));
            
            // Scroll to add to cart button
            // $('html, body').animate({
            //     scrollTop: $('.single_add_to_cart_button').offset().top - 100
            // }, 500);
        }
        
        /**
         * Deselect bundle
         */
        function deselectBundle() {
            $('.wpqb-bundle-option').removeClass('selected');
            selectedBundle = null;
            
            // Reset quantity to 1
            const $qtyInput = $('input.qty');
            if ($qtyInput.length) {
                $qtyInput.val(1).trigger('change');
            }
            
            // Reset price display
            $priceElement.html(originalPriceHTML);
            
            // Clear hidden field
            $('#wpqb-selected-bundle').val('');
        }
        
        /**
         * Update price display on product page
         */
        function updatePriceDisplay() {
            if (!selectedBundle) return;
            
            const price = selectedBundle.price;
            const regularPrice = selectedBundle.regular_price;
            const salePrice = selectedBundle.sale_price;
            const hasSale = salePrice > 0 && salePrice < regularPrice;
            
            let priceHTML = '';
            
            if (hasSale) {
                // Format with currency
                const currencySymbol = getCurrencySymbol();
                const formattedRegular = formatPrice(regularPrice);
                const formattedSale = formatPrice(salePrice);
                
                priceHTML = `<del><span class="woocommerce-Price-amount amount">${currencySymbol}${formattedRegular}</span></del> `;
                priceHTML += `<ins><span class="woocommerce-Price-amount amount">${currencySymbol}${formattedSale}</span></ins>`;
            } else {
                const currencySymbol = getCurrencySymbol();
                const formattedPrice = formatPrice(price);
                priceHTML = `<span class="woocommerce-Price-amount amount">${currencySymbol}${formattedPrice}</span>`;
            }
            
            $priceElement.html(priceHTML);
        }
        
        /**
         * Get currency symbol from existing price element
         */
        function getCurrencySymbol() {
            const priceText = $('.summary .price .woocommerce-Price-currencySymbol').first().text();
            return priceText || '$';
        }
        
        /**
         * Format price with decimals
         */
        function formatPrice(price) {
            return parseFloat(price).toFixed(2);
        }
        
        /**
         * Prevent add to cart without bundle selection (optional)
         * Uncomment if you want to force bundle selection
         */
        /*
        $('form.cart').on('submit', function(e) {
            if ($('.wpqb-bundles-frontend').length && !selectedBundle) {
                e.preventDefault();
                alert('Please select a bundle before adding to cart.');
                return false;
            }
        });
        */
        
        // Handle quantity changes to update total price display
        $(document).on('change', 'input.qty', function() {
            if (selectedBundle) {
                // You can add logic here to show total price (bundle price × quantity)
                // For now, WooCommerce will handle this in the cart
            }
        });
    });

})(jQuery);
