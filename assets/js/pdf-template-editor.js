/**
 * PDF Template Editor JavaScript
 *
 * @package Bossier_Calculator_Builder
 */

(function($) {
	'use strict';

	// Debug: immediately log to confirm script is loaded
	console.log('Boost PDF Editor: Script file loaded');

	// Wait for document ready
	$(document).ready(function() {
		console.log('Boost PDF Editor: Document ready');

		// Check if we're on the right page
		if ($('.boost-template-editor-wrap').length === 0) {
			console.log('Boost PDF Editor: Not on editor page, exiting');
			return;
		}

		// Get settings from localized script data
		var settings = window.boostPdfEditorSettings || {};
		console.log('Boost PDF Editor: Settings loaded', settings);

		if (!settings.ajaxUrl || !settings.nonce) {
			console.error('Boost PDF Editor: Missing required settings (ajaxUrl or nonce)');
			return;
		}

		var editors = {};
		var textareas = {};
		var currentTemplate = settings.currentTemplate || 'invoice';

		// Store textarea references
		$('.boost-template-textarea').each(function() {
			var template = $(this).data('template');
			if (template) {
				textareas[template] = this;
				console.log('Boost PDF Editor: Found textarea for', template);
			}
		});

		console.log('Boost PDF Editor: Total textareas found:', Object.keys(textareas).length);

		// Try to initialize CodeMirror editors if available
		var useCodeMirror = false;
		if (typeof wp !== 'undefined' && typeof wp.codeEditor !== 'undefined') {
			console.log('Boost PDF Editor: CodeMirror available, initializing...');
			$('.boost-template-textarea').each(function() {
				var textarea = this;
				var template = $(textarea).data('template');
				var mode = $(textarea).data('mode') || 'htmlmixed';

				try {
					var editorSettings = {
						codemirror: {
							lineNumbers: true,
							lineWrapping: true,
							mode: mode,
							theme: 'dracula',
							indentUnit: 4,
							indentWithTabs: true
						}
					};
					var editor = wp.codeEditor.initialize(textarea, editorSettings);
					if (editor && editor.codemirror) {
						editors[template] = editor.codemirror;
						useCodeMirror = true;
						console.log('Boost PDF Editor: CodeMirror initialized for', template);
					}
				} catch (e) {
					console.warn('Boost PDF Editor: CodeMirror init failed for', template, e);
				}
			});
		} else {
			console.log('Boost PDF Editor: CodeMirror not available, using plain textareas');
		}

		// Helper: get template content
		function getTemplateContent(template) {
			if (editors[template]) {
				return editors[template].getValue();
			}
			if (textareas[template]) {
				return $(textareas[template]).val();
			}
			return '';
		}

		// Helper: set template content
		function setTemplateContent(template, content) {
			if (editors[template]) {
				editors[template].setValue(content);
			}
			if (textareas[template]) {
				$(textareas[template]).val(content);
			}
		}

		// Tab switching
		$(document).on('click', '.boost-template-tab', function(e) {
			e.preventDefault();
			var template = $(this).data('template');
			currentTemplate = template;
			console.log('Boost PDF Editor: Switching to tab', template);

			$('.boost-template-tab').removeClass('active').attr('aria-selected', 'false');
			$(this).addClass('active').attr('aria-selected', 'true');

			$('.boost-editor-container').removeClass('active');
			$('.boost-editor-container[data-template="' + template + '"]').addClass('active');

			// Refresh CodeMirror
			if (editors[template]) {
				setTimeout(function() {
					editors[template].refresh();
				}, 10);
			}

			// Update preview type
			if (template === 'invoice' || template === 'packing-slip') {
				$('#boost-preview-type').val(template);
			}
		});

		// Save template
		$(document).on('click', '#boost-save-template', function(e) {
			e.preventDefault();
			console.log('Boost PDF Editor: Save button clicked');

			var $btn = $(this);
			var $status = $('#boost-save-status');

			$btn.prop('disabled', true);
			$status.removeClass('error').text(settings.strings.saving || 'Opslaan...').show();

			var templateData = {};
			for (var key in textareas) {
				if (textareas.hasOwnProperty(key)) {
					templateData[key] = getTemplateContent(key);
				}
			}

			console.log('Boost PDF Editor: Saving templates:', Object.keys(templateData));

			$.ajax({
				url: settings.ajaxUrl,
				type: 'POST',
				data: {
					action: 'boost_pdf_save_template',
					nonce: settings.nonce,
					templates: templateData
				},
				success: function(response) {
					console.log('Boost PDF Editor: Save response:', response);
					if (response.success) {
						$status.text(settings.strings.saved || 'Opgeslagen!');
						setTimeout(function() {
							$status.fadeOut();
						}, 2000);
					} else {
						$status.addClass('error').text(response.data || settings.strings.error || 'Fout');
					}
				},
				error: function(xhr, status, error) {
					console.error('Boost PDF Editor: Save error:', status, error);
					$status.addClass('error').text(settings.strings.error || 'Verbindingsfout');
				},
				complete: function() {
					$btn.prop('disabled', false);
				}
			});
		});

		// Preview template
		function loadPreview() {
			var orderId = $('#boost-preview-order').val();
			var $typeSelect = $('#boost-preview-type');
			var type = $typeSelect.val() || 'invoice';

			console.log('Boost PDF Editor: Loading preview for order', orderId, 'type', type, 'typeSelect exists:', $typeSelect.length > 0);

			if (!orderId) {
				$('#boost-preview-frame').hide();
				$('#boost-preview-placeholder').show().text('Selecteer een order om een preview te zien');
				return;
			}

			$('#boost-preview-placeholder').text(settings.strings.preview || 'Preview laden...').show();
			$('#boost-preview-frame').hide();

			// Get current template content
			var templateContent = getTemplateContent(type);
			var styleContent = getTemplateContent('style');

			$.ajax({
				url: settings.ajaxUrl,
				type: 'POST',
				data: {
					action: 'boost_pdf_preview',
					nonce: settings.nonce,
					order_id: orderId,
					type: type,
					template: templateContent,
					style: styleContent
				},
				success: function(response) {
					console.log('Boost PDF Editor: Preview response:', response);
					if (response.success && response.data && response.data.html) {
						$('#boost-preview-placeholder').hide();
						$('#boost-preview-frame').show();

						var iframe = document.getElementById('boost-preview-frame');
						if (iframe) {
							var doc = iframe.contentDocument || iframe.contentWindow.document;
							doc.open();
							doc.write(response.data.html);
							doc.close();
						}
					} else {
						$('#boost-preview-placeholder').text(response.data || 'Preview mislukt');
					}
				},
				error: function(xhr, status, error) {
					console.error('Boost PDF Editor: Preview error:', status, error);
					$('#boost-preview-placeholder').text('Preview mislukt - verbindingsfout');
				}
			});
		}

		$(document).on('click', '#boost-preview-template', function(e) {
			e.preventDefault();
			loadPreview();
		});

		$(document).on('change', '#boost-preview-order, #boost-preview-type', function() {
			loadPreview();
		});

		// Reset template
		$(document).on('click', '#boost-reset-template', function(e) {
			e.preventDefault();
			console.log('Boost PDF Editor: Reset button clicked for template:', currentTemplate);

			var confirmMsg = settings.strings.resetConfirm || 'Weet je zeker dat je de template wilt resetten?';
			if (!confirm(confirmMsg)) {
				return;
			}

			var $btn = $(this);
			var $status = $('#boost-save-status');
			$btn.prop('disabled', true);
			$status.removeClass('error').text('Resetten...').show();

			$.ajax({
				url: settings.ajaxUrl,
				type: 'POST',
				data: {
					action: 'boost_pdf_reset_template',
					nonce: settings.nonce,
					template: currentTemplate
				},
				success: function(response) {
					console.log('Boost PDF Editor: Reset response:', response);
					if (response.success) {
						setTemplateContent(currentTemplate, response.data.content || '');
						$status.text('Reset voltooid!');
						setTimeout(function() {
							$status.fadeOut();
						}, 2000);
					} else {
						$status.addClass('error').text(response.data || 'Reset mislukt');
					}
				},
				error: function(xhr, status, error) {
					console.error('Boost PDF Editor: Reset error:', status, error);
					$status.addClass('error').text('Reset mislukt - verbindingsfout');
				},
				complete: function() {
					$btn.prop('disabled', false);
				}
			});
		});

		// Keyboard shortcut: Ctrl+S to save
		$(document).on('keydown', function(e) {
			if ((e.ctrlKey || e.metaKey) && e.key === 's') {
				e.preventDefault();
				$('#boost-save-template').trigger('click');
			}
		});

		console.log('Boost PDF Editor: Initialization complete. CodeMirror:', useCodeMirror);
	});

})(jQuery);
