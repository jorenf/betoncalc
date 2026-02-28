<?php
/**
 * Boost Shipping Method for WooCommerce.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\Shipping;

use Bossier\Calculator\Modules_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Boost_Shipping_Method class - WooCommerce shipping method.
 */
class Boost_Shipping_Method extends \WC_Shipping_Method {

    /**
     * Constructor.
     *
     * @param int $instance_id Instance ID.
     */
    public function __construct( $instance_id = 0 ) {
        $this->id                 = 'boost_shipping';
        $this->instance_id        = absint( $instance_id );
        $this->method_title       = __( 'Boost Verzending', 'bossier-calculator' );
        $this->method_description = __( 'Aangepaste verzendberekening met zones, pallets en levertijden.', 'bossier-calculator' );
        $this->supports           = array(
            'shipping-zones',
            'instance-settings',
        );

        $this->init();
    }

    /**
     * Initialize settings.
     */
    public function init() {
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option( 'title', __( 'Verzending', 'bossier-calculator' ) );
        $this->enabled = $this->get_option( 'enabled', 'yes' );

        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
     * Define settings field for this shipping method.
     */
    public function init_form_fields() {
        $this->instance_form_fields = array(
            'title' => array(
                'title'       => __( 'Titel', 'bossier-calculator' ),
                'type'        => 'text',
                'description' => __( 'De titel die klanten zien tijdens checkout.', 'bossier-calculator' ),
                'default'     => __( 'Verzending', 'bossier-calculator' ),
            ),
        );
    }

    /**
     * Calculate shipping rates.
     *
     * Note: This method is called when Boost_Shipping_Method is added to a WooCommerce
     * shipping zone. Rates are also injected via inject_shipping_rates() filter.
     * To avoid duplicates, we only add rates here if this method is the primary source.
     *
     * @param array $package Package data.
     */
    public function calculate_shipping( $package = array() ) {
        $country  = $package['destination']['country'] ?? '';
        $postcode = $package['destination']['postcode'] ?? '';

        // Calculate delivery shipping
        $delivery = Shipping_Calculator::calculate( $country, $postcode, $package );

        if ( $delivery['available'] ) {
            $label = $this->title;

            // Add delivery days to label
            if ( ! empty( $delivery['delivery_days'] ) ) {
                $label .= ' (' . $delivery['delivery_days'] . ' ' . __( 'werkdagen', 'bossier-calculator' ) . ')';
            }

            // Shipping prices are entered as final prices (VAT inclusive).
            // We calculate the tax portion already included and pass it separately
            // so WooCommerce correctly displays the VAT in cart totals.
            $taxes = $this->calculate_inclusive_taxes( $delivery['cost'] );
            $exclusive_cost = $delivery['cost'] - array_sum( $taxes );

            $this->add_rate( array(
                'id'       => $this->get_rate_id(),
                'label'    => $label,
                'cost'     => $exclusive_cost, // Price excl. tax
                'taxes'    => $taxes, // Tax amounts calculated from inclusive price
                'meta_data' => array(
                    'zone_id'       => $delivery['zone']['id'] ?? 0,
                    'zone_name'     => $delivery['zone']['name'] ?? '',
                    'delivery_days' => $delivery['delivery_days'] ?? '',
                    'breakdown'     => $delivery['breakdown'] ?? array(),
                    'is_boost_shipping' => true,
                ),
            ) );
        }

        // Note: Pickup is added by inject_shipping_rates() to avoid duplicates.
        // Only add pickup here if this is the only active shipping method.
    }

    /**
     * Check if this method is available.
     *
     * @param array $package Package data.
     * @return bool
     */
    public function is_available( $package ) {
        return Modules_Settings::is_shipping_enabled();
    }

    /**
     * Calculate taxes from an inclusive price.
     *
     * Shipping prices are entered as VAT-inclusive.
     * This method calculates the tax portion already included.
     *
     * @param float $inclusive_price Price including tax.
     * @return array Tax amounts keyed by tax rate ID.
     */
    private function calculate_inclusive_taxes( $inclusive_price ) {
        if ( ! wc_tax_enabled() || $inclusive_price <= 0 ) {
            return array();
        }

        // Get shipping tax rates based on store location
        $tax_rates = \WC_Tax::get_shipping_tax_rates();

        if ( empty( $tax_rates ) ) {
            return array();
        }

        // Calculate taxes from the inclusive price
        $taxes = \WC_Tax::calc_inclusive_tax( $inclusive_price, $tax_rates );

        return $taxes;
    }

}
