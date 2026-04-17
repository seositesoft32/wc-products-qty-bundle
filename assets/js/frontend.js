/**
 * WC Products Qty Bundle - Frontend JavaScript
 */
(function ($) {
    'use strict';

    let selectedBundle = null;
    const pluginSettings = window.wpqbPluginSettings || {};
    const i18n = pluginSettings.i18n || {};

    $(document).ready(function () {
        const $variationForm = $('form.variations_form');
        const isVariableProduct = $variationForm.length > 0;
        const $bundleTableBody = $('.wpqb-bundles-table tbody');
        const $bundlePlaceholder = $('.wpqb-bundles-placeholder');
        const $selectedTotal = $('#wpqb-selected-total');
        const $defaultPriceElement = $('.summary .price').first();
        const defaultPriceHTML = $defaultPriceElement.length ? $defaultPriceElement.html() : '';
        let selectedVariationId = 0;
        let variationPriceHTML = '';

        /**
         * Get active price element for simple/variable products
         */
        function getActivePriceElement() {
            if (isVariableProduct) {
                const $variationPrice = $('.woocommerce-variation-price .price').first();
                if ($variationPrice.length) {
                    return $variationPrice;
                }
            }

            return $('.summary .price').first();
        }

        /**
         * Save selected bundle payload to hidden field
         */
        function syncSelectedBundleField() {
            if (!selectedBundle) {
                $('#wpqb-selected-bundle').val('');
                return;
            }

            const payload = $.extend({}, selectedBundle);
            if (selectedVariationId > 0) {
                payload.variation_id = selectedVariationId;
            }

            $('#wpqb-selected-bundle').val(JSON.stringify(payload));
        }

        /**
         * Keep quantity field aligned with selected bundle
         */
        function syncBundleQty() {
            if (!selectedBundle || !selectedBundle.qty) {
                return;
            }

            const $qtyInput = $('form.cart input.qty').first();
            if ($qtyInput.length) {
                $qtyInput.val(selectedBundle.qty).trigger('change');
            }
        }

        function getCurrentCartQty() {
            const qty = parseInt($('form.cart input.qty').first().val(), 10);
            return Number.isFinite(qty) && qty > 0 ? qty : 0;
        }

        function sortBundleRowsByQty() {
            if (!$bundleTableBody.length) {
                return;
            }

            const rows = $bundleTableBody.find('tr.wpqb-bundle-option').get();
            rows.sort(function (a, b) {
                const aQty = parseInt($(a).data('qty'), 10) || 0;
                const bQty = parseInt($(b).data('qty'), 10) || 0;
                return aQty - bQty;
            });

            $.each(rows, function (_, row) {
                $bundleTableBody.append(row);
            });
        }

        function selectBundleByQty(qty) {
            const currentQty = parseInt(qty, 10) || 0;
            const $rows = $('.wpqb-bundle-option');

            if (currentQty <= 0 || !$rows.length) {
                deselectBundle();
                return;
            }

            let $matched = null;

            $rows.each(function () {
                const tierQty = parseInt($(this).data('qty'), 10) || 0;
                if (tierQty > 0 && currentQty >= tierQty) {
                    $matched = $(this);
                }
            });

            if ($matched && $matched.length) {
                selectBundle($matched, false);
            } else {
                deselectBundle();
            }
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function renderVariationBundles(bundles) {
            if (!$bundleTableBody.length) {
                return;
            }

            const rows = Array.isArray(bundles) ? bundles.slice() : [];
            rows.sort(function (a, b) {
                const aQty = parseInt(a.qty, 10) || 0;
                const bQty = parseInt(b.qty, 10) || 0;
                return aQty - bQty;
            });
            const currencySymbol = getCurrencySymbol(getActivePriceElement());

            if (!rows.length) {
                $bundleTableBody.empty();
                if ($bundlePlaceholder.length) {
                    $bundlePlaceholder.text(i18n.noBundles || 'No bundles found for this variation.').show();
                }
                return;
            }

            let html = '';
            rows.forEach(function (bundle, index) {
                const qty = parseInt(bundle.qty, 10) || 0;
                const totalPrice = parseFloat(bundle.price) || 0;
                const totalRegularPrice = parseFloat(bundle.regular_price) || 0;
                const totalSalePrice = parseFloat(bundle.sale_price) || 0;
                const hasSale = totalSalePrice > 0 && totalSalePrice < totalRegularPrice;
                const perItemPrice = parseFloat(bundle.per_item_price) || 0;
                const bundleName = bundle.bundle_name ? bundle.bundle_name : ('Bundle ' + (index + 1));

                const priceHtml = hasSale
                    ? `<del>${formatPrice(totalRegularPrice)}</del><ins>${formatPrice(totalSalePrice)}</ins>`
                    : `${formatPrice(totalRegularPrice)}`;

                let savingsHtml = '<span class="wpqb-empty">-</span>';
                if (hasSale && totalRegularPrice > 0) {
                    const savings = totalRegularPrice - totalSalePrice;
                    const savingsPercent = Math.round((savings / totalRegularPrice) * 100);
                    if (pluginSettings.showSavings !== false) {
                        savingsHtml = `<span class="wpqb-bundle-savings">Save ${formatPrice(savings)} (${savingsPercent}%)</span>`;
                    }
                }

                html += `<tr class="wpqb-bundle-option"
                            data-bundle-index="${escapeHtml(bundle.bundle_index)}"
                            data-bundle-name="${escapeHtml(bundle.bundle_name)}"
                            data-qty="${qty}"
                            data-price="${totalPrice}"
                            data-regular-price="${totalRegularPrice}"
                            data-sale-price="${totalSalePrice}">
                            <td class="wpqb-col-name">
                                <span class="wpqb-bundle-name">${escapeHtml(bundleName)}</span>
                                <br>
                                ${savingsHtml}
                            </td>
                            <td class="wpqb-col-per-item"> ${formatPrice(perItemPrice)} x ${qty}</td>
                            <td class="wpqb-col-price wpqb-bundle-price">${priceHtml}</td>
                        </tr>`;
                // <td class="wpqb-col-qty">${qty} items</td>
                // <td class="wpqb-col-savings">${savingsHtml}</td>
            });

            $bundleTableBody.html(html);
            sortBundleRowsByQty();
            if ($bundlePlaceholder.length) {
                $bundlePlaceholder.hide();
            }
        }

        if (isVariableProduct) {
            $variationForm.on('found_variation', function (event, variation) {
                selectedVariationId = variation && variation.variation_id ? parseInt(variation.variation_id, 10) : 0;

                const $variationPrice = $('.woocommerce-variation-price .price').first();
                variationPriceHTML = $variationPrice.length ? $variationPrice.html() : '';

                deselectBundle();
                renderVariationBundles(variation && variation.wpqb_bundles ? variation.wpqb_bundles : []);
                selectBundleByQty(getCurrentCartQty());
            });

            $variationForm.on('reset_data hide_variation', function () {
                selectedVariationId = 0;
                deselectBundle();
                renderVariationBundles([]);
                if ($bundlePlaceholder.length) {
                    $bundlePlaceholder.text(i18n.selectVariation || 'Select product options to view bundles.').show();
                }
            });
        }

        // Handle bundle selection
        $(document).on('click', '.wpqb-bundle-option', function (e) {
            e.preventDefault();

            const $this = $(this);

            // Check if clicking on already selected bundle (deselect)
            if ($this.hasClass('selected')) {
                deselectBundle();
                return;
            }

            // Select new bundle
            selectBundle($this, true);
        });

        /**
         * Select a bundle
         */
        function selectBundle($bundle, setQtyInput) {
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

            if (setQtyInput) {
                syncBundleQty();
            }

            // Update custom bundle summary (do not override Woo price)
            updateSelectedBundleTotalDisplay();

            // Store bundle data in hidden field
            syncSelectedBundleField();

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

            updateSelectedBundleTotalDisplay();

            syncSelectedBundleField();
        }

        /**
         * Update selected bundle total summary below table
         */
        function updateSelectedBundleTotalDisplay() {
            if (!$selectedTotal.length || pluginSettings.showSelectedTotal === false) {
                return;
            }

            if (!selectedBundle || !selectedBundle.qty) {
                $selectedTotal.hide().text('');
                return;
            }

            const tierQty = parseInt(selectedBundle.qty, 10) || 0;
            const tierTotalPrice = parseFloat(selectedBundle.price) || 0;
            const qty = getCurrentCartQty();

            if (qty <= 0 || tierQty <= 0) {
                $selectedTotal.hide().text('');
                return;
            }

            const itemPrice = tierTotalPrice / tierQty;
            const totalPrice = itemPrice * qty;
            const text = `${formatPrice(itemPrice)} x ${qty}: ${formatPrice(totalPrice)}`;

            $selectedTotal.html(text).show();
        }

        /**
         * Get currency symbol from existing price element
         */
        function getCurrencySymbol($priceElement) {
            const priceText = $priceElement.find('.woocommerce-Price-currencySymbol').first().text();
            let currencySymbol = priceText || '$';
            return `<span class="woocommerce-Price-currencySymbol">${currencySymbol}</span>`;
        }

        /**
         * Format price with decimals
         */
        function formatPrice(price) {
            let curSymbol = getCurrencySymbol(getActivePriceElement());
            return curSymbol + parseFloat(price).toFixed(2);
        }

        /**
         * Prevent add to cart without bundle selection (optional)
         * Uncomment if you want to force bundle selection
         */
        $('form.cart').on('submit', function(e) {
            if (pluginSettings.requireBundleSelection && $('.wpqb-bundles-frontend').length && !selectedBundle) {
                e.preventDefault();
                alert(i18n.chooseBundle || 'Please select a bundle before adding this product to your cart.');
                return false;
            }
        });

        $('form.cart').on('submit', function () {
            if (isVariableProduct) {
                const postedVariationId = parseInt($(this).find('input[name="variation_id"]').val(), 10) || 0;
                if (postedVariationId > 0) {
                    selectedVariationId = postedVariationId;
                }
            }

            syncSelectedBundleField();
        });

        // Handle quantity changes to update total price display
        $(document).on('change', 'input.qty', function () {
            if (pluginSettings.selectionMode === 'auto') {
                selectBundleByQty(getCurrentCartQty());
            }

            updateSelectedBundleTotalDisplay();
        });

        sortBundleRowsByQty();
        if (pluginSettings.selectionMode === 'auto') {
            selectBundleByQty(getCurrentCartQty());
        }
    });

})(jQuery);
