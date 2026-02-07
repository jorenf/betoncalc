<?php
/**
 * PDF Template Editor class.
 *
 * Provides an admin interface to edit PDF templates with syntax highlighting.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * PDF_Template_Editor class - Admin page for editing PDF templates.
 */
class PDF_Template_Editor {

	/**
	 * Singleton instance.
	 *
	 * @var PDF_Template_Editor|null
	 */
	private static $instance = null;

	/**
	 * Available templates.
	 *
	 * @var array
	 */
	private $templates = array();

	/**
	 * Get singleton instance.
	 *
	 * @return PDF_Template_Editor
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->templates = array(
			'invoice'      => array(
				'name'         => __( 'Factuur', 'bossier-calculator' ),
				'file'         => BOSSIER_CALC_PLUGIN_DIR . 'templates/pdf/invoice.php',
				'option_key'   => 'boost_pdf_template_invoice',
			),
			'packing-slip' => array(
				'name'         => __( 'Pakbon', 'bossier-calculator' ),
				'file'         => BOSSIER_CALC_PLUGIN_DIR . 'templates/pdf/packing-slip.php',
				'option_key'   => 'boost_pdf_template_packing_slip',
			),
			'style'        => array(
				'name'         => __( 'CSS Stijlen', 'bossier-calculator' ),
				'file'         => BOSSIER_CALC_PLUGIN_DIR . 'templates/pdf/style.css',
				'option_key'   => 'boost_pdf_template_style',
			),
		);

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_boost_pdf_preview', array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_boost_pdf_save_template', array( $this, 'ajax_save_template' ) );
		add_action( 'wp_ajax_boost_pdf_reset_template', array( $this, 'ajax_reset_template' ) );
	}

	/**
	 * Add admin menu page.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'PDF Template Editor', 'bossier-calculator' ),
			__( 'PDF Templates', 'bossier-calculator' ),
			'manage_woocommerce',
			'boost-pdf-templates',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue scripts and styles for the template editor page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_boost-pdf-templates' !== $hook ) {
			return;
		}

		// Try to enqueue CodeMirror from WordPress core.
		$cm_settings = array( 'codeEditor' => false );

		// wp_enqueue_code_editor returns false if code editor is disabled.
		$code_editor = wp_enqueue_code_editor(
			array(
				'type'       => 'text/html',
				'codemirror' => array(
					'lineNumbers'  => true,
					'lineWrapping' => true,
					'mode'         => 'htmlmixed',
					'theme'        => 'dracula',
					'indentUnit'   => 4,
					'indentWithTabs' => true,
					'autoCloseTags'  => true,
					'autoCloseBrackets' => true,
					'matchBrackets'    => true,
					'foldGutter'       => true,
					'gutters'          => array( 'CodeMirror-linenumbers', 'CodeMirror-foldgutter' ),
				),
			)
		);

		if ( false !== $code_editor ) {
			$cm_settings['codeEditor'] = $code_editor;

			// Enqueue CSS mode for style.css editing.
			wp_enqueue_code_editor(
				array(
					'type'       => 'text/css',
					'codemirror' => array(
						'mode'  => 'css',
						'theme' => 'dracula',
					),
				)
			);
		}

		// Always add custom styles - use admin_enqueue_scripts for reliability
		wp_register_style( 'boost-pdf-editor', false );
		wp_enqueue_style( 'boost-pdf-editor' );
		wp_add_inline_style( 'boost-pdf-editor', $this->get_editor_styles() );

		// Enqueue jQuery (always available in admin).
		wp_enqueue_script( 'jquery' );

		// Note: Settings are now defined inline in render_page() to ensure availability
	}

	/**
	 * Get custom CSS for the editor page.
	 *
	 * @return string
	 */
	private function get_editor_styles() {
		return '
			.boost-template-editor-wrap {
				display: flex;
				gap: 20px;
				margin-top: 20px;
			}
			.boost-editor-panel {
				flex: 1;
				background: #fff;
				border-radius: 8px;
				box-shadow: 0 1px 3px rgba(0,0,0,0.1);
				overflow: hidden;
			}
			.boost-preview-panel {
				width: 45%;
				min-width: 400px;
			}
			.boost-panel-header {
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				color: #fff;
				padding: 15px 20px;
				display: flex;
				align-items: center;
				justify-content: space-between;
			}
			.boost-panel-header h3 {
				margin: 0;
				font-size: 16px;
				font-weight: 500;
			}
			.boost-panel-body {
				padding: 0;
			}
			.boost-template-tabs {
				display: flex;
				border-bottom: 1px solid #ddd;
				background: #f9fafb;
			}
			.boost-template-tab {
				padding: 12px 20px;
				cursor: pointer;
				border: none;
				background: none;
				font-size: 13px;
				color: #555;
				border-bottom: 2px solid transparent;
				transition: all 0.2s;
			}
			.boost-template-tab:hover {
				color: #667eea;
				background: #f0f3ff;
			}
			.boost-template-tab.active {
				color: #667eea;
				border-bottom-color: #667eea;
				font-weight: 500;
			}
			.boost-editor-container {
				display: none;
			}
			.boost-editor-container.active {
				display: block;
			}
			.boost-editor-container .CodeMirror {
				height: 600px;
				font-size: 13px;
				border: none;
			}
			/* Textarea fallback when CodeMirror is not available */
			.boost-template-textarea {
				width: 100% !important;
				min-height: 600px !important;
				height: 600px;
				font-family: "Consolas", "Monaco", "Courier New", monospace;
				font-size: 13px;
				line-height: 1.5;
				padding: 15px;
				border: none;
				background: #282a36;
				color: #f8f8f2;
				resize: vertical;
				box-sizing: border-box;
				white-space: pre;
				overflow: auto;
				tab-size: 4;
			}
			.boost-template-textarea:focus {
				outline: 2px solid #667eea;
				outline-offset: -2px;
			}
			.boost-toolbar {
				display: flex;
				gap: 10px;
				padding: 15px 20px;
				background: #f9fafb;
				border-top: 1px solid #eee;
			}
			.boost-btn {
				padding: 10px 20px;
				border-radius: 6px;
				font-size: 13px;
				font-weight: 500;
				cursor: pointer;
				transition: all 0.2s;
				border: none;
			}
			.boost-btn-primary {
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				color: #fff;
			}
			.boost-btn-primary:hover {
				box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
				transform: translateY(-1px);
			}
			.boost-btn-secondary {
				background: #f3f4f6;
				color: #374151;
				border: 1px solid #d1d5db;
			}
			.boost-btn-secondary:hover {
				background: #e5e7eb;
			}
			.boost-btn-danger {
				background: #fee2e2;
				color: #dc2626;
				border: 1px solid #fecaca;
			}
			.boost-btn-danger:hover {
				background: #fecaca;
			}
			.boost-order-select {
				display: flex;
				align-items: center;
				gap: 10px;
				padding: 15px 20px;
				border-bottom: 1px solid #eee;
			}
			.boost-order-select label {
				font-weight: 500;
				color: #374151;
			}
			.boost-order-select select {
				min-width: 250px;
				padding: 8px 12px;
				border: 1px solid #d1d5db;
				border-radius: 6px;
			}
			.boost-preview-frame {
				width: 100%;
				height: 650px;
				border: none;
				background: #f3f4f6;
			}
			.boost-preview-loading {
				display: flex;
				align-items: center;
				justify-content: center;
				height: 650px;
				color: #9ca3af;
				font-size: 14px;
			}
			.boost-save-status {
				margin-left: auto;
				font-size: 13px;
				color: #10b981;
				display: none;
			}
			.boost-save-status.error {
				color: #dc2626;
			}
			.boost-variables-info {
				padding: 15px 20px;
				background: #fffbeb;
				border-top: 1px solid #fef3c7;
				font-size: 12px;
				color: #92400e;
			}
			.boost-variables-info h4 {
				margin: 0 0 8px 0;
				font-size: 13px;
				color: #78350f;
			}
			.boost-variables-info code {
				background: #fef3c7;
				padding: 2px 6px;
				border-radius: 3px;
				font-size: 11px;
			}
			/* CodeMirror Dracula theme overrides */
			.CodeMirror.cm-s-dracula {
				background: #282a36;
			}
		';
	}

