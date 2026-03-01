/**
 * Boost WooPages JavaScript
 *
 * Handles interactivity for the Boost Calculator WooCommerce pages
 *
 * @package Bossier_Calculator_Builder
 */

(function($) {
    'use strict';

    // Check if boostWooPages is defined.
    if (typeof boostWooPages === 'undefined') {
        return;
    }

    /**
     * WooPages Cart Handler
     */
    var BoostCart = {
        /**
         * Initialize cart functionality
         */
        init: function() {
            this.bindEvents();
            this.initQuantityControls();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Quantity buttons
            $(document).on('click', '.boost-woo-qty-ctrl button', function(e) {
                e.preventDefault();
                self.handleQuantityClick($(this));
            });

            // Remove item
            $(document).on('click', '.boost-woo-remove-btn', function(e) {
                e.preventDefault();
                self.removeItem($(this));
            });

            // Quantity input change
            $(document).on('change', '.boost-woo-qty-ctrl input', function() {
                self.updateQuantity($(this));
            });

            // Coupon apply button click
            $(document).on('click', '.boost-woo-apply-coupon', function(e) {
                e.preventDefault();
                self.applyCoupon($(this).closest('.boost-woo-coupon-form'));
            });

            // Coupon input Enter key
            $(document).on('keypress', '.boost-woo-coupon-form input[name="coupon_code"]', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    self.applyCoupon($(this).closest('.boost-woo-coupon-form'));
                }
            });

            // Remove coupon
            $(document).on('click', '.boost-woo-applied-coupon .remove', function(e) {
                e.preventDefault();
                self.removeCoupon($(this).closest('.boost-woo-applied-coupon').data('coupon'));
            });

            // Shipping method selection
            $(document).on('click', '.boost-woo-ship-opt', function(e) {
                e.preventDefault();
                self.selectShippingMethod($(this));
            });

            // Update shipping button (postcode)
            $(document).on('click', '#boost_update_shipping', function(e) {
                e.preventDefault();
                self.updateShippingPostcode();
            });

            // Allow Enter key on postcode field
            $(document).on('keypress', '#boost_shipping_postcode', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    self.updateShippingPostcode();
                }
            });

            // Country change triggers postcode update
            $(document).on('change', '#boost_shipping_country', function() {
                var postcode = $('#boost_shipping_postcode').val();
                if (postcode && postcode.length >= 4) {
                    self.updateShippingPostcode();
                }
            });
        },

        /**
         * Initialize quantity controls
         */
        initQuantityControls: function() {
            // Add min/max attributes based on stock
            $('.boost-woo-qty-ctrl input').each(function() {
                var $input = $(this);
                if (!$input.attr('min')) {
                    $input.attr('min', 1);
                }
            });
        },

        /**
         * Handle quantity button click
         */
        handleQuantityClick: function($button) {
            var $input = $button.siblings('input');
            var currentVal = parseInt($input.val(), 10) || 1;
            var min = parseInt($input.attr('min'), 10) || 1;
            var rawMax = $input.attr('max');
            var max = (rawMax && parseInt(rawMax, 10) > 0) ? parseInt(rawMax, 10) : 9999;
            // Detect minus button: check if it's the first button (before input) or contains minus-like char
            var btnText = $button.text().trim();
            var isDecrease = $button.index() < $input.index() || btnText === '−' || btnText === '-' || btnText === '\u2212';
            var delta = isDecrease ? -1 : 1;
            var newVal = currentVal + delta;

            if (newVal >= min && newVal <= max) {
                $input.val(newVal);
                this.updateQuantity($input);
            }
        },

        /**
         * Update cart item quantity
         */
        updateQuantity: function($input) {
            var self = this;
            var $cartItem = $input.closest('.boost-woo-cart-item');
            var cartItemKey = $cartItem.data('cart-key');
            var quantity = parseInt($input.val(), 10) || 1;

            // Add loading state
            $cartItem.addClass('boost-woo-loading');

            $.ajax({
                url: boostWooPages.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'boost_woopages_update_cart',
                    nonce: boostWooPages.nonce,
                    cart_item_key: cartItemKey,
                    quantity: quantity
                },
                success: function(response) {
                    if (response.success) {
                        self.updateCartDisplay(response.data);
                    } else {
                        self.showError(response.data.message || boostWooPages.i18n.error);
                    }
                },
                error: function() {
                    self.showError(boostWooPages.i18n.error);
                },
                complete: function() {
                    $cartItem.removeClass('boost-woo-loading');
                }
            });
        },

        /**
         * Remove cart item
         */
        removeItem: function($button) {
            var self = this;
            var $cartItem = $button.closest('.boost-woo-cart-item');
            var cartItemKey = $cartItem.data('cart-key');

            // Animate removal
            $cartItem.css({
                opacity: 0,
                transform: 'translateX(-20px)',
                transition: 'all 0.25s ease'
            });

            $.ajax({
                url: boostWooPages.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'boost_woopages_update_cart',
                    nonce: boostWooPages.nonce,
                    cart_item_key: cartItemKey,
                    quantity: 0
                },
                success: function(response) {
                    if (response.success) {
                        setTimeout(function() {
                            $cartItem.remove();
                            self.updateCartDisplay(response.data);
                        }, 260);
                    } else {
                        // Restore item if error
                        $cartItem.css({
                            opacity: 1,
                            transform: 'none'
                        });
                        self.showError(response.data.message || boostWooPages.i18n.error);
                    }
                },
                error: function() {
                    $cartItem.css({
                        opacity: 1,
                        transform: 'none'
                    });
                    self.showError(boostWooPages.i18n.error);
                }
            });
        },

        /**
         * Apply coupon code
         */
        applyCoupon: function($form) {
            var self = this;
            var $input = $form.find('input[name="coupon_code"]');
            var couponCode = $input.val().trim();
            var $button = $form.find('button');

            if (!couponCode) {
                return;
            }

            // Loading state
            $button.prop('disabled', true).text(boostWooPages.i18n.processing);

            $.ajax({
                url: boostWooPages.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'boost_woopages_apply_coupon',
                    nonce: boostWooPages.nonce,
                    coupon_code: couponCode
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data.message);
                        $input.val('');
                        // Update totals
                        if (response.data.totals_html) {
                            $('.boost-woo-summary').html(response.data.totals_html);
                        }
                        // Update applied coupons badges
                        if (response.data.coupons_html !== undefined) {
                            var $couponsContainer = $('.boost-woo-applied-coupons');
                            if (!$couponsContainer.length) {
                                $form.closest('.boost-woo-panel').find('.boost-woo-panel-header').after('<div class="boost-woo-applied-coupons"></div>');
                                $couponsContainer = $('.boost-woo-applied-coupons');
                            }
                            $couponsContainer.html(response.data.coupons_html);
                        }
                    } else {
                        self.showError(response.data.message || boostWooPages.i18n.invalidCoupon);
                    }
                },
                error: function() {
                    self.showError(boostWooPages.i18n.error);
                },
                complete: function() {
                    $button.prop('disabled', false).text('Toepassen');
                }
            });
        },

        /**
         * Remove coupon
         */
        removeCoupon: function(couponCode) {
            var self = this;

            $.ajax({
                url: boostWooPages.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'boost_woopages_remove_coupon',
                    nonce: boostWooPages.nonce,
                    coupon_code: couponCode
                },
                success: function(response) {
                    if (response.success) {
                        self.showSuccess(response.data.message);
                        // Update totals
                        if (response.data.totals_html) {
                            $('.boost-woo-summary').html(response.data.totals_html);
                        }
                        // Update applied coupons badges
                        if (response.data.coupons_html !== undefined) {
                            var $couponsContainer = $('.boost-woo-applied-coupons');
                            if ($couponsContainer.length) {
                                if (response.data.coupons_html) {
                                    $couponsContainer.html(response.data.coupons_html);
                                } else {
                                    $couponsContainer.remove();
                                }
                            }
                        }
                    } else {
                        self.showError(response.data.message || boostWooPages.i18n.error);
                    }
                },
                error: function() {
                    self.showError(boostWooPages.i18n.error);
                }
            });
        },

        /**
         * Select shipping method
         */
        selectShippingMethod: function($option) {
            var self = this;
            var methodId = $option.data('method-id');

            // Update UI immediately
            $('.boost-woo-ship-opt').removeClass('active');
            $option.addClass('active');

            // Also update hidden radio if using WooCommerce shipping
            var $radio = $option.find('input[type="radio"]');
            if ($radio.length) {
                $radio.prop('checked', true);
            }

            // Add loading state to totals
            $('.boost-woo-summary').addClass('boost-woo-loading');

            // AJAX call to update session and recalculate totals
            $.ajax({
                url: boostWooPages.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'boost_woopages_select_shipping',
                    nonce: boostWooPages.nonce,
                    method_id: methodId
                },
                success: function(response) {
                    if (response.success) {
                        // Update totals display
                        if (response.data.totals_html) {
                            $('.boost-woo-summary').html(response.data.totals_html);
                        }
                        // Update shipping options if returned (to reflect selected state)
                        if (response.data.shipping_html) {
                            var $shippingOptions = $('#boost-shipping-options');
                            if ($shippingOptions.length) {
                                $shippingOptions.html(response.data.shipping_html);
                            }
                        }
                    } else {
                        self.showError(response.data.message || boostWooPages.i18n.error);
                    }
                },
                error: function() {
                    self.showError(boostWooPages.i18n.error);
                },
                complete: function() {
                    $('.boost-woo-summary').removeClass('boost-woo-loading');
                }
            });

            // Also trigger WooCommerce update for checkout page compatibility
            $(document.body).trigger('update_checkout');
        },

        /**
         * Update shipping based on postcode
         */
        updateShippingPostcode: function() {
            var self = this;
            var postcode = $('#boost_shipping_postcode').val().trim();
            var country = $('#boost_shipping_country').val();
            var $btn = $('#boost_update_shipping');
            var $notice = $('#boost-postcode-notice');
            var $shippingOptions = $('#boost-shipping-options');

            // Validate postcode
            if (!postcode || postcode.length < 4) {
                $notice.show();
                $shippingOptions.hide();
                return;
            }

            // Show loading state
            $btn.prop('disabled', true).text(boostWooPages.i18n.processing || 'Laden...');

            $.ajax({
                url: boostWooPages.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'boost_woopages_update_shipping',
                    nonce: boostWooPages.nonce,
                    postcode: postcode,
                    country: country
                },
                success: function(response) {
                    if (response.success) {
                        // Hide notice, show shipping options
                        $notice.hide();
                        $shippingOptions.html(response.data.shipping_html).show();

                        // Update postcode input styling
                        $('#boost_shipping_postcode').addClass('has-value');

                        // Enable checkout button
                        var $disabledBtn = $('#boost-checkout-btn-disabled');
                        if ($disabledBtn.length) {
                            $disabledBtn.replaceWith(
                                '<a href="' + boostWooPages.checkoutUrl + '" class="boost-woo-btn boost-woo-btn-blue boost-woo-btn-lg" id="boost-checkout-btn">' +
                                (boostWooPages.i18n.checkout || 'Doorgaan naar afrekenen') +
                                ' <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z"/></svg>' +
                                '</a>'
                            );
                        }

                        // Update totals if returned
                        if (response.data.totals_html) {
                            $('.boost-woo-summary').html(response.data.totals_html);
                        }

                        // Trigger cart update event
                        $(document.body).trigger('boost_cart_updated');
                    } else {
                        self.showError(response.data.message || boostWooPages.i18n.error);
                    }
                },
                error: function() {
                    self.showError(boostWooPages.i18n.error);
                },
                complete: function() {
                    $btn.prop('disabled', false).text(boostWooPages.i18n.calculate || 'Bereken');
                }
            });
        },

        /**
         * Update cart display after AJAX
         */
        updateCartDisplay: function(data) {
            // Update cart count in header/badge
            if (data.cart_count !== undefined) {
                $('.boost-woo-cart-badge, .cart-badge, #cartBadge').text(data.cart_count);
                $('.boost-woo-panel-count span, #itemCount').text(data.cart_count);
            }

            // Update cart items if provided (refreshes line prices and quantities)
            if (data.cart_html) {
                $('#boost-cart-items').html(data.cart_html);
            }

            // Update totals if provided
            if (data.totals_html) {
                $('.boost-woo-summary').html(data.totals_html);
            }

            // Update shipping options if provided (reflects recalculated rates)
            if (data.shipping_html) {
                var $shippingOptions = $('#boost-shipping-options');
                if ($shippingOptions.length) {
                    $shippingOptions.html(data.shipping_html).show();
                    // Re-bind click events on new shipping option elements
                    $shippingOptions.find('.boost-woo-ship-opt').off('click').on('click', function() {
                        var $opt = $(this);
                        $opt.siblings('.boost-woo-ship-opt').removeClass('active');
                        $opt.addClass('active');
                        $opt.find('input[type="radio"]').prop('checked', true).trigger('change');
                    });
                }
            }

            // Check if cart is empty
            if (data.cart_count === 0) {
                location.reload();
            }
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        /**
         * Show error message
         */
        showError: function(message) {
            this.showNotice(message, 'error');
        },

        /**
         * Show notice
         */
        showNotice: function(message, type) {
            var $notices = $('.boost-woo-notices');
            if (!$notices.length) {
                $notices = $('<div class="boost-woo-notices"></div>');
                $('.boost-woo-main').prepend($notices);
            }

            var $notice = $('<div class="boost-woo-notice boost-woo-notice-' + type + '">' + message + '</div>');
            $notices.append($notice);

            // Auto-remove after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    /**
     * WooPages Checkout Handler
     */
    var BoostCheckout = {
        vatValidationTimer: null,
        lastValidatedVat: '',

        /**
         * Initialize checkout functionality
         */
        init: function() {
            this.bindEvents();
            this.initBusinessToggle();
            this.initVatValidation();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Business toggle
            $(document).on('click', '.boost-woo-biz-toggle', function(e) {
                if (!$(e.target).is('input')) {
                    self.toggleBusiness($(this));
                }
            });

            // VAT number input - validate on blur
            $(document).on('blur', '#boost_vat_number', function() {
                self.validateVat($(this).val());
            });

            // VAT number input - validate on input with debounce
            $(document).on('input', '#boost_vat_number', function() {
                var vatNumber = $(this).val();
                clearTimeout(self.vatValidationTimer);

                if (vatNumber.length >= 8) {
                    self.vatValidationTimer = setTimeout(function() {
                        self.validateVat(vatNumber);
                    }, 500);
                } else {
                    self.hideVatResult();
                }
            });

            // Update on country change
            $(document).on('change', '#billing_country', function() {
                self.updateReverseChargeStatus();
            });

            // Payment method selection (for custom display)
            $(document).on('click', '.boost-woo-pay-method', function(e) {
                e.preventDefault();
                self.selectPaymentMethod($(this));
            });

            // Checkout form validation
            $(document).on('submit', 'form.boost-woo-checkout-form', function(e) {
                return self.validateCheckout($(this));
            });
        },

        /**
         * Initialize VAT validation if there's an existing value
         */
        initVatValidation: function() {
            var existingVat = $('#boost_vat_number').val();
            if (existingVat && existingVat.length >= 8) {
                this.validateVat(existingVat);
            }
        },

        /**
         * Validate VAT number via AJAX
         */
        validateVat: function(vatNumber) {
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
                this.hideVatResult();
                return;
            }

            this.lastValidatedVat = vatNumber;

            // Use boostWooPages AJAX URL and VAT-specific nonce
            var ajaxUrl = boostWooPages.ajaxUrl;
            var nonce = boostWooPages.vatNonce;

            // Show validating state
            $result
                .removeClass('valid invalid error')
                .addClass('validating show')
                .css('display', 'block')
                .html('<span class="boost-vat-spinner"></span> Valideren...');

            // AJAX validation
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'boost_validate_vat',
                    nonce: nonce,
                    vat_number: vatNumber
                },
                success: function(response) {
                    $result.removeClass('validating');

                    if (response.success && response.data.valid) {
                        var html = '<strong>✓ BTW-nummer gevalideerd</strong>';

                        if (response.data.company_name) {
                            html += '<span class="company-name">' + self.escapeHtml(response.data.company_name) + '</span>';
                        }

                        $result.addClass('valid').html(html);
                        $input.addClass('validated');

                        // Update reverse charge status
                        self.updateReverseChargeStatus();
                    } else {
                        var message = response.data && response.data.message
                            ? response.data.message
                            : 'BTW-nummer kon niet worden gevalideerd';

                        $result.addClass('invalid').html('<strong>✗ ' + message + '</strong>');
                        $input.removeClass('validated');
                    }

                    // Trigger checkout update to recalculate taxes
                    $('body').trigger('update_checkout');
                },
                error: function() {
                    $result
                        .removeClass('validating')
                        .addClass('error')
                        .html('<strong>⚠ Validatie fout</strong>');
                }
            });
        },

        /**
         * Hide VAT validation result
         */
        hideVatResult: function() {
            $('#boost-vat-validation-result')
                .removeClass('show validating valid invalid error')
                .css('display', 'none')
                .html('');
            this.lastValidatedVat = '';
        },

        /**
         * Update reverse charge status display
         */
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

            // Dutch VAT numbers (NL) NEVER get reverse charge
            var isDutchVat = vatCountry === 'NL';
            var isDutchBilling = billingCountry === 'NL';

            // Check if reverse charge applies - only for foreign EU businesses with valid VAT
            if (isValidVat && !isDutchVat && !isDutchBilling && billingCountry) {
                // Show reverse charge notice
                var html = '<div class="boost-vat-reverse-charge-info">';
                html += '<strong>✓ BTW wordt verlegd (0% BTW)</strong>';
                html += '</div>';

                $('#boost-business-fields, .boost-woo-biz-fields').append(html);
            } else if (isBusiness && isValidVat) {
                // Dutch business - normal VAT applies
                var html = '<div class="boost-vat-reverse-charge-info" style="background: #fffbeb; border-color: #f59e0b; color: #92400e;">';
                html += '<strong>ℹ Normale BTW van toepassing</strong>';
                html += '</div>';

                $('#boost-business-fields, .boost-woo-biz-fields').append(html);
            }
        },

        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Initialize business toggle state
         */
        initBusinessToggle: function() {
            var $toggle = $('.boost-woo-biz-toggle');
            var $checkbox = $toggle.find('input[type="checkbox"]');
            var $fields = $('#boost-business-fields, .boost-woo-biz-fields');

            if ($checkbox.is(':checked')) {
                $toggle.addClass('active');
                $fields.addClass('show').show();
            } else {
                $fields.hide();
            }
        },

        /**
         * Toggle business order
         */
        toggleBusiness: function($toggle) {
            var $checkbox = $toggle.find('input[type="checkbox"]');
            var isActive = $toggle.hasClass('active');
            var $fields = $('#boost-business-fields, .boost-woo-biz-fields');

            if (isActive) {
                $toggle.removeClass('active');
                $checkbox.prop('checked', false);
                $fields.removeClass('show').slideUp(200);
            } else {
                $toggle.addClass('active');
                $checkbox.prop('checked', true);
                $fields.addClass('show').slideDown(200);
            }

            // Persist business state in session via AJAX
            if (typeof boostWooPages !== 'undefined' && boostWooPages.vatNonce) {
                $.ajax({
                    url: boostWooPages.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'boost_set_business_state',
                        nonce: boostWooPages.vatNonce,
                        is_business: $checkbox.is(':checked') ? 1 : 0
                    }
                });
            }

            // Trigger change for BTW module (btw-checkout.js will handle VAT validation)
            $checkbox.trigger('change');

            // Trigger checkout update to recalculate taxes
            $('body').trigger('update_checkout');
        },

        /**
         * Select payment method
         */
        selectPaymentMethod: function($method) {
            var paymentId = $method.data('payment-id');

            // Update visual state
            $('.boost-woo-pay-method').removeClass('active');
            $method.addClass('active');

            // Update actual WooCommerce payment selection
            $('#payment_method_' + paymentId).prop('checked', true).trigger('change');

            // Trigger WooCommerce update
            $(document.body).trigger('payment_method_selected');
        },

        /**
         * Validate checkout form
         */
        validateCheckout: function($form) {
            var isValid = true;
            var $firstError = null;

            // Check required fields
            $form.find('[required]').each(function() {
                var $field = $(this);
                var value = $field.val().trim();

                if (!value) {
                    isValid = false;
                    $field.addClass('boost-woo-field-error');

                    if (!$firstError) {
                        $firstError = $field;
                    }
                } else {
                    $field.removeClass('boost-woo-field-error');
                }
            });

            // Email validation
            var $email = $form.find('input[type="email"]');
            if ($email.length && $email.val()) {
                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test($email.val())) {
                    isValid = false;
                    $email.addClass('boost-woo-field-error');
                    if (!$firstError) {
                        $firstError = $email;
                    }
                }
            }

            // Scroll to first error
            if (!isValid && $firstError) {
                $('html, body').animate({
                    scrollTop: $firstError.offset().top - 100
                }, 300);
                $firstError.focus();
            }

            return isValid;
        }
    };

    /**
     * WooPages Step Navigation
     */
    var BoostSteps = {
        currentStep: 1,

        /**
         * Initialize steps
         */
        init: function() {
            this.detectCurrentStep();
            this.bindEvents();
        },

        /**
         * Detect current step from page type
         */
        detectCurrentStep: function() {
            if ($('body').hasClass('boost-woopages-cart')) {
                this.currentStep = 1;
            } else if ($('body').hasClass('boost-woopages-checkout')) {
                this.currentStep = 2;
            } else if ($('body').hasClass('boost-woopages-thankyou')) {
                this.currentStep = 3;
            }

            this.updateStepUI();
        },

        /**
         * Bind step navigation events
         */
        bindEvents: function() {
            var self = this;

            $(document).on('click', '.boost-woo-step[data-step]', function(e) {
                var targetStep = parseInt($(this).data('step'), 10);

                // Can only go back, not forward
                if (targetStep < self.currentStep) {
                    var url = $(this).data('url');
                    if (url) {
                        window.location.href = url;
                    }
                }
            });
        },

        /**
         * Update step UI based on current step
         */
        updateStepUI: function() {
            var self = this;

            $('.boost-woo-step').each(function() {
                var $step = $(this);
                var stepNum = parseInt($step.data('step'), 10);

                $step.removeClass('active done');

                if (stepNum < self.currentStep) {
                    $step.addClass('done');
                } else if (stepNum === self.currentStep) {
                    $step.addClass('active');
                }
            });
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Check if we're on a WooPages page
        if (!$('body').hasClass('boost-woopages')) {
            return;
        }

        // Initialize components
        BoostSteps.init();

        if ($('body').hasClass('boost-woopages-cart')) {
            BoostCart.init();
        }

        if ($('body').hasClass('boost-woopages-checkout')) {
            BoostCart.init(); // Cart functionality also needed on checkout
            BoostCheckout.init();
        }

        // Trigger WooCommerce events for compatibility
        $(document.body).trigger('wc_fragments_loaded');
    });

    /**
     * WooCommerce checkout updates compatibility
     */
    $(document.body).on('updated_checkout', function() {
        // Re-initialize payment methods if needed
        $('.boost-woo-pay-methods').each(function() {
            var $methods = $(this);
            var $active = $methods.find('.boost-woo-pay-method.active');

            if (!$active.length) {
                // Select first method if none selected
                var $first = $methods.find('.boost-woo-pay-method').first();
                $first.addClass('active');
            }
        });

        // Refresh WooPages totals after checkout update (e.g., after VAT validation)
        $.ajax({
            url: boostWooPages.ajaxUrl,
            type: 'POST',
            data: {
                action: 'boost_woopages_refresh_totals',
                nonce: boostWooPages.nonce
            },
            success: function(response) {
                if (response.success && response.data.totals_html) {
                    $('.boost-woo-summary').html(response.data.totals_html);
                }
            }
        });
    });

})(jQuery);
