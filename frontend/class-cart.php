<?php
/**
 * Cart integration class.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\Frontend;

use Bossier\Calculator\Calculator;
use Bossier\Calculator\Price_Calculator;
use Bossier\Calculator\Frontend\Display;

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

        // Sync calculator quantity to WooCommerce cart quantity
        add_filter( 'woocommerce_add_to_cart_quantity', array( $this, 'override_add_to_cart_quantity' ), 10, 2 );

        // Load calculator data from session
        add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );

        // Display calculator data in cart
        add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );

        // Set custom price for cart item - priority 10 to run before other plugins
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_cart_item_price' ), 10 );

        // Add hidden long length surcharge as a fee
        add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_long_length_surcharge_fee' ), 20 );

        // Make each calculator product unique in cart
        add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 10, 2 );

        // Provide correct weight to shipping plugins via cart contents weight
        add_filter( 'woocommerce_cart_contents_weight', array( $this, 'calculate_cart_weight' ), 99 );

        // Hook into package weight for shipping calculations
        add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'update_shipping_packages' ), 99 );
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
        $selections   = array();
        $display_data = array();
        $fields       = $calculator->get_enabled_fields();

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
        }

        // Prepare display data — skip fields hidden by show_when
        foreach ( $selections as $field_id => $value ) {
            if ( ! isset( $fields[ $field_id ] ) ) {
                continue;
            }

            $field = $fields[ $field_id ];

            // Skip fields whose show_when condition is not met
            if ( ! Display::is_field_visible_in_post( $field, $fields ) ) {
                continue;
            }

            $display_data[ $field_id ] = $this->get_field_display_value( $field, $value, $field_id );
        }

        // Calculate price and weight - include product base price
        $price_calc = new Price_Calculator( $calculator );
        $result     = $price_calc->calculate( $selections, $product_id );

        // Get quantity from calculator if present
        $quantity = 1;
        foreach ( $fields as $field_id => $field ) {
            if ( 'quantity' === $field['type'] && isset( $selections[ $field_id ] ) ) {
                $quantity = max( 1, intval( $selections[ $field_id ] ) );
                break;
            }
        }

        // Separate long length surcharge from customer-visible price
        $long_length_surcharge = isset( $result['long_length_surcharge'] ) ? floatval( $result['long_length_surcharge'] ) : 0;
        $customer_price        = $result['price'] - $long_length_surcharge;

        // Store calculator data in cart item
        $cart_item_data['bossier_calculator'] = array(
            'calculator_id'        => $calculator_id,
            'product_id'           => $product_id,
            'selections'           => $selections,
            'display_data'         => $display_data,
            'calculated_price'     => $customer_price,
            'long_length_surcharge'=> $long_length_surcharge,
            'calculated_weight'    => $result['weight'],
            'breakdown'            => $result['breakdown'],
            'raw_values'           => isset( $result['raw_values'] ) ? $result['raw_values'] : array(),
            'quantity_multiplier'  => $quantity,
        );

        // Make this cart item unique
        $cart_item_data['unique_key'] = md5( microtime() . wp_rand() );

        return $cart_item_data;
    }

    /**
     * Override WooCommerce add-to-cart quantity with calculator's quantity field.
     *
     * Safety net for when the JS sync to WC's native quantity input
     * doesn't work (e.g., custom themes that remove the quantity field).
     *
     * @param int $quantity   Default quantity.
     * @param int $product_id Product ID.
     * @return int Modified quantity.
     */
    public function override_add_to_cart_quantity( $quantity, $product_id ) {
        $calculator_id = get_post_meta( $product_id, '_bossier_calculator_id', true );

        if ( empty( $calculator_id ) ) {
            return $quantity;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( ! isset( $_POST['bossier_calculator_id'] ) ) {
            return $quantity;
        }

        $calculator = new Calculator( $calculator_id );
        if ( ! $calculator->is_valid() ) {
            return $quantity;
        }

        // Find the quantity field and get its value from POST
        $fields = $calculator->get_enabled_fields();
        foreach ( $fields as $field_id => $field ) {
            if ( 'quantity' !== ( $field['type'] ?? '' ) ) {
                continue;
            }
            $field_key = 'bossier_calc_' . $field_id;
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ( isset( $_POST[ $field_key ] ) ) {
                $calc_qty = max( 1, intval( $_POST[ $field_key ] ) );
                return $calc_qty;
            }
        }

        return $quantity;
    }

    /**
     * Get display value for a field selection.
     *
     * @param array  $field    Field configuration.
     * @param mixed  $value    Selected value.
     * @param string $field_id Optional field ID for reading related POST data.
     * @return array Display data with label and value.
     */
    private function get_field_display_value( $field, $value, $field_id = '' ) {
        $label         = isset( $field['label'] ) ? $field['label'] : '';
        $display_value = '';
        $raw_value     = $value;

        switch ( $field['type'] ) {
            case 'length':
                $unit_type = isset( $field['unit_type'] ) ? $field['unit_type'] : 'mm';
                if ( 'fixed' === ( $field['length_mode'] ?? 'free' ) && isset( $field['fixed_options'][ $value ] ) ) {
                    $option        = $field['fixed_options'][ $value ];
                    $raw_value     = floatval( $option['value'] );
                    $display_value = ! empty( $option['label'] ) ? $option['label'] : $raw_value . ' ' . $unit_type;
                } else {
                    $raw_value     = floatval( $value );
                    $display_value = $value . ' ' . $unit_type;
                }
                break;

            case 'color':
                if ( isset( $field['colors'][ $value ] ) ) {
                    $display_value = $field['colors'][ $value ]['name'];
                    $raw_value     = $field['colors'][ $value ];
                }
                break;

            case 'mitre_angle':
                // Check for new groups structure
                if ( isset( $field['mitre_groups'] ) && is_array( $value ) ) {
                    // Multiple groups - value is array of group_id => angle_idx
                    $group_displays = array();
                    $raw_value      = array();
                    $no_mitre_label = '';

                    // First pass: check if any selected angle has is_no_mitre set
                    foreach ( $field['mitre_groups'] as $group ) {
                        $group_id = isset( $group['id'] ) ? $group['id'] : '';
                        if ( ! isset( $value[ $group_id ] ) ) {
                            continue;
                        }

                        $angle_idx    = $value[ $group_id ];
                        $group_angles = isset( $group['angles'] ) ? $group['angles'] : array();

                        if ( isset( $group_angles[ $angle_idx ] ) ) {
                            $angle = $group_angles[ $angle_idx ];
                            if ( ! empty( $angle['is_no_mitre'] ) ) {
                                // Found a "geen hoek" selection - only show this label
                                $no_mitre_label = isset( $angle['label'] ) ? $angle['label'] : '';
                                break;
                            }
                        }
                    }

                    // If geen hoek is selected, only show that label
                    if ( ! empty( $no_mitre_label ) ) {
                        $display_value = $no_mitre_label;
                        $raw_value     = array(
                            'is_no_mitre' => true,
                            'label'       => $no_mitre_label,
                        );
                    } else {
                        // Normal processing - show all groups
                        foreach ( $field['mitre_groups'] as $group ) {
                            $group_id = isset( $group['id'] ) ? $group['id'] : '';
                            if ( ! isset( $value[ $group_id ] ) ) {
                                continue;
                            }

                            $angle_idx    = $value[ $group_id ];
                            $group_label  = isset( $group['label'] ) ? $group['label'] : '';
                            $group_angles = isset( $group['angles'] ) ? $group['angles'] : array();

                            if ( isset( $group_angles[ $angle_idx ] ) ) {
                                $angle = $group_angles[ $angle_idx ];
                                $angle_label = isset( $angle['label'] ) ? $angle['label'] : '';

                                // Format: "Hoek links: 45°"
                                if ( ! empty( $group_label ) ) {
                                    $group_displays[] = $group_label . ': ' . $angle_label;
                                } else {
                                    $group_displays[] = $angle_label;
                                }

                                $raw_value[ $group_id ] = array(
                                    'group_label'  => $group_label,
                                    'angle_label'  => $angle_label,
                                    'angle_idx'    => $angle_idx,
                                    'surcharge'    => isset( $angle['surcharge'] ) ? floatval( $angle['surcharge'] ) : 0,
                                    'extra_weight' => isset( $angle['extra_weight'] ) ? floatval( $angle['extra_weight'] ) : 0,
                                );
                            }
                        }

                        $display_value = implode( ' | ', $group_displays );
                    }
                } elseif ( isset( $field['angles'] ) && ! is_array( $value ) && isset( $field['angles'][ $value ] ) ) {
                    // Legacy single angles structure
                    $angle = $field['angles'][ $value ];
                    if ( ! empty( $angle['is_no_mitre'] ) ) {
                        // Geen hoek selected - only show label
                        $display_value = $angle['label'];
                        $raw_value     = array(
                            'is_no_mitre' => true,
                            'label'       => $angle['label'],
                        );
                    } else {
                        $display_value = $angle['label'];
                        $raw_value     = $angle;
                    }
                }
                break;

            case 'quantity':
                $raw_value     = intval( $value );
                $display_value = $value;
                break;

            case 'custom':
                if ( is_array( $value ) ) {
                    $labels    = array();
                    $raw_value = array();
                    foreach ( $value as $idx ) {
                        if ( isset( $field['custom_options'][ $idx ] ) ) {
                            $option_label = $field['custom_options'][ $idx ]['label'];

                            // Append inline text input value if present
                            if ( ! empty( $field['custom_options'][ $idx ]['has_text_input'] ) && ! empty( $field_id ) ) {
                                $text_key = 'bossier_calc_' . $field_id . '_text_' . $idx;
                                // phpcs:ignore WordPress.Security.NonceVerification.Missing
                                if ( isset( $_POST[ $text_key ] ) && '' !== $_POST[ $text_key ] ) {
                                    $text_val = sanitize_text_field( wp_unslash( $_POST[ $text_key ] ) );
                                    $option_label .= ': ' . $text_val;
                                }
                            }

                            $labels[]    = $option_label;
                            $raw_value[] = $field['custom_options'][ $idx ];
                        }
                    }
                    $display_value = implode( ', ', $labels );
                } elseif ( isset( $field['custom_options'][ $value ] ) ) {
                    $display_value = $field['custom_options'][ $value ]['label'];
                    $raw_value     = $field['custom_options'][ $value ];
                }
                break;

            case 'dimension':
                $unit_type     = isset( $field['unit_type'] ) ? $field['unit_type'] : 'mm';
                $raw_value     = floatval( $value );
                $display_value = number_format( $raw_value, 0, ',', '.' ) . ' ' . $unit_type;
                break;

            case 'text':
                $raw_value     = sanitize_text_field( $value );
                $display_value = $raw_value;
                break;

            case 'brievenbus':
                $main_answer = 'nee';
                if ( is_string( $value ) ) {
                    $main_answer = $value;
                }

                if ( 'ja' === $main_answer ) {
                    $main_label = isset( $field['main_label'] ) ? $field['main_label'] : __( 'Huisnummer', 'bossier-calculator' );
                    $parts      = array();

                    // Read main text from POST
                    $main_text_key = 'bossier_calc_' . $field_id . '_main_text';
                    // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    $main_text = isset( $_POST[ $main_text_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $main_text_key ] ) ) : '';
                    $parts[]   = $main_label . ': ' . __( 'Ja', 'bossier-calculator' ) . ( $main_text ? ' (' . $main_text . ')' : '' );

                    // Check sub answer from POST toggles (stored as separate hidden or toggle state)
                    $sub_text_key = 'bossier_calc_' . $field_id . '_sub_text';
                    // phpcs:ignore WordPress.Security.NonceVerification.Missing
                    $sub_text  = isset( $_POST[ $sub_text_key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $sub_text_key ] ) ) : '';
                    $sub_label = isset( $field['sub_label'] ) ? $field['sub_label'] : __( 'Toevoeging', 'bossier-calculator' );

                    if ( ! empty( $sub_text ) ) {
                        $parts[] = $sub_label . ': ' . __( 'Ja', 'bossier-calculator' ) . ' (' . $sub_text . ')';
                    }

                    $display_value = implode( ' | ', $parts );
                    $raw_value     = array(
                        'main'      => 'ja',
                        'main_text' => $main_text,
                        'sub'       => ! empty( $sub_text ) ? 'ja' : 'nee',
                        'sub_text'  => $sub_text,
                    );
                } else {
                    $display_value = __( 'Nee', 'bossier-calculator' );
                    $raw_value     = array( 'main' => 'nee' );
                }
                break;
        }

        return array(
            'label'     => $label,
            'value'     => $display_value,
            'raw_value' => $raw_value,
            'type'      => $field['type'],
        );
    }

    /**
     * Load calculator data from session.
     *
     * @param array $cart_item    Cart item data.
     * @param array $session_data Session data.
     * @return array Modified cart item.
     */
    public function get_cart_item_from_session( $cart_item, $session_data ) {
        if ( isset( $session_data['bossier_calculator'] ) ) {
            $cart_item['bossier_calculator'] = $session_data['bossier_calculator'];

            // Re-apply calculated price to product
            if ( isset( $cart_item['bossier_calculator']['calculated_price'] ) ) {
                $cart_item['data']->set_price( floatval( $cart_item['bossier_calculator']['calculated_price'] ) );
            }

            // Re-apply weight to product for shipping calculations
            if ( isset( $cart_item['bossier_calculator']['calculated_weight'] ) ) {
                $cart_item['data']->set_weight( floatval( $cart_item['bossier_calculator']['calculated_weight'] ) );
            }
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

        // Display calculated weight (only if weight > 0)
        if ( ! empty( $calc_data['calculated_weight'] ) && $calc_data['calculated_weight'] > 0 ) {
            $weight_unit = get_option( 'woocommerce_weight_unit', 'kg' );
            $item_data[] = array(
                'key'   => __( 'Gewicht', 'bossier-calculator' ),
                'value' => wc_format_localized_decimal( $calc_data['calculated_weight'] ) . ' ' . $weight_unit,
            );
        }

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

        // Prevent running multiple times in the same request
        static $done = false;
        if ( $done ) {
            // Still need to set prices on subsequent runs
            foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
                if ( isset( $cart_item['bossier_calculator']['calculated_price'] ) ) {
                    $cart_item['data']->set_price( floatval( $cart_item['bossier_calculator']['calculated_price'] ) );
                }
            }
            return;
        }
        $done = true;

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( ! isset( $cart_item['bossier_calculator'] ) ) {
                continue;
            }

            $calc_data        = $cart_item['bossier_calculator'];
            $calculated_price = floatval( $calc_data['calculated_price'] );

            // Force the calculator price — this must override the WooCommerce product price
            $cart_item['data']->set_price( $calculated_price );

            // Set weight for shipping calculations
            if ( isset( $calc_data['calculated_weight'] ) ) {
                $cart_item['data']->set_weight( floatval( $calc_data['calculated_weight'] ) );
            }
        }
    }

    /**
     * Add hidden long length surcharge as a WooCommerce fee.
     * This keeps the product line price matching the calculator display
     * while ensuring the total includes the hidden surcharge.
     *
     * @param \WC_Cart $cart Cart object.
     */
    public function add_long_length_surcharge_fee( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        $total_surcharge = 0;

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( ! isset( $cart_item['bossier_calculator'] ) ) {
                continue;
            }

            $surcharge = floatval( $cart_item['bossier_calculator']['long_length_surcharge'] ?? 0 );
            if ( $surcharge > 0 ) {
                $total_surcharge += $surcharge * $cart_item['quantity'];
            }
        }

        if ( $total_surcharge > 0 ) {
            $cart->add_fee( __( 'Toeslag', 'bossier-calculator' ), $total_surcharge, true );
        }
    }

    /**
     * Process cart item after adding.
     *
     * @param array  $cart_item     Cart item data.
     * @param string $cart_item_key Cart item key.
     * @return array Modified cart item.
     */
    public function add_cart_item( $cart_item, $cart_item_key = '' ) {
        if ( isset( $cart_item['bossier_calculator'] ) ) {
            $calc_data = $cart_item['bossier_calculator'];

            // Set product price
            $cart_item['data']->set_price( floatval( $calc_data['calculated_price'] ) );

            // Set product weight for shipping plugins
            if ( isset( $calc_data['calculated_weight'] ) ) {
                $cart_item['data']->set_weight( floatval( $calc_data['calculated_weight'] ) );
            }
        }

        return $cart_item;
    }

    /**
     * Calculate total cart weight including calculator items.
     * This ensures shipping plugins get the correct weight.
     *
     * @param float $weight Current cart weight.
     * @return float Modified total weight.
     */
    public function calculate_cart_weight( $weight ) {
        $total_weight = 0;

        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $quantity = $cart_item['quantity'];

            if ( isset( $cart_item['bossier_calculator'] ) ) {
                // Use calculated weight from calculator
                $item_weight   = floatval( $cart_item['bossier_calculator']['calculated_weight'] );
                $total_weight += $item_weight * $quantity;
            } else {
                // Use standard product weight
                $product = $cart_item['data'];
                if ( $product && $product->has_weight() ) {
                    $total_weight += floatval( $product->get_weight() ) * $quantity;
                }
            }
        }

        return $total_weight;
    }

    /**
     * Update shipping packages with correct weights.
     *
     * @param array $packages Shipping packages.
     * @return array Modified packages.
     */
    public function update_shipping_packages( $packages ) {
        foreach ( $packages as &$package ) {
            $package_weight = 0;

            foreach ( $package['contents'] as $cart_item_key => $cart_item ) {
                $quantity = $cart_item['quantity'];

                if ( isset( $cart_item['bossier_calculator'] ) ) {
                    $item_weight     = floatval( $cart_item['bossier_calculator']['calculated_weight'] );
                    $package_weight += $item_weight * $quantity;
                } else {
                    $product = $cart_item['data'];
                    if ( $product && $product->has_weight() ) {
                        $package_weight += floatval( $product->get_weight() ) * $quantity;
                    }
                }
            }

            // Store calculated weight in package for shipping plugins
            $package['bossier_total_weight'] = $package_weight;
        }

        return $packages;
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
                $calc_data   = $cart_item['bossier_calculator'];
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

    /**
     * Get calculator data for a cart item.
     * Can be used by external plugins to access calculator data.
     *
     * Example usage:
     * $calc_data = \Bossier\Calculator\Frontend\Cart::get_item_calculator_data( $cart_item );
     * $length_mm = $calc_data['raw_values']['length_mm'];
     * $weight    = $calc_data['calculated_weight'];
     *
     * @param array $cart_item Cart item data.
     * @return array|null Calculator data or null if not a calculator item.
     */
    public static function get_item_calculator_data( $cart_item ) {
        if ( isset( $cart_item['bossier_calculator'] ) ) {
            return $cart_item['bossier_calculator'];
        }
        return null;
    }

    /**
     * Get raw value from calculator data.
     * Helper method for external plugins.
     *
     * @param array  $cart_item Cart item data.
     * @param string $key       Raw value key (e.g., 'length_mm', 'length_m', 'product_base_price').
     * @return mixed|null Value or null if not found.
     */
    public static function get_item_raw_value( $cart_item, $key ) {
        if ( isset( $cart_item['bossier_calculator']['raw_values'][ $key ] ) ) {
            return $cart_item['bossier_calculator']['raw_values'][ $key ];
        }
        return null;
    }
}
