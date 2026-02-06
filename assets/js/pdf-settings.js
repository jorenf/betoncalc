/**
 * Boost Calculator - PDF Settings JavaScript
 *
 * Handles media uploader for logo selection.
 *
 * @package Bossier_Calculator_Builder
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Add upload button after logo field
        var $logoField = $('.boost-pdf-logo-field');

        if ($logoField.length) {
            $logoField.after(
                '<button type="button" class="button boost-upload-logo" style="margin-left: 5px;">' +
                'Logo selecteren</button>' +
                '<button type="button" class="button boost-remove-logo" style="margin-left: 5px;">' +
                'Verwijderen</button>' +
                '<div class="boost-logo-preview" style="margin-top: 10px;"></div>'
            );

            // Show preview if logo is set
            updateLogoPreview($logoField.val());

            // Upload button click
            $(document).on('click', '.boost-upload-logo', function(e) {
                e.preventDefault();

                var frame = wp.media({
                    title: 'Selecteer Logo',
                    button: { text: 'Gebruik dit logo' },
                    multiple: false,
                    library: { type: 'image' }
                });

                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    $logoField.val(attachment.id);
                    updateLogoPreview(attachment.id);
                });

                frame.open();
            });

            // Remove button click
            $(document).on('click', '.boost-remove-logo', function(e) {
                e.preventDefault();
                $logoField.val('');
                updateLogoPreview('');
            });
        }

        function updateLogoPreview(attachmentId) {
            var $preview = $('.boost-logo-preview');

            if (!attachmentId) {
                $preview.html('<p style="color: #666; font-style: italic;">Geen logo geselecteerd</p>');
                return;
            }

            // Fetch attachment URL via AJAX
            if (wp.media.attachment(attachmentId)) {
                var attachment = wp.media.attachment(attachmentId);
                attachment.fetch().then(function() {
                    $preview.html(
                        '<img src="' + attachment.get('url') + '" ' +
                        'style="max-width: 200px; max-height: 80px; border: 1px solid #ddd; padding: 5px;">'
                    );
                });
            }
        }
    });

})(jQuery);
