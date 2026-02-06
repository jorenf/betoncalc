/**
 * Shipping Checkout JavaScript
 */

(function($) {
    'use strict';

    var BoostShippingCheckout = {
        init: function() {
            this.bindEvents();
            this.checkInitialState();
        },

        bindEvents: function() {
            var self = this;

            // Handle shipping choice change
            $(document).on('change', 'input[name="boost_shipping_choice"]', function() {
                self.handleShippingChoice($(this).val());
            });
        },

        checkInitialState: function() {
            var $checked = $('input[name="boost_shipping_choice"]:checked');
            if ($checked.length) {
                this.updateSelectionStyles($checked.val());
            }
        },

        handleShippingChoice: function(choice) {
            this.updateSelectionStyles(choice);
            this.toggleAddressDisplay(choice);

            // Trigger checkout update to recalculate shipping
            $('body').trigger('update_checkout');
        },

        updateSelectionStyles: function(choice) {
            $('.boost-shipping-option').removeClass('selected');
            $('input[name="boost_shipping_choice"][value="' + choice + '"]')
                .closest('.boost-shipping-option')
                .addClass('selected');
        },

        toggleAddressDisplay: function(choice) {
            if (choice === 'pickup') {
                $('.boost-pickup-address').slideDown(200);
                $('.boost-shipping-notice').slideUp(200);
            } else {
                $('.boost-pickup-address').slideUp(200);
                $('.boost-shipping-notice').slideDown(200);
            }
        }
    };

    $(document).ready(function() {
        BoostShippingCheckout.init();
    });

})(jQuery);
