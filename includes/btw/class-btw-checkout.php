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

        // Display BTW row in cart/checkout (always visible, before order total)
        add_action( 'woocommerce_cart_totals_before_order_total', array( $this, 'display_btw_row' ) );
        add_action( 'woocommerce_review_order_before_order_total', array( $this, 'display_btw_row' ) );

        // Show BTW percentage in tax label (if WC shows taxes)
        add_filter( 'woocommerce_cart_tax_totals', array( $this, 'add_tax_percentage_to_label' ), 10, 2 );
        add_filter( 'woocommerce_order_tax_totals', array( $this, 'add_tax_percentage_to_order_label' ), 10, 2 );

        // Add fields to order emails
        add_action( 'woocommerce_email_after_order_table', array( $this, 'add_vat_to_email' ), 10, 4 );

        // Also load scripts on cart page
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_cart_scripts' ) );
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

        // Get initial value - always show unchecked for fresh checkout to force verification
        $is_business_checked = false;
        if ( WC()->session && WC()->session->get( 'boost_is_business_order' ) ) {
            $is_business_checked = true;
        }

        echo '<div id="boost-btw-fields" class="boost-btw-checkout-fields">';

        // Customer type selection - forced choice
        echo '<h3 class="boost-customer-type-heading">' . esc_html__( 'Type klant', 'bossier-calculator' ) . ' <abbr class="required" title="' . esc_attr__( 'verplicht', 'bossier-calculator' ) . '">*</abbr></h3>';

        // Business order checkbox with configurable label
        $checkbox_label = $this->settings['btw_checkbox_label'] ?? __( 'Dit is een zakelijke bestelling', 'bossier-calculator' );
        woocommerce_form_field( 'boost_is_business', array(
            'type'  => 'checkbox',
            'class' => array( 'boost-business-checkbox', 'form-row-wide' ),
            'label' => esc_html( $checkbox_label ),
        ), $is_business_checked );

        echo '<div id="boost-business-fields" class="boost-business-fields" style="' . ( $is_business_checked ? '' : 'display: none;' ) . '">';

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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce verification for checkout.
        $is_business = ! empty( $_POST['boost_is_business'] );

        if ( $is_business ) {
            // Company name required for business - check multiple sources.
            $company_name = '';

            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce verification.
            if ( ! empty( $_POST['boost_company_name'] ) ) {
                $company_name = sanitize_text_field( wp_unslash( $_POST['boost_company_name'] ) );
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            } elseif ( ! empty( $_POST['billing_company'] ) ) {
                $company_name = sanitize_text_field( wp_unslash( $_POST['billing_company'] ) );
            }

            // Fallback to session if POST is empty.
            if ( empty( $company_name ) && WC()->session ) {
                $session_company = WC()->session->get( 'boost_company_name' );
                if ( ! empty( $session_company ) ) {
                    $company_name = sanitize_text_field( $session_company );
                }
            }

            if ( empty( $company_name ) ) {
                wc_add_notice( __( 'Bedrijfsnaam is verplicht voor zakelijke bestellingen.', 'bossier-calculator' ), 'error' );
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
     * Enqueue scripts on cart page for BTW display.
     */
    public function enqueue_cart_scripts() {
        if ( ! is_cart() ) {
            return;
        }

        wp_enqueue_style(
            'boost-btw-checkout',
            BOSSIER_CALC_PLUGIN_URL . 'assets/css/btw-checkout.css',
            array(),
            BOSSIER_CALC_VERSION
        );
    }

    /**
     * Display BTW row in cart/checkout totals.
     * Always shows the BTW amount and percentage.
     */
    public function display_btw_row() {
        if ( ! WC()->cart ) {
            return;
        }

        $is_reverse_charge = BTW_Module::should_apply_reverse_charge();
        $subtotal = WC()->cart->get_subtotal();

        if ( $is_reverse_charge ) {
            // Reverse charge - 0% BTW
            $btw_percentage = 0;
            $btw_amount = 0;
            $btw_label = __( 'BTW (0% - Verlegd)', 'bossier-calculator' );
            $row_class = 'boost-btw-row boost-btw-reverse-charge';
        } else {
            // Normal BTW - get from WooCommerce or calculate 21%
            $btw_percentage = $this->get_default_tax_rate();
            $btw_amount = WC()->cart->get_total_tax();

            // If WC doesn't calculate tax, calculate ourselves
            if ( $btw_amount <= 0 && $btw_percentage > 0 ) {
                $btw_amount = $subtotal * ( $btw_percentage / 100 );
            }

            $btw_label = sprintf( __( 'BTW (%s%%)', 'bossier-calculator' ), $btw_percentage );
            $row_class = 'boost-btw-row';
        }

        echo '<tr class="' . esc_attr( $row_class ) . '">';
        echo '<th>' . esc_html( $btw_label ) . '</th>';
        echo '<td data-title="' . esc_attr( $btw_label ) . '">' . wp_kses_post( wc_price( $btw_amount ) ) . '</td>';
        echo '</tr>';

        // Show reverse charge info if applicable
        if ( $is_reverse_charge ) {
            echo '<tr class="boost-reverse-charge-info-row">';
            echo '<th></th>';
            echo '<td><small class="boost-reverse-charge-text">' . esc_html__( 'BTW wordt verlegd naar afnemer', 'bossier-calculator' ) . '</small></td>';
            echo '</tr>';
        }
    }

    /**
     * Get default tax rate percentage.
     *
     * @return float
     */
    private function get_default_tax_rate() {
        // Try to get from WooCommerce settings.
        global $wpdb;

        $rate = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT tax_rate FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_country IN (%s, %s) ORDER BY tax_rate_priority ASC, tax_rate_id ASC LIMIT 1",
                'NL',
                ''
            )
        );

        if ( $rate ) {
            return floatval( $rate );
        }

        // Default to 21% for Netherlands
        return 21;
    }

    /**
     * Add tax percentage to cart tax label.
     *
     * @param array   $tax_totals Tax totals.
     * @param WC_Cart $cart       Cart object.
     * @return array
     */
    public function add_tax_percentage_to_label( $tax_totals, $cart ) {
        if ( BTW_Module::should_apply_reverse_charge() ) {
            // Replace with 0% tax notice
            $tax_totals = array();
            $tax_totals['zero-rate'] = (object) array(
                'label'               => __( 'BTW (0% - Verlegd)', 'bossier-calculator' ),
                'amount'              => 0,
                'is_compound'         => false,
                'formatted_amount'    => wc_price( 0 ),
            );
            return $tax_totals;
        }

        // Add percentage to existing tax labels
        foreach ( $tax_totals as $code => $tax ) {
            $rate = $this->get_tax_rate_percentage( $code );
            if ( $rate > 0 ) {
                $tax->label = sprintf( __( 'BTW (%s%%)', 'bossier-calculator' ), $rate );
            }
        }

        return $tax_totals;
    }

    /**
     * Add tax percentage to order tax label.
     *
     * @param array    $tax_totals Tax totals.
     * @param WC_Order $order      Order object.
     * @return array
     */
    public function add_tax_percentage_to_order_label( $tax_totals, $order ) {
        $reverse_charge = $order->get_meta( '_boost_reverse_charge' );

        if ( 'yes' === $reverse_charge ) {
            $tax_totals = array();
            $tax_totals['zero-rate'] = (object) array(
                'label'               => __( 'BTW (0% - Verlegd)', 'bossier-calculator' ),
                'amount'              => 0,
                'is_compound'         => false,
                'formatted_amount'    => wc_price( 0 ),
            );
            return $tax_totals;
        }

        // Add percentage to existing tax labels
        foreach ( $tax_totals as $code => $tax ) {
            $rate = $this->get_tax_rate_percentage( $code );
            if ( $rate > 0 ) {
                $tax->label = sprintf( __( 'BTW (%s%%)', 'bossier-calculator' ), $rate );
            }
        }

        return $tax_totals;
    }

    /**
     * Get tax rate percentage from rate code.
     *
     * @param string $rate_code Rate code or ID.
     * @return float
     */
    private function get_tax_rate_percentage( $rate_code ) {
        global $wpdb;

        // Try to extract rate ID from code
        $parts = explode( '-', $rate_code );
        $rate_id = end( $parts );

        if ( is_numeric( $rate_id ) ) {
            $rate = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT tax_rate FROM {$wpdb->prefix}woocommerce_tax_rates WHERE tax_rate_id = %d",
                    absint( $rate_id )
                )
            );

            if ( $rate ) {
                return floatval( $rate );
            }
        }

        // Default to 21% for NL
        return 21;
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
