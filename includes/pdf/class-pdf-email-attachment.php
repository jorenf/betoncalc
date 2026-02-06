<?php
/**
 * PDF Email Attachment class.
 *
 * Attaches PDF invoices to WooCommerce order emails.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * PDF_Email_Attachment class - Manages PDF attachments to emails.
 */
class PDF_Email_Attachment {

	/**
	 * Singleton instance.
	 *
	 * @var PDF_Email_Attachment|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return PDF_Email_Attachment
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
		add_filter( 'woocommerce_email_attachments', array( $this, 'attach_invoice_to_email' ), 10, 4 );
	}

	/**
	 * Attach invoice PDF to order emails.
	 *
	 * @param array    $attachments Email attachments.
	 * @param string   $email_id    Email ID.
	 * @param WC_Order $order       Order object.
	 * @param object   $email       Email object.
	 * @return array
	 */
	public function attach_invoice_to_email( $attachments, $email_id, $order, $email = null ) {
		// Check if invoice attachment is enabled.
		if ( get_option( 'boost_pdf_attach_invoice_email', 'yes' ) !== 'yes' ) {
			return $attachments;
		}

		// Check if we have a valid order.
		if ( ! $order instanceof \WC_Order ) {
			return $attachments;
		}

		// Get email types that should include invoice.
		$enabled_emails = $this->get_enabled_email_types();

		if ( ! in_array( $email_id, $enabled_emails, true ) ) {
			return $attachments;
		}

		// Check if order status matches configured statuses.
		if ( ! $this->should_attach_for_status( $order ) ) {
			return $attachments;
		}

		// Generate and attach invoice PDF.
		$invoice_path = $this->get_invoice_path( $order );

		if ( $invoice_path && file_exists( $invoice_path ) ) {
			$attachments[] = $invoice_path;
		}

		return $attachments;
	}

	/**
	 * Get email types that should include invoice attachment.
	 *
	 * @return array
	 */
	protected function get_enabled_email_types() {
		$default_emails = array(
			'customer_completed_order',
			'customer_invoice',
			'customer_processing_order',
		);

		$enabled_emails = get_option( 'boost_pdf_email_types', $default_emails );

		if ( ! is_array( $enabled_emails ) ) {
			$enabled_emails = $default_emails;
		}

		return $enabled_emails;
	}

	/**
	 * Check if invoice should be attached based on order status.
	 *
	 * @param \WC_Order $order Order object.
	 * @return bool
	 */
	protected function should_attach_for_status( $order ) {
		$default_statuses = array( 'processing', 'completed' );
		$enabled_statuses = get_option( 'boost_pdf_email_statuses', $default_statuses );

		if ( ! is_array( $enabled_statuses ) ) {
			$enabled_statuses = $default_statuses;
		}

		$order_status = $order->get_status();

		return in_array( $order_status, $enabled_statuses, true );
	}

	/**
	 * Get or generate invoice PDF path.
	 *
	 * @param \WC_Order $order Order object.
	 * @return string|false Path to invoice PDF or false on failure.
	 */
	protected function get_invoice_path( $order ) {
		// Load required classes.
		require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/autoload.php';
		require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/class-pdf-generator.php';
		require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/class-invoice.php';

		// Check if invoice PDF already exists.
		$existing_path = $order->get_meta( '_boost_invoice_path' );

		if ( ! empty( $existing_path ) && file_exists( $existing_path ) ) {
			return $existing_path;
		}

		// Generate new invoice PDF.
		try {
			$invoice = new Invoice( $order );
			$path    = $invoice->generate_and_save();

			return $path;
		} catch ( \Exception $e ) {
			// Log error.
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error(
					sprintf( 'Failed to generate invoice PDF for order %d: %s', $order->get_id(), $e->getMessage() ),
					array( 'source' => 'boost-pdf' )
				);
			}

			return false;
		}
	}

	/**
	 * Get available email types for settings.
	 *
	 * @return array
	 */
	public static function get_available_email_types() {
		return array(
			'customer_completed_order'  => __( 'Bestelling voltooid', 'bossier-calculator' ),
			'customer_processing_order' => __( 'Bestelling in behandeling', 'bossier-calculator' ),
			'customer_invoice'          => __( 'Klantfactuur', 'bossier-calculator' ),
			'customer_on_hold_order'    => __( 'Bestelling in de wacht', 'bossier-calculator' ),
			'new_order'                 => __( 'Nieuwe bestelling (admin)', 'bossier-calculator' ),
		);
	}

	/**
	 * Get available order statuses for settings.
	 *
	 * @return array
	 */
	public static function get_available_statuses() {
		return array(
			'pending'    => __( 'In afwachting', 'bossier-calculator' ),
			'processing' => __( 'In behandeling', 'bossier-calculator' ),
			'on-hold'    => __( 'In de wacht', 'bossier-calculator' ),
			'completed'  => __( 'Voltooid', 'bossier-calculator' ),
		);
	}
}
