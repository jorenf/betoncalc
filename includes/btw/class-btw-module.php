<?php
/**
 * BTW Verlegd (Reverse Charge VAT) Module.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\BTW;

use Bossier\Calculator\Modules_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * BTW_Module class - Main BTW Verlegd functionality.
 */
class BTW_Module {

    /**
     * Single instance of the class.
     *
     * @var BTW_Module|null
     */
    private static $instance = null;

    /**
     * Get single instance of the class.
     *
     * @return BTW_Module
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
        // Load required files
        require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/btw/class-vies-validator.php';
        require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/btw/class-btw-checkout.php';

        // Initialize components
        BTW_Checkout::get_instance();

        // Hook into WooCommerce
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Disable WooCommerce tax if configured
        $settings = Modules_Settings::get_settings();
        if ( ! empty( $settings['btw_disable_wc_tax'] ) ) {
            add_filter( 'woocommerce_calc_tax', '__return_empty_array', 999 );
            add_filter( 'woocommerce_product_tax_class', array( $this, 'disable_tax_class' ), 999 );
        }

        // Apply reverse charge exemption
        add_action( 'woocommerce_checkout_update_order_review', array( $this, 'maybe_apply_reverse_charge' ) );
        add_filter( 'woocommerce_cart_get_taxes', array( $this, 'maybe_zero_taxes' ), 999 );
        add_filter( 'woocommerce_calculated_total', array( $this, 'recalculate_total_after_exemption' ), 999, 2 );

        // Save VAT data to order
        add_action( 'woocommerce_checkout_create_order', array( $this, 'save_vat_data_to_order' ), 10, 2 );

        // Display VAT info in admin
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_vat_in_admin' ) );

        // Add reverse charge text to invoices
        add_filter( 'boost_pdf_invoice_data', array( $this, 'add_reverse_charge_to_invoice' ), 10, 2 );

        // AJAX handler for VAT validation
        add_action( 'wp_ajax_boost_validate_vat', array( $this, 'ajax_validate_vat' ) );
        add_action( 'wp_ajax_nopriv_boost_validate_vat', array( $this, 'ajax_validate_vat' ) );
    }

    /**
     * Disable tax class when WC tax is disabled.
     *
     * @param string $tax_class Tax class.
     * @return string
     */
    public function disable_tax_class( $tax_class ) {
        return 'zero-rate';
    }

    /**
     * Check if reverse charge should be applied.
     *
     * @return bool
     */
    public static function should_apply_reverse_charge() {
        // Check if business order
        if ( empty( WC()->session ) ) {
            return false;
        }

        $is_business = WC()->session->get( 'boost_is_business_order' );
        if ( ! $is_business ) {
            return false;
        }

        // Check if valid VAT number
        $vat_valid = WC()->session->get( 'boost_vat_valid' );
        if ( ! $vat_valid ) {
            return false;
        }

        // Check if foreign (non-NL) address
        $billing_country = WC()->customer ? WC()->customer->get_billing_country() : '';
        if ( 'NL' === $billing_country || empty( $billing_country ) ) {
            return false;
        }

        return true;
    }

    /**
     * Maybe apply reverse charge during checkout.
     *
     * @param string $post_data Posted data.
     */
    public function maybe_apply_reverse_charge( $post_data ) {
        parse_str( $post_data, $data );

        $is_business = ! empty( $data['boost_is_business'] );
        $vat_number  = isset( $data['boost_vat_number'] ) ? sanitize_text_field( $data['boost_vat_number'] ) : '';

        WC()->session->set( 'boost_is_business_order', $is_business );
        WC()->session->set( 'boost_vat_number', $vat_number );

        // Validate VAT number if provided
        if ( $is_business && ! empty( $vat_number ) ) {
            $validator = new VIES_Validator();
            $result    = $validator->validate( $vat_number );

            WC()->session->set( 'boost_vat_valid', $result['valid'] );
            WC()->session->set( 'boost_vat_company', $result['company_name'] ?? '' );
        } else {
            WC()->session->set( 'boost_vat_valid', false );
            WC()->session->set( 'boost_vat_company', '' );
        }
    }

    /**
     * Zero out taxes if reverse charge applies.
     *
     * @param array $taxes Taxes array.
     * @return array
     */
    public function maybe_zero_taxes( $taxes ) {
        if ( self::should_apply_reverse_charge() ) {
            return array();
        }
        return $taxes;
    }

    /**
     * Recalculate total after tax exemption.
     *
     * @param float    $total Cart total.
     * @param WC_Cart $cart  Cart object.
     * @return float
     */
    public function recalculate_total_after_exemption( $total, $cart ) {
        if ( self::should_apply_reverse_charge() ) {
            // Remove tax from total
            $tax_total = $cart->get_total_tax();
            $total     = $total - $tax_total;
        }
        return $total;
    }

