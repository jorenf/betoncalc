<?php
/**
 * BTW Checkout Integration.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\BTW;

use Bossier\Calculator\Modules_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * BTW_Checkout class - Adds BTW fields to WooCommerce checkout.
 */
class BTW_Checkout {

    /**
     * Single instance of the class.
     *
     * @var BTW_Checkout|null
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
     * @return BTW_Checkout
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

        // Add checkout fields
        add_action( 'woocommerce_after_checkout_billing_form', array( $this, 'add_business_fields' ) );

        // Validate checkout fields
        add_action( 'woocommerce_checkout_process', array( $this, 'validate_checkout_fields' ) );

        // Enqueue scripts
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Display reverse charge notice in cart/checkout totals
        add_action( 'woocommerce_cart_totals_before_order_total', array( $this, 'display_reverse_charge_notice' ) );
        add_action( 'woocommerce_review_order_before_order_total', array( $this, 'display_reverse_charge_notice' ) );

        // Add fields to order emails
        add_action( 'woocommerce_email_after_order_table', array( $this, 'add_vat_to_email' ), 10, 4 );
    }

    /**
     * Add business/VAT fields to checkout.
     *
     * @param WC_Checkout $checkout Checkout object.
     */
    public function add_business_fields( $checkout ) {
        // Check minimum amount if set
        $minimum_amount = floatval( $this->settings['btw_minimum_amount'] ?? 0 );
        if ( $minimum_amount > 0 && WC()->cart ) {
            $cart_total = WC()->cart->get_subtotal();
            if ( $cart_total < $minimum_amount ) {
                // Don't show BTW fields if cart is below minimum
                return;
            }
        }

        echo '<div id="boost-btw-fields" class="boost-btw-checkout-fields">';

        // Business order checkbox with configurable label
        $checkbox_label = $this->settings['btw_checkbox_label'] ?? __( 'Dit is een zakelijke bestelling', 'bossier-calculator' );
        woocommerce_form_field( 'boost_is_business', array(
            'type'  => 'checkbox',
            'class' => array( 'boost-business-checkbox', 'form-row-wide' ),
            'label' => esc_html( $checkbox_label ),
        ), WC()->session ? WC()->session->get( 'boost_is_business_order' ) : false );

        echo '<div id="boost-business-fields" class="boost-business-fields" style="display: none;">';

        // Company name (required for business) with configurable label
        $company_label = $this->settings['btw_company_label'] ?? __( 'Bedrijfsnaam', 'bossier-calculator' );
        woocommerce_form_field( 'boost_company_name', array(
            'type'        => 'text',
            'class'       => array( 'boost-company-name', 'form-row-wide' ),
            'label'       => esc_html( $company_label ),
            'required'    => true,
            'placeholder' => __( 'Uw bedrijfsnaam', 'bossier-calculator' ),
        ), WC()->checkout->get_value( 'billing_company' ) );

        // VAT number (optional) with configurable labels
        $vat_label       = $this->settings['btw_vat_label'] ?? __( 'BTW-nummer (optioneel)', 'bossier-calculator' );
        $vat_placeholder = $this->settings['btw_vat_placeholder'] ?? __( 'bijv. NL123456789B01', 'bossier-calculator' );

        echo '<div class="boost-vat-field-wrap form-row form-row-wide">';
        woocommerce_form_field( 'boost_vat_number', array(
            'type'        => 'text',
            'class'       => array( 'boost-vat-number' ),
            'label'       => esc_html( $vat_label ),
            'placeholder' => esc_attr( $vat_placeholder ),
            'description' => __( 'Voer uw EU BTW-nummer in voor BTW-vrijstelling bij levering buiten Nederland.', 'bossier-calculator' ),
        ), WC()->session ? WC()->session->get( 'boost_vat_number' ) : '' );

        echo '<div id="boost-vat-validation-result" class="boost-vat-result"></div>';
        echo '</div>';

        echo '</div>'; // .boost-business-fields
        echo '</div>'; // #boost-btw-fields
    }

