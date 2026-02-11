/**
 * Boost Calculator - Frontend JavaScript (bs-calc)
 *
 * Handles live price/weight calculation on product pages.
 *
 * @package Bossier_Calculator_Builder
 * @since   3.2.0
 */

(function($) {
    'use strict';

    /**
     * BossierCalculator class
     */
    class BossierCalculator {
        /**
         * Constructor
         *
         * @param {jQuery} $wrapper Calculator wrapper element
         * @param {Object} config   Calculator configuration
         */
        constructor($wrapper, config) {
            this.$wrapper = $wrapper;
            this.config = config;
            this.calculatorId = config.id;
            this.fields = config.fields;
            this.settings = config.settings;
            this.debounceTimer = null;

            this.init();
        }

        /**
         * Initialize calculator
         */
        init() {
            this.bindEvents();
            this.initMitreGroupVisibility();
            this.initConditionalFields();
            this.calculate();
            this.syncQuantityToWC();
            this.moveAddToCartButton();
        }

        /**
         * Initialize mitre group visibility based on default selection
         */
        initMitreGroupVisibility() {
            const self = this;

            this.$wrapper.find('[data-field-type="mitre_angle"]').each(function() {
                const $field = $(this);
                const $firstGroup = $field.find('.bs-calc__mitre-group[data-group-index="0"]');

                if (!$firstGroup.length) return;

                // Check image dropdown selected option
                const $imageDropdown = $firstGroup.find('.bs-calc__image-dropdown');
                if ($imageDropdown.length) {
                    const $selectedOption = $imageDropdown.find('.bs-calc__image-dropdown-option.selected');
                    if ($selectedOption.length) {
                        const isNoMitre = $selectedOption.data('is-no-mitre') === 1 || $selectedOption.data('is-no-mitre') === '1';
                        if (isNoMitre) {
                            self.handleNoMitreSelection($firstGroup, true);
                        }
                    }
                }

                // Check regular select
                const $select = $firstGroup.find('.bs-calc__mitre-select');
                if ($select.length) {
                    const $selectedOption = $select.find('option:selected');
                    if ($selectedOption.length) {
                        const isNoMitre = $selectedOption.data('is-no-mitre') === 1 || $selectedOption.data('is-no-mitre') === '1';
                        if (isNoMitre) {
                            self.handleNoMitreSelection($firstGroup, true);
                        }
                    }
                }
            });
        }

        /**
         * Initialize conditional field visibility.
         * Fields with data-show-when-field are shown/hidden based on another field's value.
         */
        initConditionalFields() {
            this.evaluateConditionalFields();
        }

        /**
         * Evaluate all conditional fields and show/hide them.
         */
        evaluateConditionalFields() {
            const self = this;

            this.$wrapper.find('[data-show-when-field]').each(function() {
                const $field = $(this);
                const sourceFieldId = $field.data('show-when-field');
                const expectedValue = String($field.data('show-when-value'));

                // Find the source field and get its current value
                const currentValue = self.getFieldValue(sourceFieldId);

                if (String(currentValue) === expectedValue) {
                    $field.removeClass('bs-calc__field--hidden');
                } else {
                    $field.addClass('bs-calc__field--hidden');
                }
            });
        }

        /**
         * Get the current value of a field by its ID.
         *
         * @param {string} fieldId
         * @return {string}
         */
        getFieldValue(fieldId) {
            const $field = this.$wrapper.find('[data-field-id="' + fieldId + '"]');
            if (!$field.length) return '';

            // Toggle buttons: read from hidden input
            const $toggleValue = $field.find('.bs-calc__toggle-value');
            if ($toggleValue.length) return $toggleValue.val();

            // Select
            const $select = $field.find('.bs-calc__select');
            if ($select.length) return $select.val();

            // Checkbox group: return comma-separated checked values
            const $checked = $field.find('input[type="checkbox"]:checked');
            if ($checked.length) {
                const vals = [];
                $checked.each(function() { vals.push($(this).val()); });
                return vals.join(',');
            }

            // Number/text input
            const $input = $field.find('.bs-calc__input, .bs-calc__qty-val');
            if ($input.length) return $input.val();

            return '';
        }

        /**
         * Bind event handlers
         */
        bindEvents() {
            const self = this;

            // Number inputs (dimension, text)
            this.$wrapper.on('input change', '.bs-calc__input', function() {
                self.debounceCalculate();
            });

            // Select dropdowns
            this.$wrapper.on('change', '.bs-calc__select', function() {
                self.evaluateConditionalFields();
                self.calculate();
            });

            // Checkboxes
            this.$wrapper.on('change', 'input[type="checkbox"]', function() {
                // Toggle inline text input visibility for custom checkbox options
                const $textInput = $(this).closest('.bs-calc__checkbox-label').find('.bs-calc__option-text');
                if ($textInput.length) {
                    if ($(this).is(':checked')) {
                        $textInput.slideDown(150).prop('disabled', false);
                    } else {
                        $textInput.slideUp(150).prop('disabled', true).val('');
                    }
                }
                self.evaluateConditionalFields();
                self.calculate();
            });

            // Toggle buttons (color, radio custom, length fixed)
            this.bindToggleButtons();

            // Quantity buttons
            this.$wrapper.on('click', '.bs-calc__qty-btn', function() {
                const $btn = $(this);
                const $input = $btn.closest('.bs-calc__qty').find('.bs-calc__qty-val');
                const delta = $btn.data('action') === 'plus' ? 1 : -1;
                self.adjustQuantity($input, delta);
            });

            // Quantity input changes (manual typing or +/- button triggers)
            this.$wrapper.on('input change', '.bs-calc__qty-val', function() {
                self.syncQuantityToWC();
                self.debounceCalculate();
            });

            // Dimension field validation
            this.bindDimensionInputs();

            // Custom image dropdowns
            this.bindImageDropdowns();

            // Mitre image hover preview
            this.bindMitreHoverPreview();
        }

        /**
         * Bind toggle button behavior.
         * Toggle buttons replace old swatch radio inputs.
         * Clicking a toggle sets it active and updates the hidden input.
         */
        bindToggleButtons() {
            const self = this;

            this.$wrapper.on('click', '.bs-calc__toggle', function() {
                const $btn = $(this);
                const $group = $btn.closest('.bs-calc__toggles');
                const value = $btn.data('value');

                // Remove active from siblings, add to clicked
                $group.find('.bs-calc__toggle').removeClass('bs-calc__toggle--active');
                $btn.addClass('bs-calc__toggle--active');

                // Update hidden input
                const $hidden = $group.siblings('.bs-calc__toggle-value');
                if ($hidden.length) {
                    $hidden.val(value);
                }

                self.evaluateConditionalFields();
                self.calculate();
            });
        }

        /**
         * Bind custom image dropdown behavior
         */
        bindImageDropdowns() {
            const self = this;

            // Toggle dropdown open/close
            this.$wrapper.on('click', '.bs-calc__image-dropdown-selected', function(e) {
                e.stopPropagation();
                const $dropdown = $(this).closest('.bs-calc__image-dropdown');
                const wasOpen = $dropdown.hasClass('open');

                // Close all dropdowns
                self.$wrapper.find('.bs-calc__image-dropdown').removeClass('open');

                if (!wasOpen) {
                    $dropdown.addClass('open');
                }
            });

            // Select option
            this.$wrapper.on('click', '.bs-calc__image-dropdown-option', function() {
                const $option = $(this);
                const $dropdown = $option.closest('.bs-calc__image-dropdown');
                const value = $option.data('value');
                const $image = $option.find('.bs-calc__dropdown-option-image');
                const label = $option.find('.bs-calc__dropdown-option-label').text();

                // Update hidden input
                $dropdown.find('.bs-calc__image-dropdown-value').val(value);

                // Update selected display
                let displayHtml = '';
                if ($image.length) {
                    displayHtml += '<img src="' + $image.attr('src') + '" alt="" class="bs-calc__mitre-thumb">';
                }
                displayHtml += label;
                $dropdown.find('.bs-calc__image-dropdown-text').html(displayHtml);

                // Mark as selected
                $dropdown.find('.bs-calc__image-dropdown-option').removeClass('selected');
                $option.addClass('selected');

                // Close dropdown
                $dropdown.removeClass('open');

                // Handle "geen verstekhoek" logic - only for first group (index 0)
                const $group = $dropdown.closest('.bs-calc__mitre-group');
                if ($group.data('group-index') === 0) {
                    const isNoMitre = $option.data('is-no-mitre') === 1 || $option.data('is-no-mitre') === '1';
                    self.handleNoMitreSelection($group, isNoMitre);
                }

                self.calculate();
            });

            // Handle regular mitre select change
            this.$wrapper.on('change', '.bs-calc__mitre-select', function() {
                const $select = $(this);
                const $group = $select.closest('.bs-calc__mitre-group');

                if ($group.data('group-index') === 0) {
                    const $selectedOption = $select.find('option:selected');
                    const isNoMitre = $selectedOption.data('is-no-mitre') === 1 || $selectedOption.data('is-no-mitre') === '1';
                    self.handleNoMitreSelection($group, isNoMitre);
                }

                self.calculate();
            });

            // Close dropdown when clicking outside
            $(document).on('click', function() {
                self.$wrapper.find('.bs-calc__image-dropdown').removeClass('open');
            });
        }

        /**
         * Bind floating hover preview for mitre angle images.
         */
        bindMitreHoverPreview() {
            if (!$('#bossier-mitre-preview').length) {
                $('body').append('<div id="bossier-mitre-preview" class="bs-calc__mitre-preview"><img src="" alt=""></div>');
            }

            var $preview = $('#bossier-mitre-preview');
            var $previewImg = $preview.find('img');
            var hideTimer = null;
            var previewSize = 424;

            function positionPreview(clientX, clientY) {
                if (window.innerWidth <= 600) {
                    var imgSize = window.innerWidth * 0.7;
                    if (imgSize > 400) imgSize = 400;
                    var totalSize = imgSize + 24;
                    var x = (window.innerWidth - totalSize) / 2;
                    var y = (window.innerHeight - totalSize) / 2;
                    $preview.css({ left: x + 'px', top: y + 'px' });
                } else {
                    var x = clientX + 20;
                    var y = clientY - (previewSize / 2);

                    if (x + previewSize > window.innerWidth) {
                        x = clientX - previewSize - 12;
                    }
                    if (y < 8) y = 8;
                    if (y + previewSize > window.innerHeight) {
                        y = window.innerHeight - previewSize - 8;
                    }

                    $preview.css({ left: x + 'px', top: y + 'px' });
                }
            }

            $(document).on('mouseenter', '.bs-calc__mitre-thumb', function(e) {
                var src = $(this).attr('src');
                if (!src) return;
                clearTimeout(hideTimer);
                $previewImg.attr('src', src);
                positionPreview(e.clientX, e.clientY);
                $preview.addClass('visible');
            });

            $(document).on('mousemove', '.bs-calc__mitre-thumb', function(e) {
                positionPreview(e.clientX, e.clientY);
            });

            $(document).on('mouseleave', '.bs-calc__mitre-thumb', function() {
                hideTimer = setTimeout(function() {
                    $preview.removeClass('visible');
                }, 100);
            });

            $(document).on('touchstart', '.bs-calc__mitre-thumb', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var src = $(this).attr('src');
                if (!src) return;

                if ($preview.hasClass('visible') && $previewImg.attr('src') === src) {
                    $preview.removeClass('visible');
                } else {
                    $previewImg.attr('src', src);
                    positionPreview(0, 0);
                    $preview.addClass('visible');
                }
            });

            $(document).on('touchstart', function(e) {
                if (!$(e.target).hasClass('bs-calc__mitre-thumb') && $preview.hasClass('visible')) {
                    $preview.removeClass('visible');
                }
            });
        }

        /**
         * Bind dimension input validation (blur correction)
         */
        bindDimensionInputs() {
            const self = this;

            this.$wrapper.find('.bs-calc__dimension').each(function() {
                const $input = $(this);
                const $field = $input.closest('.bs-calc__field');
                let $errorMsg = $field.find('.bs-calc__dimension-error');

                $input.on('blur', function() {
                    let value = parseInt($(this).val()) || 0;
                    const min = parseInt($(this).attr('min')) || 0;
                    const max = parseInt($(this).attr('max')) || 5000;

                    if (value > 0) {
                        if (value > max) {
                            $(this).val(max);
                            self.showDimensionError($input, $errorMsg,
                                `Waarde gecorrigeerd naar maximum: ${max} mm`);
                        } else if (value < min) {
                            $(this).val(min);
                            self.showDimensionError($input, $errorMsg,
                                `Waarde gecorrigeerd naar minimum: ${min} mm`);
                        } else {
                            self.clearDimensionError($input, $errorMsg);
                        }
                    } else if ($(this).val() === '' || value === 0) {
                        $(this).val(min);
                        self.showDimensionError($input, $errorMsg,
                            `Waarde gecorrigeerd naar minimum: ${min} mm`);
                    }
                    self.debounceCalculate();
                });
            });
        }

        showDimensionError($input, $errorMsg, message) {
            $input.css('border-color', '#DC2626');
            $errorMsg.text(message).addClass('visible');
            setTimeout(() => {
                this.clearDimensionError($input, $errorMsg);
            }, 3000);
        }

        clearDimensionError($input, $errorMsg) {
            $input.css('border-color', '');
            $errorMsg.removeClass('visible');
        }

        /**
         * Sync calculator quantity to WooCommerce's native quantity input.
         * WC's product page has input[name="quantity"] that determines cart quantity.
         */
        syncQuantityToWC() {
            const $qtyVal = this.$wrapper.find('.bs-calc__qty-val');
            if (!$qtyVal.length) return;

            const qty = parseInt($qtyVal.val()) || 1;

            // Find WooCommerce's native quantity input on the product page
            const $wcQty = this.$wrapper.closest('form.cart').find('input[name="quantity"]');
            if ($wcQty.length) {
                $wcQty.val(qty);
            }
        }

        /**
         * Adjust quantity value
         */
        adjustQuantity($input, delta) {
            const min = parseInt($input.attr('min')) || 1;
            const max = parseInt($input.attr('max')) || 100;
            const step = parseInt($input.attr('step')) || 1;
            let value = parseInt($input.val()) || min;

            value += delta * step;
            if (value < min) value = min;
            if (value > max) value = max;

            $input.val(value).trigger('change');
        }

        /**
         * Debounced calculate
         */
        debounceCalculate() {
            const self = this;
            if (this.debounceTimer) clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(function() {
                self.calculate();
            }, 300);
        }

        /**
         * Check if a field is currently visible (not hidden by show_when)
         *
         * @param {string} fieldId
         * @return {boolean}
         */
        isFieldVisible(fieldId) {
            const $field = this.$wrapper.find('[data-field-id="' + fieldId + '"]');
            return $field.length && !$field.hasClass('bs-calc__field--hidden');
        }

        /**
         * Collect current field selections (skip hidden fields)
         *
         * @return {Object} Field selections
         */
        collectSelections() {
            const selections = {};

            for (const fieldId in this.fields) {
                const field = this.fields[fieldId];

                // Skip hidden fields (show_when condition not met)
                if (!this.isFieldVisible(fieldId)) continue;

                const $field = this.$wrapper.find('[data-field-id="' + fieldId + '"]');
                if (!$field.length) continue;

                let value = null;

                switch (field.type) {
                    case 'length':
                        if (field.length_mode === 'fixed') {
                            // Toggle buttons or select
                            const $toggleVal = $field.find('.bs-calc__toggle-value');
                            if ($toggleVal.length) {
                                value = $toggleVal.val();
                            } else {
                                value = $field.find('.bs-calc__select').val();
                            }
                        } else {
                            value = $field.find('.bs-calc__input').val();
                        }
                        break;

                    case 'dimension':
                        value = $field.find('.bs-calc__dimension').val();
                        break;

                    case 'text':
                        value = $field.find('.bs-calc__text').val();
                        break;

                    case 'color': {
                        // Toggle buttons or select
                        const $colorToggle = $field.find('.bs-calc__toggle-value');
                        if ($colorToggle.length) {
                            value = $colorToggle.val();
                        } else {
                            value = $field.find('.bs-calc__select').val();
                        }
                        break;
                    }

                    case 'mitre_angle': {
                        const $mitreGroups = $field.find('.bs-calc__mitre-group');
                        if ($mitreGroups.length > 0) {
                            value = {};
                            $mitreGroups.each(function() {
                                const $group = $(this);
                                const groupId = $group.data('group-id');
                                const $input = $group.find('.bs-calc__image-dropdown-value, .bs-calc__mitre-select');
                                if ($input.length && groupId) {
                                    value[groupId] = $input.val();
                                }
                            });
                        }
                        break;
                    }

                    case 'quantity':
                        value = $field.find('.bs-calc__qty-val').val();
                        break;

                    case 'custom': {
                        if (field.input_type === 'checkbox') {
                            value = [];
                            $field.find('input[type="checkbox"]:checked').each(function() {
                                value.push($(this).val());
                            });
                        } else {
                            // Toggle buttons or select
                            const $customToggle = $field.find('.bs-calc__toggle-value');
                            if ($customToggle.length) {
                                value = $customToggle.val();
                            } else {
                                value = $field.find('.bs-calc__select').val();
                            }
                        }
                        break;
                    }
                }

                if (value !== null && value !== '' && !(Array.isArray(value) && value.length === 0)) {
                    selections[fieldId] = value;
                }
            }

            return selections;
        }

        /**
         * Handle "geen verstekhoek" selection
         */
        handleNoMitreSelection($firstGroup, isNoMitre) {
            const $field = $firstGroup.closest('[data-field-id]');
            const $allGroups = $field.find('.bs-calc__mitre-group');

            $allGroups.each(function() {
                const $group = $(this);
                const groupIndex = $group.data('group-index');

                if (groupIndex === 0) return;

                if (isNoMitre) {
                    $group.addClass('bs-calc__mitre-group--hidden');

                    // Reset image dropdown to first option
                    const $imageDropdown = $group.find('.bs-calc__image-dropdown');
                    if ($imageDropdown.length) {
                        const $firstOption = $imageDropdown.find('.bs-calc__image-dropdown-option').first();
                        const firstValue = $firstOption.data('value');
                        const $firstImage = $firstOption.find('.bs-calc__dropdown-option-image');
                        const firstLabel = $firstOption.find('.bs-calc__dropdown-option-label').text();

                        $imageDropdown.find('.bs-calc__image-dropdown-value').val(firstValue);

                        let displayHtml = '';
                        if ($firstImage.length) {
                            displayHtml += '<img src="' + $firstImage.attr('src') + '" alt="" class="bs-calc__mitre-thumb">';
                        }
                        displayHtml += firstLabel;
                        $imageDropdown.find('.bs-calc__image-dropdown-text').html(displayHtml);

                        $imageDropdown.find('.bs-calc__image-dropdown-option').removeClass('selected');
                        $firstOption.addClass('selected');
                    }

                    // Reset regular select
                    const $select = $group.find('.bs-calc__mitre-select');
                    if ($select.length) {
                        $select.prop('selectedIndex', 0);
                    }
                } else {
                    $group.removeClass('bs-calc__mitre-group--hidden');
                }
            });
        }

        /**
         * Move WooCommerce's add-to-cart button inside .bs-calc__actions
         * so it appears next to the quantity field.
         */
        moveAddToCartButton() {
            const $form = this.$wrapper.closest('form.cart');
            if (!$form.length) return;

            const $addBtn = $form.find('.single_add_to_cart_button');
            const $actions = this.$wrapper.find('.bs-calc__actions');

            if ($addBtn.length && $actions.length) {
                $addBtn.addClass('bs-calc__add');
                $actions.append($addBtn);
            }
        }

        /**
         * Calculate price and weight
         */
        calculate() {
            const selections = this.collectSelections();
            const localResult = this.calculateLocal(selections);
            this.updateDisplay(localResult);
            this.updateHiddenFields(localResult);
        }

        /**
         * Calculate price and weight locally (JavaScript)
         *
         * @param {Object} selections Field selections
         * @return {Object} Calculation result
         */
        calculateLocal(selections) {
            const productBasePrice = parseFloat(this.config.productPrice)
                || parseFloat(window.bossierCalculator?.productPrice)
                || 0;
            const additionalBaseWeight = parseFloat(this.settings.base_weight) || 0;

            let dimensionPriceExtra = 0;
            let dimensionWeight = 0;
            let quantityMultiplier = 1;
            let mitreSurcharge = 0;
            let mitreWeight = 0;
            let customSurcharge = 0;
            let customWeight = 0;
            let colorSurcharge = 0;
            let colorPriceType = 'fixed';
            let isDefaultColor = true;

            for (const fieldId in this.fields) {
                const field = this.fields[fieldId];

                // Skip hidden fields
                if (!this.isFieldVisible(fieldId)) continue;

                if (!selections.hasOwnProperty(fieldId)) continue;

                const value = selections[fieldId];

                switch (field.type) {
                    case 'dimension': {
                        let dimValue = parseFloat(value) || 0;
                        const dimMin = parseFloat(field.min_value) || 0;
                        const dimMax = parseFloat(field.max_value) || 5000;
                        const dimPricePerMm = parseFloat(field.price_per_mm) || 0;
                        const dimThreshold = parseFloat(field.threshold) || 0;
                        const dimWeightPerMm = parseFloat(field.weight_per_mm) || 0;

                        if (dimValue < dimMin) dimValue = dimMin;
                        if (dimValue > dimMax) dimValue = dimMax;

                        if (dimPricePerMm > 0) {
                            const extraAboveThreshold = Math.max(0, dimValue - dimThreshold);
                            dimensionPriceExtra += extraAboveThreshold * dimPricePerMm;
                        }
                        if (dimWeightPerMm > 0) {
                            dimensionWeight += dimValue * dimWeightPerMm;
                        }
                        break;
                    }

                    case 'length':
                    case 'text':
                        break;

                    case 'quantity':
                        quantityMultiplier = Math.max(1, parseInt(value) || 1);
                        break;

                    case 'mitre_angle':
                        if (field.mitre_groups && typeof value === 'object' && value !== null) {
                            field.mitre_groups.forEach(group => {
                                const groupId = group.id;
                                if (value.hasOwnProperty(groupId)) {
                                    const angleIdx = value[groupId];
                                    if (group.angles && group.angles[angleIdx]) {
                                        const angle = group.angles[angleIdx];
                                        mitreSurcharge += parseFloat(angle.surcharge) || 0;
                                        mitreWeight += parseFloat(angle.extra_weight) || 0;
                                    }
                                }
                            });
                        } else if (field.angles && field.angles[value]) {
                            const angle = field.angles[value];
                            mitreSurcharge += parseFloat(angle.surcharge) || 0;
                            mitreWeight += parseFloat(angle.extra_weight) || 0;
                        }
                        break;

                    case 'color':
                        if (field.colors && field.colors[value]) {
                            const color = field.colors[value];
                            isDefaultColor = color.is_default === true || color.is_default === '1' || color.is_default === 1;
                            if (!isDefaultColor) {
                                colorPriceType = color.price_type || 'fixed';
                                colorSurcharge = parseFloat(color.surcharge) || 0;
                            }
                        }
                        break;

                    case 'custom':
                        if (Array.isArray(value)) {
                            value.forEach(idx => {
                                if (field.custom_options && field.custom_options[idx]) {
                                    const option = field.custom_options[idx];
                                    customSurcharge += parseFloat(option.surcharge) || 0;
                                    customWeight += parseFloat(option.extra_weight) || 0;
                                }
                            });
                        } else if (field.custom_options && field.custom_options[value]) {
                            const option = field.custom_options[value];
                            customSurcharge += parseFloat(option.surcharge) || 0;
                            customWeight += parseFloat(option.extra_weight) || 0;
                        }
                        break;
                }
            }

            const grayPrice = productBasePrice + dimensionPriceExtra;

            let colorAmount = 0;
            if (!isDefaultColor) {
                if (colorPriceType === 'percentage') {
                    colorAmount = grayPrice * (colorSurcharge / 100);
                } else {
                    colorAmount = colorSurcharge;
                }
            }

            let weight = dimensionWeight + mitreWeight + customWeight + additionalBaseWeight;
            let price = grayPrice + mitreSurcharge + colorAmount + customSurcharge;

            const priceDecimals = parseInt(this.settings.price_decimals) || 2;
            const weightDecimals = parseInt(this.settings.weight_decimals) || 3;

            price = this.round(price, priceDecimals);
            weight = this.round(weight, weightDecimals);

            return {
                price: price,
                weight: weight,
                quantityMultiplier: quantityMultiplier,
                totalPrice: this.round(price * quantityMultiplier, priceDecimals),
                totalWeight: this.round(weight * quantityMultiplier, weightDecimals),
                grayPrice: this.round(grayPrice, priceDecimals),
                colorSurcharge: this.round(colorAmount, priceDecimals)
            };
        }

        round(value, decimals) {
            return Math.round(value * Math.pow(10, decimals)) / Math.pow(10, decimals);
        }

        updateDisplay(result) {
            const $priceEl = this.$wrapper.find('#bossier-calc-price');
            const $weightEl = this.$wrapper.find('#bossier-calc-weight');

            if ($priceEl.length) {
                $priceEl.html(this.formatPrice(result.totalPrice));
            }
            if ($weightEl.length) {
                $weightEl.text(this.formatWeight(result.totalWeight));
            }
        }

        updateHiddenFields(result) {
            this.$wrapper.find('#bossier_calculated_price').val(result.price);
            this.$wrapper.find('#bossier_calculated_weight').val(result.weight);
        }

        formatPrice(price) {
            const currencySymbol = bossierCalculator.i18n.currency;
            const decimals = parseInt(this.settings.price_decimals) || 2;

            const formattedNumber = price.toLocaleString(undefined, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });

            return currencySymbol + ' ' + formattedNumber;
        }

        formatWeight(weight) {
            const weightUnit = bossierCalculator.i18n.weightUnit;
            const decimals = parseInt(this.settings.weight_decimals) || 3;

            const formattedNumber = weight.toLocaleString(undefined, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });

            return formattedNumber + ' ' + weightUnit;
        }

        calculateRemote(selections) {
            const self = this;

            $.ajax({
                url: bossierCalculator.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'bossier_calculate_price',
                    nonce: bossierCalculator.nonce,
                    calculator_id: this.calculatorId,
                    selections: selections
                },
                success: function(response) {
                    if (response.success) {
                        self.updateDisplay({
                            price: response.data.price,
                            weight: response.data.weight
                        });
                        self.updateHiddenFields(response.data);
                    }
                }
            });
        }
    }

    /**
     * Initialize calculators on page load
     */
    $(document).ready(function() {
        if (typeof bossierCalculator === 'undefined') return;

        const $wrapper = $('.bs-calc');
        if (!$wrapper.length) return;

        const calculator = new BossierCalculator($wrapper, bossierCalculator.config);
        $wrapper.data('bossierCalculator', calculator);
    });

})(jQuery);
