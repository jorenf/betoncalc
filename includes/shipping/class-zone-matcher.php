<?php
/**
 * Zone Matcher.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\Shipping;

use Bossier\Calculator\Modules_Settings;

defined( 'ABSPATH' ) || exit;

// Ensure the logger is available.
if ( ! class_exists( __NAMESPACE__ . '\\Shipping_Logger', false ) ) {
    require_once __DIR__ . '/class-shipping-logger.php';
}

/**
 * Zone_Matcher class - Matches postcodes to shipping zones.
 */
class Zone_Matcher {

    /**
     * Find zone for country and postcode.
     *
     * When multiple zones match, the most specific zone wins
     * (smallest postcode range). This ensures that a zone for
     * "1790-1797" takes priority over a broader "1000-2999" zone.
     *
     * @param string $country  Country code.
     * @param string $postcode Postcode.
     * @return array|null Zone data or null if not found.
     */
    public static function find_zone( $country, $postcode ) {
        $settings = Modules_Settings::get_settings();
        $zones    = $settings['shipping_zones'];

        if ( empty( $zones ) ) {
            return null;
        }

        // Clean postcode (extract numeric part)
        $postcode_numeric = self::extract_numeric_postcode( $postcode, $country );

        Shipping_Logger::log( 'zone_lookup_start', array(
            'country'          => $country,
            'postcode_raw'     => $postcode,
            'postcode_numeric' => $postcode_numeric,
            'zones_available'  => count( $zones ),
        ) );

        // Find ALL matching zones, then pick the most specific one
        $best_zone        = null;
        $best_specificity = PHP_INT_MAX;
        $candidates       = array();

        foreach ( $zones as $zone ) {
            // Check if country matches
            if ( ! in_array( $country, $zone['countries'], true ) ) {
                continue;
            }

            // Check if postcode matches range
            if ( self::postcode_in_range( $postcode_numeric, $zone['postcodes'] ) ) {
                $specificity  = self::calculate_zone_specificity( $zone['postcodes'] );
                $candidates[] = array(
                    'id'          => $zone['id'],
                    'name'        => $zone['name'],
                    'postcodes'   => $zone['postcodes'],
                    'specificity' => $specificity,
                );

                if ( $specificity < $best_specificity ) {
                    $best_specificity = $specificity;
                    $best_zone        = $zone;
                }
            }
        }

        Shipping_Logger::log( 'zone_lookup_result', array(
            'country'          => $country,
            'postcode_numeric' => $postcode_numeric,
            'candidates_found' => count( $candidates ),
            'candidates'       => $candidates,
            'selected_zone'    => $best_zone ? array(
                'id'          => $best_zone['id'],
                'name'        => $best_zone['name'],
                'postcodes'   => $best_zone['postcodes'],
                'specificity' => $best_specificity,
            ) : null,
        ) );

        return $best_zone;
    }

    /**
     * Calculate how many postcodes a zone covers (lower = more specific).
     *
     * @param string $ranges Comma-separated ranges.
     * @return int Total postcodes covered.
     */
    private static function calculate_zone_specificity( $ranges ) {
        if ( empty( $ranges ) ) {
            return PHP_INT_MAX; // No range = wildcard = least specific
        }

        $total       = 0;
        $range_parts = array_map( 'trim', explode( ',', $ranges ) );

        foreach ( $range_parts as $range ) {
            if ( strpos( $range, '-' ) !== false ) {
                list( $min, $max ) = array_map( 'intval', explode( '-', $range ) );
                $total += max( 1, $max - $min + 1 );
            } else {
                $total += 1; // Single postcode
            }
        }

        return $total;
    }

    /**
     * Extract numeric part of postcode.
     *
     * @param string $postcode Postcode.
     * @param string $country  Country code.
     * @return int Numeric postcode.
     */
    private static function extract_numeric_postcode( $postcode, $country ) {
        // Remove all non-alphanumeric characters
        $postcode = preg_replace( '/[^A-Z0-9]/i', '', strtoupper( $postcode ) );

        switch ( $country ) {
            case 'NL':
                // Dutch: 1234AB -> 1234
                return (int) preg_replace( '/[^0-9]/', '', substr( $postcode, 0, 4 ) );

            case 'BE':
                // Belgian: 1000 -> 1000
                return (int) preg_replace( '/[^0-9]/', '', $postcode );

            case 'DE':
                // German: 12345 -> 12345
                return (int) preg_replace( '/[^0-9]/', '', $postcode );

            case 'FR':
                // French: 75001 -> 75001
                return (int) preg_replace( '/[^0-9]/', '', $postcode );

            case 'GB':
            case 'UK':
                // UK: SW1A 1AA -> Extract area code numerically
                preg_match( '/^([A-Z]{1,2})(\d{1,2})/', $postcode, $matches );
                if ( ! empty( $matches[2] ) ) {
                    return (int) $matches[2];
                }
                return 0;

            default:
                // Default: extract all numbers
                return (int) preg_replace( '/[^0-9]/', '', $postcode );
        }
    }

    /**
     * Check if postcode is in range.
     *
     * @param int    $postcode Numeric postcode.
     * @param string $ranges   Comma-separated ranges (e.g., "1000-2999, 3500-3599").
     * @return bool
     */
    private static function postcode_in_range( $postcode, $ranges ) {
        if ( empty( $ranges ) ) {
            return true; // No range specified = all postcodes match
        }

        // Split by comma
        $range_parts = array_map( 'trim', explode( ',', $ranges ) );

        foreach ( $range_parts as $range ) {
            // Check if it's a range (1000-2999) or single value (1000)
            if ( strpos( $range, '-' ) !== false ) {
                list( $min, $max ) = array_map( 'intval', explode( '-', $range ) );

                if ( $postcode >= $min && $postcode <= $max ) {
                    return true;
                }
            } else {
                // Single value
                if ( $postcode === (int) $range ) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get all zones.
     *
     * @return array
     */
    public static function get_all_zones() {
        $settings = Modules_Settings::get_settings();
        return $settings['shipping_zones'] ?? array();
    }

    /**
     * Get zone by ID.
     *
     * @param int $zone_id Zone ID.
     * @return array|null
     */
    public static function get_zone_by_id( $zone_id ) {
        $zones = self::get_all_zones();

        foreach ( $zones as $zone ) {
            if ( (int) $zone['id'] === (int) $zone_id ) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * Check if location is covered by any zone.
     *
     * @param string $country  Country code.
     * @param string $postcode Postcode.
     * @return bool
     */
    public static function is_location_covered( $country, $postcode ) {
        return null !== self::find_zone( $country, $postcode );
    }

    /**
     * Get delivery days for location.
     *
     * @param string $country  Country code.
     * @param string $postcode Postcode.
     * @return string|null Delivery days or null if not found.
     */
    public static function get_delivery_days( $country, $postcode ) {
        $zone = self::find_zone( $country, $postcode );

        if ( $zone && ! empty( $zone['delivery_days'] ) ) {
            return $zone['delivery_days'];
        }

        return null;
    }
}
