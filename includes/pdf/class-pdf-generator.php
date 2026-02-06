<?php
/**
 * PDF Generator class.
 *
 * Base class for generating PDF documents using DOMPDF.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\PDF;

defined( 'ABSPATH' ) || exit;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * PDF_Generator class - Base PDF generation functionality.
 */
class PDF_Generator {

	/**
	 * DOMPDF instance.
	 *
	 * @var Dompdf
	 */
	protected $dompdf;

	/**
	 * WooCommerce order.
	 *
	 * @var \WC_Order
	 */
	protected $order;

	/**
	 * Document type.
	 *
	 * @var string
	 */
	protected $document_type = '';

	/**
	 * Constructor.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 */
	public function __construct( $order ) {
		$this->order = $order;
		$this->init_dompdf();
	}

	/**
	 * Initialize DOMPDF with options.
	 */
	protected function init_dompdf() {
		// Load autoloader if not already loaded.
		if ( ! class_exists( 'Dompdf\\Dompdf' ) ) {
			require_once dirname( __FILE__ ) . '/autoload.php';
		}

		$options = new Options();
		$options->set( 'isRemoteEnabled', true );
		$options->set( 'isHtml5ParserEnabled', true );
		$options->set( 'isFontSubsettingEnabled', true );
		$options->set( 'defaultFont', 'DejaVu Sans' );
		$options->set( 'tempDir', $this->get_temp_dir() );
		$options->set( 'fontDir', $this->get_font_dir() );
		$options->set( 'fontCache', $this->get_font_dir() );
		$options->set( 'chroot', ABSPATH );

		$this->dompdf = new Dompdf( $options );
		$this->dompdf->setPaper( 'A4', 'portrait' );
	}

	/**
	 * Get temp directory for DOMPDF.
	 *
	 * @return string
	 */
	protected function get_temp_dir() {
		$upload_dir = wp_upload_dir();
		$temp_dir   = $upload_dir['basedir'] . '/boost-pdf-temp';

		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
		}

		return $temp_dir;
	}

	/**
	 * Get font directory for DOMPDF.
	 *
	 * @return string
	 */
	protected function get_font_dir() {
		$upload_dir = wp_upload_dir();
		$font_dir   = $upload_dir['basedir'] . '/boost-pdf-fonts';

		if ( ! file_exists( $font_dir ) ) {
			wp_mkdir_p( $font_dir );
		}

		return $font_dir;
	}

	/**
	 * Get storage directory for PDFs.
	 *
	 * @return string
	 */
	public function get_storage_dir() {
		$upload_dir   = wp_upload_dir();
		$storage_dir  = $upload_dir['basedir'] . '/boost-invoices';
		$year_dir     = $storage_dir . '/' . date( 'Y' );

		if ( ! file_exists( $year_dir ) ) {
			wp_mkdir_p( $year_dir );

			// Add index.php for security.
			file_put_contents( $storage_dir . '/index.php', '<?php // Silence is golden' );
			file_put_contents( $year_dir . '/index.php', '<?php // Silence is golden' );

			// Add .htaccess for security.
			file_put_contents( $storage_dir . '/.htaccess', 'deny from all' );
		}

		return $year_dir;
	}

	/**
	 * Generate PDF from HTML.
	 *
	 * @param string $html HTML content.
	 * @return string PDF content.
	 */
	public function generate_from_html( $html ) {
		$this->dompdf->loadHtml( $html );
		$this->dompdf->render();

		return $this->dompdf->output();
	}

	/**
	 * Stream PDF to browser.
	 *
	 * @param string $filename Filename for download.
	 */
	public function stream( $filename ) {
		$this->dompdf->stream( $filename, array( 'Attachment' => true ) );
	}

	/**
	 * Output PDF inline (for preview).
	 *
	 * @param string $filename Filename.
	 */
	public function output_inline( $filename ) {
		$this->dompdf->stream( $filename, array( 'Attachment' => false ) );
	}

	/**
	 * Save PDF to file.
	 *
	 * @param string $filepath Full path to save file.
	 * @return bool Success.
	 */
	public function save( $filepath ) {
		$output = $this->dompdf->output();
		return file_put_contents( $filepath, $output ) !== false;
	}

	/**
	 * Get order object.
	 *
	 * @return \WC_Order
	 */
	public function get_order() {
		return $this->order;
	}

	/**
	 * Get formatted company data from settings.
	 *
	 * @return array
	 */
	public function get_company_data() {
		return array(
			'name'         => get_option( 'boost_pdf_company_name', get_bloginfo( 'name' ) ),
			'address'      => get_option( 'boost_pdf_company_address', '' ),
			'vat_number'   => get_option( 'boost_pdf_vat_number', '' ),
			'coc_number'   => get_option( 'boost_pdf_coc_number', '' ),
			'iban'         => get_option( 'boost_pdf_iban', '' ),
			'logo'         => get_option( 'boost_pdf_logo', '' ),
			'footer'       => get_option( 'boost_pdf_footer', '' ),
		);
	}

	/**
	 * Get logo HTML.
	 *
	 * @return string
	 */
	public function get_logo_html() {
		$logo_id = get_option( 'boost_pdf_logo', '' );

		if ( empty( $logo_id ) ) {
			return '';
		}

		$logo_url = wp_get_attachment_url( $logo_id );

		if ( ! $logo_url ) {
			return '';
		}

		return sprintf(
			'<img src="%s" alt="%s" class="logo" style="max-width: 200px; max-height: 80px;">',
			esc_url( $logo_url ),
			esc_attr( get_bloginfo( 'name' ) )
		);
	}

	/**
	 * Format price for display.
	 *
	 * @param float  $price    Price value.
	 * @param string $currency Currency code.
	 * @return string
	 */
	public function format_price( $price, $currency = '' ) {
		if ( empty( $currency ) ) {
			$currency = $this->order->get_currency();
		}

		return html_entity_decode( wc_price( $price, array( 'currency' => $currency ) ) );
	}

	/**
	 * Format date for display.
	 *
	 * @param string $date   Date string or timestamp.
	 * @param string $format Date format.
	 * @return string
	 */
	public function format_date( $date, $format = '' ) {
		if ( empty( $format ) ) {
			$format = get_option( 'boost_pdf_date_format', 'd F Y' );
		}

		if ( is_numeric( $date ) ) {
			return date_i18n( $format, $date );
		}

		return date_i18n( $format, strtotime( $date ) );
	}
}
