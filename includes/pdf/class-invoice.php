<?php
/**
 * Invoice PDF class.
 *
 * Generates PDF invoices for WooCommerce orders.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Invoice class - Generates invoice PDFs.
 */
class Invoice extends PDF_Generator {

	/**
	 * Document type.
	 *
	 * @var string
	 */
	protected $document_type = 'invoice';

	/**
	 * Invoice number.
	 *
	 * @var string
	 */
	protected $invoice_number;

	/**
	 * Invoice date.
	 *
	 * @var string
	 */
	protected $invoice_date;

	/**
	 * Constructor.
	 *
	 * @param \WC_Order $order WooCommerce order.
	 */
	public function __construct( $order ) {
		parent::__construct( $order );
		$this->init_invoice_data();
	}

	/**
	 * Initialize invoice specific data.
	 */
	protected function init_invoice_data() {
		// Get or generate invoice number.
		$this->invoice_number = $this->get_or_create_invoice_number();
		$this->invoice_date   = $this->get_or_create_invoice_date();
	}

	/**
	 * Get or create invoice number.
	 *
	 * Priority:
	 * 1. Legacy number from WooCommerce PDF Invoices & Packing Slips plugin (_wcpdf_formatted_invoice_number).
	 * 2. Boost invoice number (_boost_invoice_number).
	 * 3. Generate a new number.
	 *
	 * @return string
	 */
	protected function get_or_create_invoice_number() {
		// Backwards compatibility: preserve invoice numbers assigned by the previous plugin.
		$wcpdf_number = $this->order->get_meta( '_wcpdf_invoice_number' );
		if ( ! empty( $wcpdf_number ) ) {
			return $wcpdf_number;
		}

		$invoice_number = $this->order->get_meta( '_boost_invoice_number' );

		if ( ! empty( $invoice_number ) ) {
			return $invoice_number;
		}

		// Generate new invoice number.
		$invoice_number = $this->generate_invoice_number();

		// Save to order.
		$this->order->update_meta_data( '_boost_invoice_number', $invoice_number );
		$this->order->save();

		return $invoice_number;
	}

	/**
	 * Generate a new invoice number.
	 *
	 * Format: factuur-{YYYY}{0001}
	 *
	 * @return string
	 */
	protected function generate_invoice_number() {
		$year   = date( 'Y' );
		$prefix = get_option( 'boost_pdf_invoice_prefix', 'factuur-' );

		// Get the last invoice number for this year.
		$last_number = get_option( 'boost_invoice_last_number_' . $year, 99 );
		$new_number  = $last_number + 1;

		// Update the counter.
		update_option( 'boost_invoice_last_number_' . $year, $new_number );

		// Format: factuur-0001.
		return $prefix . str_pad( $new_number, 4, '0', STR_PAD_LEFT );
	}

	/**
	 * Get or create invoice date.
	 *
	 * Priority:
	 * 1. Legacy date from WooCommerce PDF Invoices & Packing Slips plugin (_wcpdf_invoice_date).
	 * 2. Boost invoice date (_boost_invoice_date).
	 * 3. Use current date/time.
	 *
	 * @return string
	 */
	protected function get_or_create_invoice_date() {
		// Backwards compatibility: preserve invoice dates assigned by the previous plugin.
		$wcpdf_date = $this->order->get_meta( '_wcpdf_invoice_date' );
		if ( ! empty( $wcpdf_date ) ) {
			return $wcpdf_date;
		}

		$invoice_date = $this->order->get_meta( '_boost_invoice_date' );

		if ( ! empty( $invoice_date ) ) {
			return $invoice_date;
		}

		$invoice_date = $this->order->get_meta( '_wcpdf_invoice_date_formatted' );

		if( !$invoice_date ) {
			$invoice_date = $this->order->get_date_created();
		}

		if(!$invoice_date) {
			// Use current date.
			$invoice_date = current_time( 'mysql' );
		}
	

		// Save to order.
		$this->order->update_meta_data( '_boost_invoice_date', $invoice_date );
		$this->order->save();

		return $invoice_date;
	}

	/**
	 * Get invoice number.
	 *
	 * @return string
	 */
	public function get_invoice_number() {
		return $this->invoice_number;
	}

	/**
	 * Get invoice date.
	 *
	 * @return string
	 */
	public function get_invoice_date() {
		return $this->invoice_date;
	}

	/**
	 * Get formatted invoice date.
	 *
	 * @return string
	 */
	public function get_formatted_invoice_date() {
		return $this->format_date( $this->invoice_date );
	}

	/**
	 * Generate the invoice PDF.
	 *
	 * @return string PDF content.
	 */
	public function generate() {
		$html = $this->render_template();
		return $this->generate_from_html( $html );
	}

	/**
	 * Render the invoice template.
	 *
	 * @return string HTML content.
	 */
	protected function render_template() {
		ob_start();

		// Variables available in template.
		$invoice      = $this;
		$order        = $this->order;
		$company      = $this->get_company_data();

		// Load template.
		$template_path = BOSSIER_CALC_PLUGIN_DIR . 'templates/pdf/invoice.php';

		if ( file_exists( $template_path ) ) {
			include $template_path;
		}

		return ob_get_clean();
	}

	/**
	 * Get filename for download.
	 *
	 * @return string
	 */
	public function get_filename() {
		return sanitize_file_name( $this->invoice_number . '.pdf' );
	}

	/**
	 * Get filepath for storage.
	 *
	 * @return string
	 */
	public function get_filepath() {
		return $this->get_storage_dir() . '/' . $this->get_filename();
	}

