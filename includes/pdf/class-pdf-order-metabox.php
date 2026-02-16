<?php
/**
 * PDF Order Meta Box class.
 *
 * Adds PDF download buttons to WooCommerce order admin screen.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\PDF;

defined( 'ABSPATH' ) || exit;

/**
 * PDF_Order_Metabox class - Manages PDF download buttons in order admin.
 */
class PDF_Order_Metabox {

	/**
	 * Singleton instance.
	 *
	 * @var PDF_Order_Metabox|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return PDF_Order_Metabox
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
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'admin_init', array( $this, 'handle_pdf_download' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
	}

	/**
	 * Add meta box to order screen.
	 */
	public function add_meta_box() {
		$screen = $this->get_order_screen();

		if ( ! $screen ) {
			return;
		}

		add_meta_box(
			'boost-pdf-documents',
			__( 'PDF Documenten', 'bossier-calculator' ),
			array( $this, 'render_meta_box' ),
			$screen,
			'side',
			'high'
		);
	}

	/**
	 * Get the order screen ID.
	 *
	 * @return string|false
	 */
	private function get_order_screen() {
		// Support for HPOS (High Performance Order Storage).
		if ( class_exists( 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) ) {
			$controller = wc_get_container()->get( 'Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' );
			if ( $controller && $controller->custom_orders_table_usage_is_enabled() ) {
				return wc_get_page_screen_id( 'shop-order' );
			}
		}

		return 'shop_order';
	}

