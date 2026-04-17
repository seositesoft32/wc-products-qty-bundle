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
        const $bundleCards = $('.wpqb-bundles-cards');
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
            const $rows = $('.wpqb-bundle-option');
            if (!$rows.length) {
                return;
            }

            const rows = $rows.get();
            rows.sort(function (a, b) {
                const aQty = parseInt($(a).data('qty'), 10) || 0;
                const bQty = parseInt($(b).data('qty'), 10) || 0;
                return aQty - bQty;
            });

            $.each(rows, function (_, row) {
                const $row = $(row);
                if ($row.is('tr')) {
                    $bundleTableBody.append(row);
                } else {
                    $bundleCards.append(row);
                }
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
            if (!$bundleTableBody.length && !$bundleCards.length) {
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
                $bundleCards.empty();
                if ($bundlePlaceholder.length) {
                    $bundlePlaceholder.text(i18n.noBundles || 'No bundles found for this variation.').show();
                }
                return;
            }

            let tableHtml = '';
            let cardHtml = '';
            rows.forEach(function (bundle, index) {
                const qty = parseInt(bundle.qty, 10) || 0;
                const totalPrice = parseFloat(bundle.price) || 0;
                const totalRegularPrice = parseFloat(bundle.regular_price) || 0;
                const totalSalePrice = parseFloat(bundle.sale_price) || 0;
                const hasSale = totalSalePrice > 0 && totalSalePrice < totalRegularPrice;
                const perItemPrice = parseFloat(bundle.per_item_price) || 0;
                const bundleName = bundle.bundle_name ? bundle.bundle_name : ('Bundle ' + (index + 1));
                const imageId = parseInt(bundle.image_id, 10) || 0;
                const imageUrl = bundle.image_url ? bundle.image_url : '';

                const priceHtml = hasSale
                    ? (pluginSettings.showRegularPriceWhenSale !== false
                        ? `<del class="wpqb-price-cutoff">${formatPrice(totalRegularPrice)}</del><ins class="wpqb-price-sale">${formatPrice(totalSalePrice)}</ins>`
                        : `<span class="wpqb-price-sale">${formatPrice(totalSalePrice)}</span>`)
                    : `<span class="wpqb-price-regular">${formatPrice(totalRegularPrice)}</span>`;

                let savingsHtml = '';
                if (hasSale && totalRegularPrice > 0) {
                    const savings = totalRegularPrice - totalSalePrice;
                    const savingsPercent = Math.round((savings / totalRegularPrice) * 100);
                    if (pluginSettings.showSavings !== false && pluginSettings.showDiscountAfterTitle !== false) {
                        savingsHtml = `<span class="wpqb-bundle-savings">${escapeHtml(i18n.savePrefix || 'Save')} ${formatPrice(savings)} (${savingsPercent}%)</span>`;
                    }
                }

                let perItemText = `${formatPrice(perItemPrice)}`;
                if (pluginSettings.showQtyAfterPerItem !== false) {
                    perItemText = `${perItemText} x ${qty}`;
                }

                const perItemCellHtml = pluginSettings.showPerItemPrice === false ? '' : `<td class="wpqb-col-per-item">${perItemText}</td>`;
                const cardPerItemHtml = pluginSettings.showPerItemPrice === false ? '' : `<div class="wpqb-card-per-item">${perItemText}</div>`;
                const cardMediaHtml = imageUrl
                    ? `<div class="wpqb-card-media"><img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(bundleName)}"></div>`
                    : `<div class="wpqb-card-media wpqb-card-media-empty" aria-hidden="true"></div>`;

                tableHtml += `<tr class="wpqb-bundle-option"
                            data-bundle-index="${escapeHtml(bundle.bundle_index)}"
                            data-bundle-name="${escapeHtml(bundle.bundle_name)}"
                            data-qty="${qty}"
                            data-price="${totalPrice}"
                            data-regular-price="${totalRegularPrice}"
                            data-sale-price="${totalSalePrice}"
                            data-image-id="${imageId}">
                            <td class="wpqb-col-name">
                                <span class="wpqb-bundle-name">${escapeHtml(bundleName)}</span>
                                ${savingsHtml ? '<br>' : ''}
                                ${savingsHtml}
                            </td>
                            ${perItemCellHtml}
                            <td class="wpqb-col-price wpqb-bundle-price">${priceHtml}</td>
                        </tr>`;

                cardHtml += `<div class="wpqb-bundle-card wpqb-bundle-option"
                            data-bundle-index="${escapeHtml(bundle.bundle_index)}"
                            data-bundle-name="${escapeHtml(bundle.bundle_name)}"
                            data-qty="${qty}"
                            data-price="${totalPrice}"
                            data-regular-price="${totalRegularPrice}"
                            data-sale-price="${totalSalePrice}"
                            data-image-id="${imageId}">
                            ${cardMediaHtml}
                            <div class="wpqb-card-content">
                                <div class="wpqb-card-title-row">
                                    <span class="wpqb-bundle-name">${escapeHtml(bundleName)}</span>
                                    ${savingsHtml}
                                </div>
                                ${cardPerItemHtml}
                                <div class="wpqb-card-total wpqb-bundle-price">${priceHtml}</div>
                            </div>
                        </div>`;
            });

            $bundleTableBody.html(tableHtml);
            $bundleCards.html(cardHtml);
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

        function applySettingsToMarkup() {
            const headings = pluginSettings.headings || {};

            if (headings.bundle) {
                $('.wpqb-bundles-table thead th').eq(0).text(headings.bundle);
            }

            if (pluginSettings.showPerItemPrice === false) {
                $('.wpqb-bundles-table').addClass('wpqb-hide-per-item');
            } else {
                if (headings.perItem) {
                    $('.wpqb-bundles-table thead th').eq(1).text(headings.perItem);
                }
            }

            if (headings.totalPrice) {
                const index = (pluginSettings.showPerItemPrice === false) ? 1 : 2;
                $('.wpqb-bundles-table thead th').eq(index).text(headings.totalPrice);
            }

            const designType = pluginSettings.designType || 'table';
            if (designType === 'cards') {
                $('.wpqb-bundles-table-wrap').addClass('wpqb-hidden');
                $('.wpqb-bundles-cards').removeClass('wpqb-hidden');
            } else {
                $('.wpqb-bundles-cards').addClass('wpqb-hidden');
                $('.wpqb-bundles-table-wrap').removeClass('wpqb-hidden');
            }
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
        applySettingsToMarkup();
        if (pluginSettings.selectionMode === 'auto') {
            selectBundleByQty(getCurrentCartQty());
        }
    });

})(jQuery);
