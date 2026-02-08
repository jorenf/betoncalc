/**
 * Boost Cart Icon JavaScript
 *
 * Handles AJAX cart count updates for the cart icon.
 *
 * @package Bossier_Calculator_Builder
 */

(function($) {
    'use strict';

    /**
     * Initialize cart icon functionality.
     */
    function init() {
        // Listen for WooCommerce cart updates
        $(document.body).on('added_to_cart removed_from_cart updated_cart_totals wc_fragments_refreshed', function() {
            updateCartCount();
        });

        // Also listen for our own cart updates
        $(document.body).on('boost_cart_updated', function() {
            updateCartCount();
        });
    }

    /**
     * Update the cart count badge.
     */
    function updateCartCount() {
        $.ajax({
            url: boostCartIcon.ajaxUrl,
            type: 'POST',
            data: {
                action: 'boost_get_cart_count'
            },
            success: function(response) {
                if (response.success) {
                    var count = response.data.count;
                    var total = response.data.total;

                    // Update all cart count badges
                    $('.boost-cart-count').each(function() {
                        var $badge = $(this);
                        var oldCount = parseInt($badge.attr('data-count'), 10) || 0;

                        $badge.attr('data-count', count).text(count);

                        // Add pulse animation if count increased
                        if (count > oldCount) {
                            $badge.addClass('boost-cart-updated');
                            setTimeout(function() {
                                $badge.removeClass('boost-cart-updated');
                            }, 400);
                        }
                    });

                    // Update cart total if displayed
                    $('.boost-cart-total').html(total);
                }
            }
        });
    }

    // Initialize when document is ready
    $(document).ready(init);

})(jQuery);
