/**
 * Boost Modules Admin JavaScript
 */

(function($) {
    'use strict';

    var BoostModulesAdmin = {
        init: function() {
            this.initRepeaters();
            this.initZoneActions();
            this.initPalletActions();
            this.initOversizedTypeChange();
            this.initLoadDefaultZones();
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
