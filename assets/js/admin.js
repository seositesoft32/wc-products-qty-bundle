/**
 * WC Products Qty Bundle - Admin JavaScript
 */
(function($) {
    'use strict';

    let bundleIndex = 0;

    $(document).ready(function() {
        // Initialize bundle index based on existing bundles
        bundleIndex = $('.wpqb-bundle-item').length;

        // Add new bundle
        $(document).on('click', '.wpqb-add-bundle', function(e) {
            e.preventDefault();
            addBundle();
        });

        // Remove bundle
        $(document).on('click', '.wpqb-remove-bundle', function(e) {
            e.preventDefault();
            
            if (confirm('Are you sure you want to remove this bundle?')) {
                $(this).closest('.wpqb-bundle-item').fadeOut(300, function() {
                    $(this).remove();
                    updateBundleNumbers();
                });
            }
        });

        // Upload image
        $(document).on('click', '.wpqb-upload-image', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const bundleItem = button.closest('.wpqb-bundle-item');
            const preview = bundleItem.find('.wpqb-image-preview');
            const imageIdInput = bundleItem.find('.wpqb-image-id');
            const removeButton = bundleItem.find('.wpqb-remove-image');
            
            // Create media frame
            const frame = wp.media({
                title: 'Select Bundle Image',
                button: {
                    text: 'Use this image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            // When image is selected
            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                
                // Set image preview
                preview.html('<img src="' + attachment.url + '" alt="" style="max-width: 100px; max-height: 100px;" />');
                
                // Set image ID
                imageIdInput.val(attachment.id);
                
                // Show remove button
                removeButton.show();
            });

            frame.open();
        });

        // Remove image
        $(document).on('click', '.wpqb-remove-image', function(e) {
            e.preventDefault();
            
            const button = $(this);
            const bundleItem = button.closest('.wpqb-bundle-item');
            const preview = bundleItem.find('.wpqb-image-preview');
            const imageIdInput = bundleItem.find('.wpqb-image-id');
            
            // Clear preview
            preview.html('');
            
            // Clear image ID
            imageIdInput.val('');
            
            // Hide remove button
            button.hide();
        });
    });

    /**
     * Add a new bundle to the container
     */
    function addBundle() {
        const container = $('#wpqb-bundles-container');
        const newBundle = createBundleHTML(bundleIndex);
        
        container.append(newBundle);
        bundleIndex++;
        
        // Animate the new bundle
        container.find('.wpqb-bundle-item').last().hide().fadeIn(300);
    }

    /**
     * Create HTML for a new bundle
     */
    function createBundleHTML(index) {
        return `
            <div class="wpqb-bundle-item" data-index="${index}">
                <div class="wpqb-bundle-header">
                    <h4>Bundle #${index + 1}</h4>
                    <button type="button" class="button wpqb-remove-bundle">Remove</button>
                </div>
                <div class="wpqb-bundle-fields">
                    <p class="form-field wpqb-name-field">
                        <label>Bundle Name</label>
                        <input type="text" 
                               name="wpqb_bundles[${index}][name]" 
                               value="" 
                               placeholder="e.g., Starter Pack, Family Bundle" />
                    </p>
                    <p class="form-field">
                        <label>Regular Price</label>
                        <input type="text" 
                               name="wpqb_bundles[${index}][regular_price]" 
                               value="" 
                               placeholder="0.00"
                               class="short wc_input_price" />
                    </p>
                    <p class="form-field">
                        <label>Sale Price</label>
                        <input type="text" 
                               name="wpqb_bundles[${index}][sale_price]" 
                               value="" 
                               placeholder="0.00"
                               class="short wc_input_price" />
                    </p>
                    <p class="form-field">
                        <label>Quantity</label>
                        <input type="number" 
                               name="wpqb_bundles[${index}][qty]" 
                               value="" 
                               placeholder="e.g., 10"
                               min="1"
                               step="1" />
                    </p>
                    <p class="form-field wpqb-image-field">
                        <label>Bundle Image</label>
                        <div class="wpqb-image-preview"></div>
                        <input type="hidden" 
                               name="wpqb_bundles[${index}][image_id]" 
                               class="wpqb-image-id"
                               value="" />
                        <button type="button" class="button wpqb-upload-image">Upload Image</button>
                        <button type="button" class="button wpqb-remove-image" style="display:none;">Remove Image</button>
                    </p>
                </div>
            </div>
        `;
    }

    /**
     * Update bundle numbers after removal
     */
    function updateBundleNumbers() {
        $('.wpqb-bundle-item').each(function(index) {
            $(this).find('.wpqb-bundle-header h4').text('Bundle #' + (index + 1));
        });
    }

})(jQuery);
