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
     * VAT format examples per country for user-facing error messages.
     *
     * @var array
     */
    private static $format_examples = array(
        'AT' => 'ATU12345678',
        'BE' => 'BE0123456789',
        'BG' => 'BG123456789',
        'HR' => 'HR12345678901',
        'CY' => 'CY12345678X',
        'CZ' => 'CZ12345678',
        'DK' => 'DK12345678',
        'EE' => 'EE123456789',
        'FI' => 'FI12345678',
        'FR' => 'FRXX123456789',
        'DE' => 'DE123456789',
        'GR' => 'EL123456789',
        'HU' => 'HU12345678',
        'IE' => 'IE1234567X',
        'IT' => 'IT12345678901',
        'LV' => 'LV12345678901',
        'LT' => 'LT123456789',
        'LU' => 'LU12345678',
        'MT' => 'MT12345678',
        'NL' => 'NL123456789B01',
        'PL' => 'PL1234567890',
        'PT' => 'PT123456789',
        'RO' => 'RO12345678',
        'SK' => 'SK1234567890',
        'SI' => 'SI12345678',
        'ES' => 'ESX1234567X',
        'SE' => 'SE123456789012',
    );

    /**
     * Validate a VAT number.
     *
     * @param string $vat_number Full VAT number including country code.
     * @return array Validation result with keys: valid, company_name, address, error, reason.
     */
    public function validate( $vat_number ) {
        // Clean the VAT number
        $vat_number = $this->clean_vat_number( $vat_number );

        if ( empty( $vat_number ) ) {
            $this->log_validation_failure( $vat_number, 'empty', 'BTW-nummer is leeg' );
            return array(
                'valid'  => false,
                'reason' => 'empty',
                'error'  => __( 'BTW-nummer is leeg.', 'bossier-calculator' ),
            );
        }

        // Extract country code
        $country_code = substr( $vat_number, 0, 2 );
        $vat_code     = substr( $vat_number, 2 );

        // Check if a country prefix is recognisable at all (first 2 chars must be letters)
        if ( ! ctype_alpha( $country_code ) ) {
            $this->log_validation_failure( $vat_number, 'no_country_prefix', 'Geen landprefix herkend' );
            return array(
                'valid'  => false,
                'reason' => 'no_country_prefix',
                'error'  => __( 'BTW-nummer moet beginnen met een landprefix (bijv. BE, NL, DE, FR).', 'bossier-calculator' ),
            );
        }

        // Check if EU country
        if ( ! in_array( $country_code, self::$eu_countries, true ) ) {
            // Special case for Greece (EL vs GR)
            if ( 'EL' === $country_code ) {
                $country_code = 'GR';
            } else {
                $this->log_validation_failure( $vat_number, 'non_eu_country', 'Landcode niet in EU: ' . $country_code );
                return array(
                    'valid'  => false,
                    'reason' => 'non_eu_country',
                    'error'  => sprintf(
                        /* translators: %s: two-letter country code */
                        __( 'Landcode "%s" is geen geldig EU BTW-land. Gebruik bijv. BE, NL, DE, FR.', 'bossier-calculator' ),
                        $country_code
                    ),
                );
            }
        }

        // Basic format validation
        if ( ! $this->validate_format( $vat_number ) ) {
            $example = self::$format_examples[ $country_code ] ?? ( $country_code . 'XXXXXXXXX' );
            $this->log_validation_failure( $vat_number, 'invalid_format', 'Formaat onjuist voor ' . $country_code . ', voorbeeld: ' . $example );
            return array(
                'valid'  => false,
                'reason' => 'invalid_format',
                'error'  => sprintf(
                    /* translators: 1: country code, 2: example VAT number */
                    __( 'Ongeldig BTW-formaat voor %1$s (voorbeeld: %2$s)', 'bossier-calculator' ),
                    $country_code,
                    $example
                ),
            );
        }

        // Try VIES validation
        return $this->validate_via_vies( $country_code, $vat_code );
    }

    /**
     * Log a validation failure to the WordPress debug log.
     *
     * @param string $vat_number VAT number being validated.
     * @param string $reason     Short reason code.
     * @param string $detail     Human-readable detail.
     */
    private function log_validation_failure( $vat_number, $reason, $detail ) {
        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->info(
                sprintf( 'VAT validation failed for "%s" — reason: %s — %s', $vat_number, $reason, $detail ),
                array( 'source' => 'boost-vat' )
            );
        }
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
                if ( function_exists( 'wc_get_logger' ) ) {
                    wc_get_logger()->info(
                        sprintf( 'VIES validated %s%s → VALID (name: %s)', $country_code, $vat_code, $response->name ?? '' ),
                        array( 'source' => 'boost-vat' )
                    );
                }
                return array(
                    'valid'        => true,
                    'company_name' => $response->name ?? '',
                    'address'      => $response->address ?? '',
                );
            } else {
                if ( function_exists( 'wc_get_logger' ) ) {
                    wc_get_logger()->info(
                        sprintf( 'VIES validated %s%s → INVALID (VIES returned false)', $country_code, $vat_code ),
                        array( 'source' => 'boost-vat' )
                    );
                }
                return array(
                    'valid'  => false,
                    'reason' => 'vies_invalid',
                    'error'  => __( 'BTW-nummer kon niet worden bevestigd via het EU VIES-systeem.', 'bossier-calculator' ),
                );
            }
        } catch ( \SoapFault $e ) {
            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->warning(
                    sprintf( 'VIES SOAP error for %s%s: %s', $country_code, $vat_code, $e->getMessage() ),
                    array( 'source' => 'boost-vat' )
                );
            }

            if ( strpos( $e->getMessage(), 'INVALID_INPUT' ) !== false ) {
                $example = self::$format_examples[ $country_code ] ?? ( $country_code . 'XXXXXXXXX' );
                return array(
                    'valid'  => false,
                    'reason' => 'invalid_format',
                    'error'  => sprintf(
                        /* translators: 1: country code, 2: example VAT number */
                        __( 'Ongeldig BTW-formaat voor %1$s (voorbeeld: %2$s)', 'bossier-calculator' ),
                        $country_code,
                        $example
                    ),
                );
            }

            if ( strpos( $e->getMessage(), 'SERVICE_UNAVAILABLE' ) !== false ||
                 strpos( $e->getMessage(), 'MS_UNAVAILABLE' ) !== false ||
                 strpos( $e->getMessage(), 'TIMEOUT' ) !== false ) {
                return array(
                    'valid'               => null,
                    'service_unavailable' => true,
                    'error'               => __( 'BTW-validatieservice (VIES) tijdelijk niet beschikbaar. Probeer het later opnieuw.', 'bossier-calculator' ),
                );
            }

            return array(
                'valid'               => null,
                'service_unavailable' => true,
                'error'               => __( 'Kon BTW-nummer niet valideren via VIES. Probeer het later opnieuw.', 'bossier-calculator' ),
            );
        } catch ( \Exception $e ) {
            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->warning(
                    sprintf( 'VIES exception for %s%s: %s', $country_code, $vat_code, $e->getMessage() ),
                    array( 'source' => 'boost-vat' )
                );
            }

            return array(
                'valid'               => null,
                'service_unavailable' => true,
                'error'               => __( 'Validatiefout opgetreden. Probeer het later opnieuw.', 'bossier-calculator' ),
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
            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->warning(
                    sprintf( 'VIES REST error for %s%s: %s', $country_code, $vat_code, $response->get_error_message() ),
                    array( 'source' => 'boost-vat' )
                );
            }

            return array(
                'valid'               => null,
                'service_unavailable' => true,
                'error'               => __( 'BTW-validatieservice (VIES) tijdelijk niet beschikbaar. Probeer het later opnieuw.', 'bossier-calculator' ),
            );
        }

        $http_code = wp_remote_retrieve_response_code( $response );
        $body      = wp_remote_retrieve_body( $response );
        $data      = json_decode( $body, true );

        if ( $http_code >= 500 || ! $data ) {
            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->warning(
                    sprintf( 'VIES REST returned HTTP %d for %s%s', $http_code, $country_code, $vat_code ),
                    array( 'source' => 'boost-vat' )
                );
            }
            return array(
                'valid'               => null,
                'service_unavailable' => true,
                'error'               => __( 'BTW-validatieservice (VIES) tijdelijk niet beschikbaar. Probeer het later opnieuw.', 'bossier-calculator' ),
            );
        }

        if ( ! empty( $data['isValid'] ) ) {
            if ( function_exists( 'wc_get_logger' ) ) {
                wc_get_logger()->info(
                    sprintf( 'VIES REST validated %s%s → VALID (name: %s)', $country_code, $vat_code, $data['name'] ?? '' ),
                    array( 'source' => 'boost-vat' )
                );
            }
            return array(
                'valid'        => true,
                'company_name' => $data['name'] ?? '',
                'address'      => $data['address'] ?? '',
            );
        }

        if ( function_exists( 'wc_get_logger' ) ) {
            wc_get_logger()->info(
                sprintf( 'VIES REST validated %s%s → INVALID', $country_code, $vat_code ),
                array( 'source' => 'boost-vat' )
            );
        }
        return array(
            'valid'  => false,
            'reason' => 'vies_invalid',
            'error'  => __( 'BTW-nummer kon niet worden bevestigd via het EU VIES-systeem.', 'bossier-calculator' ),
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
