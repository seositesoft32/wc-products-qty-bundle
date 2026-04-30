/**
 * WC Products Qty Bundle - Frontend JavaScript
 */
(function ($) {
    'use strict';

    let selectedBundle = null;
    let suppressAutoSelect = false;
    const pluginSettings = window.wpqbPluginSettings || {};
    const i18n = pluginSettings.i18n || {};

    function isSettingEnabled(value, fallback) {
        if (typeof value === 'boolean') {
            return value;
        }

        if (value === '' || value === null || typeof value === 'undefined') {
            return false;
        }

        if (typeof value === 'number') {
            return value === 1;
        }

        if (typeof value === 'string') {
            const normalized = value.toLowerCase();
            if (['yes', 'true', '1', 'on'].indexOf(normalized) > -1) {
                return true;
            }

            if (['no', 'false', '0', 'off'].indexOf(normalized) > -1) {
                return false;
            }
        }

        return fallback;
    }

    const settingsState = {
        designType: (pluginSettings.designType === 'cards') ? 'cards' : 'table',
        showPerItemPrice: isSettingEnabled(pluginSettings.showPerItemPrice, true),
        showSavings: isSettingEnabled(pluginSettings.showSavings, true),
        showDiscountAfterTitle: isSettingEnabled(pluginSettings.showDiscountAfterTitle, true),
        showRegularPriceWhenSale: isSettingEnabled(pluginSettings.showRegularPriceWhenSale, true),
        showQtyAfterPerItem: isSettingEnabled(pluginSettings.showQtyAfterPerItem, true),
        showSelectedTotal: isSettingEnabled(pluginSettings.showSelectedTotal, true),
        requireBundleSelection: isSettingEnabled(pluginSettings.requireBundleSelection, false),
        enableBundleSorting: isSettingEnabled(pluginSettings.enableBundleSorting, true),
        autoSelectByQtyChange: isSettingEnabled(pluginSettings.autoSelectByQtyChange, true),
        selectionMode: (pluginSettings.selectionMode === 'manual') ? 'manual' : 'auto'
    };

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
                suppressAutoSelect = true;
                $qtyInput.val(selectedBundle.qty).trigger('change');
                suppressAutoSelect = false;
            }
        }

        function getCurrentCartQty() {
            const qty = parseInt($('form.cart input.qty').first().val(), 10);
            return Number.isFinite(qty) && qty > 0 ? qty : 0;
        }

        function sortBundleRowsByQty() {
            if (!settingsState.enableBundleSorting) {
                return;
            }

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

        function getSelectableBundleOptions() {
            const $allRows = $('.wpqb-bundle-option');
            if (!$allRows.length) {
                return $allRows;
            }

            // Prefer visible options so auto-select targets the active UI (table or cards).
            const $visibleRows = $allRows.filter(':visible');
            if ($visibleRows.length) {
                return $visibleRows;
            }

            if (settingsState.designType === 'table') {
                const $tableRows = $('.wpqb-bundles-table-wrap:not(.wpqb-hidden) .wpqb-bundle-option, .wpqb-bundles-table .wpqb-bundle-option');
                if ($tableRows.length) {
                    return $tableRows;
                }
            }

            const $cardRows = $('.wpqb-bundles-cards:not(.wpqb-hidden) .wpqb-bundle-option, .wpqb-bundles-cards .wpqb-bundle-option');
            if ($cardRows.length) {
                return $cardRows;
            }

            return $allRows;
        }

        function selectBundleByQty(qty) {
            const currentQty = parseInt(qty, 10) || 0;
            const $rows = getSelectableBundleOptions();

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
                    ? (settingsState.showRegularPriceWhenSale
                        ? `<del class="wpqb-price-cutoff">${formatPrice(totalRegularPrice)}</del><ins class="wpqb-price-sale">${formatPrice(totalSalePrice)}</ins>`
                        : `<span class="wpqb-price-sale">${formatPrice(totalSalePrice)}</span>`)
                    : `<span class="wpqb-price-regular">${formatPrice(totalRegularPrice)}</span>`;

                let savingsHtml = '';
                if (hasSale && totalRegularPrice > 0) {
                    const savings = totalRegularPrice - totalSalePrice;
                    const savingsPercent = Math.round((savings / totalRegularPrice) * 100);
                    if (settingsState.showSavings && settingsState.showDiscountAfterTitle) {
                        savingsHtml = `<span class="wpqb-bundle-savings">${escapeHtml(i18n.savePrefix || 'Save')} ${formatPrice(savings)} (${savingsPercent}%)</span>`;
                    }
                }

                let perItemText = `${formatPrice(perItemPrice)}`;
                if (settingsState.showQtyAfterPerItem) {
                    perItemText = `${perItemText} x ${qty}`;
                }

                const perItemCellHtml = settingsState.showPerItemPrice ? `<td class="wpqb-col-per-item">${perItemText}</td>` : '';
                const cardPerItemHtml = settingsState.showPerItemPrice ? `<div class="wpqb-card-per-item">${perItemText}</div>` : '';
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
            applyDynamicTableBodyStyles();
            sortBundleRowsByQty();
            if ($bundlePlaceholder.length) {
                $bundlePlaceholder.hide();
            }
        }

        function getBundleStyleVar(varName) {
            const host = $('.wpqb-bundles-frontend').first();
            if (!host.length || !window.getComputedStyle) {
                return '';
            }

            return window.getComputedStyle(host.get(0)).getPropertyValue(varName).trim();
        }

        function applyDynamicTableBodyStyles() {
            const bodyBg = getBundleStyleVar('--wpqb-table-body-bg');
            const bodyText = getBundleStyleVar('--wpqb-table-body-text');

            if ($bundleTableBody.length) {
                if (bodyBg) {
                    $bundleTableBody.find('td').css('background-color', bodyBg);
                }

                if (bodyText) {
                    $bundleTableBody.find('td, .wpqb-bundle-name').css('color', bodyText);
                }
            }
        }

        function resetTableRowInlineBackground($rows) {
            const bodyBg = getBundleStyleVar('--wpqb-table-body-bg');
            const $targetRows = $rows && $rows.length ? $rows : $('.wpqb-bundles-table .wpqb-bundle-option');

            $targetRows.filter('tr').find('td').each(function () {
                $(this).css('background-color', bodyBg || '');
            });
        }

        function applySelectedTableRowInlineBackground($row) {
            if (!$row || !$row.length || !$row.is('tr')) {
                return;
            }

            const selectedBg = getBundleStyleVar('--wpqb-table-selected-bg');
            $row.find('td').css('background-color', selectedBg || '');
        }

        if (isVariableProduct) {
            $variationForm.on('found_variation', function (event, variation) {
                selectedVariationId = variation && variation.variation_id ? parseInt(variation.variation_id, 10) : 0;

                const $variationPrice = $('.woocommerce-variation-price .price').first();
                variationPriceHTML = $variationPrice.length ? $variationPrice.html() : '';

                deselectBundle();
                renderVariationBundles(variation && variation.wpqb_bundles ? variation.wpqb_bundles : []);
                if (settingsState.selectionMode === 'auto' && settingsState.autoSelectByQtyChange) {
                    selectBundleByQty(getCurrentCartQty());
                }
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
            resetTableRowInlineBackground($('.wpqb-bundles-table .wpqb-bundle-option'));
            $('.wpqb-bundle-option').removeClass('selected');
            $bundle.addClass('selected');

            // For table rows, also apply selected bg inline to override any inline style set by applyDynamicTableBodyStyles
            applySelectedTableRowInlineBackground($bundle);

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
            resetTableRowInlineBackground($('.wpqb-bundles-table .wpqb-bundle-option.selected'));
            $('.wpqb-bundle-option').removeClass('selected');
            selectedBundle = null;

            updateSelectedBundleTotalDisplay();

            syncSelectedBundleField();
        }

        /**
         * Update selected bundle total summary below table
         */
        function updateSelectedBundleTotalDisplay() {
            if (!$selectedTotal.length || !settingsState.showSelectedTotal) {
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
            const $table = $('.wpqb-bundles-table');
            const bundleHeading = headings.bundle || 'Bundle';
            const perItemHeading = headings.perItem || 'Per Item';
            const totalHeading = headings.totalPrice || 'Total Price';

            $table.find('thead').html('<tr></tr>');
            const $headingRow = $table.find('thead tr').first();
            $headingRow.append(`<th>${escapeHtml(bundleHeading)}</th>`);

            if (settingsState.showPerItemPrice) {
                $headingRow.append(`<th>${escapeHtml(perItemHeading)}</th>`);
            }

            $headingRow.append(`<th>${escapeHtml(totalHeading)}</th>`);

            if (!settingsState.showDiscountAfterTitle) {
                $('.wpqb-bundle-savings').hide();
            } else {
                $('.wpqb-bundle-savings').show();
            }

            if (settingsState.designType === 'cards') {
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
        $('form.cart').on('submit', function (e) {
            if (settingsState.requireBundleSelection && $('.wpqb-bundles-frontend').length && !selectedBundle) {
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
            if (!suppressAutoSelect && settingsState.autoSelectByQtyChange) {
                selectBundleByQty(getCurrentCartQty());
            }

            updateSelectedBundleTotalDisplay();
        });

        sortBundleRowsByQty();
        applySettingsToMarkup();
        applyDynamicTableBodyStyles();
        if (settingsState.selectionMode === 'auto') {
            selectBundleByQty(getCurrentCartQty());
        }
    });

})(jQuery);
