/**
 * Boost WooPages JavaScript
 *
 * Handles interactivity for the Boost Calculator WooCommerce pages
 *
 * @package Bossier_Calculator_Builder
 */

(function($) {
    'use strict';

    // Check if boostWooPages is defined
    if (typeof boostWooPages === 'undefined') {
        console.warn('Boost WooPages: Configuration not found');
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

            // Coupon form
            $(document).on('submit', '.boost-woo-coupon-form', function(e) {
                e.preventDefault();
                self.applyCoupon($(this));
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
            var max = parseInt($input.attr('max'), 10) || 99;
            var delta = $button.text().trim() === '−' ? -1 : 1;
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
                        // Refresh page to update coupon display
                        location.reload();
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
                        location.reload();
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

            // Update UI
            $('.boost-woo-ship-opt').removeClass('active');
            $option.addClass('active');

            // Also update hidden radio if using WooCommerce shipping
            var $radio = $option.find('input[type="radio"]');
            if ($radio.length) {
                $radio.prop('checked', true).trigger('change');
            }

            // Trigger WooCommerce update
            $(document.body).trigger('update_checkout');
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

            // Update totals if provided
            if (data.totals_html) {
                $('.boost-woo-summary').html(data.totals_html);
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
        /**
         * Initialize checkout functionality
         */
        init: function() {
            this.bindEvents();
            this.initBusinessToggle();
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
         * Initialize business toggle state
         */
        initBusinessToggle: function() {
            var $toggle = $('.boost-woo-biz-toggle');
            var $checkbox = $toggle.find('input[type="checkbox"]');

            if ($checkbox.is(':checked')) {
                $toggle.addClass('active');
                $('.boost-woo-biz-fields').addClass('show');
            }
        },

        /**
         * Toggle business order
         */
        toggleBusiness: function($toggle) {
            var $checkbox = $toggle.find('input[type="checkbox"]');
            var isActive = $toggle.hasClass('active');

            if (isActive) {
                $toggle.removeClass('active');
                $checkbox.prop('checked', false);
                $('.boost-woo-biz-fields').removeClass('show');
            } else {
                $toggle.addClass('active');
                $checkbox.prop('checked', true);
                $('.boost-woo-biz-fields').addClass('show');
            }

            // Trigger change for BTW module
            $checkbox.trigger('change');
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
    });

})(jQuery);
