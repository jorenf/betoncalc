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
                    'confirmDelete' => __( 'Weet je zeker dat je dit wilt verwijderen?', 'bossier-calculator' ),
                    'validating'    => __( 'Valideren...', 'bossier-calculator' ),
                    'valid'         => __( 'Geldig', 'bossier-calculator' ),
                    'invalid'       => __( 'Ongeldig', 'bossier-calculator' ),
                    'addZone'       => __( 'Zone Toevoegen', 'bossier-calculator' ),
                    'addPallet'     => __( 'Pallet Toevoegen', 'bossier-calculator' ),
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

            // BTW Verlegd settings
            'btw_disable_wc_tax'          => false,
            'btw_invoice_text'            => 'BTW verlegd naar afnemer conform artikel 138 BTW-richtlijn',
            'btw_custom_invoice_text'     => '',

            // Shipping settings
            'shipping_disable_wc_shipping' => false,
            'shipping_pickup_enabled'      => true,
            'shipping_pickup_address'      => '',
            'shipping_oversized_threshold' => 1500,
            'shipping_oversized_type'      => 'fixed', // fixed, percentage, per_mm
            'shipping_oversized_amount'    => 25,

            // Shipping zones (stored as JSON)
            'shipping_zones' => array(
                array(
                    'id'            => 1,
                    'name'          => 'Zone 1 - Lokaal',
                    'countries'     => array( 'NL' ),
                    'postcodes'     => '1000-3999',
                    'delivery_days' => '1-2',
                ),
                array(
                    'id'            => 2,
                    'name'          => 'Zone 2 - Regionaal',
                    'countries'     => array( 'NL' ),
                    'postcodes'     => '4000-9999',
                    'delivery_days' => '2-3',
                ),
            ),

            // Pallet types
            'shipping_pallets' => array(
                array(
                    'id'     => 'euro',
                    'name'   => 'Europallet',
                    'length' => 1200,
                    'width'  => 800,
                ),
                array(
                    'id'     => 'blok',
                    'name'   => 'Blokpallet',
                    'length' => 1200,
                    'width'  => 1000,
                ),
            ),

            // Zone pricing matrix (zone_id => pallet_id => price)
            'shipping_zone_prices' => array(
                1 => array(
                    'euro'  => 75,
                    'blok'  => 95,
                    'loose' => 25,
                    'loose_per_kg' => 0.50,
                ),
                2 => array(
                    'euro'  => 125,
                    'blok'  => 150,
                    'loose' => 35,
                    'loose_per_kg' => 0.75,
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
        $sanitized = array();

        // Boolean fields
        $boolean_fields = array(
            'btw_module_enabled',
            'shipping_module_enabled',
            'btw_disable_wc_tax',
            'shipping_disable_wc_shipping',
            'shipping_pickup_enabled',
        );

        foreach ( $boolean_fields as $field ) {
            $sanitized[ $field ] = ! empty( $input[ $field ] );
        }

        // Text fields
        $sanitized['btw_invoice_text']        = isset( $input['btw_invoice_text'] ) ? sanitize_textarea_field( $input['btw_invoice_text'] ) : '';
        $sanitized['btw_custom_invoice_text'] = isset( $input['btw_custom_invoice_text'] ) ? sanitize_textarea_field( $input['btw_custom_invoice_text'] ) : '';
        $sanitized['shipping_pickup_address'] = isset( $input['shipping_pickup_address'] ) ? sanitize_textarea_field( $input['shipping_pickup_address'] ) : '';
        $sanitized['shipping_unknown_postcode_message'] = isset( $input['shipping_unknown_postcode_message'] ) ? sanitize_textarea_field( $input['shipping_unknown_postcode_message'] ) : '';

        // Numeric fields
        $sanitized['shipping_oversized_threshold'] = isset( $input['shipping_oversized_threshold'] ) ? absint( $input['shipping_oversized_threshold'] ) : 1500;
        $sanitized['shipping_oversized_amount']    = isset( $input['shipping_oversized_amount'] ) ? floatval( $input['shipping_oversized_amount'] ) : 25;

        // Oversized type
        $allowed_types = array( 'fixed', 'percentage', 'per_mm' );
        $sanitized['shipping_oversized_type'] = isset( $input['shipping_oversized_type'] ) && in_array( $input['shipping_oversized_type'], $allowed_types, true )
            ? $input['shipping_oversized_type']
            : 'fixed';

        // Zones (array)
        if ( isset( $input['shipping_zones'] ) && is_array( $input['shipping_zones'] ) ) {
            $sanitized['shipping_zones'] = $this->sanitize_zones( $input['shipping_zones'] );
        }

        // Pallets (array)
        if ( isset( $input['shipping_pallets'] ) && is_array( $input['shipping_pallets'] ) ) {
            $sanitized['shipping_pallets'] = $this->sanitize_pallets( $input['shipping_pallets'] );
        }

        // Zone prices (array)
        if ( isset( $input['shipping_zone_prices'] ) && is_array( $input['shipping_zone_prices'] ) ) {
            $sanitized['shipping_zone_prices'] = $this->sanitize_zone_prices( $input['shipping_zone_prices'] );
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
        );

        include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/modules-settings.php';
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
}