    /**
     * Save VAT data to order.
     *
     * @param WC_Order $order Order object.
     * @param array    $data  Posted data.
     */
    public function save_vat_data_to_order( $order, $data ) {
        if ( ! WC()->session ) {
            return;
        }

        $is_business = WC()->session->get( 'boost_is_business_order' );
        $vat_number  = WC()->session->get( 'boost_vat_number' );
        $vat_valid   = WC()->session->get( 'boost_vat_valid' );
        $vat_company = WC()->session->get( 'boost_vat_company' );

        $order->update_meta_data( '_boost_is_business_order', $is_business ? 'yes' : 'no' );
        $order->update_meta_data( '_boost_vat_number', $vat_number );
        $order->update_meta_data( '_boost_vat_valid', $vat_valid ? 'yes' : 'no' );
        $order->update_meta_data( '_boost_vat_company', $vat_company );
        $order->update_meta_data( '_boost_reverse_charge', self::should_apply_reverse_charge() ? 'yes' : 'no' );

        // Clear session
        WC()->session->set( 'boost_is_business_order', null );
        WC()->session->set( 'boost_vat_number', null );
        WC()->session->set( 'boost_vat_valid', null );
        WC()->session->set( 'boost_vat_company', null );
    }

    /**
     * Display VAT information in admin order page.
     *
     * @param WC_Order $order Order object.
     */
    public function display_vat_in_admin( $order ) {
        $is_business    = $order->get_meta( '_boost_is_business_order' );
        $vat_number     = $order->get_meta( '_boost_vat_number' );
        $vat_valid      = $order->get_meta( '_boost_vat_valid' );
        $reverse_charge = $order->get_meta( '_boost_reverse_charge' );

        if ( 'yes' !== $is_business ) {
            return;
        }

        echo '<div class="boost-vat-admin-info" style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-left: 4px solid #2271b1;">';
        echo '<h4 style="margin: 0 0 8px 0;">' . esc_html__( 'BTW Informatie', 'bossier-calculator' ) . '</h4>';
        echo '<p style="margin: 0;"><strong>' . esc_html__( 'Zakelijke bestelling:', 'bossier-calculator' ) . '</strong> ' . esc_html__( 'Ja', 'bossier-calculator' ) . '</p>';

        if ( $vat_number ) {
            echo '<p style="margin: 5px 0 0 0;"><strong>' . esc_html__( 'BTW-nummer:', 'bossier-calculator' ) . '</strong> ' . esc_html( $vat_number );
            if ( 'yes' === $vat_valid ) {
                echo ' <span style="color: #00a32a;">&#10003; ' . esc_html__( 'Gevalideerd', 'bossier-calculator' ) . '</span>';
            } else {
                echo ' <span style="color: #d63638;">&#10007; ' . esc_html__( 'Niet gevalideerd', 'bossier-calculator' ) . '</span>';
            }
            echo '</p>';
        }

        if ( 'yes' === $reverse_charge ) {
            echo '<p style="margin: 5px 0 0 0; color: #00a32a;"><strong>' . esc_html__( 'BTW Verlegd:', 'bossier-calculator' ) . '</strong> ' . esc_html__( 'Ja (0% BTW)', 'bossier-calculator' ) . '</p>';
        }

        echo '</div>';
    }

    /**
     * Add reverse charge text to PDF invoice.
     *
     * @param array    $data  Invoice data.
     * @param WC_Order $order Order object.
     * @return array
     */
    public function add_reverse_charge_to_invoice( $data, $order ) {
        $reverse_charge = $order->get_meta( '_boost_reverse_charge' );

        if ( 'yes' === $reverse_charge ) {
            $settings    = Modules_Settings::get_settings();
            $invoice_text = ! empty( $settings['btw_custom_invoice_text'] )
                ? $settings['btw_custom_invoice_text']
                : $settings['btw_invoice_text'];

            $data['reverse_charge']      = true;
            $data['reverse_charge_text'] = $invoice_text;
            $data['vat_number']          = $order->get_meta( '_boost_vat_number' );
        }

        return $data;
    }

    /**
     * AJAX handler for VAT validation.
     */
    public function ajax_validate_vat() {
        check_ajax_referer( 'boost_vat_nonce', 'nonce' );

        $vat_number = isset( $_POST['vat_number'] ) ? sanitize_text_field( wp_unslash( $_POST['vat_number'] ) ) : '';

        if ( empty( $vat_number ) ) {
            wp_send_json_error( array( 'message' => __( 'BTW-nummer is verplicht.', 'bossier-calculator' ) ) );
        }

        $validator = new VIES_Validator();
        $result    = $validator->validate( $vat_number );

        if ( $result['valid'] ) {
            wp_send_json_success( array(
                'valid'        => true,
                'company_name' => $result['company_name'],
                'address'      => $result['address'],
            ) );
        } else {
            wp_send_json_error( array(
                'valid'   => false,
                'message' => $result['error'] ?? __( 'BTW-nummer kon niet worden gevalideerd.', 'bossier-calculator' ),
            ) );
        }
    }
}
