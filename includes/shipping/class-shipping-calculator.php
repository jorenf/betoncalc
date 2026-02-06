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
                'message'   => $settings['shipping_unknown_postcode_message'],
            );
        }

        // Get zone prices
        $zone_prices = $settings['shipping_zone_prices'][ $zone['id'] ] ?? array();

        if ( empty( $zone_prices ) ) {
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
            'delivery_days' => $zone['delivery_days'],
        );
    }

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

            // Get item weight
            $product = wc_get_product( $product_id );
            $item_weight = 0;

            if ( $product && $product->get_weight() ) {
                $item_weight = (float) $product->get_weight() * $quantity;
            }

            // Check for calculator data (for length)
            $item_length = 0;
            if ( isset( $cart_item['bossier_calculator_data']['length'] ) ) {
                $item_length = (float) $cart_item['bossier_calculator_data']['length'];
            }

            // Check for calculated weight from calculator
            if ( isset( $cart_item['bossier_calculator_data']['weight'] ) ) {
                $item_weight = (float) $cart_item['bossier_calculator_data']['weight'] * $quantity;
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
                        'count' => 0,
                        'items' => array(),
                    );
                }

                $pallet_items[ $pallet_type ]['count'] += $quantity;
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

        // Calculate pallet costs
        foreach ( $analysis['pallet_items'] as $pallet_type => $pallet_data ) {
            $pallet_price = $zone_prices[ $pallet_type ] ?? 0;

            if ( $pallet_price > 0 ) {
                // For now, 1 pallet per order type (simplification)
                // In reality, you'd calculate how many pallets needed based on product dimensions
                $pallet_cost = $pallet_price;
                $total += $pallet_cost;

                $breakdown[] = array(
                    'type'        => 'pallet',
                    'pallet_type' => $pallet_type,
                    'cost'        => $pallet_cost,
                    'description' => sprintf( __( 'Pallet verzending (%s)', 'bossier-calculator' ), $pallet_type ),
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
