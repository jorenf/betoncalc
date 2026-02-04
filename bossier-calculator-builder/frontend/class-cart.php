<?php
/**
 * Cart integration class.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\Frontend;

use Bossier\Calculator\Calculator;
use Bossier\Calculator\Price_Calculator;

defined( 'ABSPATH' ) || exit;

/**
 * Cart class - Handles WooCommerce cart integration.
 */
class Cart {

    /**
     * Constructor.
     */
    public function __construct() {
        // Add calculator data to cart item
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 3 );

        // Load calculator data from session
        add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );

        // Display calculator data in cart
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );

        // Set custom price for cart item
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_cart_item_price' ), 20 );

        // Set custom weight for cart item
        add_filter( 'woocommerce_product_get_weight', array( $this, 'set_product_weight' ), 10, 2 );

        // Make each calculator product unique in cart
        add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 10, 1 );
    }

    /**
     * Add calculator data to cart item when product is added.
     *
     * @param array $cart_item_data Existing cart item data.
     * @param int   $product_id     Product ID.
     * @param int   $variation_id   Variation ID.
     * @return array Modified cart item data.
     */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        $calculator_id = get_post_meta( $product_id, '_bossier_calculator_id', true );

        if ( empty( $calculator_id ) ) {
            return $cart_item_data;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! isset( $_POST['bossier_calculator_id'] ) ) {
            return $cart_item_data;
        }

        $calculator = new Calculator( $calculator_id );

        if ( ! $calculator->is_valid() ) {
            return $cart_item_data;
        }

        // Collect field selections
        $selections = array();
        $display_data = array();
        $fields = $calculator->get_enabled_fields();

        foreach ( $fields as $field_id => $field ) {
            $field_key = 'bossier_calc_' . $field_id;

            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ( ! isset( $_POST[ $field_key ] ) ) {
                continue;
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $value = $_POST[ $field_key ];

            // Sanitize value
            if ( is_array( $value ) ) {
                $value = array_map( 'sanitize_text_field', $value );
            } else {
                $value = sanitize_text_field( $value );
            }

            $selections[ $field_id ] = $value;

            // Prepare display data
            $display_data[ $field_id ] = $this->get_field_display_value( $field, $value );
        }

        // Calculate price and weight
        $price_calc = new Price_Calculator( $calculator );
        $result = $price_calc->calculate( $selections );

        // Get quantity from calculator if present
        $quantity = 1;
        foreach ( $fields as $field_id => $field ) {
            if ( 'quantity' === $field['type'] && isset( $selections[ $field_id ] ) ) {
                $quantity = max( 1, intval( $selections[ $field_id ] ) );
                break;
            }
        }

        // Store calculator data in cart item
        $cart_item_data['bossier_calculator'] = array(
            'calculator_id'   => $calculator_id,
            'selections'      => $selections,
            'display_data'    => $display_data,
            'calculated_price'=> $result['price'],
            'calculated_weight' => $result['weight'],
            'breakdown'       => $result['breakdown'],
            'quantity_multiplier' => $quantity,
        );

        // Make this cart item unique
        $cart_item_data['unique_key'] = md5( microtime() . wp_rand() );

        return $cart_item_data;
    }

    /**
     * Get display value for a field selection.
     *
     * @param array $field Field configuration.
     * @param mixed $value Selected value.
     * @return array Display data with label and value.
     */
    private function get_field_display_value( $field, $value ) {
        $label = isset( $field['label'] ) ? $field['label'] : '';
        $display_value = '';

        switch ( $field['type'] ) {
            case 'length':
                if ( 'fixed' === ( $field['length_mode'] ?? 'free' ) && isset( $field['fixed_options'][ $value ] ) ) {
                    $option = $field['fixed_options'][ $value ];
                    $display_value = ! empty( $option['label'] ) ? $option['label'] : $option['value'] . ' ' . ( $field['unit_type'] ?? 'mm' );
                } else {
                    $display_value = $value . ' ' . ( $field['unit_type'] ?? 'mm' );
                }
                break;

            case 'color':
                if ( isset( $field['colors'][ $value ] ) ) {
                    $display_value = $field['colors'][ $value ]['name'];
                }
                break;

            case 'mitre_angle':
                if ( isset( $field['angles'][ $value ] ) ) {
                    $display_value = $field['angles'][ $value ]['label'];
                }
                break;

            case 'quantity':
                $display_value = $value;
                break;

            case 'custom':
                if ( is_array( $value ) ) {
                    $labels = array();
                    foreach ( $value as $idx ) {
                        if ( isset( $field['custom_options'][ $idx ] ) ) {
                            $labels[] = $field['custom_options'][ $idx ]['label'];
                        }
                    }
                    $display_value = implode( ', ', $labels );
                } elseif ( isset( $field['custom_options'][ $value ] ) ) {
                    $display_value = $field['custom_options'][ $value ]['label'];
                }
                break;
        }

        return array(
            'label' => $label,
            'value' => $display_value,
        );
    }

    /**
     * Load calculator data from session.
     *
     * @param array $cart_item     Cart item data.
     * @param array $session_data  Session data.
     * @return array Modified cart item.
     */
    public function get_cart_item_from_session( $cart_item, $session_data ) {
        if ( isset( $session_data['bossier_calculator'] ) ) {
            $cart_item['bossier_calculator'] = $session_data['bossier_calculator'];
        }
        return $cart_item;
    }

    /**
     * Display calculator data in cart and checkout.
     *
     * @param array $item_data Existing item data.
     * @param array $cart_item Cart item.
     * @return array Modified item data.
     */
    public function display_cart_item_data( $item_data, $cart_item ) {
        if ( ! isset( $cart_item['bossier_calculator'] ) ) {
            return $item_data;
        }

        $calc_data = $cart_item['bossier_calculator'];

        // Display field selections
        if ( ! empty( $calc_data['display_data'] ) ) {
            foreach ( $calc_data['display_data'] as $field_id => $data ) {
                if ( empty( $data['value'] ) ) {
                    continue;
                }

                $item_data[] = array(
                    'key'   => $data['label'],
                    'value' => $data['value'],
                );
            }
        }

        // Display calculated weight
        $weight_unit = get_option( 'woocommerce_weight_unit', 'kg' );
        $item_data[] = array(
            'key'   => __( 'Weight', 'bossier-calculator' ),
            'value' => wc_format_localized_decimal( $calc_data['calculated_weight'] ) . ' ' . $weight_unit,
        );

        return $item_data;
    }

    /**
     * Set custom price for cart item.
     *
     * @param \WC_Cart $cart Cart object.
     */
    public function set_cart_item_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( ! isset( $cart_item['bossier_calculator'] ) ) {
                continue;
            }

            $calc_data = $cart_item['bossier_calculator'];
            $calculated_price = floatval( $calc_data['calculated_price'] );

            // Apply quantity multiplier if calculator has quantity field
            $quantity_multiplier = isset( $calc_data['quantity_multiplier'] ) ? intval( $calc_data['quantity_multiplier'] ) : 1;

            // Set the price (already includes quantity from calculator)
            $cart_item['data']->set_price( $calculated_price );

            // Store weight for later use
            if ( isset( $calc_data['calculated_weight'] ) ) {
                $cart_item['data']->set_weight( $calc_data['calculated_weight'] );
            }
        }
    }

    /**
     * Set product weight from calculator data.
     *
     * @param float       $weight  Original weight.
     * @param \WC_Product $product Product object.
     * @return float Modified weight.
     */
    public function set_product_weight( $weight, $product ) {
        // Weight is handled via cart item data
        return $weight;
    }

    /**
     * Process cart item after adding.
     *
     * @param array $cart_item Cart item data.
     * @return array Modified cart item.
     */
    public function add_cart_item( $cart_item ) {
        if ( isset( $cart_item['bossier_calculator'] ) ) {
            $calc_data = $cart_item['bossier_calculator'];

            // Set product price
            $cart_item['data']->set_price( $calc_data['calculated_price'] );

            // Set product weight
            if ( isset( $calc_data['calculated_weight'] ) ) {
                $cart_item['data']->set_weight( $calc_data['calculated_weight'] );
            }
        }

        return $cart_item;
    }

    /**
     * Get total weight of cart items with calculator.
     *
     * @return float Total calculated weight.
     */
    public static function get_cart_calculated_weight() {
        $total_weight = 0;

        if ( ! WC()->cart ) {
            return $total_weight;
        }

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['bossier_calculator'] ) ) {
                $calc_data = $cart_item['bossier_calculator'];
                $item_weight = floatval( $calc_data['calculated_weight'] );

                // Multiply by cart quantity
                $total_weight += $item_weight * $cart_item['quantity'];
            } else {
                // Regular product weight
                $product = $cart_item['data'];
                if ( $product->has_weight() ) {
                    $total_weight += floatval( $product->get_weight() ) * $cart_item['quantity'];
                }
            }
        }

        return $total_weight;
    }
}