	/**
	 * Enqueue admin styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_styles( $hook ) {
		global $post, $theorder;

		// Check if we're on an order edit screen.
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		if ( ! in_array( $screen->id, array( 'shop_order', wc_get_page_screen_id( 'shop-order' ) ), true ) ) {
			return;
		}

		wp_add_inline_style( 'woocommerce_admin_styles', '
			#boost-pdf-documents .inside {
				padding: 0;
				margin: 0;
			}
			#boost-pdf-documents .hndle {
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				color: #fff;
				border: none;
			}
			#boost-pdf-documents .hndle span {
				color: #fff;
			}
			.boost-pdf-wrapper {
				padding: 15px;
				background: #f8f9fa;
			}
			.boost-pdf-buttons {
				display: flex;
				flex-direction: column;
				gap: 10px;
			}
			.boost-pdf-button {
				display: flex;
				align-items: center;
				padding: 12px 16px;
				background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
				border: none;
				border-radius: 8px;
				text-decoration: none;
				color: #fff;
				font-size: 13px;
				font-weight: 500;
				transition: all 0.3s ease;
				box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
			}
			.boost-pdf-button:hover {
				transform: translateY(-2px);
				box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
				color: #fff;
			}
			.boost-pdf-button:active {
				transform: translateY(0);
			}
			.boost-pdf-button.invoice {
				background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
				box-shadow: 0 2px 8px rgba(17, 153, 142, 0.3);
			}
			.boost-pdf-button.invoice:hover {
				box-shadow: 0 4px 12px rgba(17, 153, 142, 0.4);
			}
			.boost-pdf-button.packing-slip {
				background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
				box-shadow: 0 2px 8px rgba(79, 172, 254, 0.3);
			}
			.boost-pdf-button.packing-slip:hover {
				box-shadow: 0 4px 12px rgba(79, 172, 254, 0.4);
			}
			.boost-pdf-button .dashicons {
				margin-right: 10px;
				color: #fff;
				font-size: 18px;
				width: 18px;
				height: 18px;
			}
			.boost-pdf-button .button-text {
				flex: 1;
			}
			.boost-pdf-button .status-icon {
				margin-left: auto;
				color: #fff;
				opacity: 0.9;
				background: rgba(255,255,255,0.2);
				border-radius: 50%;
				padding: 2px;
			}
			.boost-pdf-info {
				margin-top: 12px;
				padding: 10px 12px;
				background: #fff;
				border-radius: 6px;
				border-left: 3px solid #667eea;
			}
			.boost-pdf-info-row {
				display: flex;
				justify-content: space-between;
				align-items: center;
				font-size: 12px;
				color: #555;
			}
			.boost-pdf-info-row + .boost-pdf-info-row {
				margin-top: 6px;
				padding-top: 6px;
				border-top: 1px solid #eee;
			}
			.boost-pdf-info-label {
				color: #888;
			}
			.boost-pdf-info-value {
				font-weight: 600;
				color: #333;
			}
		' );
	}

	/**
	 * Render meta box content.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order Post object or order object.
	 */
	public function render_meta_box( $post_or_order ) {
		$order = $this->get_order_from_post_or_order( $post_or_order );

		if ( ! $order ) {
			echo '<p>' . esc_html__( 'Order niet gevonden.', 'bossier-calculator' ) . '</p>';
			return;
		}

		$order_id = $order->get_id();

		// Check if features are enabled.
		$invoices_enabled      = get_option( 'boost_pdf_invoices_enabled', 'yes' ) === 'yes';
		$packing_slips_enabled = get_option( 'boost_pdf_packing_slips_enabled', 'yes' ) === 'yes';

		if ( ! $invoices_enabled && ! $packing_slips_enabled ) {
			echo '<p>' . esc_html__( 'PDF generatie is uitgeschakeld.', 'bossier-calculator' ) . '</p>';
			return;
		}

		// Check if invoice already exists.
		$invoice_number = $order->get_meta( '_boost_invoice_number' );
		$invoice_date   = $order->get_meta( '_boost_invoice_date' );
		?>
		<style>
			#boost-pdf-documents .inside { margin: 0; padding: 0; }
			.boost-pdf-wrapper { padding: 12px; background: #f6f7f7; }
			.boost-pdf-buttons { display: flex; flex-direction: column; gap: 8px; }
			.boost-pdf-btn {
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 8px;
				padding: 10px 16px;
				border-radius: 6px;
				text-decoration: none;
				font-size: 13px;
				font-weight: 500;
				cursor: pointer;
				transition: all 0.2s ease;
				border: none;
			}
			.boost-pdf-btn-invoice {
				background: linear-gradient(135deg, #10b981 0%, #059669 100%);
				color: #fff !important;
				box-shadow: 0 2px 4px rgba(16, 185, 129, 0.3);
			}
			.boost-pdf-btn-invoice:hover {
				background: linear-gradient(135deg, #059669 0%, #047857 100%);
				color: #fff !important;
				transform: translateY(-1px);
				box-shadow: 0 4px 8px rgba(16, 185, 129, 0.4);
			}
			.boost-pdf-btn-packing {
				background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
				color: #fff !important;
				box-shadow: 0 2px 4px rgba(59, 130, 246, 0.3);
			}
			.boost-pdf-btn-packing:hover {
				background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
				color: #fff !important;
				transform: translateY(-1px);
				box-shadow: 0 4px 8px rgba(59, 130, 246, 0.4);
			}
			.boost-pdf-btn .dashicons {
				font-size: 16px;
				width: 16px;
				height: 16px;
				line-height: 16px;
			}
			.boost-pdf-btn .boost-check {
				margin-left: auto;
				background: rgba(255,255,255,0.3);
				border-radius: 50%;
				width: 18px;
				height: 18px;
				display: flex;
				align-items: center;
				justify-content: center;
			}
			.boost-pdf-btn .boost-check .dashicons {
				font-size: 12px;
				width: 12px;
				height: 12px;
			}
			.boost-pdf-info {
				margin-top: 10px;
				padding: 10px;
				background: #fff;
				border-radius: 6px;
				border-left: 3px solid #3b82f6;
				font-size: 12px;
			}
			.boost-pdf-info-row {
				display: flex;
				justify-content: space-between;
				padding: 4px 0;
			}
			.boost-pdf-info-row + .boost-pdf-info-row {
				border-top: 1px solid #eee;
				margin-top: 4px;
				padding-top: 8px;
			}
			.boost-pdf-info-label { color: #6b7280; }
			.boost-pdf-info-value { font-weight: 600; color: #111827; }
		</style>
		<div class="boost-pdf-wrapper">
			<div class="boost-pdf-buttons">
				<?php if ( $invoices_enabled ) : ?>
					<a href="<?php echo esc_url( $this->get_download_url( $order_id, 'invoice' ) ); ?>"
					   class="boost-pdf-btn boost-pdf-btn-invoice"
					   target="_blank">
						<span class="dashicons dashicons-media-document"></span>
						<span><?php esc_html_e( 'Factuur downloaden', 'bossier-calculator' ); ?></span>
						<?php if ( $invoice_number ) : ?>
							<span class="boost-check"><span class="dashicons dashicons-yes"></span></span>
						<?php endif; ?>
					</a>
				<?php endif; ?>

				<?php if ( $packing_slips_enabled ) : ?>
					<a href="<?php echo esc_url( $this->get_download_url( $order_id, 'packing-slip' ) ); ?>"
					   class="boost-pdf-btn boost-pdf-btn-packing"
					   target="_blank">
						<span class="dashicons dashicons-clipboard"></span>
						<span><?php esc_html_e( 'Pakbon downloaden', 'bossier-calculator' ); ?></span>
					</a>
				<?php endif; ?>
			</div>

			<?php if ( $invoice_number ) : ?>
				<div class="boost-pdf-info">
					<div class="boost-pdf-info-row">
						<span class="boost-pdf-info-label"><?php esc_html_e( 'Factuurnummer', 'bossier-calculator' ); ?></span>
						<span class="boost-pdf-info-value"><?php echo esc_html( $invoice_number ); ?></span>
					</div>
					<?php if ( $invoice_date ) : ?>
						<div class="boost-pdf-info-row">
							<span class="boost-pdf-info-label"><?php esc_html_e( 'Factuurdatum', 'bossier-calculator' ); ?></span>
							<span class="boost-pdf-info-value"><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $invoice_date ) ) ); ?></span>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get order from post or order object.
	 *
	 * @param \WP_Post|\WC_Order $post_or_order Post or order object.
	 * @return \WC_Order|false
	 */
	private function get_order_from_post_or_order( $post_or_order ) {
		if ( $post_or_order instanceof \WC_Order ) {
			return $post_or_order;
		}

		if ( $post_or_order instanceof \WP_Post ) {
			return wc_get_order( $post_or_order->ID );
		}

		return false;
	}

	/**
	 * Get PDF download URL.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $type     Document type (invoice, packing-slip).
	 * @return string
	 */
	public function get_download_url( $order_id, $type ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'boost_pdf_download' => $type,
					'order_id'           => $order_id,
				),
				admin_url( 'admin.php' )
			),
			'boost_pdf_download_' . $order_id
		);
	}

