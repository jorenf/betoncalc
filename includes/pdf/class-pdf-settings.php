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
		woocommerce_admin_fields( $this->get_settings() );
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
			'type' => 'sectionend',
			'id'   => 'boost_pdf_email_section',
		);

		return $settings;
	}
}
