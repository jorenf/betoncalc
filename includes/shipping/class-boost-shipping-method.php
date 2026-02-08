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
            // We pass an empty taxes array to prevent WooCommerce from calculating
            // additional taxes - the admin-entered price is the price shown to customer.
            $this->add_rate( array(
                'id'       => $this->get_rate_id(),
                'label'    => $label,
                'cost'     => $delivery['cost'], // Full price as configured in admin
                'taxes'    => array(), // No additional tax calculation - price is final
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

}
