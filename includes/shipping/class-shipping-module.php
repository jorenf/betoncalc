<?php
/**
 * Shipping Module.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\Shipping;

use Bossier\Calculator\Modules_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Shipping_Module class - Main shipping functionality.
 */
class Shipping_Module {

    /**
     * Single instance of the class.
     *
     * @var Shipping_Module|null
     */
    private static $instance = null;

    /**
     * Get single instance of the class.
     *
     * @return Shipping_Module
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
        // Load required files
        require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/shipping/class-zone-matcher.php';
        require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/shipping/class-shipping-calculator.php';
        require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/shipping/class-boost-shipping-method.php';
        require_once BOSSIER_CALC_PLUGIN_DIR . 'includes/shipping/class-shipping-checkout.php';

        // Initialize checkout integration
        Shipping_Checkout::get_instance();

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Register our shipping method with WooCommerce
        add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_method' ) );

        // Inject our shipping rates directly (bypass WooCommerce zones)
        add_filter( 'woocommerce_package_rates', array( $this, 'inject_shipping_rates' ), 100, 2 );

        // Force shipping calculation even if no methods configured
        add_filter( 'woocommerce_shipping_show_shipping_calculator', '__return_true' );

        // Disable WooCommerce default shipping if configured
        $settings = Modules_Settings::get_settings();
        if ( ! empty( $settings['shipping_disable_wc_shipping'] ) ) {
            add_filter( 'woocommerce_package_rates', array( $this, 'remove_other_shipping_methods' ), 200, 2 );
        }

        // Display delivery time on product page
        add_action( 'woocommerce_single_product_summary', array( $this, 'display_delivery_time' ), 25 );

        // Display delivery time in cart
        add_filter( 'woocommerce_cart_item_name', array( $this, 'add_delivery_time_to_cart' ), 10, 3 );

        // Add product meta box for shipping settings
        add_action( 'add_meta_boxes', array( $this, 'add_product_shipping_metabox' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_shipping_meta' ) );

        // Hide internal shipping meta from order display
        add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'hide_shipping_meta' ), 10, 2 );

        // Save shipping choice to order
        add_action( 'woocommerce_checkout_create_order', array( $this, 'save_shipping_to_order' ), 20, 2 );

        // Display in admin order
        add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'display_shipping_in_admin' ) );

        // Enqueue frontend scripts
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Display shipping breakdown after shipping in cart/checkout
        add_action( 'woocommerce_cart_totals_after_shipping', array( $this, 'display_shipping_breakdown' ) );
        add_action( 'woocommerce_review_order_after_shipping', array( $this, 'display_shipping_breakdown' ) );

        // Force shipping recalculation when country/postcode changes
        add_action( 'woocommerce_checkout_update_order_review', array( $this, 'force_shipping_recalculation' ) );
    }

    /**
     * Inject our shipping rates directly into the package.
     *
     * @param array $rates   Existing shipping rates.
     * @param array $package Package data.
     * @return array
     */
    public function inject_shipping_rates( $rates, $package ) {
        $settings = Modules_Settings::get_settings();
        $country  = $package['destination']['country'] ?? '';
        $postcode = $package['destination']['postcode'] ?? '';

        // Check if boost rates already exist (from Boost_Shipping_Method) to avoid duplicates
        $has_boost_shipping = false;
        $has_boost_pickup   = false;
        foreach ( $rates as $rate_id => $rate ) {
            if ( strpos( $rate_id, 'boost_shipping' ) === 0 && strpos( $rate_id, 'pickup' ) === false ) {
                $has_boost_shipping = true;
            }
            if ( strpos( $rate_id, 'boost_pickup' ) !== false || strpos( $rate_id, 'pickup' ) !== false ) {
                $has_boost_pickup = true;
            }
        }

        // Get default/fallback shipping cost (defaults to 0 if not set)
        $default_shipping_cost = floatval( $settings['shipping_default_cost'] ?? 0 );

        // Calculate delivery shipping (only if not already added)
        if ( ! $has_boost_shipping ) {
            $delivery = Shipping_Calculator::calculate( $country, $postcode, $package );

            if ( $delivery['available'] ) {
                $label = __( 'Verzending', 'bossier-calculator' );

                // Determine the correct delivery time to display
                // Product-level delivery (made_to_order) takes priority over zone-based delivery days
                $has_product_delivery  = false;
                $longest_product_weeks = '';
                $effective_delivery    = '';

                foreach ( $package['contents'] as $cart_item ) {
                    $status = get_post_meta( $cart_item['product_id'], '_boost_delivery_status', true );
                    if ( 'made_to_order' === $status ) {
                        $has_product_delivery = true;
                        $weeks       = get_post_meta( $cart_item['product_id'], '_boost_delivery_weeks', true ) ?: '2-3';
                        $current_max = intval( preg_replace( '/^.*?(\d+)$/', '$1', $weeks ) );
                        $longest_max = intval( preg_replace( '/^.*?(\d+)$/', '$1', $longest_product_weeks ?: '0' ) );
                        if ( $current_max > $longest_max ) {
                            $longest_product_weeks = $weeks;
                        }
                    }
                }

                if ( $has_product_delivery ) {
                    // Use product-level delivery time (weeks, not days)
                    $label .= ' (' . $longest_product_weeks . ' ' . __( 'weken', 'bossier-calculator' ) . ')';
                    $effective_delivery = $longest_product_weeks . ' ' . __( 'weken', 'bossier-calculator' );
                } elseif ( ! empty( $delivery['delivery_days'] ) ) {
                    // Use zone-based delivery days
                    $label .= ' (' . $delivery['delivery_days'] . ' ' . __( 'werkdagen', 'bossier-calculator' ) . ')';
                    $effective_delivery = $delivery['delivery_days'] . ' ' . __( 'werkdagen', 'bossier-calculator' );
                }

                // Calculate taxes from VAT-inclusive price
                $taxes = $this->calculate_inclusive_taxes( $delivery['cost'] );
                $exclusive_cost = $delivery['cost'] - array_sum( $taxes );

                $rate = new \WC_Shipping_Rate(
                    'boost_shipping',
                    $label,
                    $exclusive_cost,
                    $taxes,
                    'boost_shipping'
                );

                // Add meta data
                $rate->add_meta_data( 'zone_id', $delivery['zone']['id'] ?? 0 );
                $rate->add_meta_data( 'zone_name', $delivery['zone']['name'] ?? '' );
                // Store the effective delivery time (product weeks or zone days), not just zone days
                $rate->add_meta_data( 'delivery_days', $effective_delivery );
                $rate->add_meta_data( 'is_boost_shipping', true );
                $rate->add_meta_data( 'breakdown', $delivery['breakdown'] ?? array() );

                $rates['boost_shipping'] = $rate;
            } elseif ( $default_shipping_cost > 0 ) {
                // Fallback shipping rate when zone not found
                $label = __( 'Verzending', 'bossier-calculator' );

                // Calculate taxes from VAT-inclusive price
                $fallback_taxes = $this->calculate_inclusive_taxes( $default_shipping_cost );
                $fallback_exclusive = $default_shipping_cost - array_sum( $fallback_taxes );

                $rate = new \WC_Shipping_Rate(
                    'boost_shipping',
                    $label,
                    $fallback_exclusive,
                    $fallback_taxes,
                    'boost_shipping'
                );
                $rate->add_meta_data( 'is_boost_shipping', true );
                $rate->add_meta_data( 'is_fallback', true );

                $rates['boost_shipping'] = $rate;
            }
        }

        // Add pickup option if enabled (only if not already added)
        if ( ! $has_boost_pickup && ! empty( $settings['shipping_pickup_enabled'] ) ) {
            $pickup_label   = __( 'Afhalen (Gratis)', 'bossier-calculator' );
            $pickup_address = $settings['shipping_pickup_address'] ?? '';

            if ( ! empty( $pickup_address ) ) {
                $short_address = wp_trim_words( $pickup_address, 5, '...' );
                $pickup_label .= ' - ' . $short_address;
            }

            $pickup_rate = new \WC_Shipping_Rate(
                'boost_pickup',
                $pickup_label,
                0,
                array(),
                'boost_pickup'
            );
            $pickup_rate->add_meta_data( 'is_pickup', true );
            $pickup_rate->add_meta_data( 'pickup_address', $pickup_address );
            $pickup_rate->add_meta_data( 'is_boost_shipping', true );

            $rates['boost_pickup'] = $pickup_rate;
        }

        return $rates;
    }

