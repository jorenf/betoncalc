<?php
/**
 * VIES VAT Number Validator.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\BTW;

defined( 'ABSPATH' ) || exit;

/**
 * VIES_Validator class - Validates EU VAT numbers via VIES API.
 */
class VIES_Validator {

    /**
     * VIES SOAP WSDL URL.
     *
     * @var string
     */
    const VIES_WSDL = 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

    /**
     * EU country codes.
     *
     * @var array
     */
    private static $eu_countries = array(
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    );

    /**
     * VAT number patterns per country.
     *
     * @var array
     */
    private static $vat_patterns = array(
        'AT' => '/^ATU[0-9]{8}$/',
        'BE' => '/^BE[0-9]{10}$/',
        'BG' => '/^BG[0-9]{9,10}$/',
        'HR' => '/^HR[0-9]{11}$/',
        'CY' => '/^CY[0-9]{8}[A-Z]$/',
        'CZ' => '/^CZ[0-9]{8,10}$/',
        'DK' => '/^DK[0-9]{8}$/',
        'EE' => '/^EE[0-9]{9}$/',
        'FI' => '/^FI[0-9]{8}$/',
        'FR' => '/^FR[A-Z0-9]{2}[0-9]{9}$/',
        'DE' => '/^DE[0-9]{9}$/',
        'GR' => '/^EL[0-9]{9}$/',
        'HU' => '/^HU[0-9]{8}$/',
        'IE' => '/^IE[0-9]{7}[A-Z]{1,2}$|^IE[0-9][A-Z][0-9]{5}[A-Z]$/',
        'IT' => '/^IT[0-9]{11}$/',
        'LV' => '/^LV[0-9]{11}$/',
        'LT' => '/^LT([0-9]{9}|[0-9]{12})$/',
        'LU' => '/^LU[0-9]{8}$/',
        'MT' => '/^MT[0-9]{8}$/',
        'NL' => '/^NL[0-9]{9}B[0-9]{2}$/',
        'PL' => '/^PL[0-9]{10}$/',
        'PT' => '/^PT[0-9]{9}$/',
        'RO' => '/^RO[0-9]{2,10}$/',
        'SK' => '/^SK[0-9]{10}$/',
        'SI' => '/^SI[0-9]{8}$/',
        'ES' => '/^ES[A-Z0-9][0-9]{7}[A-Z0-9]$/',
        'SE' => '/^SE[0-9]{12}$/',
    );

    /**
     * Validate a VAT number.
     *
     * @param string $vat_number Full VAT number including country code.
     * @return array Validation result with keys: valid, company_name, address, error.
     */
    public function validate( $vat_number ) {
        // Clean the VAT number
        $vat_number = $this->clean_vat_number( $vat_number );

        if ( empty( $vat_number ) ) {
            return array(
                'valid' => false,
                'error' => __( 'BTW-nummer is leeg.', 'bossier-calculator' ),
            );
        }

        // Extract country code
        $country_code = substr( $vat_number, 0, 2 );
        $vat_code     = substr( $vat_number, 2 );

        // Check if EU country
        if ( ! in_array( $country_code, self::$eu_countries, true ) ) {
            // Special case for Greece (EL vs GR)
            if ( 'EL' === $country_code ) {
                $country_code = 'GR';
            } else {
                return array(
                    'valid' => false,
                    'error' => __( 'Ongeldig land voor EU BTW-nummer.', 'bossier-calculator' ),
                );
            }
        }

        // Basic format validation
        if ( ! $this->validate_format( $vat_number ) ) {
            return array(
                'valid' => false,
                'error' => __( 'Ongeldig BTW-nummer formaat.', 'bossier-calculator' ),
            );
        }

        // Try VIES validation
        return $this->validate_via_vies( $country_code, $vat_code );
    }

    /**
     * Clean VAT number (remove spaces, dots, dashes).
     *
     * @param string $vat_number Raw VAT number.
     * @return string Cleaned VAT number.
     */
    private function clean_vat_number( $vat_number ) {
        $vat_number = strtoupper( $vat_number );
        $vat_number = preg_replace( '/[^A-Z0-9]/', '', $vat_number );
        return $vat_number;
    }

    /**
     * Validate VAT number format.
     *
     * @param string $vat_number Full VAT number.
     * @return bool
     */
    private function validate_format( $vat_number ) {
        $country_code = substr( $vat_number, 0, 2 );

        // Greece uses EL for VAT but GR for country
        if ( 'GR' === $country_code ) {
            $vat_number = 'EL' . substr( $vat_number, 2 );
            $country_code = 'EL';
        }

        // Check if we have a pattern for this country
        $pattern_key = 'EL' === $country_code ? 'GR' : $country_code;
        if ( ! isset( self::$vat_patterns[ $pattern_key ] ) ) {
            // If no pattern, just check length
            return strlen( $vat_number ) >= 8 && strlen( $vat_number ) <= 14;
        }

        return (bool) preg_match( self::$vat_patterns[ $pattern_key ], $vat_number );
    }

