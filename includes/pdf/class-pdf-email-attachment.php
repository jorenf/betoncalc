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
		add_action( 'woocommerce_order_status_changed', array( $this, 'send_admin_invoice_copy' ), 10, 4 );
		// Assign invoice numbers only for paid orders (not on creation).
		add_action( 'woocommerce_payment_complete', array( $this, 'assign_invoice_number_on_payment_complete' ), 10, 1 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'assign_invoice_number_on_status_change' ), 5, 4 );
	}

	/**
	 * Assign invoice number when an online payment is confirmed by the gateway.
	 *
	 * @param int $order_id Order ID.
	 */
	public function assign_invoice_number_on_payment_complete( $order_id ) {
		$order = wc_get_order( $order_id );
		$this->maybe_assign_invoice_number( $order );
	}

	/**
	 * Assign invoice number when an order transitions to a paid status.
	 *
	 * Covers manual/offline payment flows (e.g. BACS, admin marking processing).
	 * Runs at priority 5, before send_admin_invoice_copy (priority 10), so the
	 * number is always available when the admin-copy email is generated.
	 *
	 * @param int      $order_id   Order ID.
	 * @param string   $old_status Previous status (without 'wc-' prefix).
	 * @param string   $new_status New status (without 'wc-' prefix).
	 * @param \WC_Order $order     Order object.
	 */
	public function assign_invoice_number_on_status_change( $order_id, $old_status, $new_status, $order ) {
		if ( ! in_array( $new_status, wc_get_is_paid_statuses(), true ) ) {
			return;
		}
		$this->maybe_assign_invoice_number( $order );
	}

	/**
	 * Assign an invoice number to an order if one has not already been assigned.
	 *
	 * Shared guard used by both payment hooks to prevent duplicate assignment.
	 *
	 * @param \WC_Order $order Order object.
	 */
	protected function maybe_assign_invoice_number( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		// Skip legacy orders that already have a wcpdf invoice number.
		if ( ! empty( $order->get_meta( '_wcpdf_formatted_invoice_number' ) ) ) {
			return;
		}

		// Skip if a boost invoice number was already assigned (prevents duplicates
		// when both woocommerce_payment_complete and woocommerce_order_status_changed fire).
		if ( ! empty( $order->get_meta( '_boost_invoice_number' ) ) ) {
			return;
		}

		require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/autoload.php';
		require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/class-pdf-generator.php';
		require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/class-invoice.php';

		// Constructing the Invoice object triggers get_or_create_invoice_number(),
		// which generates and saves the number to order meta.
		new Invoice( $order );
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

		// Strip 'wc-' prefix from statuses (WC settings save with prefix, get_status() returns without)
		$enabled_statuses = array_map( function( $status ) {
			return str_replace( 'wc-', '', $status );
		}, $enabled_statuses );

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
			// Validate path is within the expected upload directory to prevent path traversal.
			$upload_dir = wp_upload_dir();
			$real_path  = realpath( $existing_path );
			if ( false !== $real_path && 0 === strpos( $real_path, realpath( $upload_dir['basedir'] ) ) ) {
				return $real_path;
			}
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

	/**
	 * Send invoice copy to admin email when order status changes.
	 *
	 * @param int      $order_id   Order ID.
	 * @param string   $old_status Old status.
	 * @param string   $new_status New status.
	 * @param WC_Order $order      Order object.
	 */
	public function send_admin_invoice_copy( $order_id, $old_status, $new_status, $order ) {
		// Check if admin copy is enabled.
		if ( get_option( 'boost_pdf_admin_copy_enabled', 'no' ) !== 'yes' ) {
			return;
		}

		// Check if status matches configured statuses.
		if ( ! $this->should_attach_for_status( $order ) ) {
			return;
		}

		// Check if we already sent a copy for this order (prevent duplicates).
		$copy_sent = $order->get_meta( '_boost_admin_invoice_copy_sent' );
		if ( 'yes' === $copy_sent ) {
			return;
		}

		// Get or generate invoice PDF.
		$invoice_path = $this->get_invoice_path( $order );

		if ( ! $invoice_path || ! file_exists( $invoice_path ) ) {
			return;
		}

		// Get admin email address.
		$admin_email = get_option( 'boost_pdf_admin_copy_email' );
		if ( empty( $admin_email ) ) {
			$admin_email = get_option( 'admin_email' );
		}

		if ( empty( $admin_email ) || ! is_email( $admin_email ) ) {
			return;
		}

		// Send the email.
		$subject = sprintf(
			/* translators: %1$s: site name, %2$s: order number */
			__( '[%1$s] Factuur kopie - Bestelling #%2$s', 'bossier-calculator' ),
			get_bloginfo( 'name' ),
			$order->get_order_number()
		);

		$message = sprintf(
			/* translators: %1$s: order number, %2$s: customer name */
			__( "Bijgevoegd vindt u een kopie van de factuur voor bestelling #%1\$s van %2\$s.\n\nDeze e-mail is automatisch gegenereerd.", 'bossier-calculator' ),
			$order->get_order_number(),
			$order->get_formatted_billing_full_name()
		);

		$headers = array(
			'Content-Type: text/plain; charset=UTF-8',
		);

		$sent = wp_mail( $admin_email, $subject, $message, $headers, array( $invoice_path ) );

		if ( $sent ) {
			// Mark as sent to prevent duplicates.
			$order->update_meta_data( '_boost_admin_invoice_copy_sent', 'yes' );
			$order->save();
		}
	}
}