    /**
     * Register our shipping method with WooCommerce.
     *
     * @param array $methods Existing shipping methods.
     * @return array
     */
    public function register_shipping_method( $methods ) {
        $methods['boost_shipping'] = 'Bossier\Calculator\Shipping\Boost_Shipping_Method';
        return $methods;
    }

    /**
     * Remove other shipping methods, keeping only Boost shipping.
     *
     * @param array $rates   Shipping rates.
     * @param array $package Package data.
     * @return array
     */
    public function remove_other_shipping_methods( $rates, $package ) {
        $boost_rates = array();

        foreach ( $rates as $rate_id => $rate ) {
            if ( strpos( $rate_id, 'boost_' ) === 0 ) {
                $boost_rates[ $rate_id ] = $rate;
            }
        }

        return ! empty( $boost_rates ) ? $boost_rates : $rates;
    }

    /**
     * Save shipping data to order.
     *
     * @param WC_Order $order Order object.
     * @param array    $data  Posted data.
     */
    public function save_shipping_to_order( $order, $data ) {
        $shipping_methods = $order->get_shipping_methods();

        foreach ( $shipping_methods as $shipping ) {
            $method_id = $shipping->get_method_id();

            if ( strpos( $method_id, 'boost' ) !== false ) {
                $order->update_meta_data( '_boost_shipping_method', $method_id );

                if ( 'boost_pickup' === $method_id ) {
                    $order->update_meta_data( '_boost_is_pickup', 'yes' );
                    $settings = Modules_Settings::get_settings();
                    $order->update_meta_data( '_boost_pickup_address', $settings['shipping_pickup_address'] ?? '' );
                } else {
                    $order->update_meta_data( '_boost_is_pickup', 'no' );

                    // Save zone data from shipping item meta
                    $zone_id       = $shipping->get_meta( 'zone_id' );
                    $zone_name     = $shipping->get_meta( 'zone_name' );
                    $delivery_days = $shipping->get_meta( 'delivery_days' );

                    if ( $zone_id ) {
                        $order->update_meta_data( '_boost_shipping_zone_id', $zone_id );
                    }
                    if ( $zone_name ) {
                        $order->update_meta_data( '_boost_shipping_zone_name', $zone_name );
                    }
                    if ( $delivery_days ) {
                        $order->update_meta_data( '_boost_shipping_delivery_days', $delivery_days );
                    }
                }
            }
        }
    }