    /**
     * Validate VAT number via VIES SOAP API.
     *
     * @param string $country_code Country code.
     * @param string $vat_code     VAT number without country code.
     * @return array Validation result.
     */
    private function validate_via_vies( $country_code, $vat_code ) {
        // Check if SOAP is available
        if ( ! class_exists( 'SoapClient' ) ) {
            // Fall back to REST API
            return $this->validate_via_rest( $country_code, $vat_code );
        }

        try {
            $client = new \SoapClient( self::VIES_WSDL, array(
                'exceptions'         => true,
                'connection_timeout' => 10,
                'cache_wsdl'         => WSDL_CACHE_MEMORY,
            ) );

            // Greece uses EL in VIES
            $vies_country = 'GR' === $country_code ? 'EL' : $country_code;

            $response = $client->checkVat( array(
                'countryCode' => $vies_country,
                'vatNumber'   => $vat_code,
            ) );

            if ( $response->valid ) {
                return array(
                    'valid'        => true,
                    'company_name' => $response->name ?? '',
                    'address'      => $response->address ?? '',
                );
            } else {
                return array(
                    'valid' => false,
                    'error' => __( 'BTW-nummer is niet geldig volgens VIES.', 'bossier-calculator' ),
                );
            }
        } catch ( \SoapFault $e ) {
            // Log the error
            error_log( 'VIES SOAP Error: ' . $e->getMessage() );

            // Check for specific error codes
            if ( strpos( $e->getMessage(), 'INVALID_INPUT' ) !== false ) {
                return array(
                    'valid' => false,
                    'error' => __( 'Ongeldig BTW-nummer formaat.', 'bossier-calculator' ),
                );
            }

            if ( strpos( $e->getMessage(), 'SERVICE_UNAVAILABLE' ) !== false ||
                 strpos( $e->getMessage(), 'MS_UNAVAILABLE' ) !== false ) {
                return array(
                    'valid' => null, // Unknown - service unavailable
                    'error' => __( 'VIES service tijdelijk niet beschikbaar. Probeer het later opnieuw.', 'bossier-calculator' ),
                );
            }

            return array(
                'valid' => null,
                'error' => __( 'Kon BTW-nummer niet valideren. Probeer het later opnieuw.', 'bossier-calculator' ),
            );
        } catch ( \Exception $e ) {
            error_log( 'VIES Error: ' . $e->getMessage() );

            return array(
                'valid' => null,
                'error' => __( 'Validatie fout opgetreden.', 'bossier-calculator' ),
            );
        }
    }

    /**
     * Validate VAT number via REST API (fallback).
     *
     * @param string $country_code Country code.
     * @param string $vat_code     VAT number without country code.
     * @return array Validation result.
     */
    private function validate_via_rest( $country_code, $vat_code ) {
        // Greece uses EL in VIES
        $vies_country = 'GR' === $country_code ? 'EL' : $country_code;

        $url = sprintf(
            'https://ec.europa.eu/taxation_customs/vies/rest-api/ms/%s/vat/%s',
            $vies_country,
            $vat_code
        );

        $response = wp_remote_get( $url, array(
            'timeout' => 15,
            'headers' => array(
                'Accept' => 'application/json',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'VIES REST Error: ' . $response->get_error_message() );

            return array(
                'valid' => null,
                'error' => __( 'Kon BTW-nummer niet valideren. Probeer het later opnieuw.', 'bossier-calculator' ),
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! $data ) {
            return array(
                'valid' => null,
                'error' => __( 'Ongeldig antwoord van VIES service.', 'bossier-calculator' ),
            );
        }

        if ( ! empty( $data['isValid'] ) ) {
            return array(
                'valid'        => true,
                'company_name' => $data['name'] ?? '',
                'address'      => $data['address'] ?? '',
            );
        }

        return array(
            'valid' => false,
            'error' => __( 'BTW-nummer is niet geldig volgens VIES.', 'bossier-calculator' ),
        );
    }

    /**
     * Check if a country is in the EU.
     *
     * @param string $country_code Country code.
     * @return bool
     */
    public static function is_eu_country( $country_code ) {
        return in_array( strtoupper( $country_code ), self::$eu_countries, true );
    }

    /**
     * Get all EU country codes.
     *
     * @return array
     */
    public static function get_eu_countries() {
        return self::$eu_countries;
    }
}
