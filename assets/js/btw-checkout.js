/**
 * BTW Checkout JavaScript
 */

(function($) {
    'use strict';

    var BoostBTWCheckout = {
        vatValidationTimer: null,
        lastValidatedVat: '',

        init: function() {
            this.bindEvents();
            this.checkInitialState();
        },

        bindEvents: function() {
            var self = this;

            // Toggle business fields
            $(document).on('change', '#boost_is_business', function() {
                self.toggleBusinessFields($(this).is(':checked'));
            });

            // Validate VAT on blur
            $(document).on('blur', '#boost_vat_number', function() {
                self.validateVAT($(this).val());
            });

            // Validate VAT on input with debounce
            $(document).on('input', '#boost_vat_number', function() {
                var vatNumber = $(this).val();

                clearTimeout(self.vatValidationTimer);

                if (vatNumber.length >= 8) {
                    self.vatValidationTimer = setTimeout(function() {
                        self.validateVAT(vatNumber);
                    }, 500);
                } else {
                    self.hideVATResult();
                }
            });

            // Update on country change
            $(document).on('change', '#billing_country', function() {
                self.updateReverseChargeStatus();
            });

            // Sync company name
            $(document).on('input', '#boost_company_name', function() {
                var companyName = $(this).val();
                $('#billing_company').val(companyName);
            });
        },

        checkInitialState: function() {
            var isBusinessChecked = $('#boost_is_business').is(':checked');
            this.toggleBusinessFields(isBusinessChecked);

            // Check if there's a VAT number already
            var existingVat = $('#boost_vat_number').val();
            if (existingVat && existingVat.length >= 8) {
                this.validateVAT(existingVat);
            }
        },

        toggleBusinessFields: function(show) {
            var $fields = $('#boost-business-fields');

            if (show) {
                $fields.slideDown(200);
                $('#boost_company_name').prop('required', true);
            } else {
                $fields.slideUp(200);
                $('#boost_company_name').prop('required', false);
                this.hideVATResult();
            }

            // Trigger checkout update
            $('body').trigger('update_checkout');
        },

        validateVAT: function(vatNumber) {
            var self = this;
            var $result = $('#boost-vat-validation-result');
            var $input = $('#boost_vat_number');

            // Clean VAT number
            vatNumber = vatNumber.toUpperCase().replace(/[^A-Z0-9]/g, '');

            // Don't validate if same as last
            if (vatNumber === this.lastValidatedVat) {
                return;
            }

            // Don't validate if too short
            if (vatNumber.length < 8) {
                this.hideVATResult();
                return;
            }

            this.lastValidatedVat = vatNumber;

            // Show validating state
            $result
                .removeClass('valid invalid error')
                .addClass('validating show')
                .html('<span class="boost-vat-spinner"></span>' + boostBTW.i18n.validating);

            // AJAX validation
            $.ajax({
                url: boostBTW.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'boost_validate_vat',
                    nonce: boostBTW.nonce,
                    vat_number: vatNumber
                },
                success: function(response) {
                    $result.removeClass('validating');

                    if (response.success && response.data.valid) {
                        var html = '<strong>✓ ' + boostBTW.i18n.valid + '</strong>';

                        if (response.data.company_name) {
                            html += '<span class="company-name">' + self.escapeHtml(response.data.company_name) + '</span>';
                        }

                        if (response.data.address) {
                            html += '<span class="company-address">' + self.escapeHtml(response.data.address) + '</span>';
                        }

                        $result.addClass('valid').html(html);
                        $input.addClass('woocommerce-validated');

                        // Update reverse charge status
                        self.updateReverseChargeStatus();
                    } else {
                        var message = response.data && response.data.message
                            ? response.data.message
                            : boostBTW.i18n.invalid;

                        $result.addClass('invalid').html('<strong>✗ ' + message + '</strong>');
                        $input.removeClass('woocommerce-validated');
                    }

                    // Trigger checkout update
                    $('body').trigger('update_checkout');
                },
                error: function() {
                    $result
                        .removeClass('validating')
                        .addClass('error')
                        .html('<strong>⚠ ' + boostBTW.i18n.error + '</strong>');
                }
            });
        },

        hideVATResult: function() {
            $('#boost-vat-validation-result')
                .removeClass('show validating valid invalid error')
                .html('');
            this.lastValidatedVat = '';
        },

        updateReverseChargeStatus: function() {
            var billingCountry = $('#billing_country').val();
            var isValidVat = $('#boost-vat-validation-result').hasClass('valid');
            var isBusiness = $('#boost_is_business').is(':checked');

            // Remove existing info
            $('.boost-vat-reverse-charge-info').remove();

            if (!isBusiness) {
                return;
            }

            // Check if reverse charge applies
            if (isValidVat && billingCountry && billingCountry !== boostBTW.homeCountry) {
                // Show reverse charge notice
                var html = '<div class="boost-vat-reverse-charge-info">';
                html += '<strong>✓ ' + boostBTW.i18n.reverseCharge + '</strong>';
                html += '</div>';

                $('#boost-business-fields').append(html);
            } else if (isBusiness && isValidVat && billingCountry === boostBTW.homeCountry) {
                // Same country - normal VAT
                var html = '<div class="boost-vat-reverse-charge-info" style="background: #fffbeb; border-color: #f59e0b; color: #92400e;">';
                html += '<strong>ℹ ' + boostBTW.i18n.normalVat + '</strong>';
                html += '</div>';

                $('#boost-business-fields').append(html);
            }
        },

        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    $(document).ready(function() {
        BoostBTWCheckout.init();
    });

})(jQuery);
