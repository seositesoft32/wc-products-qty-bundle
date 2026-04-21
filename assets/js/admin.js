/**
 * WC Products Qty Bundle - Admin JavaScript
 */
(function ($) {
    'use strict';

    let bundleIndex = 0;

    $(document).ready(function () {
        initSettingsPage();

        // Initialize bundle index based on existing bundles
        bundleIndex = $('.wpqb-bundle-item').length;

        // Calculate totals for existing bundle rows on page load
        refreshAllBundleTotals();

        // Add new bundle
        $(document).on('click', '.wpqb-add-bundle', function (e) {
            e.preventDefault();
            addBundle();
        });

        // Add new variation bundle
        $(document).on('click', '.wpqb-add-variation-bundle', function (e) {
            e.preventDefault();

            const variationId = $(this).data('variation-id');
            addVariationBundle(variationId);
        });

        // Remove bundle
        $(document).on('click', '.wpqb-remove-bundle', function (e) {
            e.preventDefault();

            if (confirm((window.wpqbAdmin && window.wpqbAdmin.confirmRemove) ? window.wpqbAdmin.confirmRemove : 'Are you sure you want to remove this bundle?')) {
                $(this).closest('.wpqb-bundle-item').fadeOut(300, function () {
                    const $container = $(this).closest('.wpqb-bundles-container');
                    $(this).remove();
                    updateBundleNumbers($container);
                });
            }
        });

        // Upload image
        $(document).on('click', '.wpqb-upload-image', function (e) {
            e.preventDefault();

            const button = $(this);
            const bundleItem = button.closest('.wpqb-bundle-item');
            const preview = bundleItem.find('.wpqb-image-preview');
            const imageIdInput = bundleItem.find('.wpqb-image-id');
            const removeButton = bundleItem.find('.wpqb-remove-image');

            // Create media frame
            const frame = wp.media({
                title: (window.wpqbAdmin && window.wpqbAdmin.mediaTitle) ? window.wpqbAdmin.mediaTitle : 'Select Bundle Image',
                button: {
                    text: (window.wpqbAdmin && window.wpqbAdmin.mediaButton) ? window.wpqbAdmin.mediaButton : 'Use this image'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            // When image is selected
            frame.on('select', function () {
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
        $(document).on('click', '.wpqb-remove-image', function (e) {
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

        // Recalculate bundle total when bundle inputs change
        $(document).on('input change',
            '.wpqb-bundle-item input[name$="[qty]"], .wpqb-bundle-item input[name$="[regular_price]"], .wpqb-bundle-item input[name$="[sale_price]"]',
            function () {
                updateBundleTotal($(this).closest('.wpqb-bundle-item'));
            }
        );

        // If product/variation base prices change, update affected bundle totals
        $(document).on('input change',
            '#_regular_price, #_price, input[name="_regular_price"], input[name="_price"], .woocommerce_variation input[name^="variable_regular_price"], .woocommerce_variation input[name^="variable_price"]',
            function () {
                const $variation = $(this).closest('.woocommerce_variation');
                if ($variation.length) {
                    $variation.find('.wpqb-bundle-item').each(function () {
                        updateBundleTotal($(this));
                    });
                    return;
                }

                $('#wpqb-bundles-container .wpqb-bundle-item').each(function () {
                    updateBundleTotal($(this));
                });
            }
        );
    });

    function initSettingsPage() {
        const $settingsPage = $('.wpqb-settings-page');
        if (!$settingsPage.length) {
            return;
        }

        const $tabs = $settingsPage.find('.wpqb-tab');
        const $panels = $settingsPage.find('.wpqb-tab-panel');
        const $form = $('#wpqb-settings-form');
        const $saveButton = $('#wpqb-save-settings');
        const $notice = $('#wpqb-settings-notice');
        const i18nSaveButton = (window.wpqbAdmin && window.wpqbAdmin.saveButton) ? window.wpqbAdmin.saveButton : 'Save Settings';
        const i18nSavingButton = (window.wpqbAdmin && window.wpqbAdmin.savingButton) ? window.wpqbAdmin.savingButton : 'Saving...';
        const i18nSavedMessage = (window.wpqbAdmin && window.wpqbAdmin.savedMessage) ? window.wpqbAdmin.savedMessage : 'Settings saved successfully.';
        const i18nErrorMessage = (window.wpqbAdmin && window.wpqbAdmin.errorMessage) ? window.wpqbAdmin.errorMessage : 'Unable to save settings. Please try again.';

        function setNotice(message, status) {
            $notice.removeClass('is-success is-error').addClass('is-visible');
            $notice.addClass('success' === status ? 'is-success' : 'is-error').text(message);
        }

        function clearNotice() {
            $notice.removeClass('is-visible is-success is-error').text('');
        }

        function switchTab(target) {
            $tabs.removeClass('is-active');
            $tabs.filter('[data-tab="' + target + '"]').addClass('is-active');

            $panels.removeClass('is-active');
            $panels.filter('[data-panel="' + target + '"]').addClass('is-active');
        }

        function syncDesignPanels() {
            const design = $('#wpqb_design_type').val();
            const isCards = 'cards' === design;
            const $tableTab = $tabs.filter('[data-tab="table-style"]');
            const $cardsTab = $tabs.filter('[data-tab="cards-style"]');

            $tableTab.toggle(!isCards);
            $cardsTab.toggle(isCards);

            $tableTab.toggleClass('is-muted', isCards);
            $cardsTab.toggleClass('is-muted', !isCards);

            const $activeTab = $tabs.filter('.is-active');
            const activeId = $activeTab.data('tab');

            if (isCards && 'table-style' === activeId) {
                switchTab('cards-style');
            }

            if (!isCards && 'cards-style' === activeId) {
                switchTab('table-style');
            }
        }

        $tabs.on('click', function () {
            const target = $(this).data('tab');
            switchTab(target);
        });

        $('#wpqb_design_type').on('change', syncDesignPanels);
        syncDesignPanels();

        function saveSettingsAjax() {
            clearNotice();
            $saveButton.prop('disabled', true).text(i18nSavingButton);

            $.ajax({
                url: (window.wpqbAdmin && window.wpqbAdmin.ajaxUrl) ? window.wpqbAdmin.ajaxUrl : ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wpqb_save_settings',
                    nonce: (window.wpqbAdmin && window.wpqbAdmin.saveNonce) ? window.wpqbAdmin.saveNonce : '',
                    form_data: $form.serialize()
                }
            }).done(function (response) {
                if (response && response.success) {
                    setNotice((response.data && response.data.message) ? response.data.message : i18nSavedMessage, 'success');
                } else {
                    setNotice((response && response.data && response.data.message) ? response.data.message : i18nErrorMessage, 'error');
                }
            }).fail(function () {
                setNotice(i18nErrorMessage, 'error');
            }).always(function () {
                $saveButton.prop('disabled', false).text(i18nSaveButton);
            });
        }

        $saveButton.on('click', function (e) {
            e.preventDefault();
            saveSettingsAjax();
        });

        $form.on('submit', function (e) {
            e.preventDefault();
            saveSettingsAjax();
        });
    }

    /**
     * Add a new bundle to the container
     */
    function addBundle() {
        const container = $('#wpqb-bundles-container');
        const namePrefix = container.data('name-prefix') || 'wpqb_bundles';
        const newBundle = createBundleHTML(bundleIndex, namePrefix);

        container.append(newBundle);
        bundleIndex++;

        // Animate the new bundle
        container.find('.wpqb-bundle-item').last().hide().fadeIn(300);
        updateBundleNumbers(container);
        updateBundleTotal(container.find('.wpqb-bundle-item').last());
    }

    /**
     * Add a new bundle to a variation section
     */
    function addVariationBundle(variationId) {
        const container = $('.wpqb-add-variation-bundle[data-variation-id="' + variationId + '"]')
            .closest('.wpqb-variation-bundles')
            .find('.wpqb-bundles-container')
            .first();

        if (!container.length) {
            return;
        }

        const namePrefix = container.data('name-prefix') || ('wpqb_variation_bundles[' + variationId + ']');
        const index = container.find('.wpqb-bundle-item').length;
        const newBundle = createBundleHTML(index, namePrefix);

        container.append(newBundle);
        container.find('.wpqb-bundle-item').last().hide().fadeIn(300);
        updateBundleNumbers(container);
        updateBundleTotal(container.find('.wpqb-bundle-item').last());
    }

    function parseNumber(value) {
        if (typeof value === 'undefined' || value === null) {
            return 0;
        }

        const normalized = String(value).replace(/,/g, '').trim();
        const num = parseFloat(normalized);
        return Number.isFinite(num) ? num : 0;
    }

    function getCurrencySymbol() {
        if (window.woocommerce_admin_meta_boxes && window.woocommerce_admin_meta_boxes.currency_format_symbol) {
            return window.woocommerce_admin_meta_boxes.currency_format_symbol;
        }

        return '$';
    }

    function formatMoney(amount) {
        return getCurrencySymbol() + amount.toFixed(2);
    }

    function getSimpleProductBaseRegularPrice() {
        let price = parseNumber($('#_regular_price').first().val());

        if (price <= 0) {
            price = parseNumber($('input[name="_regular_price"]').first().val());
        }

        if (price <= 0) {
            price = parseNumber($('#_price').first().val());
        }

        if (price <= 0) {
            price = parseNumber($('input[name="_price"]').first().val());
        }

        return price;
    }

    function getVariationBaseRegularPrice($bundleItem) {
        const $variation = $bundleItem.closest('.woocommerce_variation');
        if (!$variation.length) {
            return 0;
        }

        let price = 0;
        const $regularInput = $variation.find('input[name^="variable_regular_price"]').first();
        if ($regularInput.length) {
            price = parseNumber($regularInput.val());
        }

        if (price <= 0) {
            const $priceInput = $variation.find('input[name^="variable_price"]').first();
            if ($priceInput.length) {
                price = parseNumber($priceInput.val());
            }
        }

        return price;
    }

    function getBundleFallbackRegularPrice($bundleItem) {
        const namePrefix = String($bundleItem.closest('.wpqb-bundles-container').data('name-prefix') || '');
        const isVariationBundle = namePrefix.indexOf('wpqb_variation_bundles[') === 0;

        if (isVariationBundle) {
            const variationPrice = getVariationBaseRegularPrice($bundleItem);
            if (variationPrice > 0) {
                return variationPrice;
            }
        }

        return getSimpleProductBaseRegularPrice();
    }

    function updateBundleTotal($bundleItem) {
        if (!$bundleItem || !$bundleItem.length) {
            return;
        }

        const qty = Math.max(0, parseInt($bundleItem.find('input[name$="[qty]"]').first().val(), 10) || 0);
        let regularPrice = parseNumber($bundleItem.find('input[name$="[regular_price]"]').first().val());
        const salePrice = parseNumber($bundleItem.find('input[name$="[sale_price]"]').first().val());

        let isUsingFallbackRegular = false;
        if (regularPrice <= 0) {
            regularPrice = getBundleFallbackRegularPrice($bundleItem);
            isUsingFallbackRegular = regularPrice > 0;
        }

        const perItemPrice = (salePrice > 0 && regularPrice > 0 && salePrice < regularPrice) ? salePrice : regularPrice;
        const totalPrice = perItemPrice > 0 && qty > 0 ? (perItemPrice * qty) : 0;
        const $totalLabel = $bundleItem.find('.wpqb-bundle-total-price').first();

        if (!$totalLabel.length) {
            return;
        }

        if (totalPrice <= 0) {
            $totalLabel.text('Bundle Total: -');
            return;
        }

        let text = 'Bundle Total: ' + formatMoney(totalPrice);
        if (isUsingFallbackRegular) {
            text += ' (using product regular price)';
        }

        $totalLabel.text(text);
    }

    function refreshAllBundleTotals() {
        $('.wpqb-bundle-item').each(function () {
            updateBundleTotal($(this));
        });
    }

    /**
     * Create HTML for a new bundle
     */
    function createBundleHTML(index, namePrefix) {
        return `
            <div class="wpqb-bundle-item" data-index="${index}">
                <div class="wpqb-bundle-header">
                    <h4>Bundle #${index + 1}</h4>
                    <span class="wpqb-bundle-total-price"></span>
                    <button type="button" class="button wpqb-remove-bundle">Remove</button>
                </div>
                <div class="wpqb-bundle-fields">
                    <p class="wpqb-form-field wpqb-name-field">
                        <label>Bundle Name</label>
                        <input type="text" 
                               name="${namePrefix}[${index}][name]" 
                               value="" 
                               placeholder="e.g., Starter Pack, Family Bundle" />
                    </p>
                     <p class="wpqb-form-field">
                        <label>Quantity</label>
                        <input type="number" 
                               name="${namePrefix}[${index}][qty]" 
                               value="" 
                               placeholder="e.g., 10"
                               min="1"
                               step="1" />
                    </p>
                    <p class="wpqb-form-field">
                        <label>Regular Price</label>
                        <input type="text" 
                               name="${namePrefix}[${index}][regular_price]" 
                               value="" 
                               placeholder="0.00"
                               class="short wc_input_price" />
                    </p>
                    <p class="wpqb-form-field">
                        <label>Sale Price</label>
                        <input type="text" 
                               name="${namePrefix}[${index}][sale_price]" 
                               value="" 
                               placeholder="0.00"
                               class="short wc_input_price" />
                    </p>
                    <p class="wpqb-form-field wpqb-image-field">
                        <label>Bundle Image</label>
                        <span class="wpqb-image-preview"></span>
                        <input type="hidden"
                               name="${namePrefix}[${index}][image_id]"
                               class="wpqb-image-id"
                               value="" />
                        <button type="button" class="button wpqb-upload-image">Upload Image</button>
                        <button type="button" class="button wpqb-remove-image" style="display:none;">Remove Image</button>
                    </p>
                </div>
            </div>`;
    }

    /**
     * Update bundle numbers after removal
     */
    function updateBundleNumbers($container) {
        const $scope = $container && $container.length ? $container : $('#wpqb-bundles-container');

        $scope.find('.wpqb-bundle-item').each(function (index) {
            $(this).find('.wpqb-bundle-header h4').text('Bundle #' + (index + 1));
        });
    }

})(jQuery);
