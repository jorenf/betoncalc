<?php
/**
 * WooPages Template Helper.
 *
 * Helper functions for rendering WooPages templates.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\WooPages;

defined( 'ABSPATH' ) || exit;

/**
 * WooPages_Helper class.
 */
class WooPages_Helper {

    /**
     * Render product thumbnail for cart/checkout.
     *
     * @param array $cart_item Cart item data.
     * @return string HTML.
     */
    public static function get_product_thumbnail( $cart_item ) {
        $product   = $cart_item['data'];
        $thumbnail = $product->get_image( 'woocommerce_thumbnail' );

        if ( $thumbnail ) {
            return $thumbnail;
        }

        // Fallback placeholder
        return '<div class="boost-woo-placeholder"></div>';
    }

    /**
     * Get product specs as array of tags.
     *
     * @param array $cart_item Cart item data.
     * @return array Spec tags.
     */
    public static function get_product_specs( $cart_item ) {
        $specs = array();

        // Get calculator display data from bossier_calculator array
        if ( isset( $cart_item['bossier_calculator']['display_data'] ) && is_array( $cart_item['bossier_calculator']['display_data'] ) ) {
            foreach ( $cart_item['bossier_calculator']['display_data'] as $field_id => $data ) {
                if ( isset( $data['value'] ) && '' !== $data['value'] ) {
                    $specs[] = esc_html( $data['value'] );
                }
            }
        }

        // Get variation attributes
        $product = $cart_item['data'];
        if ( $product->is_type( 'variation' ) ) {
            $variation_attributes = $product->get_variation_attributes();
            foreach ( $variation_attributes as $attr_name => $attr_value ) {
                if ( $attr_value ) {
                    $specs[] = esc_html( $attr_value );
                }
            }
        }

        return $specs;
    }

    /**
     * Get formatted line item price.
     *
     * @param array $cart_item Cart item data.
     * @return array Price data.
     */
    public static function get_line_item_price( $cart_item ) {
        $product     = $cart_item['data'];
        $quantity    = $cart_item['quantity'];
        $line_total  = $cart_item['line_total'];
        $unit_price  = $line_total / max( 1, $quantity );

        // For calculator products, use the stored calculator price as unit price
        // This ensures the cart line price matches the calculator page exactly
        if ( isset( $cart_item['bossier_calculator']['calculated_price'] ) ) {
            $calc_price = floatval( $cart_item['bossier_calculator']['calculated_price'] );

            // Use WC line_total if available and consistent, otherwise derive from calculator price
            if ( $line_total > 0 ) {
                // Include tax in displayed price if shop settings require it
                if ( wc_prices_include_tax() || 'incl' === get_option( 'woocommerce_tax_display_cart' ) ) {
                    $line_total = $cart_item['line_total'] + ( $cart_item['line_tax'] ?? 0 );
                }
                $unit_price = $line_total / max( 1, $quantity );
            } else {
                // Fallback: use calculator price directly
                $unit_price = $calc_price;
                $line_total = $calc_price * $quantity;
            }
        } else {
            // Include tax in displayed price if shop settings require it
            if ( wc_prices_include_tax() || 'incl' === get_option( 'woocommerce_tax_display_cart' ) ) {
                $line_total = $cart_item['line_total'] + ( $cart_item['line_tax'] ?? 0 );
                $unit_price = $line_total / max( 1, $quantity );
            }
        }

        return array(
            'line_total'          => wc_price( $line_total ),
            'line_total_raw'      => $line_total,
            'unit_price'          => wc_price( $unit_price ),
            'unit_price_raw'      => $unit_price,
            'quantity'            => $quantity,
            'formatted'           => sprintf( '%d &times; %s', $quantity, wc_price( $unit_price ) ),
        );
    }

