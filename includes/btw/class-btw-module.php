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
        // Apply reverse charge exemption - zero tax only when reverse charge applies
        add_action( 'woocommerce_checkout_update_order_review', array( $this, 'maybe_apply_reverse_charge' ) );
        add_filter( 'woocommerce_product_get_tax_class', array( $this, 'maybe_apply_zero_tax_class' ), 999, 2 );
        add_filter( 'woocommerce_product_variation_get_tax_class', array( $this, 'maybe_apply_zero_tax_class' ), 999, 2 );

        // Save VAT data to order
        add_action( 'woocommerce_checkout_create_order', array( $this, 'save_vat_data_to_order' ), 10, 2 );

        // Send admin notification for reverse charge orders
        add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_send_admin_notification' ) );
        add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_send_admin_notification' ) );

        // Display VAT info in admin
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_vat_in_admin' ) );

        // Add reverse charge text to invoices
        add_filter( 'boost_pdf_invoice_data', array( $this, 'add_reverse_charge_to_invoice' ), 10, 2 );

        // AJAX handler for VAT validation
        add_action( 'wp_ajax_boost_validate_vat', array( $this, 'ajax_validate_vat' ) );
        add_action( 'wp_ajax_nopriv_boost_validate_vat', array( $this, 'ajax_validate_vat' ) );
    }

    /**
     * Apply zero tax class when reverse charge should apply.
     *
     * @param string     $tax_class Tax class.
     * @param WC_Product $product   Product object.
     * @return string
     */
    public function maybe_apply_zero_tax_class( $tax_class, $product ) {
        if ( self::should_apply_reverse_charge() ) {
            return 'zero-rate';
        }
        return $tax_class;
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

        // Check minimum amount if set
        $settings       = Modules_Settings::get_settings();
        $minimum_amount = floatval( $settings['btw_minimum_amount'] ?? 0 );

        if ( $minimum_amount > 0 && WC()->cart ) {
            $cart_total = WC()->cart->get_subtotal();
            if ( $cart_total < $minimum_amount ) {
                return false;
            }
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
     * Send admin email notification for reverse charge orders.
     *
     * @param int $order_id Order ID.
     */
    public function maybe_send_admin_notification( $order_id ) {
        $settings = Modules_Settings::get_settings();

        // Check if admin notifications are enabled
        if ( empty( $settings['btw_admin_email'] ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Check if reverse charge was applied
        $reverse_charge = $order->get_meta( '_boost_reverse_charge' );
        if ( 'yes' !== $reverse_charge ) {
            return;
        }

        // Check if we already sent a notification
        $notification_sent = $order->get_meta( '_boost_admin_notification_sent' );
        if ( 'yes' === $notification_sent ) {
            return;
        }

        // Get email address
        $to = ! empty( $settings['btw_admin_email_address'] )
            ? $settings['btw_admin_email_address']
            : get_option( 'admin_email' );

        if ( ! is_email( $to ) ) {
            return;
        }

        // Build email
        $subject = sprintf(
            /* translators: %s: Order number */
            __( '[BTW Verlegd] Nieuwe bestelling #%s met BTW verlegging', 'bossier-calculator' ),
            $order->get_order_number()
        );

        $vat_number  = $order->get_meta( '_boost_vat_number' );
        $vat_company = $order->get_meta( '_boost_vat_company' );

        $message = sprintf(
            /* translators: %s: Order number */
            __( 'Er is een nieuwe bestelling geplaatst met BTW verlegging.', 'bossier-calculator' )
        ) . "\n\n";

        $message .= __( 'Bestelgegevens:', 'bossier-calculator' ) . "\n";
        $message .= sprintf( __( 'Bestelnummer: #%s', 'bossier-calculator' ), $order->get_order_number() ) . "\n";
        $message .= sprintf( __( 'Totaal: %s', 'bossier-calculator' ), $order->get_formatted_order_total() ) . "\n";
        $message .= sprintf( __( 'Klant: %s', 'bossier-calculator' ), $order->get_formatted_billing_full_name() ) . "\n";
        $message .= sprintf( __( 'Bedrijf: %s', 'bossier-calculator' ), $order->get_billing_company() ) . "\n";
        $message .= sprintf( __( 'BTW-nummer: %s', 'bossier-calculator' ), $vat_number ) . "\n";
        $message .= sprintf( __( 'Land: %s', 'bossier-calculator' ), WC()->countries->countries[ $order->get_billing_country() ] ?? $order->get_billing_country() ) . "\n\n";

        if ( $vat_company ) {
            $message .= sprintf( __( 'Gevalideerde bedrijfsnaam (VIES): %s', 'bossier-calculator' ), $vat_company ) . "\n\n";
        }

        $message .= sprintf(
            /* translators: %s: Admin order URL */
            __( 'Bekijk de bestelling: %s', 'bossier-calculator' ),
            admin_url( 'post.php?post=' . $order_id . '&action=edit' )
        );

        // Send email
        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        $sent    = wp_mail( $to, $subject, $message, $headers );

        if ( $sent ) {
            $order->update_meta_data( '_boost_admin_notification_sent', 'yes' );
            $order->save();
        }
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

        // Get configurable messages
        $settings        = Modules_Settings::get_settings();
        $invalid_message = $settings['btw_invalid_message'] ?? __( 'BTW-nummer kon niet worden gevalideerd.', 'bossier-calculator' );

        if ( $result['valid'] ) {
            wp_send_json_success( array(
                'valid'        => true,
                'company_name' => $result['company_name'],
                'address'      => $result['address'],
            ) );
        } else {
            wp_send_json_error( array(
                'valid'   => false,
                'message' => $result['error'] ?? $invalid_message,
            ) );
        }
    }
}
