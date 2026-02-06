<?php
/**
 * PDF Integration class.
 *
 * Integrates calculator data with WooCommerce PDF Invoices & Packing Slips plugin.
 * Shows calculator configuration on packing slips only (not on invoices).
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator;

defined( 'ABSPATH' ) || exit;

/**
 * PDF_Integration class - Handles PDF packing slip integration.
 */
class PDF_Integration {

	/**
	 * Singleton instance.
	 *
	 * @var PDF_Integration|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return PDF_Integration
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
		// Only initialize if the PDF plugin is active.
		if ( ! $this->is_pdf_plugin_active() ) {
			return;
		}

		// Hook into PDF item meta output.
		add_action( 'wpo_wcpdf_after_item_meta', array( $this, 'add_calculator_data_to_packing_slip' ), 10, 3 );
	}

	/**
	 * Check if PDF Invoices & Packing Slips plugin is active.
	 *
	 * @return bool
	 */
	private function is_pdf_plugin_active() {
		return class_exists( 'WPO_WCPDF' ) || defined( 'WPO_WCPDF_VERSION' );
	}

	/**
	 * Add calculator data to packing slip item output.
	 *
	 * @param string    $document_type Document type ('packing-slip', 'invoice', etc.).
	 * @param array     $item          Item data array from the PDF plugin.
	 * @param \WC_Order $order         WooCommerce order object.
	 */
	public function add_calculator_data_to_packing_slip( $document_type, $item, $order ) {
		// Only show on packing slips, NOT on invoices.
		if ( 'packing-slip' !== $document_type ) {
			return;
		}

		// Get the order item ID from the item array.
		if ( ! isset( $item['item_id'] ) ) {
			return;
		}

		$item_id = $item['item_id'];

		// Get the WC_Order_Item_Product object.
		$order_item = $order->get_item( $item_id );

		if ( ! $order_item || ! $order_item instanceof \WC_Order_Item_Product ) {
			return;
		}

		// Check if this item has calculator data.
		$display_data = $order_item->get_meta( '_bossier_display_data' );

		if ( empty( $display_data ) || ! is_array( $display_data ) ) {
			return;
		}

		// Render the calculator configuration.
		$this->render_calculator_config( $display_data );
	}

	/**
	 * Render calculator configuration for packing slip.
	 *
	 * @param array $display_data Calculator display data.
	 */
	private function render_calculator_config( $display_data ) {
		if ( empty( $display_data ) ) {
			return;
		}

		echo '<div class="bossier-calculator-config" style="margin-top: 5px; font-size: 0.9em; color: #666;">';

		foreach ( $display_data as $field_id => $field_data ) {
			if ( empty( $field_data['value'] ) ) {
				continue;
			}

			$label = isset( $field_data['label'] ) ? $field_data['label'] : $field_id;
			$value = $field_data['value'];

			printf(
				'<div class="calc-field" style="margin: 2px 0;"><strong>%s:</strong> %s</div>',
				esc_html( $label ),
				esc_html( $value )
			);
		}

		echo '</div>';
	}
}
