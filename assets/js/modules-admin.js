/**
 * Boost Modules Admin JavaScript
 */

(function($) {
    'use strict';

    var BoostModulesAdmin = {
        init: function() {
            this.initCollapsibleSections();
            this.initRepeaters();
            this.initZoneActions();
            this.initPalletActions();
            this.initShippingMethodActions();
            this.initOversizedTypeChange();
            this.initLoadDefaultZones();
            this.initBulkPriceApply();
        },

        /**
         * Initialize collapsible section toggles
         */
        initCollapsibleSections: function() {
            $(document).on('click', '.boost-section-toggle', function(e) {
                var $section = $(this).closest('.boost-collapsible-section');
                $section.toggleClass('open');
            });
        },

        /**
         * Initialize repeater toggle functionality
         */
        initRepeaters: function() {
            // Toggle repeater content
            $(document).on('click', '.boost-repeater-header', function(e) {
                if ($(e.target).hasClass('boost-repeater-remove')) {
                    return;
                }

                var $item = $(this).closest('.boost-repeater-item');
                $item.toggleClass('open');
            });

            // Remove repeater item
            $(document).on('click', '.boost-repeater-remove', function(e) {
                e.stopPropagation();

                if (!confirm(boostModulesAdmin.i18n.confirmDelete)) {
                    return;
                }

                var $item = $(this).closest('.boost-repeater-item');
                $item.slideUp(200, function() {
                    $(this).remove();
                    BoostModulesAdmin.reindexRepeater($item.closest('.boost-repeater'));
                });
            });

            // Update title on name change
            $(document).on('input', '.boost-zone-name-input', function() {
                var $item = $(this).closest('.boost-repeater-item');
                var name = $(this).val() || boostModulesAdmin.i18n.addZone;
                $item.find('.boost-repeater-title').text(name);
            });

            $(document).on('input', '.boost-pallet-name-input', function() {
                var $item = $(this).closest('.boost-repeater-item');
                var name = $(this).val() || boostModulesAdmin.i18n.addPallet;
                $item.find('.boost-repeater-title').text(name);

                // Update ID field
                var id = name.toLowerCase().replace(/[^a-z0-9]/g, '_');
                $item.find('.boost-pallet-id-input').val(id);
            });

            // Update pallet subtitle on dimension change
            $(document).on('input', '.boost-pallet-length-input, .boost-pallet-width-input', function() {
                var $item = $(this).closest('.boost-repeater-item');
                var length = $item.find('.boost-pallet-length-input').val() || 0;
                var width = $item.find('.boost-pallet-width-input').val() || 0;
                $item.find('.boost-repeater-subtitle').text(length + 'x' + width + 'mm');
            });
        },

        /**
         * Initialize zone add/remove actions
         */
        initZoneActions: function() {
            $('.boost-add-zone').on('click', function() {
                var $container = $('#boost-shipping-zones');
                var index = $container.find('.boost-zone-item').length;
                var template = wp.template('boost-zone-item');
                var html = template({ index: index + 1 });

                $container.append(html);

                // Open the new item
                $container.find('.boost-zone-item').last().addClass('open');
            });
        },

        /**
         * Initialize pallet add/remove actions
         */
        initPalletActions: function() {
            $('.boost-add-pallet').on('click', function() {
                var $container = $('#boost-shipping-pallets');
                var index = $container.find('.boost-pallet-item').length;
                var template = wp.template('boost-pallet-item');
                var html = template({ index: index });

                $container.append(html);

                // Open the new item
                $container.find('.boost-pallet-item').last().addClass('open');
            });
        },

        /**
         * Initialize shipping method add/remove actions
         */
        initShippingMethodActions: function() {
            // Add shipping method
            $('#boost-add-shipping-method').on('click', function() {
                var $tbody = $('#boost-shipping-methods-body');
                var index = $tbody.find('.boost-shipping-method-row').length;
                var template = wp.template('boost-shipping-method-row');
                var html = template({ index: index });

                $tbody.append(html);
            });

            // Remove shipping method
            $(document).on('click', '.boost-remove-shipping-method', function(e) {
                e.preventDefault();

                if (!confirm(boostModulesAdmin.i18n.confirmDelete)) {
                    return;
                }

                var $row = $(this).closest('.boost-shipping-method-row');
                $row.fadeOut(200, function() {
                    $(this).remove();
                    BoostModulesAdmin.reindexShippingMethods();
                });
            });

            // Auto-generate ID from name
            $(document).on('input', '.boost-method-name-input', function() {
                var $row = $(this).closest('.boost-shipping-method-row');
                var id = $(this).val().toLowerCase().replace(/[^a-z0-9]/g, '_');
                $row.find('input[name*="[id]"]').val(id);
            });
        },

        /**
         * Reindex shipping method rows after removal
         */
        reindexShippingMethods: function() {
            $('#boost-shipping-methods-body .boost-shipping-method-row').each(function(index) {
                $(this).attr('data-index', index);

                // Update all input names
                $(this).find('input').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        name = name.replace(/\[shipping_methods\]\[\d+\]/, '[shipping_methods][' + index + ']');
                        $(this).attr('name', name);
                    }
                });
            });
        },

        /**
         * Reindex repeater items after removal
         */
        reindexRepeater: function($repeater) {
            $repeater.find('.boost-repeater-item').each(function(index) {
                $(this).attr('data-index', index);

                // Update all input names
                $(this).find('input, select, textarea').each(function() {
                    var name = $(this).attr('name');
                    if (name) {
                        // Replace the index in the name
                        name = name.replace(/\[\d+\]/, '[' + index + ']');
                        $(this).attr('name', name);
                    }
                });
            });
        },

        /**
         * Update oversized suffix based on type
         */
        initOversizedTypeChange: function() {
            $('select[name*="shipping_oversized_type"]').on('change', function() {
                var type = $(this).val();
                var suffix = '';

                switch (type) {
                    case 'percentage':
                        suffix = '%';
                        break;
                    case 'per_mm':
                        suffix = '€/mm';
                        break;
                    default:
                        suffix = '€';
                }

                $('.boost-oversized-suffix').text(suffix);
            });
        },

        /**
         * Bulk price assignment: set one price for a method across multiple zones
         */
        initBulkPriceApply: function() {

            // Toggle panel open/closed
            $(document).on('click', '.boost-bulk-price-toggle', function() {
                $(this).closest('.boost-bulk-price-panel').toggleClass('open');
            });

            // Header column checkbox: select/deselect entire zone column
            $(document).on('change', '.boost-bulk-col-all', function() {
                var zoneId  = $(this).data('zone-id');
                var checked = $(this).is(':checked');
                $(this).closest('table').find('.boost-bulk-zone-check[value="' + zoneId + '"]').prop('checked', checked);
            });

            // "Toepassen" per method row
            $(document).on('click', '.boost-bulk-apply-btn', function() {
                var $btn     = $(this);
                var $row     = $btn.closest('tr');
                var methodId = $row.data('method-id');
                var price    = $row.find('.boost-bulk-price-input').val();

                if ( price === '' ) {
                    $row.find('.boost-bulk-price-input').focus();
                    return;
                }

                var applied = 0;
                $row.find('.boost-bulk-zone-check:checked').each(function() {
                    var zoneId    = $(this).val();
                    var $zoneItem = $('.boost-zone-item[data-zone-id="' + zoneId + '"]');
                    $zoneItem.find('.boost-zone-price-input[data-method-id="' + methodId + '"]')
                             .val( price )
                             .trigger('change');
                    applied++;
                });

                if ( applied === 0 ) {
                    // Highlight zone checkboxes to prompt user to select
                    $row.find('.boost-bulk-zone-cell').addClass('boost-bulk-highlight');
                    setTimeout(function() {
                        $row.find('.boost-bulk-zone-cell').removeClass('boost-bulk-highlight');
                    }, 1200 );
                    return;
                }

                var origText = $btn.text();
                $btn.text( '\u2713 ' + applied + ( applied === 1 ? ' zone' : ' zones' ) ).prop( 'disabled', true );
                setTimeout(function() {
                    $btn.text( origText ).prop( 'disabled', false );
                }, 2000 );
            });
        },

        /**
         * Initialize load default zones button
         */
        initLoadDefaultZones: function() {
            var originalButtonText = '';

            $('#boost-load-default-zones').on('click', function(e) {
                e.preventDefault();

                var $button = $(this);
                originalButtonText = $button.text();

                // Confirm action
                if (!confirm(boostModulesAdmin.i18n.confirmLoadDefaults)) {
                    return;
                }

                // Disable button and show loading state
                $button.prop('disabled', true).text(boostModulesAdmin.i18n.loadingZones);

                // Make AJAX request
                $.ajax({
                    url: boostModulesAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'boost_load_default_zones',
                        nonce: boostModulesAdmin.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            // Show success message and reload
                            alert(boostModulesAdmin.i18n.zonesLoaded);
                            window.location.reload();
                        } else {
                            alert(boostModulesAdmin.i18n.zonesError);
                            $button.prop('disabled', false).text(originalButtonText);
                        }
                    },
                    error: function() {
                        alert(boostModulesAdmin.i18n.zonesError);
                        $button.prop('disabled', false).text(originalButtonText);
                    }
                });
            });
        }
    };

    $(document).ready(function() {
        BoostModulesAdmin.init();
    });

})(jQuery);