	/**
	 * Render the template editor page.
	 */
	public function render_page() {
		$current_template = isset( $_GET['template'] ) ? sanitize_key( $_GET['template'] ) : 'invoice';
		if ( ! isset( $this->templates[ $current_template ] ) ) {
			$current_template = 'invoice';
		}

		// Get recent orders for preview.
		$orders = wc_get_orders( array(
			'limit'   => 20,
			'orderby' => 'date',
			'order'   => 'DESC',
			'status'  => array( 'completed', 'processing', 'on-hold' ),
		) );

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<span class="dashicons dashicons-media-code" style="font-size: 30px; width: 30px; height: 30px; margin-right: 10px; color: #667eea;"></span>
				<?php esc_html_e( 'PDF Template Editor', 'bossier-calculator' ); ?>
			</h1>
			<p style="color: #6b7280; margin-top: 5px;">
				<?php esc_html_e( 'Bewerk de HTML/PHP templates voor facturen en pakbonnen. Wijzigingen worden direct opgeslagen.', 'bossier-calculator' ); ?>
			</p>

			<div class="boost-template-editor-wrap">
				<!-- Editor Panel -->
				<div class="boost-editor-panel">
					<div class="boost-panel-header">
						<h3><?php esc_html_e( 'Template Editor', 'bossier-calculator' ); ?></h3>
						<span class="boost-save-status" id="boost-save-status"></span>
					</div>

					<div class="boost-template-tabs" role="tablist">
						<?php foreach ( $this->templates as $key => $template ) : ?>
							<button
								class="boost-template-tab <?php echo $key === $current_template ? 'active' : ''; ?>"
								data-template="<?php echo esc_attr( $key ); ?>"
								role="tab"
								aria-selected="<?php echo $key === $current_template ? 'true' : 'false'; ?>">
								<?php echo esc_html( $template['name'] ); ?>
							</button>
						<?php endforeach; ?>
					</div>

					<div class="boost-panel-body">
						<?php foreach ( $this->templates as $key => $template ) : ?>
							<div
								class="boost-editor-container <?php echo $key === $current_template ? 'active' : ''; ?>"
								data-template="<?php echo esc_attr( $key ); ?>">
								<textarea
									id="boost-editor-<?php echo esc_attr( $key ); ?>"
									class="boost-template-textarea"
									data-template="<?php echo esc_attr( $key ); ?>"
									data-mode="<?php echo $key === 'style' ? 'css' : 'htmlmixed'; ?>"
								><?php echo esc_textarea( $this->get_template_content( $key ) ); ?></textarea>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="boost-variables-info">
						<h4><?php esc_html_e( 'Beschikbare Variabelen', 'bossier-calculator' ); ?></h4>
						<p>
							<strong>Factuur:</strong>
							<code>$invoice</code>, <code>$order</code>, <code>$company</code>
						</p>
						<p>
							<strong>Pakbon:</strong>
							<code>$packing_slip</code>, <code>$order</code>, <code>$company</code>
						</p>
						<p style="margin-top: 8px;">
							<?php esc_html_e( 'Gebruik WordPress functies zoals', 'bossier-calculator' ); ?>
							<code>esc_html()</code>, <code>wp_kses_post()</code>
							<?php esc_html_e( 'voor veilige output.', 'bossier-calculator' ); ?>
						</p>
					</div>

					<div class="boost-toolbar">
						<button type="button" class="boost-btn boost-btn-primary" id="boost-save-template">
							<span class="dashicons dashicons-saved" style="margin-right: 5px;"></span>
							<?php esc_html_e( 'Opslaan', 'bossier-calculator' ); ?>
						</button>
						<button type="button" class="boost-btn boost-btn-secondary" id="boost-preview-template">
							<span class="dashicons dashicons-visibility" style="margin-right: 5px;"></span>
							<?php esc_html_e( 'Preview', 'bossier-calculator' ); ?>
						</button>
						<button type="button" class="boost-btn boost-btn-danger" id="boost-reset-template">
							<span class="dashicons dashicons-undo" style="margin-right: 5px;"></span>
							<?php esc_html_e( 'Reset naar standaard', 'bossier-calculator' ); ?>
						</button>
					</div>
				</div>

				<!-- Preview Panel -->
				<div class="boost-editor-panel boost-preview-panel">
					<div class="boost-panel-header">
						<h3><?php esc_html_e( 'Preview', 'bossier-calculator' ); ?></h3>
					</div>

					<div class="boost-order-select">
						<label for="boost-preview-order"><?php esc_html_e( 'Selecteer order:', 'bossier-calculator' ); ?></label>
						<select id="boost-preview-order">
							<option value=""><?php esc_html_e( '-- Selecteer een order --', 'bossier-calculator' ); ?></option>
							<?php foreach ( $orders as $order ) : ?>
								<option value="<?php echo esc_attr( $order->get_id() ); ?>">
									#<?php echo esc_html( $order->get_order_number() ); ?> -
									<?php echo esc_html( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ); ?> -
									<?php echo esc_html( wc_format_datetime( $order->get_date_created(), 'd-m-Y' ) ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<select id="boost-preview-type">
							<option value="invoice"><?php esc_html_e( 'Factuur', 'bossier-calculator' ); ?></option>
							<option value="packing-slip"><?php esc_html_e( 'Pakbon', 'bossier-calculator' ); ?></option>
						</select>
					</div>

					<div class="boost-panel-body">
						<div class="boost-preview-loading" id="boost-preview-placeholder">
							<span><?php esc_html_e( 'Selecteer een order om een preview te zien', 'bossier-calculator' ); ?></span>
						</div>
						<iframe class="boost-preview-frame" id="boost-preview-frame" style="display: none;"></iframe>
					</div>
				</div>
			</div>
		</div>

		<script>
		// Settings - defined inline to ensure availability
		var boostPdfEditorSettings = <?php echo wp_json_encode( array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'nonce'        => wp_create_nonce( 'boost_pdf_template_editor' ),
			'cmSettings'   => array( 'codeEditor' => false ),
			'templates'    => array_keys( $this->templates ),
			'strings'      => array(
				'saving'       => __( 'Opslaan...', 'bossier-calculator' ),
				'saved'        => __( 'Opgeslagen!', 'bossier-calculator' ),
				'error'        => __( 'Fout bij opslaan', 'bossier-calculator' ),
				'preview'      => __( 'Preview laden...', 'bossier-calculator' ),
				'resetConfirm' => __( 'Weet je zeker dat je de template wilt terugzetten naar de standaard? Dit kan niet ongedaan worden gemaakt.', 'bossier-calculator' ),
			),
		) ); ?>;
		</script>

		<script>
		jQuery(document).ready(function($) {
			var editors = {};
			var textareas = {};
			var currentTemplate = '<?php echo esc_js( $current_template ); ?>';
			var settings = window.boostPdfEditorSettings || {};
			var useCodeMirror = false;

			// Store textarea references
			$('.boost-template-textarea').each(function() {
				var template = $(this).data('template');
				textareas[template] = this;
			});

			// Try to initialize CodeMirror editors
			if (typeof wp !== 'undefined' && wp.codeEditor && settings.cmSettings && settings.cmSettings.codeEditor) {
				useCodeMirror = true;
				$('.boost-template-textarea').each(function() {
					var textarea = this;
					var template = $(textarea).data('template');
					var mode = $(textarea).data('mode');

					try {
						var editorSettings = $.extend(true, {}, settings.cmSettings.codeEditor);
						if (editorSettings.codemirror) {
							editorSettings.codemirror.mode = mode;
						}
						var editor = wp.codeEditor.initialize(textarea, editorSettings);
						if (editor && editor.codemirror) {
							editors[template] = editor.codemirror;
						}
					} catch (e) {
						console.warn('CodeMirror initialization failed for', template, e);
					}
				});
			}

			// Fallback: show textareas if CodeMirror failed
			if (Object.keys(editors).length === 0) {
				useCodeMirror = false;
				$('.boost-template-textarea').css({
					'width': '100%',
					'height': '600px',
					'font-family': 'monospace',
					'font-size': '13px',
					'padding': '10px',
					'border': '1px solid #ddd',
					'background': '#282a36',
					'color': '#f8f8f2'
				});
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
			$('.boost-template-tab').on('click', function() {
				var template = $(this).data('template');
				currentTemplate = template;

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
			$('#boost-save-template').on('click', function() {
				var $btn = $(this);
				var $status = $('#boost-save-status');

				$btn.prop('disabled', true);
				$status.removeClass('error').text(settings.strings ? settings.strings.saving : 'Opslaan...').show();

				var templateData = {};
				Object.keys(textareas).forEach(function(key) {
					templateData[key] = getTemplateContent(key);
				});

				$.ajax({
					url: settings.ajaxUrl || ajaxurl,
					method: 'POST',
					data: {
						action: 'boost_pdf_save_template',
						nonce: settings.nonce,
						templates: templateData
					},
					success: function(response) {
						if (response.success) {
							$status.text(settings.strings ? settings.strings.saved : 'Opgeslagen!');
							setTimeout(function() {
								$status.fadeOut();
							}, 2000);
						} else {
							$status.addClass('error').text(response.data || (settings.strings ? settings.strings.error : 'Fout'));
						}
					},
					error: function() {
						$status.addClass('error').text(settings.strings ? settings.strings.error : 'Verbindingsfout');
					},
					complete: function() {
						$btn.prop('disabled', false);
					}
				});
			});

			// Preview template
			function loadPreview() {
				var orderId = $('#boost-preview-order').val();
				var type = $('#boost-preview-type').val();

				if (!orderId) {
					$('#boost-preview-frame').hide();
					$('#boost-preview-placeholder').show();
					return;
				}

				$('#boost-preview-placeholder').text(settings.strings ? settings.strings.preview : 'Preview laden...').show();
				$('#boost-preview-frame').hide();

				// Get current template content using helper
				var templateContent = getTemplateContent(type);
				var styleContent = getTemplateContent('style');

				$.ajax({
					url: settings.ajaxUrl || ajaxurl,
					method: 'POST',
					data: {
						action: 'boost_pdf_preview',
						nonce: settings.nonce,
						order_id: orderId,
						type: type,
						template: templateContent,
						style: styleContent
					},
					success: function(response) {
						if (response.success) {
							$('#boost-preview-placeholder').hide();
							$('#boost-preview-frame').show();

							var iframe = document.getElementById('boost-preview-frame');
							var doc = iframe.contentDocument || iframe.contentWindow.document;
							doc.open();
							doc.write(response.data.html);
							doc.close();
						} else {
							$('#boost-preview-placeholder').text(response.data || 'Preview mislukt');
						}
					},
					error: function() {
						$('#boost-preview-placeholder').text('Preview mislukt - verbindingsfout');
					}
				});
			}

			$('#boost-preview-template').on('click', loadPreview);
			$('#boost-preview-order, #boost-preview-type').on('change', loadPreview);

			// Reset template
			$('#boost-reset-template').on('click', function() {
				var confirmMsg = settings.strings ? settings.strings.resetConfirm : 'Weet je zeker dat je de template wilt resetten?';
				if (!confirm(confirmMsg)) {
					return;
				}

				var $btn = $(this);
				var $status = $('#boost-save-status');
				$btn.prop('disabled', true);
				$status.removeClass('error').text('Resetten...').show();

				$.ajax({
					url: settings.ajaxUrl || ajaxurl,
					method: 'POST',
					data: {
						action: 'boost_pdf_reset_template',
						nonce: settings.nonce,
						template: currentTemplate
					},
					success: function(response) {
						if (response.success) {
							// Update editor/textarea using helper
							setTemplateContent(currentTemplate, response.data.content || '');
							$status.text('Reset voltooid!');
							setTimeout(function() {
								$status.fadeOut();
							}, 2000);
						} else {
							$status.addClass('error').text(response.data || 'Reset mislukt');
						}
					},
					error: function() {
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
					$('#boost-save-template').click();
				}
			});

			// Debug: log initialization status
			console.log('PDF Template Editor initialized. CodeMirror:', useCodeMirror, 'Templates:', Object.keys(textareas));
		});
		</script>
		<?php
	}

	/**
	 * Get template content (from database or file).
	 *
	 * @param string $template Template key.
	 * @return string Template content.
	 */
	private function get_template_content( $template ) {
		if ( ! isset( $this->templates[ $template ] ) ) {
			return '';
		}

		$config = $this->templates[ $template ];

		// Check if custom template is saved.
		$custom_content = get_option( $config['option_key'], '' );
		if ( ! empty( $custom_content ) ) {
			return $custom_content;
		}

		// Return default file content.
		return $this->get_default_template_content( $template );
	}

	/**
	 * Get default template content from file.
	 *
	 * @param string $template Template key.
	 * @return string Template content.
	 */
	private function get_default_template_content( $template ) {
		if ( ! isset( $this->templates[ $template ] ) ) {
			return '';
		}

		$file = $this->templates[ $template ]['file'];

		// Check if file exists.
		if ( file_exists( $file ) ) {
			$content = file_get_contents( $file );
			if ( false !== $content ) {
				return $content;
			}
		}

		// Return hardcoded default if file doesn't exist.
		return $this->get_fallback_template( $template );
	}

	/**
	 * Get fallback template content when file is not available.
	 *
	 * @param string $template Template key.
	 * @return string Fallback template content.
	 */
	private function get_fallback_template( $template ) {
		switch ( $template ) {
			case 'invoice':
				return $this->get_default_invoice_template();
			case 'packing-slip':
				return $this->get_default_packing_slip_template();
			case 'style':
				return $this->get_default_style_template();
			default:
				return '';
		}
	}

	/**
	 * Get default invoice template.
	 *
	 * @return string
	 */
	private function get_default_invoice_template() {
		return '<?php
/**
 * Invoice PDF Template.
 *
 * Available variables:
 * - $invoice      Invoice object
 * - $order        WC_Order object
 * - $company      Company data array
 *
 * @package Bossier_Calculator_Builder
 */

defined( \'ABSPATH\' ) || exit;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo esc_html( $invoice->get_title() ); ?> #<?php echo esc_html( $invoice->get_invoice_number() ); ?></title>
    <style>
        <?php echo $invoice->get_styles(); ?>
    </style>
</head>
<body>
    <div class="invoice-container">
        <header class="invoice-header">
            <div class="company-info">
                <?php if ( ! empty( $company[\'logo\'] ) ) : ?>
                    <img src="<?php echo esc_url( $company[\'logo\'] ); ?>" alt="<?php echo esc_attr( $company[\'name\'] ); ?>" class="company-logo">
                <?php endif; ?>
                <h1><?php echo esc_html( $company[\'name\'] ); ?></h1>
                <p><?php echo nl2br( esc_html( $company[\'address\'] ) ); ?></p>
            </div>
            <div class="invoice-info">
                <h2><?php echo esc_html( $invoice->get_title() ); ?></h2>
                <table class="invoice-meta">
                    <tr><td>Factuurnummer:</td><td><?php echo esc_html( $invoice->get_invoice_number() ); ?></td></tr>
                    <tr><td>Ordernummer:</td><td><?php echo esc_html( $order->get_order_number() ); ?></td></tr>
                    <tr><td>Datum:</td><td><?php echo esc_html( $invoice->get_formatted_date() ); ?></td></tr>
                </table>
            </div>
        </header>

        <section class="addresses">
            <div class="billing-address">
                <h3>Factuuradres</h3>
                <?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?>
            </div>
            <div class="shipping-address">
                <h3>Afleveradres</h3>
                <?php echo wp_kses_post( $order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address() ); ?>
            </div>
        </section>

        <section class="order-items">
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Aantal</th>
                        <th>Prijs</th>
                        <th>Totaal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $order->get_items() as $item ) : ?>
                        <tr>
                            <td><?php echo esc_html( $item->get_name() ); ?></td>
                            <td><?php echo esc_html( $item->get_quantity() ); ?></td>
                            <td><?php echo wc_price( $order->get_item_subtotal( $item, false, true ) ); ?></td>
                            <td><?php echo wc_price( $item->get_total() ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="order-totals">
            <table class="totals-table">
                <tr><td>Subtotaal:</td><td><?php echo wc_price( $order->get_subtotal() ); ?></td></tr>
                <?php if ( $order->get_shipping_total() > 0 ) : ?>
                    <tr><td>Verzending:</td><td><?php echo wc_price( $order->get_shipping_total() ); ?></td></tr>
                <?php endif; ?>
                <tr><td>BTW:</td><td><?php echo wc_price( $order->get_total_tax() ); ?></td></tr>
                <tr class="total"><td>Totaal:</td><td><?php echo wc_price( $order->get_total() ); ?></td></tr>
            </table>
        </section>

        <footer class="invoice-footer">
            <?php if ( ! empty( $company[\'vat_number\'] ) ) : ?>
                <p>BTW-nummer: <?php echo esc_html( $company[\'vat_number\'] ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $company[\'iban\'] ) ) : ?>
                <p>IBAN: <?php echo esc_html( $company[\'iban\'] ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $company[\'footer\'] ) ) : ?>
                <p><?php echo wp_kses_post( $company[\'footer\'] ); ?></p>
            <?php endif; ?>
        </footer>
    </div>
</body>
</html>';
	}

	/**
	 * Get default packing slip template.
	 *
	 * @return string
	 */
	private function get_default_packing_slip_template() {
		return '<?php
/**
 * Packing Slip PDF Template.
 *
 * Available variables:
 * - $packing_slip  Packing_Slip object
 * - $order         WC_Order object
 * - $company       Company data array
 *
 * @package Bossier_Calculator_Builder
 */

defined( \'ABSPATH\' ) || exit;
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo esc_html( $packing_slip->get_title() ); ?> - Order #<?php echo esc_html( $order->get_order_number() ); ?></title>
    <style>
        <?php echo $packing_slip->get_styles(); ?>
    </style>
</head>
<body>
    <div class="packing-slip-container">
        <header class="packing-slip-header">
            <div class="company-info">
                <?php if ( ! empty( $company[\'logo\'] ) ) : ?>
                    <img src="<?php echo esc_url( $company[\'logo\'] ); ?>" alt="<?php echo esc_attr( $company[\'name\'] ); ?>" class="company-logo">
                <?php endif; ?>
                <h1><?php echo esc_html( $company[\'name\'] ); ?></h1>
            </div>
            <div class="document-info">
                <h2><?php echo esc_html( $packing_slip->get_title() ); ?></h2>
                <p>Order #<?php echo esc_html( $order->get_order_number() ); ?></p>
                <p>Datum: <?php echo esc_html( $packing_slip->get_formatted_date() ); ?></p>
            </div>
        </header>

        <section class="shipping-address">
            <h3>Afleveradres</h3>
            <?php echo wp_kses_post( $order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address() ); ?>
        </section>

        <section class="order-items">
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Aantal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $order->get_items() as $item ) : ?>
                        <tr>
                            <td>
                                <?php echo esc_html( $item->get_name() ); ?>
                                <?php
                                $meta_data = $item->get_formatted_meta_data();
                                if ( ! empty( $meta_data ) ) :
                                ?>
                                    <ul class="item-meta">
                                        <?php foreach ( $meta_data as $meta ) : ?>
                                            <li><?php echo wp_kses_post( $meta->display_key ); ?>: <?php echo wp_kses_post( $meta->display_value ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html( $item->get_quantity() ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <?php if ( $order->get_customer_note() ) : ?>
            <section class="customer-note">
                <h3>Opmerking</h3>
                <p><?php echo wp_kses_post( $order->get_customer_note() ); ?></p>
            </section>
        <?php endif; ?>

        <footer class="packing-slip-footer">
            <?php if ( ! empty( $company[\'footer\'] ) ) : ?>
                <p><?php echo wp_kses_post( $company[\'footer\'] ); ?></p>
            <?php endif; ?>
        </footer>
    </div>
</body>
</html>';
	}

	/**
	 * Get default CSS style template.
	 *
	 * @return string
	 */
	private function get_default_style_template() {
		return '/* PDF Base Styles */
body {
    font-family: "DejaVu Sans", sans-serif;
    font-size: 12px;
    line-height: 1.5;
    color: #333;
    margin: 0;
    padding: 20px;
}

.invoice-container,
.packing-slip-container {
    max-width: 800px;
    margin: 0 auto;
}

/* Header */
header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #667eea;
}

.company-logo {
    max-width: 150px;
    max-height: 60px;
}

.company-info h1 {
    margin: 10px 0 5px;
    font-size: 18px;
    color: #667eea;
}

.document-info h2,
.invoice-info h2 {
    margin: 0 0 10px;
    font-size: 24px;
    color: #667eea;
}

/* Addresses */
.addresses {
    display: flex;
    gap: 40px;
    margin-bottom: 30px;
}

.addresses h3 {
    font-size: 12px;
    color: #666;
    margin: 0 0 10px;
    text-transform: uppercase;
}

/* Items Table */
.items-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.items-table th,
.items-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.items-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #374151;
}

/* Totals */
.totals-table {
    width: 250px;
    margin-left: auto;
}

.totals-table td {
    padding: 8px;
}

.totals-table tr.total {
    font-weight: bold;
    font-size: 14px;
    border-top: 2px solid #667eea;
}

/* Footer */
footer {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #eee;
    font-size: 10px;
    color: #666;
    text-align: center;
}';
	}

	/**
	 * Get template content for rendering (checks for custom first).
	 *
	 * @param string $template Template key.
	 * @return string Template content.
	 */
	public static function get_template( $template ) {
		$instance = self::get_instance();
		return $instance->get_template_content( $template );
	}

	/**
	 * Check if template has custom content.
	 *
	 * @param string $template Template key.
	 * @return bool
	 */
	public static function has_custom_template( $template ) {
		$instance = self::get_instance();
		if ( ! isset( $instance->templates[ $template ] ) ) {
			return false;
		}
		$content = get_option( $instance->templates[ $template ]['option_key'], '' );
		return ! empty( $content );
	}

	/**
	 * AJAX handler for preview.
	 */
	public function ajax_preview() {
		check_ajax_referer( 'boost_pdf_template_editor', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Onvoldoende rechten.', 'bossier-calculator' ) );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		$type     = isset( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'invoice';
		$template = isset( $_POST['template'] ) ? wp_unslash( $_POST['template'] ) : '';
		$style    = isset( $_POST['style'] ) ? wp_unslash( $_POST['style'] ) : '';

		if ( ! $order_id ) {
			wp_send_json_error( __( 'Geen order geselecteerd.', 'bossier-calculator' ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( __( 'Order niet gevonden.', 'bossier-calculator' ) );
		}

		// Load required classes.
		require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/autoload.php';
		require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/class-pdf-generator.php';

		// Generate HTML based on type.
		try {
			if ( 'invoice' === $type ) {
				require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/class-invoice.php';
				$document = new Invoice( $order );
			} else {
				require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/class-packing-slip.php';
				$document = new Packing_Slip( $order );
			}

			// Get rendered HTML for preview (with custom template if provided).
			$html = $document->get_preview_html( $template, $style );

			wp_send_json_success( array( 'html' => $html ) );
		} catch ( \Exception $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	/**
	 * AJAX handler for saving templates.
	 */
	public function ajax_save_template() {
		check_ajax_referer( 'boost_pdf_template_editor', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Onvoldoende rechten.', 'bossier-calculator' ) );
		}

		$templates = isset( $_POST['templates'] ) ? $_POST['templates'] : array();

		if ( empty( $templates ) || ! is_array( $templates ) ) {
			wp_send_json_error( __( 'Geen templates ontvangen.', 'bossier-calculator' ) );
		}

		foreach ( $templates as $key => $content ) {
			if ( ! isset( $this->templates[ $key ] ) ) {
				continue;
			}

			$option_key = $this->templates[ $key ]['option_key'];
			$content    = wp_unslash( $content );

			// Save to database.
			update_option( $option_key, $content );
		}

		wp_send_json_success( array( 'message' => __( 'Templates opgeslagen.', 'bossier-calculator' ) ) );
	}

	/**
	 * AJAX handler for resetting a template.
	 */
	public function ajax_reset_template() {
		check_ajax_referer( 'boost_pdf_template_editor', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Onvoldoende rechten.', 'bossier-calculator' ) );
		}

		$template = isset( $_POST['template'] ) ? sanitize_key( $_POST['template'] ) : '';

		if ( ! isset( $this->templates[ $template ] ) ) {
			wp_send_json_error( __( 'Onbekende template.', 'bossier-calculator' ) );
		}

		// Delete custom template from database.
		delete_option( $this->templates[ $template ]['option_key'] );

		// Return default file content (with fallback).
		$content = $this->get_default_template_content( $template );

		wp_send_json_success( array( 'content' => $content ) );
	}
}
