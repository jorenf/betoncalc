<?php
/**
 * Shipping Checkout Integration.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\Shipping;

use Bossier\Calculator\Modules_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Shipping_Checkout class - Adds shipping choice to WooCommerce checkout.
 */
class Shipping_Checkout {

    /**
     * Single instance of the class.
     *
     * @var Shipping_Checkout|null
     */
    private static $instance = null;

    /**
     * Module settings.
     *
     * @var array
     */
    private $settings;

    /**
     * Get single instance of the class.
     *
     * @return Shipping_Checkout
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
        $this->settings = Modules_Settings::get_settings();

        // Add shipping method choice before shipping section
        add_action( 'woocommerce_review_order_before_shipping', array( $this, 'add_shipping_choice' ) );

        // Handle shipping choice changes via AJAX
        add_action( 'woocommerce_checkout_update_order_review', array( $this, 'save_shipping_choice' ) );

        // Modify available shipping methods based on choice
        add_filter( 'woocommerce_package_rates', array( $this, 'filter_shipping_methods' ), 100, 2 );

        // Validate shipping choice is selected
        add_action( 'woocommerce_checkout_process', array( $this, 'validate_shipping_choice' ) );

        // Save shipping choice to order
        add_action( 'woocommerce_checkout_create_order', array( $this, 'save_shipping_choice_to_order' ), 10, 2 );

        // Display shipping choice in admin
        add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_shipping_choice_admin' ) );

        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Add shipping choice before shipping section.
     */
    public function add_shipping_choice() {
        $pickup_enabled = ! empty( $this->settings['shipping_pickup_enabled'] );
        $pickup_address = $this->settings['shipping_pickup_address'] ?? '';

        // Get current choice from session
        $current_choice = 'shipping'; // Default to shipping
        if ( WC()->session ) {
            $current_choice = WC()->session->get( 'boost_shipping_choice', 'shipping' );
        }

        ?>
        <tr class="boost-shipping-choice-row">
            <th><?php esc_html_e( 'Bezorging', 'bossier-calculator' ); ?></th>
            <td>
                <div class="boost-shipping-choice">
                    <label class="boost-shipping-option <?php echo 'shipping' === $current_choice ? 'selected' : ''; ?>">
                        <input type="radio"
                               name="boost_shipping_choice"
                               value="shipping"
                               <?php checked( $current_choice, 'shipping' ); ?>>
                        <span class="boost-shipping-option-content">
                            <span class="boost-shipping-option-icon">🚚</span>
                            <span class="boost-shipping-option-text">
                                <strong><?php esc_html_e( 'Verzenden', 'bossier-calculator' ); ?></strong>
                                <small><?php esc_html_e( 'Bezorgen op uw adres', 'bossier-calculator' ); ?></small>
                            </span>
                        </span>
                    </label>

                    <?php if ( $pickup_enabled ) : ?>
                    <label class="boost-shipping-option <?php echo 'pickup' === $current_choice ? 'selected' : ''; ?>">
                        <input type="radio"
                               name="boost_shipping_choice"
                               value="pickup"
                               <?php checked( $current_choice, 'pickup' ); ?>>
                        <span class="boost-shipping-option-content">
                            <span class="boost-shipping-option-icon">📍</span>
                            <span class="boost-shipping-option-text">
                                <strong><?php esc_html_e( 'Afhalen', 'bossier-calculator' ); ?></strong>
                                <small><?php esc_html_e( 'Gratis - Ophalen op locatie', 'bossier-calculator' ); ?></small>
                            </span>
                        </span>
                    </label>
                    <?php endif; ?>
                </div>

                <?php if ( $pickup_enabled && ! empty( $pickup_address ) ) : ?>
                <div class="boost-pickup-address" style="<?php echo 'pickup' !== $current_choice ? 'display:none;' : ''; ?>">
                    <p><strong><?php esc_html_e( 'Afhaaladres:', 'bossier-calculator' ); ?></strong></p>
                    <address><?php echo nl2br( esc_html( $pickup_address ) ); ?></address>
                </div>
                <?php endif; ?>

                <div class="boost-shipping-notice" style="<?php echo 'shipping' !== $current_choice ? 'display:none;' : ''; ?>">
                    <?php
                    // Check if we can calculate shipping
                    $postcode = WC()->customer ? WC()->customer->get_shipping_postcode() : '';
                    $country  = WC()->customer ? WC()->customer->get_shipping_country() : '';

                    if ( empty( $postcode ) || empty( $country ) ) : ?>
                        <p class="boost-shipping-pending">
                            <span class="dashicons dashicons-info"></span>
                            <?php esc_html_e( 'Vul uw adresgegevens in om verzendkosten te berekenen.', 'bossier-calculator' ); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * Save shipping choice from checkout update.
     *
     * @param string $post_data Posted data.
     */
    public function save_shipping_choice( $post_data ) {
        parse_str( $post_data, $data );

        $choice = isset( $data['boost_shipping_choice'] ) ? sanitize_key( $data['boost_shipping_choice'] ) : 'shipping';

        if ( ! in_array( $choice, array( 'shipping', 'pickup' ), true ) ) {
            $choice = 'shipping';
        }

        if ( WC()->session ) {
            WC()->session->set( 'boost_shipping_choice', $choice );
        }
    }

    /**
     * Filter shipping methods based on choice.
     *
     * @param array $rates   Shipping rates.
     * @param array $package Package data.
     * @return array
     */
    public function filter_shipping_methods( $rates, $package ) {
        $choice = 'shipping';
        if ( WC()->session ) {
            $choice = WC()->session->get( 'boost_shipping_choice', 'shipping' );
        }

        if ( 'pickup' === $choice ) {
            // Only show local pickup (free)
            $pickup_rates = array();

            // Check if we have a local pickup rate
            foreach ( $rates as $rate_id => $rate ) {
                if ( strpos( $rate_id, 'local_pickup' ) !== false || strpos( $rate_id, 'pickup' ) !== false ) {
                    $pickup_rates[ $rate_id ] = $rate;
                }
            }

            // If no pickup rate exists, create one
            if ( empty( $pickup_rates ) ) {
                $pickup_rate = new \WC_Shipping_Rate(
                    'boost_pickup',
                    __( 'Afhalen (Gratis)', 'bossier-calculator' ),
                    0,
                    array(),
                    'boost_pickup'
                );
                $pickup_rates['boost_pickup'] = $pickup_rate;
            }

            return $pickup_rates;
        }

        // For shipping, filter out local pickup
        $shipping_rates = array();
        foreach ( $rates as $rate_id => $rate ) {
            if ( strpos( $rate_id, 'local_pickup' ) === false && strpos( $rate_id, 'pickup' ) === false ) {
                $shipping_rates[ $rate_id ] = $rate;
            }
        }

        return $shipping_rates;
    }

    /**
     * Validate shipping choice is selected.
     */
    public function validate_shipping_choice() {
        $choice = isset( $_POST['boost_shipping_choice'] ) ? sanitize_key( wp_unslash( $_POST['boost_shipping_choice'] ) ) : '';

        if ( empty( $choice ) || ! in_array( $choice, array( 'shipping', 'pickup' ), true ) ) {
            wc_add_notice( __( 'Selecteer een bezorgmethode (Verzenden of Afhalen).', 'bossier-calculator' ), 'error' );
        }
    }

    /**
     * Save shipping choice to order.
     *
     * @param WC_Order $order Order object.
     * @param array    $data  Posted data.
     */
    public function save_shipping_choice_to_order( $order, $data ) {
        $choice = 'shipping';
        if ( WC()->session ) {
            $choice = WC()->session->get( 'boost_shipping_choice', 'shipping' );
        }

        $order->update_meta_data( '_boost_shipping_choice', $choice );

        // Clear session
        if ( WC()->session ) {
            WC()->session->set( 'boost_shipping_choice', null );
        }
    }

    /**
     * Display shipping choice in admin.
     *
     * @param WC_Order $order Order object.
     */
    public function display_shipping_choice_admin( $order ) {
        $choice = $order->get_meta( '_boost_shipping_choice' );

        if ( empty( $choice ) ) {
            return;
        }

        $label = 'pickup' === $choice
            ? __( 'Afhalen', 'bossier-calculator' )
            : __( 'Verzenden', 'bossier-calculator' );

        echo '<p><strong>' . esc_html__( 'Leveringsmethode:', 'bossier-calculator' ) . '</strong> ' . esc_html( $label ) . '</p>';
    }

    /**
     * Enqueue checkout scripts.
     */
    public function enqueue_scripts() {
        if ( ! is_checkout() ) {
            return;
        }

        wp_enqueue_style(
            'boost-shipping-checkout',
            BOSSIER_CALC_PLUGIN_URL . 'assets/css/shipping-checkout.css',
            array(),
            BOSSIER_CALC_VERSION
        );

        wp_enqueue_script(
            'boost-shipping-checkout',
            BOSSIER_CALC_PLUGIN_URL . 'assets/js/shipping-checkout.js',
            array( 'jquery', 'wc-checkout' ),
            BOSSIER_CALC_VERSION,
            true
        );
    }
}