	/**
	 * Check if invoice PDF exists.
	 *
	 * @return bool
	 */
	public function pdf_exists() {
		return file_exists( $this->get_filepath() );
	}

	/**
	 * Generate and save invoice PDF.
	 *
	 * @return string|false Filepath on success, false on failure.
	 */
	public function generate_and_save() {
		$this->generate();

		$filepath = $this->get_filepath();

		if ( $this->save( $filepath ) ) {
			// Save filepath to order meta.
			$this->order->update_meta_data( '_boost_invoice_path', $filepath );
			$this->order->save();

			return $filepath;
		}

		return false;
	}

	/**
	 * Get order items for display.
	 *
	 * @return array
	 */
	public function get_order_items() {
		$items = array();

		foreach ( $this->order->get_items() as $item_id => $item ) {
			$product = $item->get_product();

			// Get calculator display data — extract ALL fields
			$display_data     = $item->get_meta( '_bossier_display_data' );
			$calculator_fields = array();

			if ( ! empty( $display_data ) && is_array( $display_data ) ) {
				foreach ( $display_data as $field_id => $field_data ) {
					if ( empty( $field_data['value'] ) ) {
						continue;
					}

					$calculator_fields[] = array(
						'label' => isset( $field_data['label'] ) ? $field_data['label'] : $field_id,
						'value' => $field_data['value'],
						'type'  => isset( $field_data['type'] ) ? $field_data['type'] : '',
					);
				}
			}

			$items[] = array(
				'item_id'           => $item_id,
				'name'              => $item->get_name(),
				'quantity'          => $item->get_quantity(),
				'sku'               => $product ? $product->get_sku() : '',
				'total'             => $item->get_total(),
				'total_tax'         => $item->get_total_tax(),
				'subtotal'          => $item->get_subtotal(),
				'weight'            => $item->get_meta( '_bossier_calculated_weight' ),
				'calculator_fields' => $calculator_fields,
				'type'              => 'product',
			);
		}

		return $items;
	}

	/**
	 * Get shipping item for display as line item.
	 *
	 * @return array|null
	 */
	public function get_shipping_line_item() {
		$shipping_total = $this->order->get_shipping_total();
		$shipping_tax   = $this->order->get_shipping_tax();

		// Only return if there is shipping.
		if ( $shipping_total <= 0 ) {
			return null;
		}

		// Always use "Levering" on invoices.
		$shipping_name = __( 'Levering', 'bossier-calculator' );

		// Get pallet count from order meta.
		$pallet_count = $this->order->get_meta( '_boost_pallet_count' );
		$description  = '';

		if ( ! empty( $pallet_count ) && $pallet_count > 0 ) {
			$description = sprintf(
				_n( '%d Europallet', '%d Europallets', $pallet_count, 'bossier-calculator' ),
				$pallet_count
			);
		}

		return array(
			'name'        => $shipping_name,
			'description' => $description,
			'quantity'    => 1,
			'total'       => $shipping_total,
			'total_tax'   => $shipping_tax,
			'type'        => 'shipping',
		);
	}

	/**
	 * Get billing address HTML.
	 *
	 * @return string
	 */
	public function get_billing_address() {
		return $this->order->get_formatted_billing_address();
	}

	/**
	 * Get customer VAT number if available.
	 *
	 * @return string
	 */
	public function get_customer_vat_number() {
		return $this->order->get_meta( '_boost_vat_number' );
	}

	/**
	 * Get shipping address HTML.
	 *
	 * @return string
	 */
	public function get_shipping_address() {
		return $this->order->get_formatted_shipping_address();
	}

	/**
	 * Get shipping information for invoice.
	 *
	 * @return array
	 */
	public function get_shipping_info() {
		$is_pickup      = $this->order->get_meta( '_boost_is_pickup' );
		$delivery_days  = $this->order->get_meta( '_boost_shipping_delivery_days' );
		$pickup_address = $this->order->get_meta( '_boost_pickup_address' );

		return array(
			'method'         => $this->order->get_shipping_method(),
			'cost'           => $this->order->get_shipping_total(),
			'is_pickup'      => 'yes' === $is_pickup,
			'delivery_days'  => $delivery_days,
			'pickup_address' => $pickup_address,
		);
	}

	/**
	 * Get order totals for display.
	 *
	 * Shipping is now shown as a line item in the products table.
	 *
	 * @return array
	 */
	public function get_totals() {
		$totals = array();

		// Calculate subtotal including shipping (since shipping is now a line item).
		$subtotal = $this->order->get_subtotal() + $this->order->get_shipping_total();

		$totals['subtotal'] = array(
			'label' => __( 'Subtotaal', 'bossier-calculator' ),
			'value' => $this->format_price( $subtotal ),
		);

		if ( $this->order->get_total_discount() > 0 ) {
			$totals['discount'] = array(
				'label' => __( 'Korting', 'bossier-calculator' ),
				'value' => '-' . $this->format_price( $this->order->get_total_discount() ),
			);
		}

		// VAT.
		$tax_totals = $this->order->get_tax_totals();
		foreach ( $tax_totals as $code => $tax ) {
			$totals[ 'tax_' . $code ] = array(
				'label' => $tax->label,
				'value' => $this->format_price( $tax->amount ),
			);
		}

		$totals['total'] = array(
			'label' => __( 'Totaal', 'bossier-calculator' ),
			'value' => $this->format_price( $this->order->get_total() ),
		);

		return $totals;
	}
}