    /**
     * Display shipping info in admin order.
     *
     * @param WC_Order $order Order object.
     */
    public function display_shipping_in_admin( $order ) {
        $shipping_method = $order->get_meta( '_boost_shipping_method' );
        $is_pickup = $order->get_meta( '_boost_is_pickup' );

        if ( empty( $shipping_method ) ) {
            return;
        }

        echo '<div class="boost-shipping-admin-info" style="margin-top: 15px; padding: 12px 15px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 0 4px 4px 0;">';
        echo '<h4 style="margin: 0 0 10px 0; color: #1e40af;">' . esc_html__( 'Boost Verzending', 'bossier-calculator' ) . '</h4>';

        if ( 'yes' === $is_pickup ) {
            echo '<p style="margin: 0;"><strong>' . esc_html__( 'Methode:', 'bossier-calculator' ) . '</strong> ' . esc_html__( 'Afhalen', 'bossier-calculator' ) . '</p>';
            $pickup_address = $order->get_meta( '_boost_pickup_address' );
            if ( $pickup_address ) {
                echo '<p style="margin: 5px 0 0 0;"><strong>' . esc_html__( 'Afhaaladres:', 'bossier-calculator' ) . '</strong><br>' . nl2br( esc_html( $pickup_address ) ) . '</p>';
            }
        } else {
            echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__( 'Methode:', 'bossier-calculator' ) . '</strong> ' . esc_html__( 'Bezorging', 'bossier-calculator' ) . '</p>';

            // Display zone info
            $zone_name     = $order->get_meta( '_boost_shipping_zone_name' );
            $delivery_days = $order->get_meta( '_boost_shipping_delivery_days' );

            if ( $zone_name ) {
                echo '<p style="margin: 0 0 5px 0;"><strong>' . esc_html__( 'Zone:', 'bossier-calculator' ) . '</strong> ' . esc_html( $zone_name ) . '</p>';
            }
            if ( $delivery_days ) {
                echo '<p style="margin: 0;"><strong>' . esc_html__( 'Levertijd:', 'bossier-calculator' ) . '</strong> ' . esc_html( $delivery_days ) . ' ' . esc_html__( 'werkdagen', 'bossier-calculator' ) . '</p>';
            }
        }

        echo '</div>';
    }

