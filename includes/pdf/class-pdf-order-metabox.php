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
			.boost-pdf-buttons {
				display: flex;
				flex-direction: column;
				gap: 8px;
			}
			.boost-pdf-button {
				display: flex;
				align-items: center;
				padding: 8px 12px;
				background: #f0f0f0;
				border: 1px solid #ddd;
				border-radius: 4px;
				text-decoration: none;
				color: #333;
				font-size: 13px;
				transition: all 0.2s ease;
			}
			.boost-pdf-button:hover {
				background: #e0e0e0;
				border-color: #ccc;
				color: #333;
			}
			.boost-pdf-button .dashicons {
				margin-right: 8px;
				color: #0073aa;
			}
			.boost-pdf-button.invoice .dashicons {
				color: #2271b1;
			}
			.boost-pdf-button.packing-slip .dashicons {
				color: #135e96;
			}
			.boost-pdf-button .status-icon {
				margin-left: auto;
				color: #46b450;
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
		?>
		<div class="boost-pdf-buttons">
			<?php if ( $invoices_enabled ) : ?>
				<a href="<?php echo esc_url( $this->get_download_url( $order_id, 'invoice' ) ); ?>"
				   class="boost-pdf-button invoice"
				   target="_blank">
					<span class="dashicons dashicons-media-document"></span>
					<?php esc_html_e( 'Factuur downloaden', 'bossier-calculator' ); ?>
					<?php if ( $invoice_number ) : ?>
						<span class="status-icon dashicons dashicons-yes-alt"></span>
					<?php endif; ?>
				</a>
			<?php endif; ?>

			<?php if ( $packing_slips_enabled ) : ?>
				<a href="<?php echo esc_url( $this->get_download_url( $order_id, 'packing-slip' ) ); ?>"
				   class="boost-pdf-button packing-slip"
				   target="_blank">
					<span class="dashicons dashicons-clipboard"></span>
					<?php esc_html_e( 'Pakbon downloaden', 'bossier-calculator' ); ?>
					<span class="status-icon dashicons dashicons-yes-alt"></span>
				</a>
			<?php endif; ?>
		</div>

		<?php if ( $invoice_number ) : ?>
			<p style="margin-top: 10px; font-size: 12px; color: #666;">
				<?php printf( esc_html__( 'Factuurnummer: %s', 'bossier-calculator' ), '<strong>' . esc_html( $invoice_number ) . '</strong>' ); ?>
			</p>
		<?php endif; ?>
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

		$type     = sanitize_text_field( $_GET['boost_pdf_download'] );
		$order_id = absint( $_GET['order_id'] );

		// Verify nonce.
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'boost_pdf_download_' . $order_id ) ) {
			wp_die( __( 'Beveiligingscontrole mislukt.', 'bossier-calculator' ) );
		}

		// Check capabilities.
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( __( 'Onvoldoende rechten.', 'bossier-calculator' ) );
		}

		// Get order.
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			wp_die( __( 'Order niet gevonden.', 'bossier-calculator' ) );
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
