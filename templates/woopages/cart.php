<?php
/**
 * Boost WooPages - Cart Template
 *
 * This template overrides the standard WooCommerce cart page.
 *
 * @package Bossier_Calculator_Builder
 */

defined( 'ABSPATH' ) || exit;

use Bossier\Calculator\WooPages\WooPages_Helper;

// Ensure WooCommerce is active and cart exists
if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
    wp_redirect( home_url() );
    exit;
}

// Calculate cart totals
WC()->cart->calculate_totals();

// Get shop header data
$shop_data = \Bossier\Calculator\WooPages\WooPages_Loader::get_shop_header_data();
$cart_summary = WooPages_Helper::get_cart_summary();
$total_weight = WooPages_Helper::get_cart_total_weight();

get_header( 'shop' );
?>

<!-- Page Header -->
<div class="boost-woo-page-header">
    <div class="boost-woo-wrapper">
        <div class="boost-woo-breadcrumb">
            <a href="<?php echo esc_url( home_url() ); ?>"><?php esc_html_e( 'Home', 'bossier-calculator' ); ?></a>
            <span>/</span>
            <span><?php esc_html_e( 'Winkelwagen', 'bossier-calculator' ); ?></span>
        </div>
        <h1><?php esc_html_e( 'Winkelwagen', 'bossier-calculator' ); ?></h1>
        <p><?php esc_html_e( 'Controleer je bestelling en ga door naar afrekenen', 'bossier-calculator' ); ?></p>
    </div>
</div>