	/**
	 * Handle PDF download request.
	 */
	public function handle_pdf_download() {
		if ( ! isset( $_GET['boost_pdf_download'] ) || ! isset( $_GET['order_id'] ) ) {
			return;
		}

		$type     = sanitize_text_field( wp_unslash( $_GET['boost_pdf_download'] ) );
		$order_id = absint( $_GET['order_id'] );

		// Verify nonce.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'boost_pdf_download_' . $order_id ) ) {
			wp_die( esc_html__( 'Beveiligingscontrole mislukt.', 'bossier-calculator' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'Onvoldoende rechten.', 'bossier-calculator' ) );
		}

		// Validate document type.
		if ( ! in_array( $type, array( 'invoice', 'packing-slip' ), true ) ) {
			wp_die( esc_html__( 'Ongeldig documenttype.', 'bossier-calculator' ) );
		}

		// Get order.
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_die( esc_html__( 'Order niet gevonden.', 'bossier-calculator' ) );
		}

		// Generate and output PDF.
		$this->output_pdf( $order, $type );
	}

	/**
	 * Output PDF for download.
	 *
	 * @param \WC_Order $order Order object.
	 * @param string    $type  Document type.
	 */
	private function output_pdf( $order, $type ) {
		// Load PDF classes.
		require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/autoload.php';
		require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/class-pdf-generator.php';

		if ( 'invoice' === $type ) {
			require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/class-invoice.php';

			$document = new Invoice( $order );
			$document->generate();
			$document->stream( $document->get_filename() );

		} elseif ( 'packing-slip' === $type ) {
			require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/pdf/class-packing-slip.php';

			$document = new Packing_Slip( $order );
			$document->generate();
			$document->stream( $document->get_filename() );
		}

		exit;
	}
}
