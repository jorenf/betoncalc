<?php
/**
 * WooPages Template Loader.
 *
 * Handles loading of custom WooCommerce page templates when WooPages is enabled.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\WooPages;

use Bossier\Calculator\Modules_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * WooPages_Loader class.
 */
class WooPages_Loader {

    /**
     * Single instance of the class.
     *
     * @var WooPages_Loader|null
     */
    private static $instance = null;

    /**
     * Path to template directory.
     *
     * @var string
     */
    private $template_path;

    /**
     * Get single instance of the class.
     *
     * @return WooPages_Loader
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
        $this->template_path = BOSSIER_CALC_PLUGIN_DIR . 'templates/woopages/';
        $this->init_hooks();
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Only hook if WooPages is enabled
        if ( ! Modules_Settings::is_woopages_enabled() ) {
            return;
        }

        // Template override - high priority to override theme templates
        add_filter( 'template_include', array( $this, 'override_template' ), 999 );

        // Enqueue assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // Remove duplicate WooCommerce order details on thank you page
        // Our custom template already displays all order info
        add_action( 'wp', array( $this, 'remove_duplicate_order_details' ) );

        // Add body class for styling
        add_filter( 'body_class', array( $this, 'add_body_class' ) );

        // Handle AJAX cart updates
        add_action( 'wp_ajax_boost_woopages_update_cart', array( $this, 'ajax_update_cart' ) );
        add_action( 'wp_ajax_nopriv_boost_woopages_update_cart', array( $this, 'ajax_update_cart' ) );

        // Handle coupon application
        add_action( 'wp_ajax_boost_woopages_apply_coupon', array( $this, 'ajax_apply_coupon' ) );
        add_action( 'wp_ajax_nopriv_boost_woopages_apply_coupon', array( $this, 'ajax_apply_coupon' ) );

        // Handle coupon removal
        add_action( 'wp_ajax_boost_woopages_remove_coupon', array( $this, 'ajax_remove_coupon' ) );
        add_action( 'wp_ajax_nopriv_boost_woopages_remove_coupon', array( $this, 'ajax_remove_coupon' ) );

        // Handle shipping postcode update
        add_action( 'wp_ajax_boost_woopages_update_shipping', array( $this, 'ajax_update_shipping' ) );
        add_action( 'wp_ajax_nopriv_boost_woopages_update_shipping', array( $this, 'ajax_update_shipping' ) );

        // Handle totals refresh (used after VAT validation / checkout update)
        add_action( 'wp_ajax_boost_woopages_refresh_totals', array( $this, 'ajax_refresh_totals' ) );
        add_action( 'wp_ajax_nopriv_boost_woopages_refresh_totals', array( $this, 'ajax_refresh_totals' ) );

        // Handle shipping method selection (updates session and recalculates totals)
        add_action( 'wp_ajax_boost_woopages_select_shipping', array( $this, 'ajax_select_shipping' ) );
        add_action( 'wp_ajax_nopriv_boost_woopages_select_shipping', array( $this, 'ajax_select_shipping' ) );
    }

    /**
     * Override template based on current page.
     *
     * @param string $template Original template path.
     * @return string Template path to use.
     */
    public function override_template( $template ) {
        // Don't override if WooCommerce is not active
        if ( ! class_exists( 'WooCommerce' ) ) {
            return $template;
        }

        // Cart page
        if ( is_cart() ) {
            $custom_template = $this->template_path . 'cart.php';
            if ( file_exists( $custom_template ) ) {
                return $custom_template;
            }
        }

        // Checkout page (but not order received/thank you)
        if ( is_checkout() && ! is_order_received_page() ) {
            $custom_template = $this->template_path . 'checkout.php';
            if ( file_exists( $custom_template ) ) {
                return $custom_template;
            }
        }

        // Order received / Thank you page
        if ( is_order_received_page() ) {
            $custom_template = $this->template_path . 'thankyou.php';
            if ( file_exists( $custom_template ) ) {
                return $custom_template;
            }
        }

        return $template;
    }

