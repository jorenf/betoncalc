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

        // Direct tax exemption filter - more reliable than tax class
        add_filter( 'woocommerce_calc_tax', array( $this, 'maybe_zero_calculated_tax' ), 999, 5 );
        add_filter( 'woocommerce_shipping_tax_class', array( $this, 'maybe_zero_shipping_tax_class' ), 999 );

        // Zero pre-calculated taxes stored directly on shipping rates (Boost shipping provides
        // explicit taxes that are NOT re-processed by woocommerce_calc_tax).
        add_filter( 'woocommerce_package_rates', array( $this, 'maybe_zero_shipping_rate_taxes' ), 999, 2 );

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

        // AJAX handler for persisting business order state
        add_action( 'wp_ajax_boost_set_business_state', array( $this, 'ajax_set_business_state' ) );
        add_action( 'wp_ajax_nopriv_boost_set_business_state', array( $this, 'ajax_set_business_state' ) );
    }

    /**
     * Apply zero tax class when reverse charge should apply.
     *
     * @param string     $tax_class Tax class.
     * @param WC_Product $product   Product object.
     * @return string
     */
    public function maybe_apply_zero_tax_class( $tax_class, $product ) {
        // Only apply reverse charge on checkout, never on cart
        if ( self::is_cart_context() ) {
            return $tax_class;
        }
        if ( self::should_apply_reverse_charge() ) {
            // Try zero-rate first, fall back to empty string
            $zero_rate_exists = in_array( 'zero-rate', \WC_Tax::get_tax_classes(), true );
            return $zero_rate_exists ? 'zero-rate' : '';
        }
        return $tax_class;
    }

    /**
     * Zero out calculated taxes when reverse charge applies.
     *
     * This is the most reliable way to ensure 0% VAT for reverse charge orders.
     *
     * @param array  $taxes      Calculated taxes.
     * @param float  $price      Price to calculate tax for.
     * @param array  $rates      Tax rates.
     * @param bool   $price_incl Whether price includes tax.
     * @param bool   $suppress   Whether to suppress rounding.
     * @return array Modified taxes (empty array if reverse charge).
     */
    public function maybe_zero_calculated_tax( $taxes, $price, $rates, $price_incl, $suppress ) {
        // Only apply reverse charge on checkout, never on cart
        if ( self::is_cart_context() ) {
            return $taxes;
        }
        if ( self::should_apply_reverse_charge() ) {
            // Return empty array to zero out all taxes
            return array();
        }
        return $taxes;
    }

    /**
     * Zero shipping tax class when reverse charge applies.
     *
     * @param string $tax_class Shipping tax class.
     * @return string
     */
    public function maybe_zero_shipping_tax_class( $tax_class ) {
        // Only apply reverse charge on checkout, never on cart
        if ( self::is_cart_context() ) {
            return $tax_class;
        }
        if ( self::should_apply_reverse_charge() ) {
            return '';
        }
        return $tax_class;
    }

    /**
     * Zero taxes stored directly on shipping rates when reverse charge applies.
     *
     * The Boost shipping method pre-calculates taxes and passes them explicitly
     * to WC_Shipping_Method::add_rate(). These bypass woocommerce_calc_tax, so
     * we must strip them here to ensure the shipping line is also VAT-free.
     *
     * @param WC_Shipping_Rate[] $rates   Available rates for the package.
     * @param array              $package WooCommerce shipping package.
     * @return WC_Shipping_Rate[]
     */
    public function maybe_zero_shipping_rate_taxes( $rates, $package ) {
        if ( self::is_cart_context() ) {
            return $rates;
        }

        if ( ! self::should_apply_reverse_charge() ) {
            return $rates;
        }

        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->info(
                'Reverse charge: zeroing shipping taxes for ' . count( $rates ) . ' rate(s)',
                array( 'source' => 'boost-btw' )
            );
        }

        foreach ( $rates as $rate ) {
            $meta              = $rate->get_meta_data();
            $is_boost_shipping = ! empty( $meta['is_boost_shipping'] );
            $inclusive_cost    = isset( $meta['boost_shipping_inclusive_cost'] ) ? (float) $meta['boost_shipping_inclusive_cost'] : null;

            if ( $is_boost_shipping && null !== $inclusive_cost ) {
                $rate->set_cost( $this->get_exclusive_shipping_cost( $inclusive_cost ) );
            }

            $rate->set_taxes( array() );
        }

        return $rates;
    }

    /**
     * Convert a VAT-inclusive Boost shipping price to an exclusive base.
     *
     * This deliberately does not use WC_Tax::calc_inclusive_tax(), because the
     * reverse-charge filters can make that return zero during checkout.
     *
     * @param float $inclusive_cost VAT-inclusive shipping cost.
     * @return float
     */
    private function get_exclusive_shipping_cost( $inclusive_cost ) {
        $inclusive_cost = (float) $inclusive_cost;
        if ( $inclusive_cost <= 0 ) {
            return 0;
        }

        return $inclusive_cost / $this->get_default_vat_divisor();
    }

    /**
     * Get the VAT divisor for inclusive Dutch prices.
     *
     * @return float
     */
    private function get_default_vat_divisor() {
        $tax_rates = \WC_Tax::get_shipping_tax_rates();
        if ( empty( $tax_rates ) ) {
            $tax_rates = \WC_Tax::get_rates( '' );
        }

        foreach ( $tax_rates as $tax_rate ) {
            $rate = isset( $tax_rate['rate'] ) ? (float) $tax_rate['rate'] : 0;
            if ( $rate > 0 ) {
                return 1 + ( $rate / 100 );
            }
        }

        return 1.21;
    }

    /**
     * Check if we are in a cart page context (not checkout).
     *
     * Reverse charge tax zeroing must NEVER apply on the cart page.
     * Cart always shows 21% VAT. Reverse charge only applies at checkout.
     *
     * @return bool True if we are on the cart page or handling a cart AJAX request.
     */
    private static function is_cart_context() {
        // Standard WordPress page check
        if ( function_exists( 'is_cart' ) && is_cart() ) {
            return true;
        }

        // AJAX requests from the WooPages cart page
        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            $action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
            $cart_actions = array(
                'boost_woopages_update_cart',
                'boost_woopages_apply_coupon',
                'boost_woopages_remove_coupon',
                'boost_woopages_update_shipping',
            );
            if ( in_array( $action, $cart_actions, true ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if reverse charge should be applied.
     *
     * Reverse charge only applies when:
     * 1. It's a business order
     * 2. VAT number is valid
     * 3. VAT number is NOT Dutch (NL)
     * 4. Billing country is NOT Netherlands
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

        $validation_status = WC()->session->get( 'boost_vat_status' );
        if ( 'valid' !== $validation_status ) {
            return false;
        }

        // Get VAT number and extract country code.
        $vat_number           = self::clean_vat_number( WC()->session->get( 'boost_vat_number' ) );
        $validated_vat_number = self::clean_vat_number( WC()->session->get( 'boost_vat_validated_number' ) );
        if ( empty( $vat_number ) || $vat_number !== $validated_vat_number ) {
            return false;
        }

        $vat_country = substr( $vat_number, 0, 2 );

        // Dutch VAT numbers (NL) NEVER get reverse charge
        if ( 'NL' === $vat_country ) {
            return false;
        }

        $vat_country_for_eu = 'EL' === $vat_country ? 'GR' : $vat_country;
        if ( ! VIES_Validator::is_eu_country( $vat_country_for_eu ) ) {
            return false;
        }

        // Check if foreign (non-NL) billing address.
        $billing_country = WC()->customer ? WC()->customer->get_billing_country() : '';
        if ( empty( $billing_country ) ) {
            $billing_country = WC()->session->get( 'boost_vat_billing_country' );
        }
        $billing_country = strtoupper( (string) $billing_country );
        if ( 'NL' === $billing_country || empty( $billing_country ) ) {
            return false;
        }

        $validated_billing_country = strtoupper( (string) WC()->session->get( 'boost_vat_billing_country' ) );
        if ( ! empty( $validated_billing_country ) && $billing_country !== $validated_billing_country ) {
            return false;
        }

        if ( ! WC()->session->get( 'boost_vat_is_business' ) ) {
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
     * Clean a VAT number for stable session comparisons.
     *
     * @param string $vat_number VAT number.
     * @return string
     */
    private static function clean_vat_number( $vat_number ) {
        return strtoupper( preg_replace( '/[^A-Z0-9]/i', '', (string) $vat_number ) );
    }

    /**
     * Persist a coherent VAT validation state in the WooCommerce session.
     *
     * @param string $vat_number      VAT number.
     * @param string $status          Validation status: valid, invalid, unavailable, or empty.
     * @param string $billing_country Billing country.
     * @param bool   $is_business     Whether this is a business order.
     * @param array  $result          VIES result.
     */
    private function store_vat_validation_state( $vat_number, $status, $billing_country, $is_business, $result = array() ) {
        if ( ! WC()->session ) {
            return;
        }

        $vat_number      = self::clean_vat_number( $vat_number );
        $billing_country = strtoupper( (string) $billing_country );

        WC()->session->set( 'boost_vat_number', $vat_number );
        WC()->session->set( 'boost_vat_status', $status );
        WC()->session->set( 'boost_vat_valid', 'valid' === $status );
        WC()->session->set( 'boost_vat_validated_number', ! empty( $status ) ? $vat_number : '' );
        WC()->session->set( 'boost_vat_billing_country', $billing_country );
        WC()->session->set( 'boost_vat_is_business', (bool) $is_business );
        WC()->session->set( 'boost_vat_validated_at', time() );
        WC()->session->set( 'boost_vat_company', 'valid' === $status ? ( $result['company_name'] ?? '' ) : '' );
    }

    /**
     * Clear VAT validation state while optionally keeping the raw VAT value.
     *
     * @param string $vat_number      VAT number to keep in session.
     * @param string $billing_country Billing country.
     * @param bool   $is_business     Whether this is a business order.
     */
    private function clear_vat_validation_state( $vat_number = '', $billing_country = '', $is_business = false ) {
        $this->store_vat_validation_state( $vat_number, '', $billing_country, $is_business );
    }

    /**
     * Check if the current session already has a matching valid VAT state.
     *
     * @param string $vat_number      VAT number.
     * @param string $billing_country Billing country.
     * @param bool   $is_business     Whether this is a business order.
     * @return bool
     */
    private function has_matching_valid_vat_state( $vat_number, $billing_country, $is_business ) {
        if ( ! WC()->session || ! $is_business ) {
            return false;
        }

        return 'valid' === WC()->session->get( 'boost_vat_status' )
            && self::clean_vat_number( $vat_number ) === self::clean_vat_number( WC()->session->get( 'boost_vat_validated_number' ) )
            && strtoupper( (string) $billing_country ) === strtoupper( (string) WC()->session->get( 'boost_vat_billing_country' ) )
            && (bool) WC()->session->get( 'boost_vat_is_business' );
    }

    /**
     * Store a VIES result, preserving a matching valid state during temporary outages.
     *
     * @param string $vat_number      VAT number.
     * @param string $billing_country Billing country.
     * @param bool   $is_business     Whether this is a business order.
     * @param array  $result          VIES result.
     * @return bool True when an existing valid state was preserved.
     */
    private function apply_vat_validation_result( $vat_number, $billing_country, $is_business, $result ) {
        if ( ! empty( $result['valid'] ) ) {
            $this->store_vat_validation_state( $vat_number, 'valid', $billing_country, $is_business, $result );
            return false;
        }

        if ( array_key_exists( 'valid', $result ) && null === $result['valid'] ) {
            if ( $this->has_matching_valid_vat_state( $vat_number, $billing_country, $is_business ) ) {
                return true;
            }
            $this->store_vat_validation_state( $vat_number, 'unavailable', $billing_country, $is_business, $result );
            return false;
        }

        $this->store_vat_validation_state( $vat_number, 'invalid', $billing_country, $is_business, $result );
        return false;
    }

    /**
     * Maybe apply reverse charge during checkout.
     *
     * @param string $post_data Posted data.
     */
    public function maybe_apply_reverse_charge( $post_data ) {
        parse_str( $post_data, $data );

        $is_business = ! empty( $data['boost_is_business'] );
        $vat_number  = isset( $data['boost_vat_number'] ) ? self::clean_vat_number( sanitize_text_field( $data['boost_vat_number'] ) ) : '';
        $billing_country = isset( $data['billing_country'] ) ? sanitize_text_field( $data['billing_country'] ) : '';

        // Capture company name too - check multiple sources
        $company_name = '';
        if ( ! empty( $data['boost_company_name'] ) ) {
            $company_name = sanitize_text_field( $data['boost_company_name'] );
        } elseif ( ! empty( $data['billing_company'] ) ) {
            $company_name = sanitize_text_field( $data['billing_company'] );
        }

        WC()->session->set( 'boost_is_business_order', $is_business );
        WC()->session->set( 'boost_vat_number', $vat_number );
        WC()->session->set( 'boost_company_name', $company_name );

        if ( ! empty( $billing_country ) && WC()->customer ) {
            WC()->customer->set_billing_country( $billing_country );
            WC()->customer->set_shipping_country( $billing_country );
            WC()->customer->save();
        } elseif ( WC()->customer ) {
            $billing_country = WC()->customer->get_billing_country();
        }

        // Validate VAT number if provided
        if ( $is_business && ! empty( $vat_number ) ) {
            $current_status = WC()->session->get( 'boost_vat_status' );
            $current_vat    = self::clean_vat_number( WC()->session->get( 'boost_vat_validated_number' ) );
            $current_country = strtoupper( (string) WC()->session->get( 'boost_vat_billing_country' ) );

            // Order-review recalculation should only trust the AJAX validation state.
            // Do not call VIES from this hook; stale state is cleared and the
            // boost_validate_vat AJAX endpoint becomes the single validation owner.
            if (
                empty( $current_status )
                || $current_vat !== $vat_number
                || $current_country !== strtoupper( (string) $billing_country )
                || ! WC()->session->get( 'boost_vat_is_business' )
            ) {
                $this->clear_vat_validation_state( $vat_number, $billing_country, $is_business );
            }
        } else {
            $this->clear_vat_validation_state( $vat_number, $billing_country, $is_business );
        }
    }

    /**
     * Zero out taxes if reverse charge applies.
     *
     * @param array $taxes Taxes array.
     * @return array
     */
    public function maybe_zero_taxes( $taxes ) {
        if ( self::is_cart_context() ) {
            return $taxes;
        }
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
        if ( self::is_cart_context() ) {
            return $total;
        }
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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce handles nonce verification for checkout.
        $is_business = ! empty( $_POST['boost_is_business'] );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $vat_number  = isset( $_POST['boost_vat_number'] ) ? sanitize_text_field( wp_unslash( $_POST['boost_vat_number'] ) ) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $company     = isset( $_POST['boost_company_name'] ) ? sanitize_text_field( wp_unslash( $_POST['boost_company_name'] ) ) : '';

        // Fallback to session if POST is empty
        if ( WC()->session ) {
            if ( ! $is_business && WC()->session->get( 'boost_is_business_order' ) ) {
                $is_business = true;
            }
            if ( empty( $vat_number ) ) {
                $vat_number = WC()->session->get( 'boost_vat_number' ) ?: '';
            }
        }

        // Validate VAT if provided
        $vat_valid = false;
        $vat_company = '';

        if ( ! empty( $vat_number ) ) {
            if ( WC()->session && $this->has_matching_valid_vat_state( $vat_number, $order->get_billing_country(), $is_business ) ) {
                $vat_valid = true;
                $vat_company = WC()->session->get( 'boost_vat_company' ) ?: '';
            } else {
                $validator = new VIES_Validator();
                $result = $validator->validate( $vat_number );
                $this->apply_vat_validation_result( $vat_number, $order->get_billing_country(), $is_business, $result );
                $vat_valid = ! empty( $result['valid'] );
                $vat_company = $result['company_name'] ?? '';
            }
        }

        // Extract VAT country code
        $vat_number_clean = strtoupper( preg_replace( '/[^A-Z0-9]/i', '', $vat_number ) );
        $vat_country = substr( $vat_number_clean, 0, 2 );

        // Determine if reverse charge applies
        // Only for foreign EU businesses - Dutch VAT (NL) NEVER gets reverse charge
        $billing_country = $order->get_billing_country();
        $is_reverse_charge = $is_business
            && $vat_valid
            && ! empty( $billing_country )
            && 'NL' !== $billing_country
            && 'NL' !== $vat_country;

        // Save all meta data
        $order->update_meta_data( '_boost_is_business_order', $is_business ? 'yes' : 'no' );
        $order->update_meta_data( '_boost_company_name', $company );
        $order->update_meta_data( '_boost_vat_number', $vat_number );
        $order->update_meta_data( '_boost_vat_valid', $vat_valid ? 'yes' : 'no' );
        $order->update_meta_data( '_boost_vat_company', $vat_company );
        $order->update_meta_data( '_boost_reverse_charge', $is_reverse_charge ? 'yes' : 'no' );

        // Clear session
        if ( WC()->session ) {
            WC()->session->set( 'boost_is_business_order', null );
            WC()->session->set( 'boost_vat_number', null );
            WC()->session->set( 'boost_vat_valid', null );
            WC()->session->set( 'boost_vat_status', null );
            WC()->session->set( 'boost_vat_validated_number', null );
            WC()->session->set( 'boost_vat_billing_country', null );
            WC()->session->set( 'boost_vat_is_business', null );
            WC()->session->set( 'boost_vat_validated_at', null );
            WC()->session->set( 'boost_vat_company', null );
        }
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
     * Mask a VAT number before writing it to logs.
     *
     * @param string $vat_number VAT number.
     * @return string Masked VAT number.
     */
    private function mask_vat_number_for_log( $vat_number ) {
        $vat_number = preg_replace( '/\s+/', '', (string) $vat_number );
        $length     = strlen( $vat_number );

        if ( $length <= 4 ) {
            return str_repeat( '*', $length );
        }

        return substr( $vat_number, 0, 2 ) . str_repeat( '*', max( 0, $length - 6 ) ) . substr( $vat_number, -4 );
    }

    /**
     * AJAX handler for VAT validation.
     */
    public function ajax_validate_vat() {
        check_ajax_referer( 'boost_vat_nonce', 'nonce' );

        $vat_number = isset( $_POST['vat_number'] ) ? sanitize_text_field( wp_unslash( $_POST['vat_number'] ) ) : '';
        $has_business_state = isset( $_POST['is_business'] );
        $is_business = ! empty( $_POST['is_business'] );
        $billing_country = isset( $_POST['billing_country'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_country'] ) ) : '';

        if ( $has_business_state && WC()->session ) {
            WC()->session->set( 'boost_is_business_order', $is_business );
        }

        if ( ! empty( $billing_country ) && WC()->customer ) {
            WC()->customer->set_billing_country( $billing_country );
            WC()->customer->set_shipping_country( $billing_country );
            WC()->customer->save();
        }

        if ( empty( $vat_number ) ) {
            // Clear session validation state
            if ( WC()->session ) {
                $this->clear_vat_validation_state( '', $billing_country, $is_business );
            }
            wp_send_json_error( array( 'message' => __( 'BTW-nummer is verplicht.', 'bossier-calculator' ) ) );
        }

        $vat_number = self::clean_vat_number( $vat_number );
        $validator = new VIES_Validator();
        $result    = $validator->validate( $vat_number );
        $preserved_valid = $this->apply_vat_validation_result( $vat_number, $billing_country, $is_business, $result );

        // Get configurable messages
        $settings        = Modules_Settings::get_settings();
        $invalid_message = $settings['btw_invalid_message'] ?? __( 'BTW-nummer kon niet worden gevalideerd.', 'bossier-calculator' );
        $masked_vat      = $this->mask_vat_number_for_log( $vat_number );

        if ( $result['valid'] || $preserved_valid ) {
            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->info(
                    'VAT AJAX: ' . $masked_vat . ( $preserved_valid ? ' -> PRESERVED VALID DURING SERVICE OUTAGE' : ' -> VALID' ),
                    array( 'source' => 'boost-vat' )
                );
            }
            wp_send_json_success( array(
                'valid'           => true,
                'preserved_valid' => $preserved_valid,
                'company_name'    => $preserved_valid && WC()->session ? ( WC()->session->get( 'boost_vat_company' ) ?: '' ) : ( $result['company_name'] ?? '' ),
                'address'         => $result['address'] ?? '',
                'message'         => $preserved_valid ? __( 'Eerder gevalideerd BTW-nummer behouden; VIES is tijdelijk niet beschikbaar.', 'bossier-calculator' ) : '',
            ) );
        } elseif ( null === $result['valid'] ) {
            // VIES service temporarily unavailable — tell the customer clearly
            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->warning(
                    'VAT AJAX: ' . $masked_vat . ' -> SERVICE UNAVAILABLE (' . ( $result['error'] ?? '' ) . ')',
                    array( 'source' => 'boost-vat' )
                );
            }
            wp_send_json_error( array(
                'valid'               => null,
                'service_unavailable' => true,
                'message'             => $result['error'] ?? __( 'BTW-validatieservice tijdelijk niet beschikbaar. Probeer het later opnieuw.', 'bossier-calculator' ),
            ) );
        } else {
            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->info(
                    'VAT AJAX: ' . $masked_vat . ' -> INVALID (reason: ' . ( $result['reason'] ?? 'unknown' ) . ', msg: ' . ( $result['error'] ?? '' ) . ')',
                    array( 'source' => 'boost-vat' )
                );
            }
            wp_send_json_error( array(
                'valid'   => false,
                'message' => $result['error'] ?? $invalid_message,
            ) );
        }
    }

    /**
     * AJAX handler to persist business order state in session.
     */
    public function ajax_set_business_state() {
        check_ajax_referer( 'boost_vat_nonce', 'nonce' );

        $is_business = ! empty( $_POST['is_business'] );
        $billing_country = isset( $_POST['billing_country'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_country'] ) ) : '';

        if ( WC()->session ) {
            WC()->session->set( 'boost_is_business_order', $is_business );

            // If unchecked, clear all business-related session data
            if ( ! $is_business ) {
                $this->clear_vat_validation_state( '', $billing_country, false );
                WC()->session->set( 'boost_btw_reverse_charge', false );
            }
        }

        if ( ! empty( $billing_country ) && WC()->customer ) {
            WC()->customer->set_billing_country( $billing_country );
            WC()->customer->set_shipping_country( $billing_country );
            WC()->customer->save();
        }

        wp_send_json_success( array( 'is_business' => $is_business ) );
    }
}
