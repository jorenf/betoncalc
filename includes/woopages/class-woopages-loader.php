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

        // JavaScript
        wp_enqueue_script(
            'boost-woopages',
            BOSSIER_CALC_PLUGIN_URL . 'assets/js/woopages.js',
            array( 'jquery', 'wc-checkout' ),
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
                'cartUrl'        => wc_get_cart_url(),
                'checkoutUrl'    => wc_get_checkout_url(),
                'shopUrl'        => wc_get_page_permalink( 'shop' ),
                'currencySymbol' => get_woocommerce_currency_symbol(),
                'i18n'           => array(
                    'processing'     => __( 'Verwerken...', 'bossier-calculator' ),
                    'error'          => __( 'Er is een fout opgetreden. Probeer het opnieuw.', 'bossier-calculator' ),
                    'emptyCart'      => __( 'Je winkelwagen is leeg.', 'bossier-calculator' ),
                    'couponApplied'  => __( 'Kortingscode toegepast!', 'bossier-calculator' ),
                    'couponRemoved'  => __( 'Kortingscode verwijderd.', 'bossier-calculator' ),
                    'invalidCoupon'  => __( 'Ongeldige kortingscode.', 'bossier-calculator' ),
                    'removingItem'   => __( 'Verwijderen...', 'bossier-calculator' ),
                    'updatingCart'   => __( 'Winkelwagen bijwerken...', 'bossier-calculator' ),
                ),
            )
        );
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

        // Recalculate totals
        WC()->cart->calculate_totals();

        wp_send_json_success( array(
            'cart_html'   => $this->get_cart_html(),
            'totals_html' => $this->get_totals_html(),
            'cart_count'  => WC()->cart->get_cart_contents_count(),
            'cart_total'  => WC()->cart->get_total( 'edit' ),
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
            WC()->cart->calculate_totals();
            wp_send_json_success( array(
                'message'     => __( 'Kortingscode toegepast!', 'bossier-calculator' ),
                'totals_html' => $this->get_totals_html(),
            ) );
        } else {
            wp_send_json_error( array(
                'message' => wc_print_notices( true ),
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
            'message'     => __( 'Kortingscode verwijderd.', 'bossier-calculator' ),
            'totals_html' => $this->get_totals_html(),
        ) );
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

        // Check if this item has calculator data
        if ( isset( $cart_item['_bossier_display_data'] ) && is_array( $cart_item['_bossier_display_data'] ) ) {
            $display_data = $cart_item['_bossier_display_data'];
        }

        // Get weight if available
        $weight = 0;
        if ( isset( $cart_item['_bossier_calculated_weight'] ) ) {
            $weight = floatval( $cart_item['_bossier_calculated_weight'] );
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
