<?php
/**
 * Shipping Calculator.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\Shipping;

use Bossier\Calculator\Modules_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Shipping_Calculator class - Calculates shipping costs.
 */
class Shipping_Calculator {

    /**
     * Calculate shipping cost for cart.
     *
     * @param string $country  Destination country.
     * @param string $postcode Destination postcode.
     * @param array  $package  WooCommerce package.
     * @return array Shipping cost data.
     */
    public static function calculate( $country, $postcode, $package ) {
        $settings = Modules_Settings::get_settings();

        // Find zone
        $zone = Zone_Matcher::find_zone( $country, $postcode );

        if ( ! $zone ) {
            return array(
                'available' => false,
                'message'   => $settings['shipping_unknown_postcode_message'] ?? __( 'Neem contact met ons op voor verzendkosten.', 'bossier-calculator' ),
            );
        }

        // Get zone prices
        $zone_prices = $settings['shipping_zone_prices'][ $zone['id'] ] ?? array();

        // If no zone prices configured, try to use default fallback cost
        if ( empty( $zone_prices ) ) {
            $default_cost = floatval( $settings['shipping_default_cost'] ?? 0 );
            if ( $default_cost > 0 ) {
                return array(
                    'available'     => true,
                    'cost'          => $default_cost,
                    'breakdown'     => array(),
                    'zone'          => $zone,
                    'delivery_days' => $zone['delivery_days'] ?? '',
                    'is_fallback'   => true,
                );
            }

            return array(
                'available' => false,
                'message'   => __( 'Geen verzendtarieven beschikbaar voor deze zone.', 'bossier-calculator' ),
            );
        }

        // Analyze cart items
        $cart_analysis = self::analyze_cart( $package['contents'] );

        // Calculate shipping cost
        $cost = self::calculate_cost( $cart_analysis, $zone_prices, $settings );

        return array(
            'available'     => true,
            'cost'          => $cost['total'],
            'breakdown'     => $cost['breakdown'],
            'zone'          => $zone,
            'delivery_days' => $zone['delivery_days'] ?? '',
        );
    }

    /**
     * Default maximum weight per pallet in kg (fallback when no shipping methods configured).
     */
    const MAX_PALLET_WEIGHT = 800;

    /**
     * Get configured shipping methods (enabled only), sorted by max_weight ascending.
     *
     * @return array Enabled shipping methods.
     */
    public static function get_enabled_methods() {
        $settings = Modules_Settings::get_settings();
        $methods  = $settings['shipping_methods'] ?? array();
        $enabled  = array();

        foreach ( $methods as $method ) {
            if ( ! empty( $method['enabled'] ) ) {
                $enabled[] = $method;
            }
        }

        // Sort by max_weight ascending so smallest method is first
        usort( $enabled, function( $a, $b ) {
            return $a['max_weight'] - $b['max_weight'];
        } );

        return $enabled;
    }

    /**
     * Get the max weight limit for a specific pallet type.
     * Checks configured shipping methods first; falls back to MAX_PALLET_WEIGHT.
     *
     * @return float Maximum weight per unit.
     */
    public static function get_max_pallet_weight() {
        $methods = self::get_enabled_methods();
        if ( empty( $methods ) ) {
            return self::MAX_PALLET_WEIGHT;
        }
        // Return the largest method's max weight
        $last = end( $methods );
        return floatval( $last['max_weight'] );
    }