    /**
     * Hide internal shipping meta from order item display.
     *
     * @param array         $formatted_meta Formatted meta data.
     * @param \WC_Order_Item $item          Order item.
     * @return array Filtered meta data.
     */
    public function hide_shipping_meta( $formatted_meta, $item ) {
        // Only filter shipping items
        if ( ! $item instanceof \WC_Order_Item_Shipping ) {
            return $formatted_meta;
        }

        // Keys to hide from display
        $hidden_keys = array(
            'zone_id',
            'zone_name',
            'delivery_days',
            'is_boost_shipping',
            'is_pickup',
            'is_fallback',
            'pickup_address',
            'breakdown',
        );

        foreach ( $formatted_meta as $key => $meta ) {
            if ( in_array( $meta->key, $hidden_keys, true ) ) {
                unset( $formatted_meta[ $key ] );
            }
        }

        return $formatted_meta;
    }

    /**
     * Display delivery time on product page.
     */
    public function display_delivery_time() {
        global $product;

        if ( ! $product ) {
            return;
        }

        $delivery_status = get_post_meta( $product->get_id(), '_boost_delivery_status', true );
        $delivery_weeks  = get_post_meta( $product->get_id(), '_boost_delivery_weeks', true );

        if ( empty( $delivery_status ) ) {
            $delivery_status = 'in_stock'; // Default
        }

        $html = '<div class="boost-delivery-time">';

        if ( 'in_stock' === $delivery_status ) {
            $html .= '<span class="boost-delivery-badge boost-in-stock">';
            $html .= '<span class="boost-delivery-icon">✓</span> ';
            $html .= esc_html__( 'Op voorraad', 'bossier-calculator' );
            $html .= '</span>';
        } else {
            $weeks_text = $delivery_weeks ?: '2-3';
            $html .= '<span class="boost-delivery-badge boost-made-to-order">';
            $html .= '<span class="boost-delivery-icon">⏱</span> ';
            $html .= sprintf( esc_html__( 'Levertijd: %s weken', 'bossier-calculator' ), esc_html( $weeks_text ) );
            $html .= '</span>';
        }

        $html .= '</div>';

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Add delivery time to cart item name.
     *
     * @param string $name      Item name.
     * @param array  $cart_item Cart item.
     * @param string $cart_key  Cart item key.
     * @return string
     */
    public function add_delivery_time_to_cart( $name, $cart_item, $cart_key ) {
        if ( ! is_cart() && ! is_checkout() ) {
            return $name;
        }

        $product_id = $cart_item['product_id'];
        $delivery_status = get_post_meta( $product_id, '_boost_delivery_status', true );
        $delivery_weeks  = get_post_meta( $product_id, '_boost_delivery_weeks', true );

        if ( empty( $delivery_status ) ) {
            return $name;
        }

        if ( 'in_stock' === $delivery_status ) {
            $badge = '<span class="boost-cart-delivery boost-in-stock">' . esc_html__( 'Op voorraad', 'bossier-calculator' ) . '</span>';
        } else {
            $weeks_text = $delivery_weeks ?: '2-3';
            $badge = '<span class="boost-cart-delivery boost-made-to-order">' . sprintf( esc_html__( 'Levertijd: %s weken', 'bossier-calculator' ), esc_html( $weeks_text ) ) . '</span>';
        }

        return $name . ' ' . $badge;
    }

    /**
     * Display delivery estimate at checkout based on zone.
     */
    public function display_delivery_estimate_checkout() {
        // Get customer postcode and country
        $postcode = WC()->customer ? WC()->customer->get_shipping_postcode() : '';
        $country  = WC()->customer ? WC()->customer->get_shipping_country() : '';

        if ( empty( $postcode ) || empty( $country ) ) {
            return;
        }

        // Find zone
        $zone = Zone_Matcher::find_zone( $country, $postcode );

        if ( ! $zone || empty( $zone['delivery_days'] ) ) {
            return;
        }

        echo '<tr class="boost-delivery-estimate">';
        echo '<th>' . esc_html__( 'Geschatte levertijd', 'bossier-calculator' ) . '</th>';
        echo '<td><strong>' . esc_html( $zone['delivery_days'] ) . ' ' . esc_html__( 'werkdagen', 'bossier-calculator' ) . '</strong></td>';
        echo '</tr>';
    }

    /**
     * Add product shipping metabox.
     */
    public function add_product_shipping_metabox() {
        add_meta_box(
            'boost_product_shipping',
            __( 'Boost Verzending', 'bossier-calculator' ),
            array( $this, 'render_product_shipping_metabox' ),
            'product',
            'side',
            'default'
        );
    }

    /**
     * Render product shipping metabox.
     *
     * @param WP_Post $post Post object.
     */
    public function render_product_shipping_metabox( $post ) {
        wp_nonce_field( 'boost_product_shipping', 'boost_shipping_nonce' );

        $delivery_status = get_post_meta( $post->ID, '_boost_delivery_status', true ) ?: 'in_stock';
        $delivery_weeks  = get_post_meta( $post->ID, '_boost_delivery_weeks', true ) ?: '2-3';
        $shipping_type   = get_post_meta( $post->ID, '_boost_shipping_type', true ) ?: 'pallet';
        $pallet_type     = get_post_meta( $post->ID, '_boost_pallet_type', true ) ?: 'euro';

        $settings = Modules_Settings::get_settings();
        $pallets  = $settings['shipping_pallets'];
        ?>
        <p>
            <label for="boost_delivery_status"><strong><?php esc_html_e( 'Levertijd Status', 'bossier-calculator' ); ?></strong></label>
            <select name="boost_delivery_status" id="boost_delivery_status" class="widefat">
                <option value="in_stock" <?php selected( $delivery_status, 'in_stock' ); ?>><?php esc_html_e( 'Op voorraad', 'bossier-calculator' ); ?></option>
                <option value="made_to_order" <?php selected( $delivery_status, 'made_to_order' ); ?>><?php esc_html_e( 'Op maat gemaakt', 'bossier-calculator' ); ?></option>
            </select>
        </p>

        <p class="boost-delivery-weeks-field" style="<?php echo 'in_stock' === $delivery_status ? 'display:none;' : ''; ?>">
            <label for="boost_delivery_weeks"><strong><?php esc_html_e( 'Levertijd (weken)', 'bossier-calculator' ); ?></strong></label>
            <input type="text" name="boost_delivery_weeks" id="boost_delivery_weeks" value="<?php echo esc_attr( $delivery_weeks ); ?>" class="widefat" placeholder="2-3">
            <span class="description"><?php esc_html_e( 'Bijv: 2-3 of 4', 'bossier-calculator' ); ?></span>
        </p>

        <hr>

        <p>
            <label for="boost_shipping_type"><strong><?php esc_html_e( 'Verzendtype', 'bossier-calculator' ); ?></strong></label>
            <select name="boost_shipping_type" id="boost_shipping_type" class="widefat">
                <option value="pallet" <?php selected( $shipping_type, 'pallet' ); ?>><?php esc_html_e( 'Pallet', 'bossier-calculator' ); ?></option>
                <option value="loose" <?php selected( $shipping_type, 'loose' ); ?>><?php esc_html_e( 'Los', 'bossier-calculator' ); ?></option>
            </select>
        </p>

        <p class="boost-pallet-type-field" style="<?php echo 'loose' === $shipping_type ? 'display:none;' : ''; ?>">
            <label for="boost_pallet_type"><strong><?php esc_html_e( 'Pallet Type', 'bossier-calculator' ); ?></strong></label>
            <select name="boost_pallet_type" id="boost_pallet_type" class="widefat">
                <?php foreach ( $pallets as $pallet ) : ?>
                    <option value="<?php echo esc_attr( $pallet['id'] ); ?>" <?php selected( $pallet_type, $pallet['id'] ); ?>>
                        <?php echo esc_html( $pallet['name'] . ' (' . $pallet['length'] . 'x' . $pallet['width'] . 'mm)' ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <script>
        jQuery(function($) {
            $('#boost_delivery_status').on('change', function() {
                if ($(this).val() === 'made_to_order') {
                    $('.boost-delivery-weeks-field').slideDown();
                } else {
                    $('.boost-delivery-weeks-field').slideUp();
                }
            });

            $('#boost_shipping_type').on('change', function() {
                if ($(this).val() === 'pallet') {
                    $('.boost-pallet-type-field').slideDown();
                } else {
                    $('.boost-pallet-type-field').slideUp();
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Save product shipping meta.
     *
     * @param int $post_id Post ID.
     */
    public function save_product_shipping_meta( $post_id ) {
        if ( ! isset( $_POST['boost_shipping_nonce'] ) ||
             ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['boost_shipping_nonce'] ) ), 'boost_product_shipping' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = array(
            '_boost_delivery_status' => 'sanitize_key',
            '_boost_delivery_weeks'  => 'sanitize_text_field',
            '_boost_shipping_type'   => 'sanitize_key',
            '_boost_pallet_type'     => 'sanitize_key',
        );

        foreach ( $fields as $meta_key => $sanitize_func ) {
            $field_name = str_replace( '_boost_', 'boost_', $meta_key );
            if ( isset( $_POST[ $field_name ] ) ) {
                $value = call_user_func( $sanitize_func, wp_unslash( $_POST[ $field_name ] ) );
                update_post_meta( $post_id, $meta_key, $value );
            }
        }
    }

    /**
     * Display shipping breakdown (pallets, weight, surcharges) after shipping totals.
     */
    public function display_shipping_breakdown() {
        // Get chosen shipping method
        $chosen_methods = WC()->session ? WC()->session->get( 'chosen_shipping_methods', array() ) : array();

        if ( empty( $chosen_methods ) ) {
            return;
        }

        $chosen_method = reset( $chosen_methods );

        // Only show breakdown for boost shipping (not pickup)
        if ( strpos( $chosen_method, 'boost_shipping' ) === false ) {
            return;
        }

        // Get shipping packages to find the breakdown
        $packages = WC()->shipping()->get_packages();

        foreach ( $packages as $package ) {
            if ( ! isset( $package['rates'][ $chosen_method ] ) ) {
                continue;
            }

            $rate = $package['rates'][ $chosen_method ];

            // Get breakdown from rate meta
            $breakdown = array();
            $meta_data = $rate->get_meta_data();

            // Calculate breakdown from cart if not in meta
            $country  = $package['destination']['country'] ?? '';
            $postcode = $package['destination']['postcode'] ?? '';

            if ( ! empty( $country ) && ! empty( $postcode ) ) {
                $delivery = Shipping_Calculator::calculate( $country, $postcode, $package );

                if ( $delivery['available'] && ! empty( $delivery['breakdown'] ) ) {
                    $breakdown = $delivery['breakdown'];
                }
            }

            if ( empty( $breakdown ) ) {
                return;
            }

            // Display the breakdown
            echo '<tr class="boost-shipping-breakdown">';
            echo '<th>' . esc_html__( 'Verzendspecificatie', 'bossier-calculator' ) . '</th>';
            echo '<td><ul class="boost-breakdown-list">';

            foreach ( $breakdown as $item ) {
                echo '<li class="boost-breakdown-item boost-breakdown-' . esc_attr( $item['type'] ) . '">';

                switch ( $item['type'] ) {
                    case 'pallet':
                        printf(
                            /* translators: 1: number of pallets, 2: weight in kg, 3: cost */
                            esc_html__( '%1$d pallet(s) (%2$s kg) - %3$s', 'bossier-calculator' ),
                            intval( $item['pallets_needed'] ),
                            number_format_i18n( $item['weight'], 1 ),
                            wc_price( $item['cost'] )
                        );
                        break;

                    case 'loose':
                        printf(
                            /* translators: 1: weight in kg, 2: cost */
                            esc_html__( 'Losse zending (%1$s kg) - %2$s', 'bossier-calculator' ),
                            number_format_i18n( $item['weight'], 1 ),
                            wc_price( $item['cost'] )
                        );
                        break;

                    case 'oversized':
                        printf(
                            /* translators: 1: length in mm, 2: cost */
                            esc_html__( 'Toeslag lang product (%1$d mm) - %2$s', 'bossier-calculator' ),
                            intval( $item['length'] ),
                            wc_price( $item['cost'] )
                        );
                        break;
                }

                echo '</li>';
            }

            echo '</ul></td>';
            echo '</tr>';

            // Only process first package
            break;
        }
    }

    /**
     * Force shipping recalculation when country/postcode changes.
     *
     * @param string $post_data Posted checkout data.
     */
    public function force_shipping_recalculation( $post_data ) {
        // Parse posted data
        parse_str( $post_data, $data );

        // Get new country and postcode
        $new_country  = isset( $data['shipping_country'] ) ? sanitize_text_field( $data['shipping_country'] ) : '';
        $new_postcode = isset( $data['shipping_postcode'] ) ? sanitize_text_field( $data['shipping_postcode'] ) : '';

        // Fall back to billing if ship_to_different_address is not set
        if ( empty( $new_country ) || ! isset( $data['ship_to_different_address'] ) ) {
            $new_country  = isset( $data['billing_country'] ) ? sanitize_text_field( $data['billing_country'] ) : '';
            $new_postcode = isset( $data['billing_postcode'] ) ? sanitize_text_field( $data['billing_postcode'] ) : '';
        }

        if ( empty( $new_country ) ) {
            return;
        }

        // Update WC customer with new location
        if ( WC()->customer ) {
            $current_country  = WC()->customer->get_shipping_country();
            $current_postcode = WC()->customer->get_shipping_postcode();

            // If location changed, update customer and invalidate shipping cache
            if ( $new_country !== $current_country || $new_postcode !== $current_postcode ) {
                WC()->customer->set_shipping_country( $new_country );
                WC()->customer->set_shipping_postcode( $new_postcode );

                // Also update billing if same
                if ( ! isset( $data['ship_to_different_address'] ) ) {
                    WC()->customer->set_billing_country( $new_country );
                    WC()->customer->set_billing_postcode( $new_postcode );
                }

                // Invalidate shipping cache to force recalculation
                $packages = WC()->cart->get_shipping_packages();
                foreach ( $packages as $package_key => $package ) {
                    WC()->session->set( 'shipping_for_package_' . $package_key, false );
                }

                // Clear any cached rates
                WC()->shipping()->reset_shipping();
            }
        }
    }

    /**
     * Enqueue frontend scripts.
     */
    public function enqueue_scripts() {
        if ( ! is_product() && ! is_cart() && ! is_checkout() ) {
            return;
        }

        wp_enqueue_style(
            'boost-shipping-frontend',
            BOSSIER_CALC_PLUGIN_URL . 'assets/css/shipping-frontend.css',
            array(),
            BOSSIER_CALC_VERSION
        );
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