    /**
     * Enqueue WooPages assets.
     */
    public function enqueue_assets() {
        // Only on cart, checkout, and thank you pages
        if ( ! is_cart() && ! is_checkout() ) {
            return;
        }

        // Google Fonts (DM Sans and Manrope)
        wp_enqueue_style(
            'boost-woopages-fonts',
            'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap',
            array(),
            null
        );

        // CSS
        wp_enqueue_style(
            'boost-woopages',
            BOSSIER_CALC_PLUGIN_URL . 'assets/css/woopages.css',
            array( 'boost-woopages-fonts' ),
            BOSSIER_CALC_VERSION
        );

        // JavaScript - wc-checkout is only available on checkout, so make it conditional
        $js_deps = array( 'jquery' );
        if ( is_checkout() ) {
            $js_deps[] = 'wc-checkout';
        }
        wp_enqueue_script(
            'boost-woopages',
            BOSSIER_CALC_PLUGIN_URL . 'assets/js/woopages.js',
            $js_deps,
            BOSSIER_CALC_VERSION,
            true
        );

        // Localized data
        wp_localize_script(
            'boost-woopages',
            'boostWooPages',
            array(
                'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
                'nonce'          => wp_create_nonce( 'boost_woopages_nonce' ),
                'vatNonce'       => wp_create_nonce( 'boost_vat_nonce' ),
                'cartUrl'        => wc_get_cart_url(),
                'checkoutUrl'    => wc_get_checkout_url(),
                'shopUrl'        => wc_get_page_permalink( 'shop' ),
                'currencySymbol' => get_woocommerce_currency_symbol(),
                'homeCountry'    => WC()->countries->get_base_country(),
                'i18n'           => array(
                    'processing'     => __( 'Verwerken...', 'bossier-calculator' ),
                    'error'          => __( 'Er is een fout opgetreden. Probeer het opnieuw.', 'bossier-calculator' ),
                    'emptyCart'      => __( 'Je winkelwagen is leeg.', 'bossier-calculator' ),
                    'couponApplied'  => __( 'Kortingscode toegepast!', 'bossier-calculator' ),
                    'couponRemoved'  => __( 'Kortingscode verwijderd.', 'bossier-calculator' ),
                    'invalidCoupon'  => __( 'Ongeldige kortingscode.', 'bossier-calculator' ),
                    'removingItem'   => __( 'Verwijderen...', 'bossier-calculator' ),
                    'updatingCart'   => __( 'Winkelwagen bijwerken...', 'bossier-calculator' ),
                    'calculate'      => __( 'Bereken', 'bossier-calculator' ),
                    'checkout'       => __( 'Doorgaan naar afrekenen', 'bossier-calculator' ),
                ),
            )
        );
    }

    /**
     * Remove duplicate WooCommerce order details output on thank you page.
     *
     * Our custom thank you template already displays all order information,
     * so we need to remove the default WooCommerce output that's triggered
     * by the woocommerce_thankyou action.
     */
    public function remove_duplicate_order_details() {
        if ( ! is_order_received_page() ) {
            return;
        }

        // Remove WooCommerce's default order details table output
        // This is hooked to woocommerce_thankyou at priority 10
        remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
    }

    /**
     * Add body class on WooPages pages.
     *
     * @param array $classes Body classes.
     * @return array Modified body classes.
     */
    public function add_body_class( $classes ) {
        if ( is_cart() || is_checkout() ) {
            $classes[] = 'boost-woopages';

            if ( is_cart() ) {
                $classes[] = 'boost-woopages-cart';
            } elseif ( is_order_received_page() ) {
                $classes[] = 'boost-woopages-thankyou';
            } elseif ( is_checkout() ) {
                $classes[] = 'boost-woopages-checkout';
            }
        }
        return $classes;
    }

    /**
     * AJAX handler to update cart quantities.
     */
    public function ajax_update_cart() {
        check_ajax_referer( 'boost_woopages_nonce', 'nonce' );

        $cart_item_key = isset( $_POST['cart_item_key'] ) ? sanitize_text_field( wp_unslash( $_POST['cart_item_key'] ) ) : '';
        $quantity      = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0;

        if ( empty( $cart_item_key ) ) {
            wp_send_json_error( array( 'message' => __( 'Ongeldig product.', 'bossier-calculator' ) ) );
        }

        // If quantity is 0, remove item
        if ( 0 === $quantity ) {
            WC()->cart->remove_cart_item( $cart_item_key );
        } else {
            WC()->cart->set_quantity( $cart_item_key, $quantity );
        }

        // Invalidate shipping session cache so rates are recalculated with new weight
        $packages = WC()->cart->get_shipping_packages();
        foreach ( $packages as $package_key => $package ) {
            WC()->session->set( 'shipping_for_package_' . $package_key, false );
        }

        // Reset shipping calculations so rates are recalculated with new quantities/weight
        WC()->shipping()->reset_shipping();

        // Recalculate totals (includes shipping)
        WC()->cart->calculate_totals();

        wp_send_json_success( array(
            'cart_html'     => $this->get_cart_html(),
            'totals_html'   => $this->get_totals_html(),
            'shipping_html' => $this->get_shipping_options_html(),
            'cart_count'    => WC()->cart->get_cart_contents_count(),
            'cart_total'    => WC()->cart->get_total( 'edit' ),
        ) );
    }

