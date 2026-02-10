/**
 * Boost Calculator - Frontend JavaScript
 *
 * Handles live price/weight calculation on product pages.
 *
 * @package Bossier_Calculator_Builder
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
            this.calculate();
        }

        /**
         * Initialize mitre group visibility based on default selection
         * If first group has "geen verstekhoek" selected by default, hide other groups
         */
        initMitreGroupVisibility() {
            const self = this;

            // Find all mitre fields
            this.$wrapper.find('[data-field-type="mitre_angle"]').each(function() {
                const $field = $(this);
                const $firstGroup = $field.find('.bossier-calc-mitre-group[data-group-index="0"]');

                if (!$firstGroup.length) {
                    return;
                }

                // Check image dropdown selected option
                const $imageDropdown = $firstGroup.find('.bossier-calc-image-dropdown');
                if ($imageDropdown.length) {
                    const $selectedOption = $imageDropdown.find('.bossier-calc-image-dropdown-option.selected');
                    if ($selectedOption.length) {
                        const isNoMitre = $selectedOption.data('is-no-mitre') === 1 || $selectedOption.data('is-no-mitre') === '1';
                        if (isNoMitre) {
                            self.handleNoMitreSelection($firstGroup, true);
                        }
                    }
                }

                // Check regular select
                const $select = $firstGroup.find('.bossier-calc-mitre-select');
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
         * Bind event handlers
         */
        bindEvents() {
            const self = this;

            // Number inputs
            this.$wrapper.on('input change', 'input[type="number"]', function() {
                self.debounceCalculate();
            });

            // Select dropdowns
            this.$wrapper.on('change', 'select', function() {
                self.calculate();
            });

            // Radio buttons
            this.$wrapper.on('change', 'input[type="radio"]', function() {
                self.calculate();
            });

            // Checkboxes
            this.$wrapper.on('change', 'input[type="checkbox"]', function() {
                self.calculate();
            });

            // Color swatches - add selection class
            this.$wrapper.on('change', '.bossier-calc-swatch input', function() {
                const $swatch = $(this).closest('.bossier-calc-swatch');
                $swatch.siblings().removeClass('selected');
                $swatch.addClass('selected');
                self.calculate();
            });

            // Quantity buttons
            this.$wrapper.on('click', '.bossier-calc-qty-minus', function() {
                self.adjustQuantity($(this).siblings('.bossier-calc-qty'), -1);
            });

            this.$wrapper.on('click', '.bossier-calc-qty-plus', function() {
                self.adjustQuantity($(this).siblings('.bossier-calc-qty'), 1);
            });

            // Length slider synchronization
            this.bindLengthSlider();

            // Custom image dropdowns
            this.bindImageDropdowns();

            // Mitre image hover preview
            this.bindMitreHoverPreview();
        }

        /**
         * Bind custom image dropdown behavior
         */
        bindImageDropdowns() {
            const self = this;

            // Toggle dropdown open/close
            this.$wrapper.on('click', '.bossier-calc-image-dropdown-selected', function(e) {
                e.stopPropagation();
                const $dropdown = $(this).closest('.bossier-calc-image-dropdown');
                const wasOpen = $dropdown.hasClass('open');

                // Close all dropdowns
                self.$wrapper.find('.bossier-calc-image-dropdown').removeClass('open');

                // Toggle this dropdown
                if (!wasOpen) {
                    $dropdown.addClass('open');
                }
            });

            // Select option
            this.$wrapper.on('click', '.bossier-calc-image-dropdown-option', function() {
                const $option = $(this);
                const $dropdown = $option.closest('.bossier-calc-image-dropdown');
                const value = $option.data('value');
                const $image = $option.find('.bossier-calc-dropdown-option-image');
                const label = $option.find('.bossier-calc-dropdown-option-label').text();

                // Update hidden input
                $dropdown.find('.bossier-calc-image-dropdown-value').val(value);

                // Update selected display
                let displayHtml = '';
                if ($image.length) {
                    displayHtml += '<img src="' + $image.attr('src') + '" alt="">';
                }
                displayHtml += label;
                $dropdown.find('.bossier-calc-image-dropdown-text').html(displayHtml);

                // Mark as selected
                $dropdown.find('.bossier-calc-image-dropdown-option').removeClass('selected');
                $option.addClass('selected');

                // Close dropdown
                $dropdown.removeClass('open');

                // Handle "geen verstekhoek" logic - only for first group (index 0)
                const $group = $dropdown.closest('.bossier-calc-mitre-group');
                if ($group.data('group-index') === 0) {
                    const isNoMitre = $option.data('is-no-mitre') === 1 || $option.data('is-no-mitre') === '1';
                    self.handleNoMitreSelection($group, isNoMitre);
                }

                // Trigger calculation
                self.calculate();
            });

            // Handle regular mitre select change
            this.$wrapper.on('change', '.bossier-calc-mitre-select', function() {
                const $select = $(this);
                const $group = $select.closest('.bossier-calc-mitre-group');

                // Only apply "geen verstekhoek" logic for first group (index 0)
                if ($group.data('group-index') === 0) {
                    const $selectedOption = $select.find('option:selected');
                    const isNoMitre = $selectedOption.data('is-no-mitre') === 1 || $selectedOption.data('is-no-mitre') === '1';
                    self.handleNoMitreSelection($group, isNoMitre);
                }

                // Trigger calculation
                self.calculate();
            });

            // Close dropdown when clicking outside
            $(document).on('click', function() {
                self.$wrapper.find('.bossier-calc-image-dropdown').removeClass('open');
            });
        }

        /**
         * Bind floating hover preview for mitre angle images.
         * Desktop: shows large preview near cursor on hover.
         * Mobile: tap on thumbnail opens preview, tap anywhere else closes it.
         *         Tapping the option text/label selects the option normally.
         */
        bindMitreHoverPreview() {
            // Create single shared preview element
            if (!$('#bossier-mitre-preview').length) {
                $('body').append('<div id="bossier-mitre-preview" class="bossier-calc-mitre-preview"><img src="" alt=""></div>');
            }

            var $preview = $('#bossier-mitre-preview');
            var $previewImg = $preview.find('img');
            var hideTimer = null;
            var isTouchDevice = ('ontouchstart' in window) || navigator.maxTouchPoints > 0;
            var previewSize = 424; // 400 + padding

            function positionPreview(clientX, clientY) {
                if (window.innerWidth <= 600) {
                    // Mobile: center on screen
                    var imgSize = window.innerWidth * 0.7;
                    if (imgSize > 400) imgSize = 400;
                    var totalSize = imgSize + 24; // padding
                    var x = (window.innerWidth - totalSize) / 2;
                    var y = (window.innerHeight - totalSize) / 2;
                    $preview.css({ left: x + 'px', top: y + 'px' });
                } else {
                    // Desktop: near cursor
                    var x = clientX + 20;
                    var y = clientY - (previewSize / 2);

                    if (x + previewSize > window.innerWidth) {
                        x = clientX - previewSize - 12;
                    }
                    if (y < 8) {
                        y = 8;
                    }
                    if (y + previewSize > window.innerHeight) {
                        y = window.innerHeight - previewSize - 8;
                    }

                    $preview.css({ left: x + 'px', top: y + 'px' });
                }
            }

            // Desktop: hover events
            if (!isTouchDevice) {
                $(document).on('mouseenter', '.bossier-calc-mitre-thumb', function(e) {
                    var src = $(this).attr('src');
                    if (!src) return;

                    clearTimeout(hideTimer);
                    $previewImg.attr('src', src);
                    positionPreview(e.clientX, e.clientY);
                    $preview.addClass('visible');
                });

                $(document).on('mousemove', '.bossier-calc-mitre-thumb', function(e) {
                    positionPreview(e.clientX, e.clientY);
                });

                $(document).on('mouseleave', '.bossier-calc-mitre-thumb', function() {
                    hideTimer = setTimeout(function() {
                        $preview.removeClass('visible');
                    }, 100);
                });
            }

            // Mobile: tap on thumbnail image to show preview,
            // tap anywhere else to close. Option selection works normally
            // because we only intercept taps on the <img> itself.
            if (isTouchDevice) {
                $(document).on('touchstart', '.bossier-calc-mitre-thumb', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var src = $(this).attr('src');
                    if (!src) return;

                    if ($preview.hasClass('visible') && $previewImg.attr('src') === src) {
                        // Same image tapped again — close
                        $preview.removeClass('visible');
                    } else {
                        $previewImg.attr('src', src);
                        positionPreview(0, 0);
                        $preview.addClass('visible');
                    }
                });

                // Tap anywhere outside closes the preview
                $(document).on('touchstart', function(e) {
                    if (!$(e.target).hasClass('bossier-calc-mitre-thumb') && $preview.hasClass('visible')) {
                        $preview.removeClass('visible');
                    }
                });
            }
        }

        /**
         * Bind length input validation
         */
        bindLengthSlider() {
            const self = this;
            const $lengthInput = this.$wrapper.find('#bossier_calc_length');

            if (!$lengthInput.length) {
                return;
            }

            // Create error message element if not exists
            let $errorMsg = this.$wrapper.find('.bossier-calc-length-error');
            if (!$errorMsg.length) {
                $errorMsg = $('<div class="bossier-calc-length-error" style="color: #d63638; font-size: 13px; margin-top: 5px; display: none;"></div>');
                $lengthInput.closest('.bossier-calc-field').append($errorMsg);
            }

            // On input, just trigger calculation - don't correct value while typing
            $lengthInput.on('input change', function() {
                // Allow user to freely type, only trigger debounced calculation
                self.debounceCalculate();
            });

            // Validate and correct value only on blur (when field loses focus)
            $lengthInput.on('blur', function() {
                let value = parseInt($(this).val()) || 0;
                const min = parseInt($(this).attr('min')) || 0;
                const max = parseInt($(this).attr('max')) || 5000;

                // Only validate if there's a value
                if (value > 0) {
                    if (value > max) {
                        $(this).val(max);
                        self.showLengthError($lengthInput, $errorMsg,
                            `Waarde gecorrigeerd naar maximum: ${max} mm`);
                    } else if (value < min) {
                        $(this).val(min);
                        self.showLengthError($lengthInput, $errorMsg,
                            `Waarde gecorrigeerd naar minimum: ${min} mm`);
                    } else {
                        self.clearLengthError($lengthInput, $errorMsg);
                    }
                } else if ($(this).val() === '' || value === 0) {
                    // If empty or 0, set to minimum
                    $(this).val(min);
                    self.showLengthError($lengthInput, $errorMsg,
                        `Waarde gecorrigeerd naar minimum: ${min} mm`);
                }
                self.debounceCalculate();
            });
        }

        /**
         * Show length validation error
         */
        showLengthError($input, $errorMsg, message) {
            $input.css('border-color', '#d63638');
            $errorMsg.text(message).show();

            // Auto-hide after 3 seconds
            setTimeout(() => {
                this.clearLengthError($input, $errorMsg);
            }, 3000);
        }

        /**
         * Clear length validation error
         */
        clearLengthError($input, $errorMsg) {
            $input.css('border-color', '');
            $errorMsg.hide();
        }

        /**
         * Adjust quantity value
         *
         * @param {jQuery} $input Quantity input
         * @param {number} delta  Change amount (+1 or -1)
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
         * Debounced calculate (for number inputs)
         */
        debounceCalculate() {
            const self = this;

            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }

            this.debounceTimer = setTimeout(function() {
                self.calculate();
            }, 300);
        }

        /**
         * Collect current field selections
         *
         * @return {Object} Field selections
         */
        collectSelections() {
            const selections = {};

            // Get core length field value (always rendered automatically)
            const $coreLengthInput = this.$wrapper.find('#bossier_calc_length');
            if ($coreLengthInput.length) {
                selections['_core_length'] = $coreLengthInput.val();
            }

            // Iterate through configured fields
            for (const fieldId in this.fields) {
                const field = this.fields[fieldId];
                const $field = this.$wrapper.find('[data-field-id="' + fieldId + '"]');

                if (!$field.length) continue;

                const fieldName = 'bossier_calc_' + fieldId;
                let value = null;

                switch (field.type) {
                    case 'length':
                        if (field.length_mode === 'fixed') {
                            // Fixed options - get selected radio or dropdown
                            const $selected = $field.find('input:checked, select');
                            value = $selected.val();
                        } else {
                            // Free input
                            value = $field.find('input[type="number"]').val();
                        }
                        break;

                    case 'color':
                        // Radio or dropdown
                        const $colorSelected = $field.find('input:checked, select');
                        value = $colorSelected.val();
                        break;

                    case 'mitre_angle':
                        // Collect values from all mitre groups
                        const $mitreGroups = $field.find('.bossier-calc-mitre-group');
                        if ($mitreGroups.length > 0) {
                            value = {};
                            $mitreGroups.each(function() {
                                const $group = $(this);
                                const groupId = $group.data('group-id');
                                // For image dropdowns: hidden input with class bossier-calc-image-dropdown-value
                                // For regular dropdowns: select with class bossier-calc-mitre-select
                                const $input = $group.find('.bossier-calc-image-dropdown-value, .bossier-calc-mitre-select');
                                if ($input.length && groupId) {
                                    value[groupId] = $input.val();
                                }
                            });
                        } else {
                            // Legacy fallback: single input/select
                            const $mitreSelected = $field.find('input:checked, select');
                            value = $mitreSelected.val();
                        }
                        break;

                    case 'quantity':
                        value = $field.find('input[type="number"]').val();
                        break;

                    case 'custom':
                        if (field.input_type === 'checkbox') {
                            // Multiple selections
                            value = [];
                            $field.find('input:checked').each(function() {
                                value.push($(this).val());
                            });
                        } else {
                            const $customSelected = $field.find('input:checked, select');
                            value = $customSelected.val();
                        }
                        break;
                }

                if (value !== null && value !== '' && !(Array.isArray(value) && value.length === 0)) {
                    selections[fieldId] = value;
                }
            }

            return selections;
        }

        /**
         * Handle "geen verstekhoek" selection
         * When selected in first group, hide all other mitre groups and reset their values
         *
         * @param {jQuery} $firstGroup The first mitre group element
         * @param {boolean} isNoMitre Whether a "geen verstekhoek" option is selected
         */
        handleNoMitreSelection($firstGroup, isNoMitre) {
            const $field = $firstGroup.closest('[data-field-id]');
            const $allGroups = $field.find('.bossier-calc-mitre-group');

            // Find all groups except the first one (index > 0)
            $allGroups.each(function() {
                const $group = $(this);
                const groupIndex = $group.data('group-index');

                // Skip the first group
                if (groupIndex === 0) {
                    return;
                }

                if (isNoMitre) {
                    // Hide other groups and reset to default (index 0)
                    $group.addClass('bossier-calc-mitre-group-hidden');

                    // Reset image dropdown to first option
                    const $imageDropdown = $group.find('.bossier-calc-image-dropdown');
                    if ($imageDropdown.length) {
                        const $firstOption = $imageDropdown.find('.bossier-calc-image-dropdown-option').first();
                        const firstValue = $firstOption.data('value');
                        const $firstImage = $firstOption.find('.bossier-calc-dropdown-option-image');
                        const firstLabel = $firstOption.find('.bossier-calc-dropdown-option-label').text();

                        // Update hidden input
                        $imageDropdown.find('.bossier-calc-image-dropdown-value').val(firstValue);

                        // Update display
                        let displayHtml = '';
                        if ($firstImage.length) {
                            displayHtml += '<img src="' + $firstImage.attr('src') + '" alt="">';
                        }
                        displayHtml += firstLabel;
                        $imageDropdown.find('.bossier-calc-image-dropdown-text').html(displayHtml);

                        // Mark as selected
                        $imageDropdown.find('.bossier-calc-image-dropdown-option').removeClass('selected');
                        $firstOption.addClass('selected');
                    }

                    // Reset regular select to first option
                    const $select = $group.find('.bossier-calc-mitre-select');
                    if ($select.length) {
                        $select.prop('selectedIndex', 0);
                    }
                } else {
                    // Show other groups
                    $group.removeClass('bossier-calc-mitre-group-hidden');
                }
            });
        }

        /**
         * Calculate price and weight
         */
        calculate() {
            const self = this;
            const selections = this.collectSelections();

            // Calculate locally first for instant feedback
            const localResult = this.calculateLocal(selections);
            this.updateDisplay(localResult);
            this.updateHiddenFields(localResult);

            // Optional: Also send to server for validation
            // this.calculateRemote(selections);
        }

        /**
         * Calculate price and weight locally (JavaScript)
         *
         * New pricing formula:
         * 1. Product base price covers minimum length (default 1000mm) - gray color included
         * 2. Extra length = (selected_length - min_length) * price_per_mm
         * 3. Gray price = product_base + length_extra (basis for color percentage)
         * 4. Long length surcharge is NOT calculated here (hidden from customer, server-side only)
         * 5. Mitre surcharges (fixed amounts)
         * 6. Color surcharge = fixed € OR percentage of gray_price
         *
         * @param {Object} selections Field selections
         * @return {Object} Calculation result
         */
        calculateLocal(selections) {
            // Get configuration
            // Product base price comes from WooCommerce product - try multiple sources with fallbacks
            const productBasePrice = parseFloat(this.config.productPrice)
                || parseFloat(window.bossierCalculator?.productPrice)
                || 0;
            const minLengthInput = parseFloat(this.settings.min_length_input) || 100; // Minimum selectable length
            const minLength = parseFloat(this.settings.min_length) || 1000; // Price threshold (0-1000mm = fixed price)
            const maxLength = parseFloat(this.settings.max_length) || 5000;
            const pricePerMm = parseFloat(this.settings.price_per_mm) || 0;
            const baseWeightPerMm = parseFloat(this.settings.base_weight_per_mm) || 0;
            // Note: settings.base_price is NOT added separately - it's only used for initial display
            // The authoritative base price is productBasePrice from WooCommerce
            const additionalBaseWeight = parseFloat(this.settings.base_weight) || 0;

            // Initialize results
            let selectedLength = minLengthInput; // Default to minimum selectable length
            let quantityMultiplier = 1;
            let mitreSurcharge = 0;
            let mitreWeight = 0;
            let customSurcharge = 0;
            let customWeight = 0;
            let colorSurcharge = 0;
            let colorPriceType = 'fixed';
            let isDefaultColor = true;

            // Get core length from automatic length field
            if (selections.hasOwnProperty('_core_length')) {
                selectedLength = parseFloat(selections['_core_length']) || minLengthInput;
                // Clamp to min/max input range (not price threshold)
                if (selectedLength < minLengthInput) selectedLength = minLengthInput;
                if (selectedLength > maxLength) selectedLength = maxLength;
            }

            // First pass: collect length, quantity, mitre, and custom values
            for (const fieldId in this.fields) {
                const field = this.fields[fieldId];

                if (!selections.hasOwnProperty(fieldId)) continue;

                const value = selections[fieldId];

                switch (field.type) {
                    case 'length':
                        selectedLength = this.getLengthValue(field, value);
                        break;

                    case 'quantity':
                        quantityMultiplier = Math.max(1, parseInt(value) || 1);
                        break;

                    case 'mitre_angle':
                        // Handle new mitre_groups structure (multiple groups)
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
                        }
                        // Handle legacy single angles structure
                        else if (field.angles && field.angles[value]) {
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
                            // Multiple selections (checkboxes)
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

            // Calculate length extra (for lengths above price threshold)
            // Price threshold is minLength (1000mm) - all lengths below get the same base price
            const extraLength = Math.max(0, selectedLength - minLength);
            const lengthExtra = extraLength * pricePerMm;

            // Gray price = product base price + length extra (basis for color percentage)
            const grayPrice = productBasePrice + lengthExtra;

            // Calculate color surcharge
            let colorAmount = 0;
            if (!isDefaultColor) {
                if (colorPriceType === 'percentage') {
                    colorAmount = grayPrice * (colorSurcharge / 100);
                } else {
                    colorAmount = colorSurcharge;
                }
            }

            // Calculate weight - always based on actual selected length
            let weight = (selectedLength * baseWeightPerMm) + mitreWeight + customWeight + additionalBaseWeight;

            // Calculate final price (no long surcharge - it's hidden and server-side only)
            // Must match PHP Price_Calculator logic: productBase + lengthExtra + surcharges
            let price = grayPrice + mitreSurcharge + colorAmount + customSurcharge;

            // Apply rounding
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
                // Store intermediate values for display
                grayPrice: this.round(grayPrice, priceDecimals),
                selectedLength: selectedLength,
                colorSurcharge: this.round(colorAmount, priceDecimals)
            };
        }

        /**
         * Get length value from field selection
         *
         * @param {Object} field Length field config
         * @param {mixed}  value Selected value
         * @return {number} Length in mm
         */
        getLengthValue(field, value) {
            if (field.length_mode === 'fixed' && field.fixed_options) {
                // Fixed options - get the value from the option
                const optionIndex = parseInt(value);
                if (field.fixed_options[optionIndex]) {
                    return parseFloat(field.fixed_options[optionIndex].value) || 0;
                }
                return 0;
            } else {
                // Free input
                let lengthValue = parseFloat(value) || 0;

                // Clamp to min/max
                const minValue = parseFloat(field.min_value) || 0;
                const maxValue = parseFloat(field.max_value) || 10000;

                if (lengthValue < minValue) lengthValue = minValue;
                if (lengthValue > maxValue) lengthValue = maxValue;

                return lengthValue;
            }
        }

        /**
         * Round number to specified decimals
         *
         * @param {number} value    Value to round
         * @param {number} decimals Decimal places
         * @return {number} Rounded value
         */
        round(value, decimals) {
            return Math.round(value * Math.pow(10, decimals)) / Math.pow(10, decimals);
        }

        /**
         * Update display with calculation result
         *
         * @param {Object} result Calculation result
         */
        updateDisplay(result) {
            const $priceEl = this.$wrapper.find('#bossier-calc-price');
            const $weightEl = this.$wrapper.find('#bossier-calc-weight');

            if ($priceEl.length) {
                $priceEl.html(this.formatPrice(result.price));
            }

            if ($weightEl.length) {
                $weightEl.text(this.formatWeight(result.weight));
            }
        }

        /**
         * Update hidden form fields
         *
         * @param {Object} result Calculation result
         */
        updateHiddenFields(result) {
            this.$wrapper.find('#bossier_calculated_price').val(result.price);
            this.$wrapper.find('#bossier_calculated_weight').val(result.weight);
        }

        /**
         * Format price for display
         *
         * @param {number} price Price value
         * @return {string} Formatted price
         */
        formatPrice(price) {
            const currencySymbol = bossierCalculator.i18n.currency;
            const decimals = parseInt(this.settings.price_decimals) || 2;

            // Get WooCommerce price format from page if available
            const formattedNumber = price.toLocaleString(undefined, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });

            // Default format: symbol before number
            return currencySymbol + ' ' + formattedNumber;
        }

        /**
         * Format weight for display
         *
         * @param {number} weight Weight value
         * @return {string} Formatted weight
         */
        formatWeight(weight) {
            const weightUnit = bossierCalculator.i18n.weightUnit;
            const decimals = parseInt(this.settings.weight_decimals) || 3;

            const formattedNumber = weight.toLocaleString(undefined, {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            });

            return formattedNumber + ' ' + weightUnit;
        }

        /**
         * Calculate via AJAX (optional server-side validation)
         *
         * @param {Object} selections Field selections
         */
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
        // Check if calculator config exists
        if (typeof bossierCalculator === 'undefined') {
            return;
        }

        // Find calculator wrapper
        const $wrapper = $('.bossier-calculator-wrap');

        if (!$wrapper.length) {
            return;
        }

        // Initialize calculator
        const calculator = new BossierCalculator($wrapper, bossierCalculator.config);

        // Store instance for external access
        $wrapper.data('bossierCalculator', calculator);
    });

})(jQuery);
