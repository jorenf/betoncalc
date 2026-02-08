<?php
/**
 * Modules Settings Page.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator;

defined( 'ABSPATH' ) || exit;

/**
 * Modules_Settings class - Handles the main settings page with tabs.
 */
class Modules_Settings {

    /**
     * Single instance of the class.
     *
     * @var Modules_Settings|null
     */
    private static $instance = null;

    /**
     * Settings option name.
     *
     * @var string
     */
    const OPTION_NAME = 'boost_modules_settings';

    /**
     * Get single instance of the class.
     *
     * @return Modules_Settings
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_submenu_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_boost_load_default_zones', array( $this, 'ajax_load_default_zones' ) );
    }

    /**
     * Add submenu page under Boost Calculators.
     */
    public function add_submenu_page() {
        add_submenu_page(
            'edit.php?post_type=' . Plugin::POST_TYPE,
            __( 'Modules Instellingen', 'bossier-calculator' ),
            __( 'Modules', 'bossier-calculator' ),
            'manage_woocommerce',
            'boost-modules',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Enqueue admin assets for the settings page.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_assets( $hook ) {
        if ( 'bossier_calculator_page_boost-modules' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'boost-modules-admin',
            BOSSIER_CALC_PLUGIN_URL . 'assets/css/modules-admin.css',
            array(),
            BOSSIER_CALC_VERSION
        );

        wp_enqueue_script(
            'boost-modules-admin',
            BOSSIER_CALC_PLUGIN_URL . 'assets/js/modules-admin.js',
            array( 'jquery', 'wp-util' ),
            BOSSIER_CALC_VERSION,
            true
        );

        wp_localize_script(
            'boost-modules-admin',
            'boostModulesAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'boost_modules_nonce' ),
                'i18n'    => array(
                    'confirmDelete'       => __( 'Weet je zeker dat je dit wilt verwijderen?', 'bossier-calculator' ),
                    'confirmLoadDefaults' => __( 'Dit vervangt alle huidige zones door de standaard zones voor NL, BE en DE. Weet je het zeker?', 'bossier-calculator' ),
                    'validating'          => __( 'Valideren...', 'bossier-calculator' ),
                    'valid'               => __( 'Geldig', 'bossier-calculator' ),
                    'invalid'             => __( 'Ongeldig', 'bossier-calculator' ),
                    'addZone'             => __( 'Zone Toevoegen', 'bossier-calculator' ),
                    'addPallet'           => __( 'Pallet Toevoegen', 'bossier-calculator' ),
                    'loadingZones'        => __( 'Zones laden...', 'bossier-calculator' ),
                    'zonesLoaded'         => __( 'Standaard zones geladen! Pagina wordt herladen...', 'bossier-calculator' ),
                    'zonesError'          => __( 'Er ging iets mis bij het laden van de zones.', 'bossier-calculator' ),
                ),
            )
        );
    }

    /**
     * Register settings.
     */
    public function register_settings() {
        register_setting(
            'boost_modules_settings',
            self::OPTION_NAME,
            array( $this, 'sanitize_settings' )
        );
    }

    /**
     * Get all settings with defaults.
     *
     * @return array
     */
    public static function get_settings() {
        $defaults = array(
            // General
            'btw_module_enabled'      => false,
            'shipping_module_enabled' => false,
            'woopages_enabled'        => false,
            'cart_icon_enabled'       => false,
            'cart_icon_menu_location' => 'none',

            // BTW Verlegd settings
            'btw_disable_wc_tax'          => false,
            'btw_invoice_text'            => 'BTW verlegd naar afnemer conform artikel 138 BTW-richtlijn',
            'btw_custom_invoice_text'     => '',
            'btw_checkbox_label'          => 'Dit is een zakelijke bestelling',
            'btw_company_label'           => 'Bedrijfsnaam',
            'btw_vat_label'               => 'BTW-nummer (optioneel)',
            'btw_vat_placeholder'         => 'bijv. NL123456789B01',
            'btw_valid_message'           => 'BTW-nummer gevalideerd',
            'btw_invalid_message'         => 'BTW-nummer kon niet worden gevalideerd',
            'btw_admin_email'             => false,
            'btw_admin_email_address'     => '',
            'btw_minimum_amount'          => 0,

            // Shipping settings
            'shipping_disable_wc_shipping' => false,
            'shipping_pickup_enabled'      => true,
            'shipping_pickup_address'      => '',
            'shipping_default_cost'        => 0, // Fallback shipping cost when no zone matches
            'shipping_oversized_threshold' => 1500,
            'shipping_oversized_type'      => 'fixed', // fixed, percentage, per_mm
            'shipping_oversized_amount'    => 25,

            // Shipping zones (stored as JSON)
            // Standard zones for NL, BE, DE - customers can add/modify
            'shipping_zones' => array(
                // Netherlands zones
                array(
                    'id'            => 1,
                    'name'          => 'NL Zone 1 - Noord-Holland/Zuid-Holland',
                    'countries'     => array( 'NL' ),
                    'postcodes'     => '1000-2999',
                    'delivery_days' => '1-2',
                ),
                array(
                    'id'            => 2,
                    'name'          => 'NL Zone 2 - Utrecht/Gelderland/Noord-Brabant',
                    'countries'     => array( 'NL' ),
                    'postcodes'     => '3000-5999',
                    'delivery_days' => '2-3',
                ),
                array(
                    'id'            => 3,
                    'name'          => 'NL Zone 3 - Overig Nederland',
                    'countries'     => array( 'NL' ),
                    'postcodes'     => '6000-9999',
                    'delivery_days' => '2-4',
                ),
                // Belgium zones
                array(
                    'id'            => 4,
                    'name'          => 'BE Zone 1 - Antwerpen/Limburg/Vlaams-Brabant',
                    'countries'     => array( 'BE' ),
                    'postcodes'     => '2000-3999',
                    'delivery_days' => '2-4',
                ),
                array(
                    'id'            => 5,
                    'name'          => 'BE Zone 2 - Oost/West-Vlaanderen',
                    'countries'     => array( 'BE' ),
                    'postcodes'     => '8000-9999',
                    'delivery_days' => '3-5',
                ),
                array(
                    'id'            => 6,
                    'name'          => 'BE Zone 3 - Brussel/Waals-Brabant/Henegouwen',
                    'countries'     => array( 'BE' ),
                    'postcodes'     => '1000-1999,6000-7999',
                    'delivery_days' => '3-5',
                ),
                array(
                    'id'            => 7,
                    'name'          => 'BE Zone 4 - Namen/Luik/Luxemburg',
                    'countries'     => array( 'BE' ),
                    'postcodes'     => '4000-5999',
                    'delivery_days' => '4-6',
                ),
                // Germany zones
                array(
                    'id'            => 8,
                    'name'          => 'DE Zone 1 - Nordrhein-Westfalen',
                    'countries'     => array( 'DE' ),
                    'postcodes'     => '40000-48999,50000-53999,57000-59999',
                    'delivery_days' => '2-4',
                ),
                array(
                    'id'            => 9,
                    'name'          => 'DE Zone 2 - Niedersachsen/Bremen',
                    'countries'     => array( 'DE' ),
                    'postcodes'     => '26000-31999,37000-38999,49000-49999',
                    'delivery_days' => '3-5',
                ),
                array(
                    'id'            => 10,
                    'name'          => 'DE Zone 3 - Overig Duitsland',
                    'countries'     => array( 'DE' ),
                    'postcodes'     => '01000-25999,32000-36999,39000-39999,54000-56999,60000-99999',
                    'delivery_days' => '4-7',
                ),
            ),

            // Pallet types
            'shipping_pallets' => array(
                array(
                    'id'     => 'euro',
                    'name'   => 'Europallet (120x80)',
                    'length' => 1200,
                    'width'  => 800,
                ),
                array(
                    'id'     => 'blok',
                    'name'   => 'Blokpallet (120x100)',
                    'length' => 1200,
                    'width'  => 1000,
                ),
            ),

            // Zone pricing matrix (zone_id => pallet_id => price)
            // Prices are examples - adjust to your actual rates
            'shipping_zone_prices' => array(
                // NL Zone 1 - Noord-Holland/Zuid-Holland
                1 => array(
                    'euro'         => 75,
                    'blok'         => 95,
                    'loose'        => 25,
                    'loose_per_kg' => 0.50,
                ),
                // NL Zone 2 - Utrecht/Gelderland/Noord-Brabant
                2 => array(
                    'euro'         => 95,
                    'blok'         => 115,
                    'loose'        => 30,
                    'loose_per_kg' => 0.60,
                ),
                // NL Zone 3 - Overig Nederland
                3 => array(
                    'euro'         => 125,
                    'blok'         => 150,
                    'loose'        => 40,
                    'loose_per_kg' => 0.75,
                ),
                // BE Zone 1 - Antwerpen/Limburg/Vlaams-Brabant
                4 => array(
                    'euro'         => 150,
                    'blok'         => 175,
                    'loose'        => 50,
                    'loose_per_kg' => 0.85,
                ),
                // BE Zone 2 - Oost/West-Vlaanderen
                5 => array(
                    'euro'         => 175,
                    'blok'         => 200,
                    'loose'        => 60,
                    'loose_per_kg' => 0.95,
                ),
                // BE Zone 3 - Brussel/Waals-Brabant/Henegouwen
                6 => array(
                    'euro'         => 175,
                    'blok'         => 200,
                    'loose'        => 60,
                    'loose_per_kg' => 0.95,
                ),
                // BE Zone 4 - Namen/Luik/Luxemburg
                7 => array(
                    'euro'         => 200,
                    'blok'         => 225,
                    'loose'        => 70,
                    'loose_per_kg' => 1.10,
                ),
                // DE Zone 1 - Nordrhein-Westfalen
                8 => array(
                    'euro'         => 175,
                    'blok'         => 200,
                    'loose'        => 55,
                    'loose_per_kg' => 0.90,
                ),
                // DE Zone 2 - Niedersachsen/Bremen
                9 => array(
                    'euro'         => 200,
                    'blok'         => 225,
                    'loose'        => 65,
                    'loose_per_kg' => 1.00,
                ),
                // DE Zone 3 - Overig Duitsland
                10 => array(
                    'euro'         => 250,
                    'blok'         => 285,
                    'loose'        => 85,
                    'loose_per_kg' => 1.25,
                ),
            ),

            // Unknown postcode message
            'shipping_unknown_postcode_message' => 'Neem contact met ons op voor een offerte voor uw locatie.',
        );

        $settings = get_option( self::OPTION_NAME, array() );

        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Sanitize settings.
     *
     * @param array $input Raw input.
     * @return array Sanitized settings.
     */
    public function sanitize_settings( $input ) {
        // Start with existing settings to preserve values from other tabs
        $existing  = self::get_settings();
        $sanitized = array();

        // Boolean fields - only update if the field was actually in the form
        $boolean_fields = array(
            'btw_module_enabled',
            'shipping_module_enabled',
            'woopages_enabled',
            'cart_icon_enabled',
            'btw_disable_wc_tax',
            'btw_admin_email',
            'shipping_disable_wc_shipping',
            'shipping_pickup_enabled',
        );

        foreach ( $boolean_fields as $field ) {
            // Check if this field's form section was submitted
            // by looking for a hidden field or checking if related fields exist
            if ( array_key_exists( $field, $input ) || $this->is_field_in_current_tab( $field, $input ) ) {
                $sanitized[ $field ] = ! empty( $input[ $field ] );
            } else {
                // Preserve existing value
                $sanitized[ $field ] = ! empty( $existing[ $field ] );
            }
        }

        // Text fields - BTW (preserve existing if not in form)
        $sanitized['btw_invoice_text']        = isset( $input['btw_invoice_text'] ) ? sanitize_textarea_field( $input['btw_invoice_text'] ) : ( $existing['btw_invoice_text'] ?? '' );
        $sanitized['btw_custom_invoice_text'] = isset( $input['btw_custom_invoice_text'] ) ? sanitize_textarea_field( $input['btw_custom_invoice_text'] ) : ( $existing['btw_custom_invoice_text'] ?? '' );
        $sanitized['btw_checkbox_label']      = isset( $input['btw_checkbox_label'] ) ? sanitize_text_field( $input['btw_checkbox_label'] ) : ( $existing['btw_checkbox_label'] ?? '' );
        $sanitized['btw_company_label']       = isset( $input['btw_company_label'] ) ? sanitize_text_field( $input['btw_company_label'] ) : ( $existing['btw_company_label'] ?? '' );
        $sanitized['btw_vat_label']           = isset( $input['btw_vat_label'] ) ? sanitize_text_field( $input['btw_vat_label'] ) : ( $existing['btw_vat_label'] ?? '' );
        $sanitized['btw_vat_placeholder']     = isset( $input['btw_vat_placeholder'] ) ? sanitize_text_field( $input['btw_vat_placeholder'] ) : ( $existing['btw_vat_placeholder'] ?? '' );
        $sanitized['btw_valid_message']       = isset( $input['btw_valid_message'] ) ? sanitize_text_field( $input['btw_valid_message'] ) : ( $existing['btw_valid_message'] ?? '' );
        $sanitized['btw_invalid_message']     = isset( $input['btw_invalid_message'] ) ? sanitize_text_field( $input['btw_invalid_message'] ) : ( $existing['btw_invalid_message'] ?? '' );
        $sanitized['btw_admin_email_address'] = isset( $input['btw_admin_email_address'] ) ? sanitize_email( $input['btw_admin_email_address'] ) : ( $existing['btw_admin_email_address'] ?? '' );

        // Text fields - Shipping (preserve existing if not in form)
        $sanitized['shipping_pickup_address'] = isset( $input['shipping_pickup_address'] ) ? sanitize_textarea_field( $input['shipping_pickup_address'] ) : ( $existing['shipping_pickup_address'] ?? '' );
        $sanitized['shipping_unknown_postcode_message'] = isset( $input['shipping_unknown_postcode_message'] ) ? sanitize_textarea_field( $input['shipping_unknown_postcode_message'] ) : ( $existing['shipping_unknown_postcode_message'] ?? '' );

        // Cart icon menu location
        $sanitized['cart_icon_menu_location'] = isset( $input['cart_icon_menu_location'] ) ? sanitize_text_field( $input['cart_icon_menu_location'] ) : ( $existing['cart_icon_menu_location'] ?? 'none' );

        // Numeric fields (preserve existing if not in form)
        $sanitized['btw_minimum_amount']           = isset( $input['btw_minimum_amount'] ) ? floatval( $input['btw_minimum_amount'] ) : ( $existing['btw_minimum_amount'] ?? 0 );
        $sanitized['shipping_default_cost']        = isset( $input['shipping_default_cost'] ) ? floatval( $input['shipping_default_cost'] ) : ( $existing['shipping_default_cost'] ?? 0 );
        $sanitized['shipping_oversized_threshold'] = isset( $input['shipping_oversized_threshold'] ) ? absint( $input['shipping_oversized_threshold'] ) : ( $existing['shipping_oversized_threshold'] ?? 1500 );
        $sanitized['shipping_oversized_amount']    = isset( $input['shipping_oversized_amount'] ) ? floatval( $input['shipping_oversized_amount'] ) : ( $existing['shipping_oversized_amount'] ?? 25 );

        // Oversized type (preserve existing if not in form)
        $allowed_types = array( 'fixed', 'percentage', 'per_mm' );
        if ( isset( $input['shipping_oversized_type'] ) && in_array( $input['shipping_oversized_type'], $allowed_types, true ) ) {
            $sanitized['shipping_oversized_type'] = $input['shipping_oversized_type'];
        } else {
            $sanitized['shipping_oversized_type'] = $existing['shipping_oversized_type'] ?? 'fixed';
        }

        // Zones (array) - preserve existing if not in form
        if ( isset( $input['shipping_zones'] ) && is_array( $input['shipping_zones'] ) ) {
            $sanitized['shipping_zones'] = $this->sanitize_zones( $input['shipping_zones'] );
        } else {
            $sanitized['shipping_zones'] = $existing['shipping_zones'] ?? array();
        }

        // Pallets (array) - preserve existing if not in form
        if ( isset( $input['shipping_pallets'] ) && is_array( $input['shipping_pallets'] ) ) {
            $sanitized['shipping_pallets'] = $this->sanitize_pallets( $input['shipping_pallets'] );
        } else {
            $sanitized['shipping_pallets'] = $existing['shipping_pallets'] ?? array();
        }

        // Zone prices (array) - preserve existing if not in form
        if ( isset( $input['shipping_zone_prices'] ) && is_array( $input['shipping_zone_prices'] ) ) {
            $sanitized['shipping_zone_prices'] = $this->sanitize_zone_prices( $input['shipping_zone_prices'] );
        } else {
            $sanitized['shipping_zone_prices'] = $existing['shipping_zone_prices'] ?? array();
        }

        return $sanitized;
    }

    /**
     * Sanitize shipping zones.
     *
     * @param array $zones Raw zones.
     * @return array Sanitized zones.
     */
    private function sanitize_zones( $zones ) {
        $sanitized = array();

        foreach ( $zones as $zone ) {
            if ( empty( $zone['name'] ) ) {
                continue;
            }

            $sanitized[] = array(
                'id'            => isset( $zone['id'] ) ? absint( $zone['id'] ) : count( $sanitized ) + 1,
                'name'          => sanitize_text_field( $zone['name'] ),
                'countries'     => isset( $zone['countries'] ) ? array_map( 'sanitize_text_field', (array) $zone['countries'] ) : array(),
                'postcodes'     => isset( $zone['postcodes'] ) ? sanitize_text_field( $zone['postcodes'] ) : '',
                'delivery_days' => isset( $zone['delivery_days'] ) ? sanitize_text_field( $zone['delivery_days'] ) : '',
            );
        }

        return $sanitized;
    }

    /**
     * Sanitize pallet types.
     *
     * @param array $pallets Raw pallets.
     * @return array Sanitized pallets.
     */
    private function sanitize_pallets( $pallets ) {
        $sanitized = array();

        foreach ( $pallets as $pallet ) {
            if ( empty( $pallet['name'] ) ) {
                continue;
            }

            $sanitized[] = array(
                'id'     => isset( $pallet['id'] ) ? sanitize_key( $pallet['id'] ) : sanitize_key( $pallet['name'] ),
                'name'   => sanitize_text_field( $pallet['name'] ),
                'length' => isset( $pallet['length'] ) ? absint( $pallet['length'] ) : 1200,
                'width'  => isset( $pallet['width'] ) ? absint( $pallet['width'] ) : 800,
            );
        }

        return $sanitized;
    }

    /**
     * Sanitize zone prices.
     *
     * @param array $prices Raw prices.
     * @return array Sanitized prices.
     */
    private function sanitize_zone_prices( $prices ) {
        $sanitized = array();

        foreach ( $prices as $zone_id => $zone_prices ) {
            $zone_id = absint( $zone_id );
            $sanitized[ $zone_id ] = array();

            foreach ( $zone_prices as $pallet_id => $price ) {
                $pallet_id = sanitize_key( $pallet_id );
                $sanitized[ $zone_id ][ $pallet_id ] = floatval( $price );
            }
        }

        return $sanitized;
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        $settings    = self::get_settings();
        $current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

        $tabs = array(
            'general'  => __( 'Algemeen', 'bossier-calculator' ),
            'btw'      => __( 'BTW Verlegd', 'bossier-calculator' ),
            'shipping' => __( 'Verzending', 'bossier-calculator' ),
            'woopages' => __( 'WooPages', 'bossier-calculator' ),
        );

        include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/modules-settings.php';
    }

    /**
     * Check if a boolean field belongs to the current form tab.
     *
     * @param string $field Field name.
     * @param array  $input Form input data.
     * @return bool
     */
    private function is_field_in_current_tab( $field, $input ) {
        // General tab fields - these are submitted together
        $general_fields = array(
            'btw_module_enabled',
            'shipping_module_enabled',
            'woopages_enabled',
            'btw_disable_wc_tax',
            'shipping_disable_wc_shipping',
        );

        // BTW tab fields
        $btw_fields = array(
            'btw_admin_email',
        );

        // Shipping tab fields
        $shipping_fields = array(
            'shipping_pickup_enabled',
        );

        // WooPages tab fields
        $woopages_fields = array(
            'cart_icon_enabled',
        );

        // Check if any field from the same tab group is in the input
        if ( in_array( $field, $general_fields, true ) ) {
            // If any general field is set, we're on general tab
            foreach ( $general_fields as $gf ) {
                if ( array_key_exists( $gf, $input ) ) {
                    return true;
                }
            }
            // Also check for related text fields that indicate general tab
            return false;
        }

        if ( in_array( $field, $btw_fields, true ) ) {
            // Check for BTW-specific fields
            return isset( $input['btw_invoice_text'] ) || isset( $input['btw_checkbox_label'] );
        }

        if ( in_array( $field, $shipping_fields, true ) ) {
            // Check for shipping-specific fields
            return isset( $input['shipping_pickup_address'] ) || isset( $input['shipping_zones'] );
        }

        if ( in_array( $field, $woopages_fields, true ) ) {
            // Check for WooPages-specific fields
            return isset( $input['cart_icon_menu_location'] );
        }

        return false;
    }

    /**
     * Check if BTW module is enabled.
     *
     * @return bool
     */
    public static function is_btw_enabled() {
        $settings = self::get_settings();
        return ! empty( $settings['btw_module_enabled'] );
    }

    /**
     * Check if Shipping module is enabled.
     *
     * @return bool
     */
    public static function is_shipping_enabled() {
        $settings = self::get_settings();
        return ! empty( $settings['shipping_module_enabled'] );
    }

    /**
     * Check if WooPages module is enabled.
     *
     * @return bool
     */
    public static function is_woopages_enabled() {
        $settings = self::get_settings();
        return ! empty( $settings['woopages_enabled'] );
    }

    /**
     * Check if Cart Icon feature is enabled.
     *
     * @return bool
     */
    public static function is_cart_icon_enabled() {
        $settings = self::get_settings();
        return ! empty( $settings['cart_icon_enabled'] );
    }

    /**
     * AJAX handler to load default shipping zones.
     */
    public function ajax_load_default_zones() {
        check_ajax_referer( 'boost_modules_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        // Get the default zones and prices
        $defaults = $this->get_default_shipping_data();

        // Get current settings and merge with defaults
        $settings = self::get_settings();
        $settings['shipping_zones']       = $defaults['zones'];
        $settings['shipping_pallets']     = $defaults['pallets'];
        $settings['shipping_zone_prices'] = $defaults['prices'];

        // Save to database
        update_option( self::OPTION_NAME, $settings );

        wp_send_json_success( array(
            'message' => __( 'Standaard zones geladen. Pagina wordt herladen...', 'bossier-calculator' ),
        ) );
    }

    /**
     * Get default shipping zones, pallets, and prices.
     *
     * @return array Default shipping data.
     */
    private function get_default_shipping_data() {
        return array(
            'zones'   => array(
                // Netherlands zones
                array(
                    'id'            => 1,
                    'name'          => 'NL Zone 1 - Noord-Holland/Zuid-Holland',
                    'countries'     => array( 'NL' ),
                    'postcodes'     => '1000-2999',
                    'delivery_days' => '1-2',
                ),
                array(
                    'id'            => 2,
                    'name'          => 'NL Zone 2 - Utrecht/Gelderland/Noord-Brabant',
                    'countries'     => array( 'NL' ),
                    'postcodes'     => '3000-5999',
                    'delivery_days' => '2-3',
                ),
                array(
                    'id'            => 3,
                    'name'          => 'NL Zone 3 - Overig Nederland',
                    'countries'     => array( 'NL' ),
                    'postcodes'     => '6000-9999',
                    'delivery_days' => '2-4',
                ),
                // Belgium zones
                array(
                    'id'            => 4,
                    'name'          => 'BE Zone 1 - Antwerpen/Limburg/Vlaams-Brabant',
                    'countries'     => array( 'BE' ),
                    'postcodes'     => '2000-3999',
                    'delivery_days' => '2-4',
                ),
                array(
                    'id'            => 5,
                    'name'          => 'BE Zone 2 - Oost/West-Vlaanderen',
                    'countries'     => array( 'BE' ),
                    'postcodes'     => '8000-9999',
                    'delivery_days' => '3-5',
                ),
                array(
                    'id'            => 6,
                    'name'          => 'BE Zone 3 - Brussel/Waals-Brabant/Henegouwen',
                    'countries'     => array( 'BE' ),
                    'postcodes'     => '1000-1999,6000-7999',
                    'delivery_days' => '3-5',
                ),
                array(
                    'id'            => 7,
                    'name'          => 'BE Zone 4 - Namen/Luik/Luxemburg',
                    'countries'     => array( 'BE' ),
                    'postcodes'     => '4000-5999',
                    'delivery_days' => '4-6',
                ),
                // Germany zones
                array(
                    'id'            => 8,
                    'name'          => 'DE Zone 1 - Nordrhein-Westfalen',
                    'countries'     => array( 'DE' ),
                    'postcodes'     => '40000-48999,50000-53999,57000-59999',
                    'delivery_days' => '2-4',
                ),
                array(
                    'id'            => 9,
                    'name'          => 'DE Zone 2 - Niedersachsen/Bremen',
                    'countries'     => array( 'DE' ),
                    'postcodes'     => '26000-31999,37000-38999,49000-49999',
                    'delivery_days' => '3-5',
                ),
                array(
                    'id'            => 10,
                    'name'          => 'DE Zone 3 - Overig Duitsland',
                    'countries'     => array( 'DE' ),
                    'postcodes'     => '01000-25999,32000-36999,39000-39999,54000-56999,60000-99999',
                    'delivery_days' => '4-7',
                ),
            ),
            'pallets' => array(
                array(
                    'id'     => 'euro',
                    'name'   => 'Europallet (120x80)',
                    'length' => 1200,
                    'width'  => 800,
                ),
                array(
                    'id'     => 'blok',
                    'name'   => 'Blokpallet (120x100)',
                    'length' => 1200,
                    'width'  => 1000,
                ),
            ),
            'prices'  => array(
                1  => array( 'euro' => 75, 'blok' => 95, 'loose' => 25, 'loose_per_kg' => 0.50 ),
                2  => array( 'euro' => 95, 'blok' => 115, 'loose' => 30, 'loose_per_kg' => 0.60 ),
                3  => array( 'euro' => 125, 'blok' => 150, 'loose' => 40, 'loose_per_kg' => 0.75 ),
                4  => array( 'euro' => 150, 'blok' => 175, 'loose' => 50, 'loose_per_kg' => 0.85 ),
                5  => array( 'euro' => 175, 'blok' => 200, 'loose' => 60, 'loose_per_kg' => 0.95 ),
                6  => array( 'euro' => 175, 'blok' => 200, 'loose' => 60, 'loose_per_kg' => 0.95 ),
                7  => array( 'euro' => 200, 'blok' => 225, 'loose' => 70, 'loose_per_kg' => 1.10 ),
                8  => array( 'euro' => 175, 'blok' => 200, 'loose' => 55, 'loose_per_kg' => 0.90 ),
                9  => array( 'euro' => 200, 'blok' => 225, 'loose' => 65, 'loose_per_kg' => 1.00 ),
                10 => array( 'euro' => 250, 'blok' => 285, 'loose' => 85, 'loose_per_kg' => 1.25 ),
            ),
        );
    }
}