    /**
     * AJAX handler to apply coupon.
     */
    public function ajax_apply_coupon() {
        check_ajax_referer( 'boost_woopages_nonce', 'nonce' );

        $coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';

        if ( empty( $coupon_code ) ) {
            wp_send_json_error( array( 'message' => __( 'Voer een kortingscode in.', 'bossier-calculator' ) ) );
        }

        $result = WC()->cart->apply_coupon( $coupon_code );

        if ( $result ) {
            // Clear WC success notices to prevent stale session notices
            wc_clear_notices();
            WC()->cart->calculate_totals();
            wp_send_json_success( array(
                'message'      => __( 'Kortingscode toegepast!', 'bossier-calculator' ),
                'totals_html'  => $this->get_totals_html(),
                'coupons_html' => $this->get_coupons_html(),
            ) );
        } else {
            // Extract clean text from WooCommerce notices (strips HTML markup)
            $notices_html = wc_print_notices( true );
            $clean_message = wp_strip_all_tags( $notices_html );
            $clean_message = trim( $clean_message );
            if ( empty( $clean_message ) ) {
                $clean_message = __( 'Ongeldige kortingscode.', 'bossier-calculator' );
            }
            wp_send_json_error( array(
                'message' => $clean_message,
            ) );
        }
    }

    /**
     * AJAX handler to remove coupon.
     */
    public function ajax_remove_coupon() {
        check_ajax_referer( 'boost_woopages_nonce', 'nonce' );

        $coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';

        if ( empty( $coupon_code ) ) {
            wp_send_json_error( array( 'message' => __( 'Ongeldige kortingscode.', 'bossier-calculator' ) ) );
        }

        WC()->cart->remove_coupon( $coupon_code );
        WC()->cart->calculate_totals();

        wp_send_json_success( array(
            'message'      => __( 'Kortingscode verwijderd.', 'bossier-calculator' ),
            'totals_html'  => $this->get_totals_html(),
            'coupons_html' => $this->get_coupons_html(),
        ) );
    }

