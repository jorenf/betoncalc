/**
 * BTW Checkout JavaScript
 */

(function($) {
    'use strict';

    var BoostBTWCheckout = {
        vatValidationTimer: null,
        lastValidatedVat: '',
        vatValidationRequest: null,
        vatValidationSeq: 0,

        init: function() {
            if ($('body').hasClass('boost-woopages-checkout')) {
                return;
            }

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
                self.hideVATResult();
                $('body').trigger('update_checkout');

                if (self.cleanVATNumber(vatNumber).length >= 8) {
                    self.vatValidationTimer = setTimeout(function() {
                        self.validateVAT(vatNumber);
                    }, 500);
                }
            });

            // Update on country change
            $(document).on('change', '#billing_country', function() {
                self.hideVATResult();
                var vatNumber = $('#boost_vat_number').val();
                if (vatNumber && vatNumber.length >= 8 && $('#boost_is_business').is(':checked')) {
                    self.validateVAT(vatNumber);
                } else {
                    $('body').trigger('update_checkout');
                }
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
                this.resetVATValidationCache();
                var vatNumber = $('#boost_vat_number').val();
                if (vatNumber && vatNumber.length >= 8) {
                    this.validateVAT(vatNumber);
                    return;
                }
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
            var validationKey = this.getValidationKey(vatNumber);

            // Don't validate if same as last
            if (validationKey === this.lastValidatedVat) {
                return;
            }

            // Don't validate if too short
            if (vatNumber.length < 8) {
                this.hideVATResult();
                return;
            }

            this.lastValidatedVat = validationKey;
            var requestSeq = ++this.vatValidationSeq;

            if (this.vatValidationRequest && this.vatValidationRequest.readyState !== 4) {
                this.vatValidationRequest.abort();
            }

            // Show validating state
            $result
                .removeClass('valid invalid warning error')
                .addClass('validating show')
                .html('<span class="boost-vat-spinner"></span>' + boostBTW.i18n.validating);

            // AJAX validation
            this.vatValidationRequest = $.ajax({
                url: boostBTW.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'boost_validate_vat',
                    nonce: boostBTW.nonce,
                    vat_number: vatNumber,
                    is_business: $('#boost_is_business').is(':checked') ? 1 : 0,
                    billing_country: $('#billing_country').val() || ''
                },
                success: function(response) {
                    if (requestSeq !== self.vatValidationSeq) {
                        return;
                    }

                    $result.removeClass('validating');

                    if (response.success && response.data.valid) {
                        var html = '<strong>✓ ' + boostBTW.i18n.valid + '</strong>';

                        if (response.data.company_name) {
                            html += '<span class="company-name">' + self.escapeHtml(response.data.company_name) + '</span>';
                        }

                        if (response.data.address) {
                            html += '<span class="company-address">' + self.escapeHtml(response.data.address) + '</span>';
                        }

                        if (response.data.preserved_valid && response.data.message) {
                            html += '<span class="company-address">' + self.escapeHtml(response.data.message) + '</span>';
                        }

                        $result.addClass('valid show').html(html);
                        $input.addClass('woocommerce-validated');

                        // Update reverse charge status
                        self.updateReverseChargeStatus();
                    } else if (response.data && response.data.service_unavailable) {
                        // VIES temporarily down — warn customer but don't block them
                        var unavailableMsg = (response.data.message)
                            ? response.data.message
                            : boostBTW.i18n.serviceUnavailable;
                        $result.addClass('warning show').html('<strong>⚠ ' + unavailableMsg + '</strong>');
                        $input.removeClass('woocommerce-validated');
                        self.lastValidatedVat = '';
                    } else {
                        // Truly invalid VAT number — show specific reason from backend
                        var message = (response.data && response.data.message)
                            ? response.data.message
                            : boostBTW.i18n.invalid;

                        $result.addClass('invalid show').html('<strong>✗ ' + message + '</strong>');
                        $input.removeClass('woocommerce-validated');
                    }

                    // Trigger checkout update
                    $('body').trigger('update_checkout');
                },
                error: function(xhr, status) {
                    if (status === 'abort' || requestSeq !== self.vatValidationSeq) {
                        return;
                    }

                    $result
                        .removeClass('validating')
                        .addClass('warning show')
                        .html('<strong>⚠ ' + boostBTW.i18n.serviceUnavailable + '</strong>');

                    self.lastValidatedVat = '';
                    $('body').trigger('update_checkout');
                }
            });
        },

        hideVATResult: function() {
            $('#boost-vat-validation-result')
                .removeClass('show validating valid invalid warning error')
                .html('');
            $('#boost_vat_number').removeClass('validated woocommerce-validated');
            $('.boost-vat-reverse-charge-info').remove();
            this.resetVATValidationCache();
        },

        resetVATValidationCache: function() {
            this.lastValidatedVat = '';
            this.vatValidationSeq++;

            if (this.vatValidationRequest && this.vatValidationRequest.readyState !== 4) {
                this.vatValidationRequest.abort();
            }
        },

        getValidationKey: function(vatNumber) {
            return [
                this.cleanVATNumber(vatNumber),
                $('#billing_country').val() || '',
                $('#boost_is_business').is(':checked') ? '1' : '0'
            ].join('|');
        },

        cleanVATNumber: function(vatNumber) {
            return (vatNumber || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
        },

        updateReverseChargeStatus: function() {
            var billingCountry = $('#billing_country').val();
            var isValidVat = $('#boost-vat-validation-result').hasClass('valid');
            var isBusiness = $('#boost_is_business').is(':checked');
            var vatNumber = $('#boost_vat_number').val().toUpperCase().replace(/[^A-Z0-9]/g, '');
            var vatCountry = vatNumber.substring(0, 2);

            // Remove existing info
            $('.boost-vat-reverse-charge-info').remove();

            if (!isBusiness) {
                return;
            }

            // Dutch VAT numbers (starting with NL) NEVER get reverse charge
            // Also billing country must be non-NL for reverse charge
            var isDutchVat = vatCountry === 'NL';
            var isDutchBilling = billingCountry === 'NL' || billingCountry === boostBTW.homeCountry;

            // Check if reverse charge applies - only for foreign EU businesses with valid VAT
            if (isValidVat && !isDutchVat && !isDutchBilling && billingCountry) {
                // Show reverse charge notice - foreign EU business
                var html = '<div class="boost-vat-reverse-charge-info">';
                html += '<strong>✓ ' + boostBTW.i18n.reverseCharge + '</strong>';
                html += '</div>';

                $('#boost-business-fields').append(html);
            } else if (isBusiness && isValidVat) {
                // Dutch business OR Dutch billing country - normal VAT applies
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
