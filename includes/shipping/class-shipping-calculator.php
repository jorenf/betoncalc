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
     * Maximum weight per pallet in kg.
     */
    const MAX_PALLET_WEIGHT = 800;

    /**
     * Analyze cart contents for shipping calculation.
     *
     * @param array $cart_contents Cart contents.
     * @return array Analysis result.
     */
    private static function analyze_cart( $cart_contents ) {
        $pallet_items = array();
        $loose_items  = array();
        $max_length   = 0;
        $total_weight = 0;

        foreach ( $cart_contents as $cart_item ) {
            $product_id = $cart_item['product_id'];
            $quantity   = $cart_item['quantity'];

            // Get product shipping settings
            $shipping_type = get_post_meta( $product_id, '_boost_shipping_type', true ) ?: 'pallet';
            $pallet_type   = get_post_meta( $product_id, '_boost_pallet_type', true ) ?: 'euro';

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
            'pallet_items' => $pallet_items,
            'loose_items'  => $loose_items,
            'max_length'   => $max_length,
            'total_weight' => $total_weight,
        );
    }

    /**
     * Calculate shipping cost from analysis.
     *
     * @param array $analysis    Cart analysis.
     * @param array $zone_prices Zone prices.
     * @param array $settings    Module settings.
     * @return array Cost calculation.
     */
    private static function calculate_cost( $analysis, $zone_prices, $settings ) {
        $total     = 0;
        $breakdown = array();

        // Calculate pallet costs based on weight (max 800kg per pallet)
        foreach ( $analysis['pallet_items'] as $pallet_type => $pallet_data ) {
            $pallet_price  = $zone_prices[ $pallet_type ] ?? 0;
            $pallet_weight = $pallet_data['total_weight'] ?? 0;

            if ( $pallet_price > 0 ) {
                // Calculate number of pallets needed based on weight limit
                $pallets_needed = 1;
                if ( $pallet_weight > 0 ) {
                    $pallets_needed = max( 1, ceil( $pallet_weight / self::MAX_PALLET_WEIGHT ) );
                }

                $pallet_cost = $pallet_price * $pallets_needed;
                $total += $pallet_cost;

                // Build description with weight info
                $description = sprintf(
                    /* translators: 1: pallet type, 2: number of pallets, 3: weight */
                    __( 'Pallet verzending (%1$s) - %2$dx pallet (%3$s kg)', 'bossier-calculator' ),
                    $pallet_type,
                    $pallets_needed,
                    number_format_i18n( $pallet_weight, 1 )
                );

                $breakdown[] = array(
                    'type'           => 'pallet',
                    'pallet_type'    => $pallet_type,
                    'pallets_needed' => $pallets_needed,
                    'weight'         => $pallet_weight,
                    'cost'           => $pallet_cost,
                    'description'    => $description,
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