    /**
     * AJAX handler to update shipping based on postcode.
     */
    public function ajax_update_shipping() {
        check_ajax_referer( 'boost_woopages_nonce', 'nonce' );

        $postcode = isset( $_POST['postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) : '';
        $country  = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : 'NL';

        if ( empty( $postcode ) ) {
            wp_send_json_error( array( 'message' => __( 'Postcode is verplicht.', 'bossier-calculator' ) ) );
        }

        // Update customer shipping address
        WC()->customer->set_shipping_postcode( $postcode );
        WC()->customer->set_shipping_country( $country );
        WC()->customer->set_billing_postcode( $postcode );
        WC()->customer->set_billing_country( $country );

        // Save to session
        WC()->customer->save();

        // When free pickup is active the postcode doesn't affect the shipping
        // cost (always €0).  Resetting the cache would cause WooCommerce to
        // default back to the postcode-based delivery rate.  Only reset when
        // pickup is NOT the currently chosen method.
        $chosen_methods   = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods', array() ) : array();
        $pickup_is_active = false;
        foreach ( $chosen_methods as $method ) {
            if ( false !== strpos( (string) $method, 'pickup' ) ) {
                $pickup_is_active = true;
                break;
            }
        }

        if ( ! $pickup_is_active ) {
            // Reset shipping calculations to get new rates
            WC()->shipping()->reset_shipping();
        }

        // Recalculate cart totals (includes shipping)
        WC()->cart->calculate_totals();

        // Get shipping methods HTML
        $shipping_html = $this->get_shipping_options_html();

        wp_send_json_success( array(
            'shipping_html' => $shipping_html,
            'totals_html'   => $this->get_totals_html(),
        ) );
    }

    /**
     * AJAX handler to refresh totals (after VAT validation or checkout update).
     */
    public function ajax_refresh_totals() {
        check_ajax_referer( 'boost_woopages_nonce', 'nonce' );

        // When free pickup is the active shipping method, the postcode does not
        // affect the shipping cost (it is always €0).  Resetting the shipping
        // cache here can cause WooCommerce to fall back to the first available
        // rate (the postcode-based delivery rate), overwriting the €0 pickup cost.
        // Skip the reset in that case; just recalculate totals as-is.
        $chosen_methods    = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods', array() ) : array();
        $pickup_is_active  = false;
        foreach ( $chosen_methods as $method ) {
            if ( false !== strpos( (string) $method, 'pickup' ) ) {
                $pickup_is_active = true;
                break;
            }
        }

        if ( ! $pickup_is_active ) {
            // Invalidate shipping session cache to ensure fresh rates
            $packages = WC()->cart->get_shipping_packages();
            foreach ( $packages as $package_key => $package ) {
                WC()->session->set( 'shipping_for_package_' . $package_key, false );
            }
            WC()->shipping()->reset_shipping();
        }

        // Recalculate totals to reflect any session changes (e.g., reverse charge)
        WC()->cart->calculate_totals();

        wp_send_json_success( array(
            'totals_html' => $this->get_totals_html(),
            'cart_html'   => $this->get_cart_html(),
            'cart_count'  => WC()->cart->get_cart_contents_count(),
            'cart_total'  => WC()->cart->get_total( 'edit' ),
        ) );
    }

    /**
     * AJAX handler to select shipping method and recalculate totals.
     *
     * This is needed because the cart page doesn't have WooCommerce's checkout JS
     * which normally handles shipping method selection updates.
     */
    public function ajax_select_shipping() {
        check_ajax_referer( 'boost_woopages_nonce', 'nonce' );

        $method_id = isset( $_POST['method_id'] ) ? sanitize_text_field( wp_unslash( $_POST['method_id'] ) ) : '';

        if ( empty( $method_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Ongeldige verzendmethode.', 'bossier-calculator' ) ) );
        }

        // Validate the posted method against the currently available package rates.
        WC()->cart->calculate_shipping();
        $packages       = WC()->shipping()->get_packages();
        $chosen_methods = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods', array() ) : array();
        $valid_method   = false;

        foreach ( $packages as $package_key => $package ) {
            if ( isset( $package['rates'][ $method_id ] ) ) {
                $chosen_methods[ $package_key ] = $method_id;
                $valid_method = true;
                break;
            }
        }

        if ( ! $valid_method ) {
            wp_send_json_error( array( 'message' => __( 'Ongeldige verzendmethode.', 'bossier-calculator' ) ) );
        }

        // Update the chosen shipping method in session.
        WC()->session->set( 'chosen_shipping_methods', $chosen_methods );

        // Recalculate cart totals with new shipping method
        WC()->cart->calculate_totals();

        wp_send_json_success( array(
            'totals_html'   => $this->get_totals_html(),
            'shipping_html' => $this->get_shipping_options_html(),
            'cart_total'    => WC()->cart->get_total( 'edit' ),
        ) );
    }

    /**
     * Get rendered shipping options HTML.
     *
     * @return string HTML.
     */
    private function get_shipping_options_html() {
        $packages      = WC()->shipping()->get_packages();
        $chosen_method = isset( WC()->session->chosen_shipping_methods[0] ) ? WC()->session->chosen_shipping_methods[0] : '';
        $cart_delivery = WooPages_Helper::get_cart_delivery_time();

        ob_start();

        $tax_display_mode = get_option( 'woocommerce_tax_display_cart' );

        if ( ! empty( $packages ) ) {
            foreach ( $packages as $i => $package ) {
                $available_methods = $package['rates'];
                foreach ( $available_methods as $method ) {
                    $is_selected = $chosen_method === $method->get_id();

                    // Calculate shipping cost with tax if tax display is set to 'incl'
                    $method_cost = floatval( $method->get_cost() );
                    $method_cost_display = $method_cost;
                    if ( 'incl' === $tax_display_mode && $method_cost > 0 ) {
                        $method_taxes = $method->get_taxes();
                        $method_cost_display = $method_cost + array_sum( $method_taxes );
                    }
                    ?>
                    <label class="boost-woo-ship-opt <?php echo $is_selected ? 'active' : ''; ?>" data-method-id="<?php echo esc_attr( $method->get_id() ); ?>">
                        <div class="boost-woo-ship-radio"></div>
                        <input type="radio"
                               name="shipping_method[<?php echo esc_attr( $i ); ?>]"
                               value="<?php echo esc_attr( $method->get_id() ); ?>"
                               class="shipping_method"
                               <?php checked( $is_selected ); ?>
                               style="display: none;" />
                        <div class="boost-woo-ship-opt-info">
                            <?php
                            // When product delivery time overrides, strip parenthetical
                            // delivery info (e.g. "(2-4 werkdagen)") from the label.
                            $label = $method->get_label();
                            if ( $cart_delivery ) {
                                $label = trim( preg_replace( '/\s*\([^)]*\)\s*$/', '', $label ) );
                            }
                            ?>
                            <div class="name"><?php echo esc_html( $label ); ?></div>
                            <?php
                            // Use product-level delivery time when available (e.g. made-to-order),
                            // otherwise fall back to shipping method delivery_days meta.
                            if ( $cart_delivery ) :
                            ?>
                                <div class="desc"><?php echo esc_html( sprintf( __( 'Levertijd: %s', 'bossier-calculator' ), $cart_delivery['text'] ) ); ?></div>
                            <?php
                            else :
                                $meta = $method->get_meta_data();
                                if ( ! empty( $meta['delivery_days'] ) ) :
                            ?>
                                <div class="desc"><?php echo esc_html( sprintf( __( 'Levertijd: %s', 'bossier-calculator' ), $meta['delivery_days'] ) ); ?></div>
                            <?php
                                endif;
                            endif;
                            ?>
                        </div>
                        <?php if ( $method_cost !== 0.0 ) : ?>
                            <div class="boost-woo-ship-price">
                                <?php echo wc_price( $method_cost_display ); ?>
                            </div>
                        <?php endif; ?>
                    </label>
                    <?php
                }
            }
        } else {
            ?>
            <div class="boost-woo-no-shipping">
                <?php esc_html_e( 'Geen verzendmethoden beschikbaar voor deze locatie.', 'bossier-calculator' ); ?>
            </div>
            <?php
        }

        return ob_get_clean();
    }

    /**
     * Get rendered applied coupons HTML.
     *
     * @return string HTML.
     */
    private function get_coupons_html() {
        $coupons = WC()->cart->get_applied_coupons();

        ob_start();
        if ( ! empty( $coupons ) ) :
            foreach ( $coupons as $coupon_code ) :
                ?>
                <span class="boost-woo-applied-coupon" data-coupon="<?php echo esc_attr( $coupon_code ); ?>">
                    <?php echo esc_html( $coupon_code ); ?>
                    <span class="remove" title="<?php esc_attr_e( 'Verwijderen', 'bossier-calculator' ); ?>">&#10005;</span>
                </span>
                <?php
            endforeach;
        endif;
        return ob_get_clean();
    }

    /**
     * Get rendered cart items HTML.
     *
     * @return string HTML.
     */
    private function get_cart_html() {
        ob_start();
        include $this->template_path . 'parts/cart-items.php';
        return ob_get_clean();
    }

    /**
     * Get rendered totals HTML.
     *
     * @return string HTML.
     */
    private function get_totals_html() {
        ob_start();
        include $this->template_path . 'parts/cart-totals.php';
        return ob_get_clean();
    }

    /**
     * Get calculator display data for a cart item.
     *
     * @param array $cart_item Cart item data.
     * @return array Display data.
     */
    public static function get_cart_item_calculator_data( $cart_item ) {
        $display_data = array();

        // Check if this item has calculator data (stored in bossier_calculator array)
        if ( isset( $cart_item['bossier_calculator']['display_data'] ) && is_array( $cart_item['bossier_calculator']['display_data'] ) ) {
            $display_data = $cart_item['bossier_calculator']['display_data'];
        }

        // Get weight if available (stored per unit in bossier_calculator array)
        $weight = 0;
        if ( isset( $cart_item['bossier_calculator']['calculated_weight'] ) ) {
            $weight = floatval( $cart_item['bossier_calculator']['calculated_weight'] );
        }

        return array(
            'display_data' => $display_data,
            'weight'       => $weight,
            'has_data'     => ! empty( $display_data ),
        );
    }

    /**
     * Get formatted weight string.
     *
     * @param float $weight Weight in kg.
     * @return string Formatted weight.
     */
    public static function format_weight( $weight ) {
        if ( $weight <= 0 ) {
            return '';
        }
        return number_format( $weight, 2, ',', '.' ) . ' kg';
    }

    /**
     * Check if current order has reverse charge VAT.
     *
     * @return bool
     */
    public static function is_reverse_charge() {
        if ( ! class_exists( '\Bossier\Calculator\BTW\BTW_Checkout' ) ) {
            return false;
        }

        // Check session for reverse charge flag
        if ( WC()->session ) {
            return (bool) WC()->session->get( 'boost_btw_reverse_charge', false );
        }

        return false;
    }

    /**
     * Get shop header data (logo, name, etc).
     *
     * @return array
     */
    public static function get_shop_header_data() {
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        $logo_url       = '';

        if ( $custom_logo_id ) {
            $logo_url = wp_get_attachment_image_url( $custom_logo_id, 'medium' );
        }

        return array(
            'name'     => get_bloginfo( 'name' ),
            'logo_url' => $logo_url,
            'home_url' => home_url(),
            'shop_url' => wc_get_page_permalink( 'shop' ),
            'cart_url' => wc_get_cart_url(),
        );
    }
}
