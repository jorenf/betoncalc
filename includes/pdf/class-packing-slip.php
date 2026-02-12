<?php
/**
 * Packing Slip PDF class.
 *
 * Generates PDF packing slips for WooCommerce orders.
 * Includes calculator configuration (length, color, angle) for each item.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * Packing_Slip class - Generates packing slip PDFs.
 */
class Packing_Slip extends PDF_Generator {

	/**
	 * Document type.
	 *
	 * @var string
	 */
	protected $document_type = 'packing-slip';

	/**
	 * Generate the packing slip PDF.
	 *
	 * @return string PDF content.
	 */
	public function generate() {
		$html = $this->render_template();
		return $this->generate_from_html( $html );
	}

	/**
	 * Render the packing slip template.
	 *
	 * @return string HTML content.
	 */
	protected function render_template() {
		ob_start();

		// Variables available in template.
		$packing_slip = $this;
		$order        = $this->order;
		$company      = $this->get_company_data();

		// Load template.
		$template_path = BOSSIER_CALC_PLUGIN_DIR . 'templates/pdf/packing-slip.php';

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
		return sanitize_file_name( 'pakbon-' . $this->order->get_order_number() . '.pdf' );
	}

	/**
	 * Get order items for display with calculator data.
	 *
	 * @return array
	 */
	public function get_order_items() {
		$items = array();

		foreach ( $this->order->get_items() as $item_id => $item ) {
			$product = $item->get_product();

			$item_data = array(
				'item_id'         => $item_id,
				'name'            => $item->get_name(),
				'quantity'        => $item->get_quantity(),
				'sku'             => $product ? $product->get_sku() : '',
				'weight'          => $item->get_meta( '_bossier_calculated_weight' ),
				'calculator_data' => array(),
			);

			// Get calculator display data.
			$display_data = $item->get_meta( '_bossier_display_data' );

			if ( ! empty( $display_data ) && is_array( $display_data ) ) {
				$item_data['calculator_data'] = $this->format_calculator_data( $display_data );
			}

			$items[] = $item_data;
		}

		return $items;
	}

	/**
	 * Format calculator data for display on packing slip.
	 *
	 * @param array $display_data Raw calculator display data.
	 * @return array Formatted data.
	 */
	protected function format_calculator_data( $display_data ) {
		$formatted = array();

		foreach ( $display_data as $field_id => $field_data ) {
			if ( empty( $field_data['value'] ) ) {
				continue;
			}

			$formatted[] = array(
				'label' => isset( $field_data['label'] ) ? $field_data['label'] : $field_id,
				'value' => $field_data['value'],
				'type'  => isset( $field_data['type'] ) ? $field_data['type'] : '',
			);
		}

		return $formatted;
	}

	/**
	 * Collect all unique calculator column labels across all order items.
	 *
	 * Returns an ordered list of field labels to use as table columns.
	 * Skips types that don't belong in the packing table (quantity, text).
	 *
	 * @param array $items Order items from get_order_items().
	 * @return array Associative array of label => label.
	 */
	public function get_calculator_columns( $items ) {
		$columns = array();
		$skip_types = array( 'quantity', 'text' );

		foreach ( $items as $item ) {
			if ( empty( $item['calculator_data'] ) ) {
				continue;
			}
			foreach ( $item['calculator_data'] as $field ) {
				if ( in_array( $field['type'], $skip_types, true ) ) {
					continue;
				}
				$label = $field['label'];
				if ( ! isset( $columns[ $label ] ) ) {
					$columns[ $label ] = $label;
				}
			}
		}

		return $columns;
	}

	/**
	 * Check if calculator config should be shown.
	 *
	 * @return bool
	 */
	public function show_calculator_config() {
		return get_option( 'boost_pdf_packing_slip_show_config', 'yes' ) === 'yes';
	}

	/**
	 * Get shipping address HTML.
	 *
	 * @return string
	 */
	public function get_shipping_address() {
		$address = $this->order->get_formatted_shipping_address();

		// Fall back to billing address if no shipping address.
		if ( empty( $address ) ) {
			$address = $this->order->get_formatted_billing_address();
		}

		return $address;
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
	 * Get shipping method.
	 *
	 * @return string
	 */
	public function get_shipping_method() {
		return $this->order->get_shipping_method();
	}

	/**
	 * Get formatted order date.
	 *
	 * @return string
	 */
	public function get_formatted_order_date() {
		return $this->format_date( $this->order->get_date_created()->getTimestamp() );
	}
}
