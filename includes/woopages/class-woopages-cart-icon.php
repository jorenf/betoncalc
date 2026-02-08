<?php
/**
 * WooPages Cart Icon.
 *
 * Provides a cart icon shortcode and function for use in themes/menus.
 *
 * @package Bossier_Calculator_Builder
 */

namespace Bossier\Calculator\WooPages;

defined( 'ABSPATH' ) || exit;

/**
 * WooPages_Cart_Icon class.
 */
class WooPages_Cart_Icon {

    /**
     * Single instance of the class.
     *
     * @var WooPages_Cart_Icon|null
     */
    private static $instance = null;

    /**
     * Get single instance of the class.
     *
     * @return WooPages_Cart_Icon
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
        // Register shortcode
        add_shortcode( 'boost_cart_icon', array( $this, 'render_shortcode' ) );

        // AJAX handlers for cart count
        add_action( 'wp_ajax_boost_get_cart_count', array( $this, 'ajax_get_cart_count' ) );
        add_action( 'wp_ajax_nopriv_boost_get_cart_count', array( $this, 'ajax_get_cart_count' ) );

        // Add fragment for cart count updates
        add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cart_count_fragment' ) );

        // Enqueue assets when shortcode is used
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

        // Auto-add cart icon to menu if enabled
        add_filter( 'wp_nav_menu_items', array( $this, 'add_cart_to_menu' ), 10, 2 );
    }

    /**
     * Add cart icon to navigation menu.
     *
     * @param string $items Menu items HTML.
     * @param object $args  Menu arguments.
     * @return string Modified menu items.
     */
    public function add_cart_to_menu( $items, $args ) {
        // Check if we should add to this menu
        $menu_location = $this->get_menu_location();

        if ( empty( $menu_location ) || 'none' === $menu_location ) {
            return $items;
        }

        // Check if this is the correct menu location
        $theme_location = isset( $args->theme_location ) ? $args->theme_location : '';
        if ( empty( $theme_location ) || $theme_location !== $menu_location ) {
            return $items;
        }

        // Ensure WooCommerce cart is available (may not be on early hooks)
        if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
            return $items;
        }

        // Add the cart icon as a menu item
        $cart_html = self::render( false, 'menu-cart-icon' );
        if ( ! empty( $cart_html ) ) {
            $items .= '<li class="menu-item menu-item-boost-cart">' . $cart_html . '</li>';
        }

        return $items;
    }

    /**
     * Get the menu location setting.
     *
     * @return string Menu location slug.
     */
    private function get_menu_location() {
        $settings = \Bossier\Calculator\Modules_Settings::get_settings();
        return isset( $settings['cart_icon_menu_location'] ) ? $settings['cart_icon_menu_location'] : '';
    }

    /**
     * Enqueue cart icon assets.
     */
    public function enqueue_assets() {
        // Only enqueue if WooCommerce is active
        if ( ! function_exists( 'WC' ) ) {
            return;
        }

        wp_enqueue_style(
            'boost-cart-icon',
            BOSSIER_CALC_PLUGIN_URL . 'assets/css/cart-icon.css',
            array(),
            BOSSIER_CALC_VERSION
        );

        wp_enqueue_script(
            'boost-cart-icon',
            BOSSIER_CALC_PLUGIN_URL . 'assets/js/cart-icon.js',
            array( 'jquery' ),
            BOSSIER_CALC_VERSION,
            true
        );

        wp_localize_script(
            'boost-cart-icon',
            'boostCartIcon',
            array(
                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'boost_cart_icon_nonce' ),
                'cartUrl'  => wc_get_cart_url(),
            )
        );
    }

    /**
     * Render cart icon shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public function render_shortcode( $atts ) {
        $atts = shortcode_atts(
            array(
                'show_total' => 'no',
                'class'      => '',
            ),
            $atts,
            'boost_cart_icon'
        );

        return self::render( $atts['show_total'] === 'yes', $atts['class'] );
    }

    /**
     * Render the cart icon HTML.
     *
     * @param bool   $show_total Whether to show cart total.
     * @param string $extra_class Additional CSS class.
     * @return string HTML output.
     */
    public static function render( $show_total = false, $extra_class = '' ) {
        if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
            return '';
        }

        $count = WC()->cart->get_cart_contents_count();
        $total = WC()->cart->get_cart_total();
        $url   = wc_get_cart_url();

        $classes = 'boost-cart-icon-wrapper';
        if ( ! empty( $extra_class ) ) {
            $classes .= ' ' . esc_attr( $extra_class );
        }

        ob_start();
        ?>
        <a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $classes ); ?>" title="<?php esc_attr_e( 'Winkelwagen bekijken', 'bossier-calculator' ); ?>">
            <span class="boost-cart-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                <span class="boost-cart-count" data-count="<?php echo esc_attr( $count ); ?>"><?php echo esc_html( $count ); ?></span>
            </span>
            <?php if ( $show_total ) : ?>
                <span class="boost-cart-total"><?php echo wp_kses_post( $total ); ?></span>
            <?php endif; ?>
        </a>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler to get cart count.
     */
    public function ajax_get_cart_count() {
        if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
            wp_send_json_success( array( 'count' => 0, 'total' => '' ) );
        }

        wp_send_json_success( array(
            'count' => WC()->cart->get_cart_contents_count(),
            'total' => WC()->cart->get_cart_total(),
        ) );
    }

    /**
     * Add cart count fragment for AJAX updates.
     *
     * @param array $fragments Cart fragments.
     * @return array Modified fragments.
     */
    public function cart_count_fragment( $fragments ) {
        if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
            return $fragments;
        }

        $count = WC()->cart->get_cart_contents_count();

        // Add fragment for the cart count badge
        $fragments['.boost-cart-count'] = '<span class="boost-cart-count" data-count="' . esc_attr( $count ) . '">' . esc_html( $count ) . '</span>';

        // Add fragment for the total
        $fragments['.boost-cart-total'] = '<span class="boost-cart-total">' . WC()->cart->get_cart_total() . '</span>';

        return $fragments;
    }
}

/**
 * Template function to render the cart icon.
 *
 * Usage: <?php boost_cart_icon(); ?>
 * Or with total: <?php boost_cart_icon( true ); ?>
 *
 * @param bool   $show_total  Whether to show cart total.
 * @param string $extra_class Additional CSS class.
 * @return void
 */
function boost_cart_icon( $show_total = false, $extra_class = '' ) {
    echo WooPages_Cart_Icon::render( $show_total, $extra_class );
}