    /**
     * Validate checkout fields.
     */
    public function validate_checkout_fields() {
        $is_business = isset( $_POST['boost_is_business'] ) && $_POST['boost_is_business'];

        if ( $is_business ) {
            // Company name required for business
            $company_name = isset( $_POST['boost_company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['boost_company_name'] ) ) : '';

            if ( empty( $company_name ) ) {
                wc_add_notice( __( 'Bedrijfsnaam is verplicht voor zakelijke bestellingen.', 'bossier-calculator' ), 'error' );
            }

            // Update billing company
            if ( ! empty( $company_name ) && empty( $_POST['billing_company'] ) ) {
                $_POST['billing_company'] = $company_name;
            }
        }
    }

    /**
     * Enqueue checkout scripts.
     */
    public function enqueue_scripts() {
        if ( ! is_checkout() ) {
            return;
        }

        // Check minimum amount - don't load scripts if not eligible
        $minimum_amount = floatval( $this->settings['btw_minimum_amount'] ?? 0 );
        if ( $minimum_amount > 0 && WC()->cart ) {
            $cart_total = WC()->cart->get_subtotal();
            if ( $cart_total < $minimum_amount ) {
                return;
            }
        }

        wp_enqueue_style(
            'boost-btw-checkout',
            BOSSIER_CALC_PLUGIN_URL . 'assets/css/btw-checkout.css',
            array(),
            BOSSIER_CALC_VERSION
        );

        wp_enqueue_script(
            'boost-btw-checkout',
            BOSSIER_CALC_PLUGIN_URL . 'assets/js/btw-checkout.js',
            array( 'jquery', 'wc-checkout' ),
            BOSSIER_CALC_VERSION,
            true
        );

        // Use configurable messages
        $valid_message   = $this->settings['btw_valid_message'] ?? __( 'BTW-nummer gevalideerd', 'bossier-calculator' );
        $invalid_message = $this->settings['btw_invalid_message'] ?? __( 'BTW-nummer kon niet worden gevalideerd', 'bossier-calculator' );

        wp_localize_script(
            'boost-btw-checkout',
            'boostBTW',
            array(
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'boost_vat_nonce' ),
                'homeCountry'  => WC()->countries->get_base_country(),
                'isEUCountry'  => VIES_Validator::is_eu_country( WC()->countries->get_base_country() ),
                'i18n'         => array(
                    'validating'    => __( 'Valideren...', 'bossier-calculator' ),
                    'valid'         => esc_html( $valid_message ),
                    'invalid'       => esc_html( $invalid_message ),
                    'error'         => __( 'Validatie fout', 'bossier-calculator' ),
                    'reverseCharge' => __( 'BTW wordt verlegd (0% BTW)', 'bossier-calculator' ),
                    'normalVat'     => __( 'Normale BTW van toepassing', 'bossier-calculator' ),
                ),
            )
        );
    }

    /**
     * Display reverse charge notice in totals.
     */
    public function display_reverse_charge_notice() {
        if ( BTW_Module::should_apply_reverse_charge() ) {
            echo '<tr class="boost-reverse-charge-notice">';
            echo '<th>' . esc_html__( 'BTW Verlegd', 'bossier-calculator' ) . '</th>';
            echo '<td><span class="boost-reverse-charge-text">' . esc_html__( 'Ja - 0% BTW', 'bossier-calculator' ) . '</span></td>';
            echo '</tr>';
        }
    }

    /**
     * Add VAT info to order emails.
     *
     * @param WC_Order $order         Order object.
     * @param bool     $sent_to_admin Whether email is for admin.
     * @param bool     $plain_text    Whether email is plain text.
     * @param WC_Email $email         Email object.
     */
    public function add_vat_to_email( $order, $sent_to_admin, $plain_text, $email = null ) {
        $reverse_charge = $order->get_meta( '_boost_reverse_charge' );
        $vat_number     = $order->get_meta( '_boost_vat_number' );

        if ( 'yes' !== $reverse_charge ) {
            return;
        }

        if ( $plain_text ) {
            echo "\n" . esc_html__( 'BTW Verlegd', 'bossier-calculator' ) . "\n";
            echo esc_html__( 'BTW-nummer:', 'bossier-calculator' ) . ' ' . esc_html( $vat_number ) . "\n";
        } else {
            echo '<div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-left: 4px solid #2271b1;">';
            echo '<h3 style="margin: 0 0 5px 0; font-size: 14px;">' . esc_html__( 'BTW Verlegd', 'bossier-calculator' ) . '</h3>';
            echo '<p style="margin: 0;"><strong>' . esc_html__( 'BTW-nummer:', 'bossier-calculator' ) . '</strong> ' . esc_html( $vat_number ) . '</p>';
            echo '</div>';
        }
    }
}