    /**
     * Get cart summary data.
     *
     * @return array Summary data.
     */
    public static function get_cart_summary() {
        $cart = WC()->cart;

        // Subtotal (excl tax)
        $subtotal_excl = $cart->get_subtotal();

        // Tax total
        $tax_total = $cart->get_total_tax();

        // Shipping - include tax if prices are displayed including tax
        $shipping_total = $cart->get_shipping_total();
        $shipping_tax   = $cart->get_shipping_tax();
        $tax_display    = get_option( 'woocommerce_tax_display_cart' );

        // Calculate shipping display amount (include tax if needed)
        $shipping_display = $shipping_total;
        if ( 'incl' === $tax_display ) {
            $shipping_display = $shipping_total + $shipping_tax;
        }

        // Discount
        $discount_total = $cart->get_discount_total();
        $discount_tax   = $cart->get_discount_tax();

        // Grand total
        $total = $cart->get_total( 'edit' );

        // Get shipping breakdown from shipping rate meta data
        $shipping_breakdown = array();
        $packages = WC()->shipping()->get_packages();
        foreach ( $packages as $package_key => $package ) {
            if ( empty( $package['rates'] ) ) {
                continue;
            }
            $chosen_methods = WC()->session->get( 'chosen_shipping_methods', array() );
            $chosen_method  = isset( $chosen_methods[ $package_key ] ) ? $chosen_methods[ $package_key ] : '';
            if ( ! empty( $chosen_method ) && isset( $package['rates'][ $chosen_method ] ) ) {
                $rate = $package['rates'][ $chosen_method ];
                $meta = $rate->get_meta_data();
                if ( ! empty( $meta['breakdown'] ) && is_array( $meta['breakdown'] ) ) {
                    $shipping_breakdown = $meta['breakdown'];
                }
            }
        }

        // Check reverse charge state
        $is_reverse_charge = false;
        if ( class_exists( '\Bossier\Calculator\BTW\BTW_Module' ) ) {
            $is_reverse_charge = \Bossier\Calculator\BTW\BTW_Module::should_apply_reverse_charge();
        }

        // Get tax percentage for display
        $tax_percentage = 21; // Default NL rate
        if ( $is_reverse_charge ) {
            $tax_percentage = 0;
        }

        return array(
            'subtotal'            => $cart->get_cart_subtotal(),
            'subtotal_raw'        => $subtotal_excl,
            'shipping'            => $shipping_display > 0 ? wc_price( $shipping_display ) : __( 'Gratis', 'bossier-calculator' ),
            'shipping_raw'        => $shipping_display,
            'shipping_breakdown'  => $shipping_breakdown,
            'discount'            => $discount_total > 0 ? wc_price( $discount_total ) : '',
            'discount_raw'        => $discount_total,
            'tax'                 => wc_price( $tax_total ),
            'tax_raw'             => $tax_total,
            'total'               => $cart->get_total(),
            'total_raw'           => $total,
            'total_excl_tax'      => wc_price( $total - $tax_total ),
            'item_count'          => $cart->get_cart_contents_count(),
            'tax_display'         => $tax_display,
            'coupons'             => $cart->get_applied_coupons(),
            'is_reverse_charge'   => $is_reverse_charge,
            'tax_percentage'      => $tax_percentage,
        );
    }

    /**
     * Get available shipping methods.
     *
     * @return array Shipping methods.
     */
    public static function get_shipping_methods() {
        $methods = array();

        // Get available shipping packages
        $packages = WC()->shipping()->get_packages();
        $tax_display = get_option( 'woocommerce_tax_display_cart' );

        foreach ( $packages as $package_key => $package ) {
            if ( ! empty( $package['rates'] ) ) {
                foreach ( $package['rates'] as $rate_id => $rate ) {
                    $cost = floatval( $rate->get_cost() );
                    $cost_display = $cost;

                    // Include tax in display cost if tax display is set to 'incl'
                    if ( 'incl' === $tax_display && $cost > 0 ) {
                        $taxes = $rate->get_taxes();
                        $cost_display = $cost + array_sum( $taxes );
                    }

                    $methods[] = array(
                        'id'       => $rate_id,
                        'label'    => $rate->get_label(),
                        'cost'     => $cost,
                        'cost_incl_tax' => $cost_display,
                        'cost_fmt' => $cost > 0 ? wc_price( $cost_display ) : __( 'n.v.t.', 'bossier-calculator' ),
                        'selected' => WC()->session->get( 'chosen_shipping_methods' )[ $package_key ] === $rate_id,
                    );
                }
            }
        }

        return $methods;
    }

