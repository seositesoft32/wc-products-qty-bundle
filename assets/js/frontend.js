/**
 * WC Products Qty Bundle - Frontend JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Handle bundle selection
        $('.wpqb-bundle-option').on('click', function() {
            const $this = $(this);
            const qty = $this.data('qty');
            const price = $this.data('price');
            
            // Toggle selection
            $('.wpqb-bundle-option').removeClass('selected');
            $this.addClass('selected');
            
            // Update quantity field
            const $qtyInput = $('input.qty');
            if ($qtyInput.length) {
                $qtyInput.val(qty).trigger('change');
            }
            
            // Optional: You can add custom logic here to update pricing
            // This is a basic implementation that sets the quantity
        });
        
        // Allow deselecting by clicking again
        $('.wpqb-bundle-option').on('click', function(e) {
            const $this = $(this);
            
            if ($this.hasClass('selected')) {
                e.stopPropagation();
                $this.removeClass('selected');
                
                // Reset to default quantity
                const $qtyInput = $('input.qty');
                if ($qtyInput.length) {
                    $qtyInput.val(1).trigger('change');
                }
            }
        });
    });

})(jQuery);
