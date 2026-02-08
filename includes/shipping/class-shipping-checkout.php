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

        // Rename "Shipment 1" to "Bezorging"
        add_filter( 'woocommerce_shipping_package_name', array( $this, 'rename_shipping_package' ), 10, 3 );

        // Save shipping choice to order (based on selected WC shipping method)
        add_action( 'woocommerce_checkout_create_order', array( $this, 'save_shipping_choice_to_order' ), 10, 2 );

        // Display shipping choice in admin
        add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_shipping_choice_admin' ) );

        // Enqueue styles only (no custom JS needed for WC radios)
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Display shipping breakdown (surcharges) in cart/checkout totals
        add_action( 'woocommerce_cart_totals_after_shipping', array( $this, 'display_shipping_breakdown' ) );
        add_action( 'woocommerce_review_order_after_shipping', array( $this, 'display_shipping_breakdown' ) );
    }

    /**
     * Rename shipping package from "Shipment 1" to "Bezorging".
     *
     * @param string $name        Default package name.
     * @param int    $i           Package index.
     * @param array  $package     Package data.
     * @return string Modified package name.
     */
    public function rename_shipping_package( $name, $i, $package ) {
        return __( 'Bezorging', 'bossier-calculator' );
    }

    /**
     * Save shipping choice to order based on selected WC shipping method.
     *
     * @param WC_Order $order Order object.
     * @param array    $data  Posted data.
     */
    public function save_shipping_choice_to_order( $order, $data ) {
        // Determine choice from the actual shipping method selected
        $shipping_methods = $order->get_shipping_methods();
        $choice = 'shipping'; // Default

        foreach ( $shipping_methods as $shipping ) {
            $method_id = $shipping->get_method_id();
            if ( strpos( $method_id, 'pickup' ) !== false ) {
                $choice = 'pickup';
                break;
            }
        }

        $order->update_meta_data( '_boost_shipping_choice', $choice );
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
     * Enqueue checkout styles.
     */
    public function enqueue_scripts() {
        if ( ! is_checkout() && ! is_cart() ) {
            return;
        }

        // Only enqueue minimal styles for WC shipping display
        wp_enqueue_style(
            'boost-shipping-checkout',
            BOSSIER_CALC_PLUGIN_URL . 'assets/css/shipping-checkout.css',
            array(),
            BOSSIER_CALC_VERSION
        );
    }

    /**
     * Display shipping breakdown (surcharges) in cart/checkout totals.
     *
     * Shows extra surcharges like oversized length fees separately.
     */
    public function display_shipping_breakdown() {
        if ( ! WC()->cart ) {
            return;
        }

        $packages = WC()->shipping()->get_packages();

        foreach ( $packages as $package ) {
            if ( empty( $package['rates'] ) ) {
                continue;
            }

            $chosen_method = WC()->session ? WC()->session->get( 'chosen_shipping_methods' ) : array();

            foreach ( $package['rates'] as $rate_id => $rate ) {
                // Only show breakdown for the selected shipping rate
                if ( ! empty( $chosen_method ) && ! in_array( $rate_id, $chosen_method, true ) ) {
                    continue;
                }

                // Check if this is a Boost shipping rate with breakdown
                $meta_data = $rate->get_meta_data();
                if ( empty( $meta_data['breakdown'] ) || ! is_array( $meta_data['breakdown'] ) ) {
                    continue;
                }

                $breakdown = $meta_data['breakdown'];

                // Display oversized surcharge items separately
                foreach ( $breakdown as $item ) {
                    // Show oversized surcharge as separate line
                    if ( 'oversized' === ( $item['type'] ?? '' ) && ! empty( $item['cost'] ) && floatval( $item['cost'] ) > 0 ) {
                        $surcharge = floatval( $item['cost'] );
                        $label     = $item['description'] ?? __( 'Toeslag lang product', 'bossier-calculator' );

                        echo '<tr class="boost-shipping-surcharge">';
                        echo '<th>' . esc_html( $label ) . '</th>';
                        echo '<td data-title="' . esc_attr( $label ) . '">' . wp_kses_post( wc_price( $surcharge ) ) . ' <small style="color: #6b7280;">' . esc_html__( '(inbegrepen)', 'bossier-calculator' ) . '</small></td>';
                        echo '</tr>';
                    }
                }
            }
        }
    }
}
