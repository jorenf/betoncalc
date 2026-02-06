/**
 * Boost Calculator - Admin JavaScript
 *
 * Handles the calculator builder admin interface.
 *
 * @package Bossier_Calculator_Builder
 */

(function($) {
    'use strict';

    /**
     * BossierCalculatorAdmin class
     */
    const BossierCalculatorAdmin = {

        /**
         * Field counter for unique IDs
         */
        fieldCounter: 0,

        /**
         * Option counters per field
         */
        optionCounters: {},

        /**
         * Initialize admin functionality
         */
        init: function() {
            this.initFieldCounter();
            this.bindEvents();
            this.initSortable();
            this.initColorPickers();
        },

        /**
         * Initialize field counter from existing fields
         */
        initFieldCounter: function() {
            const $fields = $('#bossier-fields-container .bossier-field-item');
            this.fieldCounter = $fields.length;

            // Initialize option counters
            $fields.each(function() {
                const fieldId = $(this).data('field-id');
                const $options = $(this).find('.bossier-option-row');
                BossierCalculatorAdmin.optionCounters[fieldId] = $options.length;
            });
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            // Add new field
            $(document).on('click', '#bossier-add-field', function() {
                self.addField();
            });

            // Delete field
            $(document).on('click', '.bossier-field-delete', function(e) {
                e.preventDefault();
                if (confirm(bossierCalculatorAdmin.i18n.confirmDelete)) {
                    $(this).closest('.bossier-field-item').slideUp(300, function() {
                        $(this).remove();
                        self.updateFieldOrder();
                        self.checkEmptyState();
                    });
                }
            });

            // Toggle field body
            $(document).on('click', '.bossier-field-toggle', function(e) {
                e.preventDefault();
                const $fieldItem = $(this).closest('.bossier-field-item');
                $fieldItem.toggleClass('collapsed');
                $fieldItem.find('.bossier-field-body').slideToggle(200);
            });

            // Length mode change
            $(document).on('change', '.bossier-length-mode-select', function() {
                const mode = $(this).val();
                const $fieldItem = $(this).closest('.bossier-field-item');

                $fieldItem.find('.bossier-length-mode-settings').hide();
                $fieldItem.find('.bossier-length-mode-' + mode).show();
            });

            // Add length option
            $(document).on('click', '.bossier-add-length-option', function() {
                self.addLengthOption($(this));
            });

            // Add color option
            $(document).on('click', '.bossier-add-color-option', function() {
                self.addColorOption($(this));
            });

            // Add angle option (legacy - single group)
            $(document).on('click', '.bossier-add-angle-option', function() {
                self.addAngleOption($(this));
            });

            // Add mitre group
            $(document).on('click', '.bossier-add-mitre-group', function() {
                self.addMitreGroup($(this));
            });

            // Remove mitre group
            $(document).on('click', '.bossier-remove-mitre-group', function() {
                if (confirm(bossierCalculatorAdmin.i18n.confirmDelete || 'Weet je zeker dat je deze groep wilt verwijderen?')) {
                    $(this).closest('.bossier-mitre-group').remove();
                }
            });

            // Add angle option within a mitre group
            $(document).on('click', '.bossier-add-group-angle-option', function() {
                self.addMitreGroupAngle($(this));
            });

            // Add custom option
            $(document).on('click', '.bossier-add-custom-option', function() {
                self.addCustomOption($(this));
            });

            // Remove option
            $(document).on('click', '.bossier-remove-option', function() {
                $(this).closest('.bossier-option-row').remove();
            });

            // Image upload
            $(document).on('click', '.bossier-upload-image', function(e) {
                e.preventDefault();
                self.openMediaUploader($(this));
            });
        },

        /**
         * Initialize sortable fields
         */
        initSortable: function() {
            const self = this;

            $('#bossier-fields-container').sortable({
                handle: '.bossier-field-drag',
                placeholder: 'bossier-field-placeholder',
                update: function() {
                    self.updateFieldOrder();
                }
            });
        },

        /**
         * Initialize color pickers
         */
        initColorPickers: function() {
            $('.bossier-color-picker').wpColorPicker();
        },

        /**
         * Add new field
         */
        addField: function() {
            const fieldType = $('#bossier-add-field-type').val();

            if (!fieldType) {
                alert('Please select a field type');
                return;
            }

            const $template = $('#bossier-field-template-' + fieldType);

            if (!$template.length) {
                console.error('Template not found for field type:', fieldType);
                return;
            }

            // Generate unique field ID
            const fieldId = 'field_' + Date.now() + '_' + this.fieldCounter;
            this.fieldCounter++;

            // Get template HTML and replace placeholder
            let html = $template.html();
            html = html.replace(/\{\{FIELD_ID\}\}/g, fieldId);

            // Remove no-fields message
            $('#bossier-fields-container .bossier-no-fields').remove();

            // Append new field
            const $newField = $(html);
            $('#bossier-fields-container').append($newField);

            // Initialize components in new field
            $newField.find('.bossier-color-picker').wpColorPicker();

            // Update field order
            this.updateFieldOrder();

            // Scroll to new field
            $('html, body').animate({
                scrollTop: $newField.offset().top - 100
            }, 300);

            // Reset selector
            $('#bossier-add-field-type').val('');
        },

        /**
         * Update field display order values
         */
        updateFieldOrder: function() {
            $('#bossier-fields-container .bossier-field-item').each(function(index) {
                $(this).find('.bossier-field-order').val(index);
            });
        },

        /**
         * Check if fields container is empty
         */
        checkEmptyState: function() {
            const $container = $('#bossier-fields-container');
            const $fields = $container.find('.bossier-field-item');

            if ($fields.length === 0) {
                $container.html('<div class="bossier-no-fields"><p>No fields configured. Add a field using the dropdown above.</p></div>');
            }
        },

        /**
         * Get next option index for a field
         *
         * @param {string} fieldId Field ID
         * @return {number} Next option index
         */
        getNextOptionIndex: function(fieldId) {
            if (!this.optionCounters[fieldId]) {
                const $field = $('[data-field-id="' + fieldId + '"]');
                this.optionCounters[fieldId] = $field.find('.bossier-option-row').length;
            }
            return this.optionCounters[fieldId]++;
        },

        /**
         * Add length fixed option
         *
         * @param {jQuery} $button Add button
         */
        addLengthOption: function($button) {
            const prefix = $button.data('prefix');
            const fieldId = $button.closest('.bossier-field-item').data('field-id');
            const idx = this.getNextOptionIndex(fieldId + '_length');

            const html = `
                <tr class="bossier-option-row">
                    <td><input type="number" name="${prefix}[fixed_options][${idx}][value]" value="" step="any" class="small-text"></td>
                    <td><input type="text" name="${prefix}[fixed_options][${idx}][label]" value="" class="regular-text"></td>
                    <td><input type="number" name="${prefix}[fixed_options][${idx}][price]" value="0" step="any" class="small-text"></td>
                    <td><input type="number" name="${prefix}[fixed_options][${idx}][weight]" value="0" step="any" class="small-text"></td>
                    <td><button type="button" class="button bossier-remove-option"><span class="dashicons dashicons-no-alt"></span></button></td>
                </tr>
            `;

            $button.prev('table').find('tbody').append(html);
        },

        /**
         * Add color option
         *
         * @param {jQuery} $button Add button
         */
        addColorOption: function($button) {
            const prefix = $button.data('prefix');
            const fieldId = $button.closest('.bossier-field-item').data('field-id');
            const idx = this.getNextOptionIndex(fieldId + '_color');
            const currencySymbol = bossierCalculatorAdmin.currencySymbol || '€';

            const html = `
                <tr class="bossier-option-row bossier-color-option-row">
                    <td style="text-align: center;">
                        <input type="radio" name="${prefix}[default_color]" value="${idx}" class="bossier-default-color-radio">
                        <input type="hidden" name="${prefix}[colors][${idx}][is_default]" value="0" class="bossier-is-default-hidden">
                    </td>
                    <td>
                        <input type="text" name="${prefix}[colors][${idx}][name]" value="" class="regular-text" placeholder="e.g., Gray">
                    </td>
                    <td>
                        <div class="bossier-color-hex-image">
                            <input type="text" name="${prefix}[colors][${idx}][hex]" value="#808080" class="bossier-color-picker" data-default-color="#808080" style="width: 80px;">
                            <div class="bossier-image-field" style="display: inline-flex; margin-left: 5px;">
                                <input type="text" name="${prefix}[colors][${idx}][image]" value="" class="bossier-image-url" placeholder="Image URL" style="width: 100px;">
                                <button type="button" class="button bossier-upload-image"><span class="dashicons dashicons-upload"></span></button>
                            </div>
                        </div>
                    </td>
                    <td>
                        <select name="${prefix}[colors][${idx}][price_type]" class="bossier-color-price-type" style="width: 100px;">
                            <option value="fixed">${currencySymbol} Fixed</option>
                            <option value="percentage">% of Gray</option>
                        </select>
                    </td>
                    <td>
                        <input type="number" name="${prefix}[colors][${idx}][surcharge]" value="0" step="any" class="small-text bossier-color-surcharge" style="width: 70px;">
                        <span class="bossier-surcharge-unit">${currencySymbol}</span>
                    </td>
                    <td><button type="button" class="button bossier-remove-option"><span class="dashicons dashicons-no-alt"></span></button></td>
                </tr>
            `;

            const $row = $(html);
            $button.prev('table').find('tbody').append($row);

            // Initialize color picker
            $row.find('.bossier-color-picker').wpColorPicker();
        },

        /**
         * Add angle option
         *
         * @param {jQuery} $button Add button
         */
        addAngleOption: function($button) {
            const prefix = $button.data('prefix');
            const fieldId = $button.closest('.bossier-field-item').data('field-id');
            const idx = this.getNextOptionIndex(fieldId + '_angle');
            const currencySymbol = bossierCalculatorAdmin.currencySymbol || '€';
            const weightUnit = bossierCalculatorAdmin.weightUnit || 'kg';

            const html = `
                <tr class="bossier-option-row bossier-angle-option-row">
                    <td style="text-align: center;">
                        <input type="radio" name="${prefix}[default_angle]" value="${idx}">
                    </td>
                    <td><input type="text" name="${prefix}[angles][${idx}][label]" value="" class="regular-text" placeholder="e.g., 45° left"></td>
                    <td>
                        <div class="bossier-angle-image-field">
                            <input type="text" name="${prefix}[angles][${idx}][image]" value="" class="bossier-image-url bossier-angle-image-url" placeholder="Image URL" style="width: 120px;">
                            <button type="button" class="button bossier-upload-image bossier-upload-angle-image"><span class="dashicons dashicons-upload"></span></button>
                        </div>
                    </td>
                    <td>
                        <input type="number" name="${prefix}[angles][${idx}][surcharge]" value="0" step="any" class="small-text" style="width: 70px;">
                        <span class="description">${currencySymbol}</span>
                    </td>
                    <td>
                        <input type="number" name="${prefix}[angles][${idx}][extra_weight]" value="0" step="any" class="small-text" style="width: 70px;">
                        <span class="description">${weightUnit}</span>
                    </td>
                    <td><button type="button" class="button bossier-remove-option"><span class="dashicons dashicons-no-alt"></span></button></td>
                </tr>
            `;

            $button.prev('table').find('tbody').append(html);
        },

        /**
         * Add mitre group
         *
         * @param {jQuery} $button Add button
         */
        addMitreGroup: function($button) {
            const prefix = $button.data('prefix');
            const $container = $button.closest('.bossier-mitre-groups-section').find('.bossier-mitre-groups-container');
            const groupIdx = $container.find('.bossier-mitre-group').length;
            const currencySymbol = bossierCalculatorAdmin.currencySymbol || '€';
            const weightUnit = bossierCalculatorAdmin.weightUnit || 'kg';

            const html = `
                <div class="bossier-mitre-group" data-group-idx="${groupIdx}">
                    <div class="bossier-mitre-group-header">
                        <input type="hidden" name="${prefix}[mitre_groups][${groupIdx}][id]" value="group_${groupIdx}">
                        <label>Groep Label:</label>
                        <input type="text" name="${prefix}[mitre_groups][${groupIdx}][label]" value="" class="regular-text bossier-mitre-group-label" placeholder="bijv. Hoek links">
                        <button type="button" class="button bossier-remove-mitre-group" title="Groep verwijderen">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                    <div class="bossier-mitre-group-options">
                        <table class="bossier-options-table bossier-angle-options-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;">Standaard</th>
                                    <th>Label</th>
                                    <th>Afbeelding</th>
                                    <th>Prijs</th>
                                    <th>Gewicht</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="bossier-option-row bossier-angle-option-row">
                                    <td style="text-align: center;">
                                        <input type="radio" name="${prefix}[mitre_groups][${groupIdx}][default]" value="0" checked>
                                    </td>
                                    <td><input type="text" name="${prefix}[mitre_groups][${groupIdx}][angles][0][label]" value="Geen" class="regular-text" placeholder="bijv. 45°"></td>
                                    <td>
                                        <div class="bossier-angle-image-field">
                                            <input type="text" name="${prefix}[mitre_groups][${groupIdx}][angles][0][image]" value="" class="bossier-image-url bossier-angle-image-url" placeholder="URL" style="width: 100px;">
                                            <button type="button" class="button bossier-upload-image bossier-upload-angle-image"><span class="dashicons dashicons-upload"></span></button>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" name="${prefix}[mitre_groups][${groupIdx}][angles][0][surcharge]" value="0" step="any" class="small-text" style="width: 70px;">
                                        <span class="description">${currencySymbol}</span>
                                    </td>
                                    <td>
                                        <input type="number" name="${prefix}[mitre_groups][${groupIdx}][angles][0][extra_weight]" value="0" step="any" class="small-text" style="width: 70px;">
                                        <span class="description">${weightUnit}</span>
                                    </td>
                                    <td><button type="button" class="button bossier-remove-option"><span class="dashicons dashicons-no-alt"></span></button></td>
                                </tr>
                            </tbody>
                        </table>
                        <button type="button" class="button bossier-add-group-angle-option" data-group-idx="${groupIdx}">Optie Toevoegen</button>
                    </div>
                </div>
            `;

            $container.append(html);
        },

        /**
         * Add angle option within a mitre group
         *
         * @param {jQuery} $button Add button
         */
        addMitreGroupAngle: function($button) {
            const $group = $button.closest('.bossier-mitre-group');
            const groupIdx = $group.data('group-idx');
            const $tbody = $group.find('tbody');
            const angleIdx = $tbody.find('tr').length;
            const prefix = $group.closest('.bossier-mitre-groups-container').data('prefix');
            const currencySymbol = bossierCalculatorAdmin.currencySymbol || '€';
            const weightUnit = bossierCalculatorAdmin.weightUnit || 'kg';

            const html = `
                <tr class="bossier-option-row bossier-angle-option-row">
                    <td style="text-align: center;">
                        <input type="radio" name="${prefix}[mitre_groups][${groupIdx}][default]" value="${angleIdx}">
                    </td>
                    <td><input type="text" name="${prefix}[mitre_groups][${groupIdx}][angles][${angleIdx}][label]" value="" class="regular-text" placeholder="bijv. 45°"></td>
                    <td>
                        <div class="bossier-angle-image-field">
                            <input type="text" name="${prefix}[mitre_groups][${groupIdx}][angles][${angleIdx}][image]" value="" class="bossier-image-url bossier-angle-image-url" placeholder="URL" style="width: 100px;">
                            <button type="button" class="button bossier-upload-image bossier-upload-angle-image"><span class="dashicons dashicons-upload"></span></button>
                        </div>
                    </td>
                    <td>
                        <input type="number" name="${prefix}[mitre_groups][${groupIdx}][angles][${angleIdx}][surcharge]" value="0" step="any" class="small-text" style="width: 70px;">
                        <span class="description">${currencySymbol}</span>
                    </td>
                    <td>
                        <input type="number" name="${prefix}[mitre_groups][${groupIdx}][angles][${angleIdx}][extra_weight]" value="0" step="any" class="small-text" style="width: 70px;">
                        <span class="description">${weightUnit}</span>
                    </td>
                    <td><button type="button" class="button bossier-remove-option"><span class="dashicons dashicons-no-alt"></span></button></td>
                </tr>
            `;

            $tbody.append(html);
        },

        /**
         * Add custom option
         *
         * @param {jQuery} $button Add button
         */
        addCustomOption: function($button) {
            const prefix = $button.data('prefix');
            const fieldId = $button.closest('.bossier-field-item').data('field-id');
            const idx = this.getNextOptionIndex(fieldId + '_custom');

            const html = `
                <tr class="bossier-option-row">
                    <td><input type="text" name="${prefix}[custom_options][${idx}][label]" value="" class="regular-text"></td>
                    <td><input type="text" name="${prefix}[custom_options][${idx}][value]" value="" class="regular-text"></td>
                    <td>
                        <input type="number" name="${prefix}[custom_options][${idx}][surcharge]" value="0" step="any" class="small-text">
                        <span class="description">${bossierCalculatorAdmin.currencySymbol || '€'}</span>
                    </td>
                    <td>
                        <input type="number" name="${prefix}[custom_options][${idx}][extra_weight]" value="0" step="any" class="small-text">
                        <span class="description">${bossierCalculatorAdmin.weightUnit || 'kg'}</span>
                    </td>
                    <td><button type="button" class="button bossier-remove-option"><span class="dashicons dashicons-no-alt"></span></button></td>
                </tr>
            `;

            $button.prev('table').find('tbody').append(html);
        },

        /**
         * Open media uploader for image selection
         *
         * @param {jQuery} $button Upload button
         */
        openMediaUploader: function($button) {
            const $container = $button.closest('.bossier-image-field, .bossier-angle-image-field');
            const $input = $container.find('.bossier-image-url');
            const $preview = $container.find('.bossier-angle-image-preview');

            // Create media frame
            const frame = wp.media({
                title: bossierCalculatorAdmin.i18n.selectImage,
                button: {
                    text: bossierCalculatorAdmin.i18n.useImage
                },
                multiple: false
            });

            // Handle selection
            frame.on('select', function() {
                const attachment = frame.state().get('selection').first().toJSON();
                $input.val(attachment.url);

                // Update preview if exists (for angle images)
                if ($preview.length) {
                    $preview.attr('src', attachment.url).show();
                } else if ($button.hasClass('bossier-upload-angle-image')) {
                    // Add preview image if not exists
                    $input.before('<img src="' + attachment.url + '" alt="" class="bossier-angle-image-preview" style="max-width: 40px; max-height: 40px; vertical-align: middle; margin-right: 5px; border-radius: 3px;">');
                }
            });

            frame.open();
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Only init on calculator edit pages
        if ($('#bossier-fields-container').length) {
            BossierCalculatorAdmin.init();
        }
    });

})(jQuery);