    /**
     * Get checkout field value.
     *
     * @param string $field_key Field key.
     * @return string Field value.
     */
    public static function get_checkout_field_value( $field_key ) {
        $checkout = WC()->checkout();
        return $checkout->get_value( $field_key );
    }

    /**
     * Get order summary for checkout sidebar.
     *
     * @return array Order items summary.
     */
    public static function get_order_review_items() {
        $items = array();

        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
            $product = $cart_item['data'];
            $items[] = array(
                'name'      => $product->get_name(),
                'quantity'  => $cart_item['quantity'],
                'thumbnail' => self::get_product_thumbnail( $cart_item ),
                'specs'     => self::get_product_specs( $cart_item ),
                'price'     => self::get_line_item_price( $cart_item ),
            );
        }

        return $items;
    }

    /**
     * Get order details for thank you page.
     *
     * @param \WC_Order $order Order object.
     * @return array Order details.
     */
    public static function get_order_details( $order ) {
        if ( ! $order ) {
            return array();
        }

        $items = array();
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            $items[] = array(
                'name'      => $item->get_name(),
                'quantity'  => $item->get_quantity(),
                'total'     => $order->get_formatted_line_subtotal( $item ),
                'meta'      => wc_display_item_meta( $item, array( 'echo' => false ) ),
                'thumbnail' => $product ? $product->get_image( 'woocommerce_thumbnail' ) : '',
            );
        }

        return array(
            'id'               => $order->get_id(),
            'number'           => $order->get_order_number(),
            'date'             => wc_format_datetime( $order->get_date_created() ),
            'status'           => wc_get_order_status_name( $order->get_status() ),
            'email'            => $order->get_billing_email(),
            'items'            => $items,
            'subtotal'         => $order->get_subtotal_to_display(),
            'shipping'         => $order->get_shipping_to_display(),
            'discount'         => $order->get_discount_to_display(),
            'tax'              => wc_price( $order->get_total_tax() ),
            'total'            => $order->get_formatted_order_total(),
            'payment_method'   => $order->get_payment_method_title(),
            'billing_address'  => $order->get_formatted_billing_address(),
            'shipping_address' => $order->get_formatted_shipping_address(),
        );
    }

    /**
     * Render notices.
     *
     * @return string HTML.
     */
    public static function render_notices() {
        ob_start();
        wc_print_notices();
        return ob_get_clean();
    }

    /**
     * Get cart item weight.
     *
     * @param array $cart_item Cart item data.
     * @return float Weight in kg.
     */
    public static function get_cart_item_weight( $cart_item ) {
        // Check for calculator weight (stored per unit in bossier_calculator array)
        if ( isset( $cart_item['bossier_calculator']['calculated_weight'] ) ) {
            $weight_per_unit = floatval( $cart_item['bossier_calculator']['calculated_weight'] );
            return $weight_per_unit * $cart_item['quantity'];
        }

        // Fall back to product weight
        $product = $cart_item['data'];
        $weight  = $product->get_weight();

        if ( $weight ) {
            return wc_get_weight( $weight, 'kg' ) * $cart_item['quantity'];
        }

        return 0;
    }

    /**
     * Get total cart weight.
     *
     * @return float Total weight in kg.
     */
    public static function get_cart_total_weight() {
        $total_weight = 0;

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $total_weight += self::get_cart_item_weight( $cart_item );
        }

        return $total_weight;
    }

    /**
     * Format weight for display.
     *
     * @param float  $weight Weight value.
     * @param string $unit   Unit (default: kg).
     * @return string Formatted weight.
     */
    public static function format_weight( $weight, $unit = 'kg' ) {
        if ( $weight <= 0 ) {
            return '';
        }

        return number_format( $weight, 2, ',', '.' ) . ' ' . $unit;
    }

    /**
     * Get the longest product-level delivery time across all cart items.
     *
     * @return array|null Delivery info with 'text' and 'status', or null if all in stock.
     */
    public static function get_cart_delivery_time() {
        if ( ! WC()->cart ) {
            return null;
        }

        $max_weeks_low  = 0;
        $max_weeks_high = 0;
        $has_made_to_order = false;

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product_id      = $cart_item['product_id'];
            $delivery_status = get_post_meta( $product_id, '_boost_delivery_status', true );
            $delivery_weeks  = get_post_meta( $product_id, '_boost_delivery_weeks', true );

            if ( 'made_to_order' === $delivery_status ) {
                $has_made_to_order = true;
                $weeks = $delivery_weeks ?: '2-3';

                // Parse range like "2-3" or single value like "4"
                if ( strpos( $weeks, '-' ) !== false ) {
                    $parts = explode( '-', $weeks );
                    $low   = intval( trim( $parts[0] ) );
                    $high  = intval( trim( $parts[1] ) );
                } else {
                    $low  = intval( trim( $weeks ) );
                    $high = $low;
                }

                if ( $high > $max_weeks_high || ( $high === $max_weeks_high && $low > $max_weeks_low ) ) {
                    $max_weeks_low  = $low;
                    $max_weeks_high = $high;
                }
            }
        }

        if ( ! $has_made_to_order ) {
            return null;
        }

        $weeks_text = ( $max_weeks_low === $max_weeks_high )
            ? (string) $max_weeks_high
            : $max_weeks_low . '-' . $max_weeks_high;

        return array(
            'text'   => sprintf( __( '%s weken', 'bossier-calculator' ), $weeks_text ),
            'status' => 'made_to_order',
            'weeks'  => $weeks_text,
        );
    }

    /**
     * Get cart fees (e.g., malkosten, opstartkosten).
     *
     * @return array Array of fees with name, amount, and formatted price.
     */
    public static function get_cart_fees() {
        $fees = array();

        if ( ! WC()->cart ) {
            return $fees;
        }

        // Get all fees from the cart
        $cart_fees = WC()->cart->get_fees();

        if ( empty( $cart_fees ) ) {
            return $fees;
        }

        $tax_display = get_option( 'woocommerce_tax_display_cart' );

        foreach ( $cart_fees as $fee ) {
            $fee_amount = $fee->amount;

            // Include tax in displayed amount if needed
            if ( 'incl' === $tax_display && $fee->taxable ) {
                $fee_amount = $fee->amount + $fee->tax;
            }

            $fees[] = array(
                'name'          => $fee->name,
                'amount'        => $fee_amount,
                'amount_raw'    => $fee->amount,
                'tax'           => $fee->tax,
                'formatted'     => wc_price( $fee_amount ),
                'is_one_time'   => true,
            );
        }

        return $fees;
    }

    /**
     * Check if reverse charge VAT is active for current checkout.
     *
     * @return bool
     */
    public static function is_reverse_charge_active() {
        if ( ! WC()->session ) {
            return false;
        }

        return (bool) WC()->session->get( 'boost_btw_reverse_charge', false );
    }

    /**
     * Get reverse charge message.
     *
     * @return string
     */
    public static function get_reverse_charge_message() {
        $settings = \Bossier\Calculator\Modules_Settings::get_settings();
        return $settings['btw_invoice_text'] ?? 'BTW verlegd naar afnemer conform artikel 138 BTW-richtlijn';
    }
}