    /**
     * Analyze cart contents for shipping calculation.
     *
     * @param array $cart_contents Cart contents.
     * @return array Analysis result.
     */
    private static function analyze_cart( $cart_contents ) {
        $pallet_items    = array();
        $loose_items     = array();
        $max_length      = 0;
        $total_weight    = 0;
        $product_methods = array(); // Collect per-product allowed methods

        foreach ( $cart_contents as $cart_item ) {
            $product_id = $cart_item['product_id'];
            $quantity   = $cart_item['quantity'];

            // Get product shipping settings
            $shipping_type   = get_post_meta( $product_id, '_boost_shipping_type', true ) ?: 'pallet';
            $pallet_type     = get_post_meta( $product_id, '_boost_pallet_type', true ) ?: 'euro';
            $allowed_methods = get_post_meta( $product_id, '_boost_allowed_shipping_methods', true );
            $requires_pallet = get_post_meta( $product_id, '_boost_requires_pallet', true );

            // Force pallet shipping if product requires it
            if ( '1' === $requires_pallet ) {
                $shipping_type = 'pallet';
            }

            // Collect product-level method restrictions
            if ( is_array( $allowed_methods ) && ! empty( $allowed_methods ) ) {
                $product_methods[ $product_id ] = $allowed_methods;
            }

            // Get item weight - first check calculator data, then product weight
            $item_weight = 0;
            $item_length = 0;

            // Check for calculator data (stored as 'bossier_calculator')
            if ( isset( $cart_item['bossier_calculator'] ) ) {
                $calc_data = $cart_item['bossier_calculator'];

                // Get calculated weight (per unit, then multiply by quantity)
                if ( isset( $calc_data['calculated_weight'] ) && $calc_data['calculated_weight'] > 0 ) {
                    $item_weight = (float) $calc_data['calculated_weight'] * $quantity;
                }

                // Get length from raw_values
                if ( isset( $calc_data['raw_values']['length_mm'] ) ) {
                    $item_length = (float) $calc_data['raw_values']['length_mm'];
                }
            }

            // Fallback to product weight if no calculator weight
            if ( $item_weight <= 0 ) {
                $product = wc_get_product( $product_id );
                if ( $product && $product->get_weight() ) {
                    $item_weight = (float) $product->get_weight() * $quantity;
                }
            }

            $total_weight += $item_weight;

            // Track max length for oversized calculation
            if ( $item_length > $max_length ) {
                $max_length = $item_length;
            }

            // Group by shipping type
            if ( 'loose' === $shipping_type ) {
                $loose_items[] = array(
                    'product_id' => $product_id,
                    'quantity'   => $quantity,
                    'weight'     => $item_weight,
                    'length'     => $item_length,
                );
            } else {
                if ( ! isset( $pallet_items[ $pallet_type ] ) ) {
                    $pallet_items[ $pallet_type ] = array(
                        'count'        => 0,
                        'total_weight' => 0,
                        'items'        => array(),
                    );
                }

                $pallet_items[ $pallet_type ]['count'] += $quantity;
                $pallet_items[ $pallet_type ]['total_weight'] += $item_weight;
                $pallet_items[ $pallet_type ]['items'][] = array(
                    'product_id' => $product_id,
                    'quantity'   => $quantity,
                    'weight'     => $item_weight,
                    'length'     => $item_length,
                );
            }
        }

        return array(
            'pallet_items'    => $pallet_items,
            'loose_items'     => $loose_items,
            'max_length'      => $max_length,
            'total_weight'    => $total_weight,
            'product_methods' => $product_methods,
        );
    }

