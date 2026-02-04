/**
 * Bossier Calculator - Frontend JavaScript
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
            this.calculate();
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
                    case 'mitre_angle':
                        // Radio or dropdown
                        const $colorSelected = $field.find('input:checked, select');
                        value = $colorSelected.val();
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
         * @param {Object} selections Field selections
         * @return {Object} Calculation result
         */
        calculateLocal(selections) {
            let price = parseFloat(this.settings.base_price) || 0;
            let weight = parseFloat(this.settings.base_weight) || 0;
            let quantityMultiplier = 1;

            for (const fieldId in this.fields) {
                const field = this.fields[fieldId];

                if (!selections.hasOwnProperty(fieldId)) continue;

                const value = selections[fieldId];

                switch (field.type) {
                    case 'length':
                        const lengthResult = this.calculateLengthField(field, value);
                        price += lengthResult.price;
                        weight += lengthResult.weight;
                        break;

                    case 'color':
                        if (field.colors && field.colors[value]) {
                            const color = field.colors[value];
                            price += parseFloat(color.surcharge) || 0;
                        }
                        break;

                    case 'mitre_angle':
                        if (field.angles && field.angles[value]) {
                            const angle = field.angles[value];
                            price += parseFloat(angle.surcharge) || 0;
                            weight += parseFloat(angle.extra_weight) || 0;
                        }
                        break;

                    case 'quantity':
                        quantityMultiplier = Math.max(1, parseInt(value) || 1);
                        break;

                    case 'custom':
                        if (Array.isArray(value)) {
                            // Multiple selections (checkboxes)
                            value.forEach(idx => {
                                if (field.custom_options && field.custom_options[idx]) {
                                    const option = field.custom_options[idx];
                                    price += parseFloat(option.surcharge) || 0;
                                    weight += parseFloat(option.extra_weight) || 0;
                                }
                            });
                        } else if (field.custom_options && field.custom_options[value]) {
                            const option = field.custom_options[value];
                            price += parseFloat(option.surcharge) || 0;
                            weight += parseFloat(option.extra_weight) || 0;
                        }
                        break;
                }
            }

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
                totalWeight: this.round(weight * quantityMultiplier, weightDecimals)
            };
        }

        /**
         * Calculate length field contribution
         *
         * @param {Object} field Field configuration
         * @param {mixed}  value Selected value
         * @return {Object} Price and weight
         */
        calculateLengthField(field, value) {
            let price = 0;
            let weight = 0;

            if (field.length_mode === 'fixed' && field.fixed_options) {
                // Fixed options
                const optionIndex = parseInt(value);
                if (field.fixed_options[optionIndex]) {
                    const option = field.fixed_options[optionIndex];
                    price = parseFloat(option.price) || 0;
                    weight = parseFloat(option.weight) || 0;
                }
            } else {
                // Free input
                let lengthValue = parseFloat(value) || 0;

                // Clamp to min/max
                const minValue = parseFloat(field.min_value) || 0;
                const maxValue = parseFloat(field.max_value) || 10000;

                if (lengthValue < minValue) lengthValue = minValue;
                if (lengthValue > maxValue) lengthValue = maxValue;

                // Calculate based on price/weight per unit
                const pricePerUnit = parseFloat(field.price_per_unit) || 0;
                const weightPerUnit = parseFloat(field.weight_per_unit) || 0;

                price = lengthValue * pricePerUnit;
                weight = lengthValue * weightPerUnit;
            }

            return { price, weight };
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