<div class="boost-woo-wrapper" style="padding-top: 28px;">

    <!-- Steps -->
    <div class="boost-woo-steps">
        <div class="boost-woo-step active" data-step="1" data-url="<?php echo esc_url( wc_get_cart_url() ); ?>">
            <div class="boost-woo-step-num">1</div>
            <div class="boost-woo-step-label"><?php esc_html_e( 'Winkelwagen', 'bossier-calculator' ); ?></div>
        </div>
        <div class="boost-woo-step" data-step="2" data-url="<?php echo esc_url( wc_get_checkout_url() ); ?>">
            <div class="boost-woo-step-num">2</div>
            <div class="boost-woo-step-label"><?php esc_html_e( 'Gegevens & Betaling', 'bossier-calculator' ); ?></div>
        </div>
        <div class="boost-woo-step" data-step="3">
            <div class="boost-woo-step-num">3</div>
            <div class="boost-woo-step-label"><?php esc_html_e( 'Bevestiging', 'bossier-calculator' ); ?></div>
        </div>
    </div>

    <!-- Notices -->
    <div class="boost-woo-notices">
        <?php wc_print_notices(); ?>
    </div>

    <?php if ( WC()->cart->is_empty() ) : ?>
        <!-- Empty Cart -->
        <div class="boost-woo-panel">
            <div class="boost-woo-cart-empty">
                <div class="boost-woo-cart-empty-icon">🛒</div>
                <h3><?php esc_html_e( 'Je winkelwagen is leeg', 'bossier-calculator' ); ?></h3>
                <p><?php esc_html_e( 'Voeg producten toe om verder te winkelen.', 'bossier-calculator' ); ?></p>
                <a href="<?php echo esc_url( $shop_data['shop_url'] ); ?>" class="boost-woo-btn boost-woo-btn-blue">
                    <?php esc_html_e( 'Bekijk producten', 'bossier-calculator' ); ?>
                </a>
            </div>
        </div>
    <?php else : ?>
        <!-- Cart Content -->
        <form class="woocommerce-cart-form" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
            <?php do_action( 'woocommerce_before_cart' ); ?>

            <div class="boost-woo-main">
                <!-- Cart Items -->
                <div>
                    <div class="boost-woo-panel" id="boost-cart-items">
                        <div class="boost-woo-panel-header">
                            <h3><?php esc_html_e( 'Jouw producten', 'bossier-calculator' ); ?></h3>
                            <span class="boost-woo-panel-count">
                                <span id="itemCount"><?php echo esc_html( $cart_summary['item_count'] ); ?></span>
                                <?php esc_html_e( 'artikelen', 'bossier-calculator' ); ?>
                            </span>
                        </div>

                        <?php
                        foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
                            $_product = $cart_item['data'];

                            if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 ) {
                                continue;
                            }

                            $product_id     = $cart_item['product_id'];
                            $product_name   = $_product->get_name();
                            $thumbnail      = WooPages_Helper::get_product_thumbnail( $cart_item );
                            $specs          = WooPages_Helper::get_product_specs( $cart_item );
                            $price_data     = WooPages_Helper::get_line_item_price( $cart_item );
                            $product_weight = WooPages_Helper::get_cart_item_weight( $cart_item );
                            $permalink      = $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '';
                        ?>
                        <div class="boost-woo-cart-item" data-cart-key="<?php echo esc_attr( $cart_item_key ); ?>">
                            <div class="boost-woo-item-img">
                                <?php if ( $permalink ) : ?>
                                    <a href="<?php echo esc_url( $permalink ); ?>">
                                        <?php echo wp_kses_post( $thumbnail ); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo wp_kses_post( $thumbnail ); ?>
                                <?php endif; ?>
                            </div>
                            <div class="boost-woo-item-info">
                                <h4>
                                    <?php if ( $permalink ) : ?>
                                        <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $product_name ); ?></a>
                                    <?php else : ?>
                                        <?php echo esc_html( $product_name ); ?>
                                    <?php endif; ?>
                                </h4>
                                <div class="boost-woo-item-sku"><?php echo esc_html( $_product->get_sku() ); ?></div>

                                <?php if ( ! empty( $specs ) ) : ?>
                                <div class="boost-woo-item-specs">
                                    <?php foreach ( $specs as $spec ) : ?>
                                        <span class="boost-woo-spec-tag"><?php echo esc_html( $spec ); ?></span>
                                    <?php endforeach; ?>

                                    <?php if ( $product_weight > 0 ) : ?>
                                        <span class="boost-woo-spec-tag"><?php echo esc_html( WooPages_Helper::format_weight( $product_weight ) ); ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>

                                <div class="boost-woo-item-actions">
                                    <?php if ( $_product->is_sold_individually() ) : ?>
                                        <input type="hidden" name="cart[<?php echo esc_attr( $cart_item_key ); ?>][qty]" value="1" />
                                    <?php else : ?>
                                        <div class="boost-woo-qty-ctrl">
                                            <button type="button" aria-label="<?php esc_attr_e( 'Verminder aantal', 'bossier-calculator' ); ?>">−</button>
                                            <input type="number"
                                                   class="qty"
                                                   name="cart[<?php echo esc_attr( $cart_item_key ); ?>][qty]"
                                                   value="<?php echo esc_attr( $cart_item['quantity'] ); ?>"
                                                   min="1"
                                                   max="<?php echo esc_attr( $_product->get_max_purchase_quantity() ); ?>"
                                                   step="1"
                                                   inputmode="numeric"
                                                   aria-label="<?php esc_attr_e( 'Aantal', 'bossier-calculator' ); ?>" />
                                            <button type="button" aria-label="<?php esc_attr_e( 'Verhoog aantal', 'bossier-calculator' ); ?>">+</button>
                                        </div>
                                    <?php endif; ?>

                                    <button type="button"
                                            class="boost-woo-remove-btn"
                                            data-product_id="<?php echo esc_attr( $product_id ); ?>"
                                            data-cart_item_key="<?php echo esc_attr( $cart_item_key ); ?>"
                                            aria-label="<?php esc_attr_e( 'Verwijder dit item', 'bossier-calculator' ); ?>">
                                        ✕ <?php esc_html_e( 'Verwijderen', 'bossier-calculator' ); ?>
                                    </button>
                                </div>
                            </div>
                            <div class="boost-woo-item-price">
                                <div class="price"><?php echo wp_kses_post( $price_data['line_total'] ); ?></div>
                                <?php if ( $cart_item['quantity'] > 1 ) : ?>
                                    <div class="per"><?php echo wp_kses_post( $price_data['formatted'] ); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Continue Shopping -->
                    <a href="<?php echo esc_url( $shop_data['shop_url'] ); ?>" class="boost-woo-continue-link">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z"/></svg>
                        <?php esc_html_e( 'Verder winkelen', 'bossier-calculator' ); ?>
                    </a>
                </div>

                <!-- Sidebar -->
                <div class="boost-woo-sidebar">
                    <!-- Shipping -->
                    <?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
                    <div class="boost-woo-panel">
                        <div class="boost-woo-panel-header">
                            <h3><?php esc_html_e( 'Verzending', 'bossier-calculator' ); ?></h3>
                        </div>
                        <div class="boost-woo-ship-options">
                            <?php
                            $packages = WC()->shipping()->get_packages();
                            $chosen_method = isset( WC()->session->chosen_shipping_methods[0] ) ? WC()->session->chosen_shipping_methods[0] : '';

                            foreach ( $packages as $i => $package ) :
                                $available_methods = $package['rates'];
                                foreach ( $available_methods as $method ) :
                                    $is_selected = $chosen_method === $method->get_id();
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
                                    <div class="name"><?php echo esc_html( $method->get_label() ); ?></div>
                                    <?php
                                    $meta = $method->get_meta_data();
                                    if ( ! empty( $meta['delivery_days'] ) ) :
                                    ?>
                                        <div class="desc"><?php echo esc_html( sprintf( __( 'Levertijd: %s werkdagen', 'bossier-calculator' ), $meta['delivery_days'] ) ); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="boost-woo-ship-price <?php echo ( floatval( $method->get_cost() ) === 0.0 ) ? 'free' : ''; ?>">
                                    <?php echo ( floatval( $method->get_cost() ) === 0.0 ) ? esc_html__( 'Gratis', 'bossier-calculator' ) : wc_price( $method->get_cost() ); ?>
                                </div>
                            </label>
                            <?php
                                endforeach;
                            endforeach;
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Coupon -->
                    <?php if ( wc_coupons_enabled() ) : ?>
                    <div class="boost-woo-panel">
                        <div class="boost-woo-panel-header">
                            <h3><?php esc_html_e( 'Kortingscode', 'bossier-calculator' ); ?></h3>
                        </div>

                        <?php if ( ! empty( $cart_summary['coupons'] ) ) : ?>
                        <div class="boost-woo-applied-coupons">
                            <?php foreach ( $cart_summary['coupons'] as $coupon_code ) : ?>
                                <span class="boost-woo-applied-coupon" data-coupon="<?php echo esc_attr( $coupon_code ); ?>">
                                    <?php echo esc_html( $coupon_code ); ?>
                                    <span class="remove" title="<?php esc_attr_e( 'Verwijderen', 'bossier-calculator' ); ?>">✕</span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div style="padding-top: 12px;">
                            <form class="boost-woo-coupon-form">
                                <div class="boost-woo-coupon-row">
                                    <input type="text" name="coupon_code" placeholder="<?php esc_attr_e( 'Voer code in...', 'bossier-calculator' ); ?>" />
                                    <button type="submit"><?php esc_html_e( 'Toepassen', 'bossier-calculator' ); ?></button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Summary -->
                    <div class="boost-woo-panel">
                        <div class="boost-woo-panel-header">
                            <h3><?php esc_html_e( 'Overzicht', 'bossier-calculator' ); ?></h3>
                        </div>
                        <div class="boost-woo-summary">
                            <div class="boost-woo-sum-row">
                                <span class="lbl"><?php printf( esc_html__( 'Subtotaal (%d artikelen)', 'bossier-calculator' ), $cart_summary['item_count'] ); ?></span>
                                <span class="val"><?php echo wp_kses_post( $cart_summary['subtotal'] ); ?></span>
                            </div>

                            <?php if ( WC()->cart->needs_shipping() ) : ?>
                            <div class="boost-woo-sum-row">
                                <span class="lbl"><?php esc_html_e( 'Verzendkosten', 'bossier-calculator' ); ?></span>
                                <span class="val"><?php echo wp_kses_post( $cart_summary['shipping'] ); ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if ( $cart_summary['discount_raw'] > 0 ) : ?>
                            <div class="boost-woo-sum-row discount">
                                <span class="lbl"><?php esc_html_e( 'Korting', 'bossier-calculator' ); ?></span>
                                <span class="val">-<?php echo wp_kses_post( $cart_summary['discount'] ); ?></span>
                            </div>
                            <?php endif; ?>

                            <div class="boost-woo-sum-divider"></div>

                            <div class="boost-woo-sum-row">
                                <span class="lbl"><?php esc_html_e( 'Totaal excl. BTW', 'bossier-calculator' ); ?></span>
                                <span class="val"><?php echo wp_kses_post( $cart_summary['total_excl_tax'] ); ?></span>
                            </div>

                            <div class="boost-woo-sum-row">
                                <span class="lbl"><?php esc_html_e( 'BTW (21%)', 'bossier-calculator' ); ?></span>
                                <span class="val"><?php echo wp_kses_post( $cart_summary['tax'] ); ?></span>
                            </div>

                            <div class="boost-woo-sum-divider"></div>

                            <div class="boost-woo-sum-total">
                                <span><?php esc_html_e( 'Totaal', 'bossier-calculator' ); ?></span>
                                <span class="val"><?php echo wp_kses_post( $cart_summary['total'] ); ?></span>
                            </div>
                            <div class="boost-woo-sum-vat">
                                <?php printf( esc_html__( 'Inclusief %s BTW', 'bossier-calculator' ), wp_kses_post( $cart_summary['tax'] ) ); ?>
                            </div>

                            <?php if ( $total_weight > 0 ) : ?>
                            <div class="boost-woo-weight-row">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L11 6.477V16h2a1 1 0 110 2H7a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.788l1.599.799L9 4.323V3a1 1 0 011-1z"/></svg>
                                <span class="lbl"><?php esc_html_e( 'Totaal gewicht', 'bossier-calculator' ); ?></span>
                                <span class="val"><?php echo esc_html( WooPages_Helper::format_weight( $total_weight ) ); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="boost-woo-cta-wrap">
                            <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="boost-woo-btn boost-woo-btn-blue boost-woo-btn-lg">
                                <?php esc_html_e( 'Doorgaan naar afrekenen', 'bossier-calculator' ); ?>
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z"/></svg>
                            </a>
                            <div class="boost-woo-secure-note">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/></svg>
                                <?php esc_html_e( 'Veilig afrekenen via SSL verbinding', 'bossier-calculator' ); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php do_action( 'woocommerce_after_cart' ); ?>
        </form>
    <?php endif; ?>

</div>

<?php
get_footer( 'shop' );