    /**
     * Calculate shipping cost from analysis.
     *
     * Uses configured shipping methods for weight-based calculation.
     * Each method has a max_weight; if total pallet weight exceeds it,
     * multiple units of that method are needed.
     *
     * @param array $analysis    Cart analysis.
     * @param array $zone_prices Zone prices.
     * @param array $settings    Module settings.
     * @return array Cost calculation.
     */
    private static function calculate_cost( $analysis, $zone_prices, $settings ) {
        $total     = 0;
        $breakdown = array();

        // Get enabled shipping methods for weight-based calculation
        $all_methods = self::get_enabled_methods();

        // Filter methods by product-level restrictions
        $product_methods = $analysis['product_methods'] ?? array();
        $methods = $all_methods;

        if ( ! empty( $product_methods ) ) {
            // Intersect: only allow methods that ALL restricted products permit
            $allowed_ids = null;
            foreach ( $product_methods as $prod_id => $prod_allowed ) {
                if ( null === $allowed_ids ) {
                    $allowed_ids = $prod_allowed;
                } else {
                    $allowed_ids = array_intersect( $allowed_ids, $prod_allowed );
                }
            }

            if ( is_array( $allowed_ids ) && ! empty( $allowed_ids ) ) {
                $methods = array_filter( $all_methods, function( $method ) use ( $allowed_ids ) {
                    return in_array( $method['id'], $allowed_ids, true );
                } );
                $methods = array_values( $methods ); // Re-index
            }
        }

        // Calculate pallet costs per pallet type, matching methods by pallet_type
        foreach ( $analysis['pallet_items'] as $pallet_type => $pallet_data ) {
            $pallet_weight = $pallet_data['total_weight'] ?? 0;

            if ( $pallet_weight <= 0 ) {
                continue;
            }

            // Filter methods: only those linked to this pallet type (or linked to all via empty pallet_type)
            $type_methods = array_filter( $methods, function( $m ) use ( $pallet_type ) {
                return empty( $m['pallet_type'] ) || $m['pallet_type'] === $pallet_type;
            } );

            if ( empty( $type_methods ) ) {
                // Fallback: use all methods if none match this pallet type
                $type_methods = $methods;
            }

            // Find the best shipping method for this pallet type's weight
            // Prefer: 1) lowest total cost, 2) fewest units when costs are equal
            $best_method     = null;
            $best_cost       = PHP_FLOAT_MAX;
            $best_units      = PHP_INT_MAX;
            $best_unit_price = 0;

            foreach ( $type_methods as $method ) {
                $method_max   = floatval( $method['max_weight'] );
                $units_needed = ( $method_max > 0 ) ? max( 1, ceil( $pallet_weight / $method_max ) ) : 1;

                // Zone price overrides base price
                $unit_price = ( isset( $zone_prices[ $method['id'] ] ) && floatval( $zone_prices[ $method['id'] ] ) > 0 )
                    ? floatval( $zone_prices[ $method['id'] ] )
                    : floatval( $method['base_price'] ?? 0 );

                $method_cost = $unit_price * $units_needed;

                // Pick this method if: cheaper total cost, OR same cost but fewer units
                if ( $method_cost < $best_cost
                    || ( $method_cost == $best_cost && $units_needed < $best_units ) ) {
                    $best_cost       = $method_cost;
                    $best_method     = $method;
                    $best_units      = $units_needed;
                    $best_unit_price = $unit_price;
                }
            }

            if ( $best_method ) {
                $total += $best_cost;

                $breakdown[] = array(
                    'type'        => 'pallet',
                    'pallet_type' => $pallet_type,
                    'method_id'   => $best_method['id'],
                    'method_name' => $best_method['name'],
                    'units'       => $best_units,
                    'weight'      => $pallet_weight,
                    'cost'        => $best_cost,
                    'description' => sprintf(
                        /* translators: 1: method name, 2: number of units, 3: weight */
                        __( 'Verzending (%1$s) - %2$dx (%3$s kg)', 'bossier-calculator' ),
                        $best_method['name'],
                        $best_units,
                        number_format_i18n( $pallet_weight, 1 )
                    ),
                );
            }
        }

        // Calculate loose shipping costs
        if ( ! empty( $analysis['loose_items'] ) ) {
            $loose_base   = $zone_prices['loose'] ?? 0;
            $loose_per_kg = $zone_prices['loose_per_kg'] ?? 0;

            $loose_weight = 0;
            foreach ( $analysis['loose_items'] as $item ) {
                $loose_weight += $item['weight'];
            }

            $loose_cost = $loose_base + ( $loose_weight * $loose_per_kg );
            $total += $loose_cost;

            $breakdown[] = array(
                'type'        => 'loose',
                'cost'        => $loose_cost,
                'weight'      => $loose_weight,
                'description' => __( 'Losse verzending', 'bossier-calculator' ),
            );
        }

        // Add oversized surcharge if applicable
        $oversized_threshold = $settings['shipping_oversized_threshold'] ?? 1500;

        if ( $analysis['max_length'] > $oversized_threshold ) {
            $oversized_type   = $settings['shipping_oversized_type'] ?? 'fixed';
            $oversized_amount = $settings['shipping_oversized_amount'] ?? 25;

            $oversized_cost = 0;

            switch ( $oversized_type ) {
                case 'fixed':
                    $oversized_cost = $oversized_amount;
                    break;

                case 'percentage':
                    $oversized_cost = $total * ( $oversized_amount / 100 );
                    break;

                case 'per_mm':
                    $extra_mm = $analysis['max_length'] - $oversized_threshold;
                    $oversized_cost = $extra_mm * $oversized_amount;
                    break;
            }

            if ( $oversized_cost > 0 ) {
                $total += $oversized_cost;

                $breakdown[] = array(
                    'type'        => 'oversized',
                    'cost'        => $oversized_cost,
                    'length'      => $analysis['max_length'],
                    'description' => sprintf( __( 'Toeslag lang product (>%dmm)', 'bossier-calculator' ), $oversized_threshold ),
                );
            }
        }

        return array(
            'total'     => $total,
            'breakdown' => $breakdown,
        );
    }

    /**
     * Calculate pickup (always free).
     *
     * @return array
     */
    public static function calculate_pickup() {
        $settings = Modules_Settings::get_settings();

        return array(
            'available' => ! empty( $settings['shipping_pickup_enabled'] ),
            'cost'      => 0,
            'address'   => $settings['shipping_pickup_address'],
        );
    }
}
