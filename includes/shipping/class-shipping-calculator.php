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

        // Get zone prices and per-price excl. BTW flags for this zone.
        $zone_prices          = $settings['shipping_zone_prices'][ $zone['id'] ] ?? array();
        $zone_excl_btw_flags  = $settings['shipping_zone_prices_excl_btw_flags'][ $zone['id'] ] ?? array();

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
        $zone_excluded_methods = $settings['shipping_zone_excluded_methods'][ $zone['id'] ] ?? array();
        $cost      = self::calculate_cost( $cart_analysis, $zone_prices, $zone_excl_btw_flags, $settings, $zone_excluded_methods );
        $total     = $cost['total'];
        $breakdown = $cost['breakdown'];

        return array(
            'available'     => true,
            'cost'          => $total,
            'breakdown'     => $breakdown,
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
        $raw_pallet_items = array(); // Flat list before grouping; each entry includes acceptable_types.
        $loose_items      = array();
        $max_length       = 0;
        $total_weight     = 0;
        $product_methods  = array(); // Collect per-product allowed methods

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
                // Determine which pallet types this product is compatible with.
                // The main pallet type is always acceptable; additional types come from
                // the _boost_compatible_pallets meta (set in the product shipping metabox).
                $compatible = get_post_meta( $product_id, '_boost_compatible_pallets', true );
                if ( ! is_array( $compatible ) ) {
                    $compatible = array();
                }
                $acceptable_types = array_unique( array_merge( array( $pallet_type ), $compatible ) );

                $raw_pallet_items[] = array(
                    'product_id'       => $product_id,
                    'quantity'         => $quantity,
                    'weight'           => $item_weight,
                    'length'           => $item_length,
                    'pallet_type'      => $pallet_type,
                    'acceptable_types' => $acceptable_types,
                );
            }
        }

        // Find a single common pallet type that all pallet items accept (intersection).
        // This allows products with different primary pallet types to be combined onto
        // one shared pallet type, avoiding unnecessary extra pallet shipping costs.
        $common_type = null;
        if ( ! empty( $raw_pallet_items ) ) {
            $common_types = null;
            foreach ( $raw_pallet_items as $raw_item ) {
                if ( null === $common_types ) {
                    $common_types = $raw_item['acceptable_types'];
                } else {
                    $common_types = array_values( array_intersect( $common_types, $raw_item['acceptable_types'] ) );
                }
            }
            if ( ! empty( $common_types ) ) {
                $common_type = $common_types[0];
            }
        }

        // Group pallet items by the resolved common type (if found) or each product's own type.
        $pallet_items = array();
        foreach ( $raw_pallet_items as $raw_item ) {
            $resolved_type = ( null !== $common_type ) ? $common_type : $raw_item['pallet_type'];

            if ( ! isset( $pallet_items[ $resolved_type ] ) ) {
                $pallet_items[ $resolved_type ] = array(
                    'count'        => 0,
                    'total_weight' => 0,
                    'items'        => array(),
                );
            }

            $pallet_items[ $resolved_type ]['count']        += $raw_item['quantity'];
            $pallet_items[ $resolved_type ]['total_weight'] += $raw_item['weight'];
            $pallet_items[ $resolved_type ]['items'][]       = array(
                'product_id' => $raw_item['product_id'],
                'quantity'   => $raw_item['quantity'],
                'weight'     => $raw_item['weight'],
                'length'     => $raw_item['length'],
            );
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
    private static function calculate_cost( $analysis, $zone_prices, $zone_excl_btw_flags, $settings, $zone_excluded_methods = array() ) {
        $total          = 0;
        $excl_btw_total = 0; // Only tracks costs explicitly entered as excl. BTW (flagged prices).
        $breakdown      = array();

        // Get enabled shipping methods for weight-based calculation
        $all_methods = self::get_enabled_methods();

        // Remove methods excluded for this zone.
        if ( ! empty( $zone_excluded_methods ) ) {
            $all_methods = array_values( array_filter( $all_methods, function ( $m ) use ( $zone_excluded_methods ) {
                return empty( $zone_excluded_methods[ $m['id'] ] );
            } ) );
        }

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
                $type_methods = $methods;
            }

            // Sort methods by max_weight ascending (smallest capacity first)
            usort( $type_methods, function( $a, $b ) {
                return floatval( $a['max_weight'] ) - floatval( $b['max_weight'] );
            } );

            // Step-up logic: try methods from smallest to largest.
            // Use the smallest method that can handle the weight in 1 unit.
            // Only split into multiple units using the LARGEST method if none can handle it in 1.
            $selected_method = null;
            $selected_units  = 1;

            foreach ( $type_methods as $method ) {
                $method_max = floatval( $method['max_weight'] );
                if ( $method_max >= $pallet_weight ) {
                    // This method fits the entire weight in 1 unit — use it
                    $selected_method = $method;
                    $selected_units  = 1;
                    break;
                }
            }

            // No single method can handle the weight — use the largest method and split
            if ( ! $selected_method && ! empty( $type_methods ) ) {
                $largest_method  = end( $type_methods );
                $largest_max     = floatval( $largest_method['max_weight'] );
                $selected_method = $largest_method;
                $selected_units  = ( $largest_max > 0 ) ? max( 1, ceil( $pallet_weight / $largest_max ) ) : 1;
            }

            if ( $selected_method ) {
                // Zone price overrides base price.
                $has_zone_price = isset( $zone_prices[ $selected_method['id'] ] ) && floatval( $zone_prices[ $selected_method['id'] ] ) > 0;
                $unit_price     = $has_zone_price
                    ? floatval( $zone_prices[ $selected_method['id'] ] )
                    : floatval( $selected_method['base_price'] ?? 0 );

                $pallet_cost = $unit_price * $selected_units;
                $total      += $pallet_cost;

                // Only treat as excl. BTW when the per-price flag is explicitly set.
                // Prices entered before the excl. BTW setting was enabled have no flag (incl. BTW already).
                if ( ! empty( $zone_excl_btw_flags[ $selected_method['id'] ] ) ) {
                    $excl_btw_total += $pallet_cost;
                }

                $breakdown[] = array(
                    'type'        => 'pallet',
                    'pallet_type' => $pallet_type,
                    'method_id'   => $selected_method['id'],
                    'method_name' => $selected_method['name'],
                    'units'       => $selected_units,
                    'weight'      => $pallet_weight,
                    'cost'        => $pallet_cost,
                    'description' => sprintf(
                        /* translators: 1: method name, 2: number of units, 3: weight */
                        __( 'Verzending (%1$s) - %2$dx (%3$s kg)', 'bossier-calculator' ),
                        $selected_method['name'],
                        $selected_units,
                        number_format_i18n( $pallet_weight, 1 )
                    ),
                );
            }
        }

        // Calculate loose shipping costs
        if ( ! empty( $analysis['loose_items'] ) ) {
            $loose_base   = empty( $zone_excluded_methods['loose'] )        ? ( $zone_prices['loose'] ?? 0 )        : 0;
            $loose_per_kg = empty( $zone_excluded_methods['loose_per_kg'] ) ? ( $zone_prices['loose_per_kg'] ?? 0 ) : 0;

            $loose_weight = 0;
            foreach ( $analysis['loose_items'] as $item ) {
                $loose_weight += $item['weight'];
            }

            $loose_cost = $loose_base + ( $loose_weight * $loose_per_kg );
            $total     += $loose_cost;
            // Only apply BTW if loose prices were explicitly flagged as excl. BTW.
            if ( ! empty( $zone_excl_btw_flags['loose'] ) || ! empty( $zone_excl_btw_flags['loose_per_kg'] ) ) {
                $excl_btw_total += $loose_cost;
            }

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
                // Oversized surcharge: only add to excl_btw_total when at least one
                // zone price is already flagged excl. BTW (avoid triggering BTW on
                // orders that have zero explicitly-excl-BTW shipping lines).
                if ( $excl_btw_total > 0 ) {
                    $excl_btw_total += $oversized_cost;
                }

                $breakdown[] = array(
                    'type'        => 'oversized',
                    'cost'        => $oversized_cost,
                    'length'      => $analysis['max_length'],
                    'description' => sprintf( __( 'Toeslag lang product (>%dmm)', 'bossier-calculator' ), $oversized_threshold ),
                );
            }
        }

        // Apply diesel + inpak surcharges and BTW when zone prices are excl. BTW.
        //
        // Order of operations (per spec):
        //   1. Base shipping cost  (excl. BTW)
        //   2. Oversized surcharge (already applied above, also excl. BTW)
        //   3. Diesel + inpak toeslag  → on the combined excl. total
        //   4. Tol                     → on the surcharge-adjusted total (before BTW)
        //   5. BTW (21%)               → multiplied on top of all surcharges
        // Apply toeslag + BTW only to the excl. BTW portion (explicit zone prices).
        // Prices that fell back to base_price are already incl. BTW and are left unchanged.
        if ( ! empty( $settings['shipping_prices_excl_btw'] ) && $excl_btw_total > 0 ) {
            $incl_btw_part = $total - $excl_btw_total;
            $toeslag_pct   = Surcharge_Calculator::get_effective_surcharge( $settings );
            $toll_pct      = floatval( $settings['shipping_toll_percentage'] ?? 0 );
            $toeslag_pct  += $toll_pct; // diesel + inpak + tol combined, then × BTW

            // Show toeslag breakdown only when it is non-zero.
            if ( 0.0 !== $toeslag_pct ) {
                $toeslag_bedrag = round( $excl_btw_total * ( $toeslag_pct / 100.0 ), 2 );

                $breakdown[] = array(
                    'type'        => 'surcharge',
                    'cost'        => $toeslag_bedrag,
                    /* translators: %s: surcharge percentage with sign */
                    'description' => sprintf( __( 'Toeslag (%s%%)', 'bossier-calculator' ), number_format( $toeslag_pct, 2 ) ),
                );
            }

            $prijs_na_toeslag = max( 0.0, $excl_btw_total * ( 1.0 + $toeslag_pct / 100.0 ) );

            $btw_bedrag = round( $prijs_na_toeslag * ( Surcharge_Calculator::BTW_PERCENTAGE / 100.0 ), 2 );

            $breakdown[] = array(
                'type'        => 'btw',
                'cost'        => $btw_bedrag,
                /* translators: %s: BTW percentage */
                'description' => sprintf( __( 'BTW (%s%%)', 'bossier-calculator' ), number_format( Surcharge_Calculator::BTW_PERCENTAGE, 0 ) ),
            );

            // Round excl. BTW part to whole euros; incl. BTW part stays as-is.
            $total = round( $prijs_na_toeslag * ( 1.0 + Surcharge_Calculator::BTW_PERCENTAGE / 100.0 ), 0 ) + $incl_btw_part;
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
