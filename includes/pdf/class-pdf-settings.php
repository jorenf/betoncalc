<?php
/**
 * PDF Settings class.
 *
 * Adds PDF document settings to WooCommerce Settings.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * PDF_Settings class - Manages PDF document settings.
 */
class PDF_Settings {

	/**
	 * Singleton instance.
	 *
	 * @var PDF_Settings|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return PDF_Settings
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
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_boost_calculator', array( $this, 'settings_tab_content' ) );
		add_action( 'woocommerce_update_options_boost_calculator', array( $this, 'update_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add settings tab to WooCommerce settings.
	 *
	 * @param array $tabs Existing tabs.
	 * @return array Modified tabs.
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['boost_calculator'] = __( 'Boost Calculator', 'bossier-calculator' );
		return $tabs;
	}

	/**
	 * Render settings tab content.
	 */
	public function settings_tab_content() {
		$this->render_settings_header();
		woocommerce_admin_fields( $this->get_settings() );
	}

	/**
	 * Render custom settings header.
	 */
	private function render_settings_header() {
		?>
		<div class="boost-settings-header">
			<div class="boost-settings-header-content">
				<div class="boost-settings-logo">
					<span class="dashicons dashicons-calculator"></span>
				</div>
				<div class="boost-settings-title">
					<h2><?php esc_html_e( 'Boost Calculator', 'bossier-calculator' ); ?></h2>
					<p><?php esc_html_e( 'PDF Documenten & Instellingen', 'bossier-calculator' ); ?></p>
				</div>
				<div class="boost-settings-version">
					<span class="version-badge">v<?php echo esc_html( BOSSIER_CALC_VERSION ); ?></span>
				</div>
			</div>
		</div>
		<style>
			.boost-settings-header {
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				margin: -10px -20px 30px -20px;
				padding: 30px;
				border-radius: 0 0 12px 12px;
				box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
			}
			.boost-settings-header-content {
				display: flex;
				align-items: center;
				max-width: 1200px;
			}
			.boost-settings-logo {
				width: 60px;
				height: 60px;
				background: rgba(255,255,255,0.2);
				border-radius: 12px;
				display: flex;
				align-items: center;
				justify-content: center;
				margin-right: 20px;
			}
			.boost-settings-logo .dashicons {
				font-size: 32px;
				width: 32px;
				height: 32px;
				color: #fff;
			}
			.boost-settings-title h2 {
				color: #fff;
				font-size: 24px;
				font-weight: 600;
				margin: 0 0 5px 0;
				padding: 0;
			}
			.boost-settings-title p {
				color: rgba(255,255,255,0.8);
				margin: 0;
				font-size: 14px;
			}
			.boost-settings-version {
				margin-left: auto;
			}
			.version-badge {
				background: rgba(255,255,255,0.2);
				color: #fff;
				padding: 6px 14px;
				border-radius: 20px;
				font-size: 12px;
				font-weight: 500;
			}
			/* Improved section styling */
			.woocommerce-settings-boost_calculator h2 {
				font-size: 18px;
				color: #333;
				border-bottom: 2px solid #667eea;
				padding-bottom: 10px;
				margin-top: 30px;
			}
			.woocommerce-settings-boost_calculator .form-table {
				background: #fff;
				border-radius: 8px;
				box-shadow: 0 1px 3px rgba(0,0,0,0.08);
				padding: 10px 0;
				margin-bottom: 20px;
			}
			.woocommerce-settings-boost_calculator .form-table th {
				padding: 20px 20px 20px 25px;
				color: #444;
				font-weight: 500;
			}
			.woocommerce-settings-boost_calculator .form-table td {
				padding: 20px 25px 20px 20px;
			}
			.woocommerce-settings-boost_calculator .form-table input[type="text"],
			.woocommerce-settings-boost_calculator .form-table textarea,
			.woocommerce-settings-boost_calculator .form-table select {
				border: 1px solid #ddd;
				border-radius: 6px;
				padding: 10px 12px;
				transition: border-color 0.2s, box-shadow 0.2s;
			}
			.woocommerce-settings-boost_calculator .form-table input[type="text"]:focus,
			.woocommerce-settings-boost_calculator .form-table textarea:focus,
			.woocommerce-settings-boost_calculator .form-table select:focus {
				border-color: #667eea;
				box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.15);
				outline: none;
			}
			.woocommerce-settings-boost_calculator .form-table .description {
				color: #888;
				font-style: normal;
				margin-top: 8px;
			}
			/* Logo preview styling */
			.boost-logo-preview {
				margin-top: 15px;
			}
			.boost-logo-preview img {
				border-radius: 6px;
				border: 2px solid #eee;
				padding: 8px;
				background: #fafafa;
			}
			/* Buttons */
			.boost-upload-logo,
			.boost-remove-logo {
				border-radius: 6px !important;
			}
		</style>
		<?php
	}

	/**
	 * Update settings.
	 */
	public function update_settings() {
		woocommerce_update_options( $this->get_settings() );
	}

	/**
	 * Enqueue admin scripts for media uploader.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		if ( ! isset( $_GET['tab'] ) || 'boost_calculator' !== $_GET['tab'] ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script(
			'boost-pdf-settings',
			BOSSIER_CALC_PLUGIN_URL . 'assets/js/pdf-settings.js',
			array( 'jquery' ),
			BOSSIER_CALC_VERSION,
			true
		);
	}

	/**
	 * Get all settings.
	 *
	 * @return array
	 */
	public function get_settings() {
		$settings = array();

		// Section: General.
		$settings[] = array(
			'title' => __( 'PDF Documenten', 'bossier-calculator' ),
			'type'  => 'title',
			'desc'  => __( 'Instellingen voor PDF facturen en pakbonnen.', 'bossier-calculator' ),
			'id'    => 'boost_pdf_general_section',
		);

		$settings[] = array(
			'title'   => __( 'Facturen inschakelen', 'bossier-calculator' ),
			'desc'    => __( 'Schakel PDF facturen generatie in', 'bossier-calculator' ),
			'id'      => 'boost_pdf_invoices_enabled',
			'default' => 'yes',
			'type'    => 'checkbox',
		);

		$settings[] = array(
			'title'   => __( 'Pakbonnen inschakelen', 'bossier-calculator' ),
			'desc'    => __( 'Schakel PDF pakbonnen generatie in', 'bossier-calculator' ),
			'id'      => 'boost_pdf_packing_slips_enabled',
			'default' => 'yes',
			'type'    => 'checkbox',
		);

		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'boost_pdf_general_section',
		);

		// Section: Company Info.
		$settings[] = array(
			'title' => __( 'Bedrijfsgegevens', 'bossier-calculator' ),
			'type'  => 'title',
			'desc'  => __( 'Deze gegevens verschijnen op facturen en pakbonnen.', 'bossier-calculator' ),
			'id'    => 'boost_pdf_company_section',
		);

		$settings[] = array(
			'title'    => __( 'Bedrijfsnaam', 'bossier-calculator' ),
			'id'       => 'boost_pdf_company_name',
			'default'  => get_bloginfo( 'name' ),
			'type'     => 'text',
			'css'      => 'min-width: 350px;',
		);

		$settings[] = array(
			'title'    => __( 'Adres', 'bossier-calculator' ),
			'id'       => 'boost_pdf_company_address',
			'default'  => '',
			'type'     => 'textarea',
			'css'      => 'min-width: 350px; height: 80px;',
		);

		$settings[] = array(
			'title'    => __( 'BTW Nummer', 'bossier-calculator' ),
			'id'       => 'boost_pdf_vat_number',
			'default'  => '',
			'type'     => 'text',
			'css'      => 'min-width: 350px;',
		);

		$settings[] = array(
			'title'    => __( 'KVK Nummer', 'bossier-calculator' ),
			'id'       => 'boost_pdf_coc_number',
			'default'  => '',
			'type'     => 'text',
			'css'      => 'min-width: 350px;',
		);

		$settings[] = array(
			'title'    => __( 'IBAN', 'bossier-calculator' ),
			'id'       => 'boost_pdf_iban',
			'default'  => '',
			'type'     => 'text',
			'css'      => 'min-width: 350px;',
		);

		$settings[] = array(
			'title'    => __( 'Logo', 'bossier-calculator' ),
			'desc'     => __( 'Upload een logo voor facturen en pakbonnen (max. 200x80px aanbevolen).', 'bossier-calculator' ),
			'id'       => 'boost_pdf_logo',
			'default'  => '',
			'type'     => 'text',
			'class'    => 'boost-pdf-logo-field',
			'css'      => 'min-width: 350px;',
		);

		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'boost_pdf_company_section',
		);

		// Section: Invoice Settings.
		$settings[] = array(
			'title' => __( 'Factuur Instellingen', 'bossier-calculator' ),
			'type'  => 'title',
			'id'    => 'boost_pdf_invoice_section',
		);

		$settings[] = array(
			'title'    => __( 'Factuur Titel', 'bossier-calculator' ),
			'id'       => 'boost_pdf_invoice_title',
			'default'  => __( 'FACTUUR', 'bossier-calculator' ),
			'type'     => 'text',
			'css'      => 'min-width: 350px;',
		);

		$settings[] = array(
			'title'    => __( 'Factuurnummer Prefix', 'bossier-calculator' ),
			'desc'     => __( 'Bijv. "factuur-" resulteert in factuur-20250001', 'bossier-calculator' ),
			'id'       => 'boost_pdf_invoice_prefix',
			'default'  => 'factuur-',
			'type'     => 'text',
			'css'      => 'min-width: 350px;',
		);

		$settings[] = array(
			'title'    => __( 'Datum Formaat', 'bossier-calculator' ),
			'desc'     => __( 'PHP datum formaat. Standaard: d F Y (bijv. 23 december 2025)', 'bossier-calculator' ),
			'id'       => 'boost_pdf_date_format',
			'default'  => 'd F Y',
			'type'     => 'text',
			'css'      => 'min-width: 200px;',
		);

		$settings[] = array(
			'title'    => __( 'Voettekst', 'bossier-calculator' ),
			'desc'     => __( 'Tekst die onderaan de factuur verschijnt.', 'bossier-calculator' ),
			'id'       => 'boost_pdf_footer',
			'default'  => '',
			'type'     => 'textarea',
			'css'      => 'min-width: 350px; height: 60px;',
		);

		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'boost_pdf_invoice_section',
		);

		// Section: Packing Slip Settings.
		$settings[] = array(
			'title' => __( 'Pakbon Instellingen', 'bossier-calculator' ),
			'type'  => 'title',
			'id'    => 'boost_pdf_packing_slip_section',
		);

		$settings[] = array(
			'title'    => __( 'Pakbon Titel', 'bossier-calculator' ),
			'id'       => 'boost_pdf_packing_slip_title',
			'default'  => __( 'PAKBON', 'bossier-calculator' ),
			'type'     => 'text',
			'css'      => 'min-width: 350px;',
		);

		$settings[] = array(
			'title'   => __( 'Toon Calculator Configuratie', 'bossier-calculator' ),
			'desc'    => __( 'Toon lengte, kleur en hoek onder elk product op de pakbon', 'bossier-calculator' ),
			'id'      => 'boost_pdf_packing_slip_show_config',
			'default' => 'yes',
			'type'    => 'checkbox',
		);

		$settings[] = array(
			'title'    => __( 'Pakbon Voettekst', 'bossier-calculator' ),
			'desc'     => __( 'Optionele aparte voettekst voor pakbonnen. Laat leeg om de standaard voettekst te gebruiken.', 'bossier-calculator' ),
			'id'       => 'boost_pdf_packing_slip_footer',
			'default'  => '',
			'type'     => 'textarea',
			'css'      => 'min-width: 350px; height: 60px;',
		);

		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'boost_pdf_packing_slip_section',
		);

		// Section: Email Settings.
		$settings[] = array(
			'title' => __( 'E-mail Instellingen', 'bossier-calculator' ),
			'type'  => 'title',
			'id'    => 'boost_pdf_email_section',
		);

		$settings[] = array(
			'title'   => __( 'Factuur bijvoegen bij e-mail', 'bossier-calculator' ),
			'desc'    => __( 'Voeg de factuur PDF automatisch toe aan bestel e-mails', 'bossier-calculator' ),
			'id'      => 'boost_pdf_attach_invoice_email',
			'default' => 'yes',
			'type'    => 'checkbox',
		);

		$settings[] = array(
			'title'    => __( 'E-mail bijlage bij status', 'bossier-calculator' ),
			'desc'     => __( 'Selecteer bij welke order statussen de factuur wordt bijgevoegd.', 'bossier-calculator' ),
			'id'       => 'boost_pdf_email_statuses',
			'default'  => array( 'completed', 'processing' ),
			'type'     => 'multiselect',
			'class'    => 'wc-enhanced-select',
			'options'  => wc_get_order_statuses(),
			'css'      => 'min-width: 350px;',
		);

		$settings[] = array(
			'title'   => __( 'Kopie naar administratie', 'bossier-calculator' ),
			'desc'    => __( 'Stuur een kopie van de factuur e-mail naar het administratie e-mailadres', 'bossier-calculator' ),
			'id'      => 'boost_pdf_admin_copy_enabled',
			'default' => 'no',
			'type'    => 'checkbox',
		);

		$settings[] = array(
			'title'       => __( 'Administratie e-mailadres', 'bossier-calculator' ),
			'desc'        => __( 'E-mailadres waar factuur kopieën naartoe gestuurd worden. Laat leeg om het standaard admin e-mailadres te gebruiken.', 'bossier-calculator' ),
			'id'          => 'boost_pdf_admin_copy_email',
			'default'     => '',
			'type'        => 'email',
			'placeholder' => get_option( 'admin_email' ),
			'css'         => 'min-width: 350px;',
		);

		$settings[] = array(
			'type' => 'sectionend',
			'id'   => 'boost_pdf_email_section',
		);

		return $settings;
	}
}
