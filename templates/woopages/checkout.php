<?php
/**
 * Boost WooPages - Checkout Template
 *
 * This template overrides the standard WooCommerce checkout page.
 *
 * @package Bossier_Calculator_Builder
 */

defined( 'ABSPATH' ) || exit;

use Bossier\Calculator\WooPages\WooPages_Helper;
use Bossier\Calculator\Modules_Settings;

// Ensure WooCommerce is active and cart exists
if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
    wp_redirect( home_url() );
    exit;
}

// Redirect to cart if empty
if ( WC()->cart->is_empty() ) {
    wp_redirect( wc_get_cart_url() );
    exit;
}

// Get checkout object
$checkout = WC()->checkout();

// Calculate cart totals
WC()->cart->calculate_totals();

// Get cart summary
$cart_summary = WooPages_Helper::get_cart_summary();
$total_weight = WooPages_Helper::get_cart_total_weight();
$order_items = WooPages_Helper::get_order_review_items();
$cart_fees = WooPages_Helper::get_cart_fees();

// Check if BTW module is active
$btw_enabled = Modules_Settings::is_btw_enabled();
$btw_settings = Modules_Settings::get_settings();

get_header( 'shop' );
?>

<!-- Page Header -->
<div class="boost-woo-page-header">
    <div class="boost-woo-wrapper">
        <div class="boost-woo-breadcrumb">
            <a href="<?php echo esc_url( home_url() ); ?>"><?php esc_html_e( 'Home', 'bossier-calculator' ); ?></a>
            <span>/</span>
            <a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php esc_html_e( 'Winkelwagen', 'bossier-calculator' ); ?></a>
            <span>/</span>
            <span><?php esc_html_e( 'Afrekenen', 'bossier-calculator' ); ?></span>
        </div>
        <h1><?php esc_html_e( 'Afrekenen', 'bossier-calculator' ); ?></h1>
        <p><?php esc_html_e( 'Vul je gegevens in en kies een betaalmethode', 'bossier-calculator' ); ?></p>
    </div>
</div>

<div class="boost-woo-wrapper" style="padding-top: 28px;">

    <!-- Steps -->
    <div class="boost-woo-steps">
        <a class="boost-woo-step done" data-step="1" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
            <div class="boost-woo-step-num">✓</div>
            <div class="boost-woo-step-label"><?php esc_html_e( 'Winkelwagen', 'bossier-calculator' ); ?></div>
        </a>
        <div class="boost-woo-step active" data-step="2">
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

    <?php do_action( 'woocommerce_before_checkout_form', $checkout ); ?>

    <?php if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) : ?>
        <div class="boost-woo-panel">
            <div class="boost-woo-form-section">
                <p><?php echo wp_kses_post( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) ); ?></p>
            </div>
        </div>
    <?php else : ?>

    <form name="checkout" method="post" class="checkout woocommerce-checkout boost-woo-checkout-form" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

        <div class="boost-woo-main">
            <!-- Checkout Form -->
            <div>
                <div class="boost-woo-panel" style="margin-bottom: 16px;">
                    <!-- Customer Details -->
                    <div class="boost-woo-form-section">
                        <h3>
                            <span class="icon">👤</span>
                            <?php esc_html_e( 'Klantgegevens', 'bossier-calculator' ); ?>
                        </h3>

                        <?php if ( $btw_enabled ) : ?>
                        <?php
                        // Read persisted business state from session
                        $is_business_order = WC()->session ? WC()->session->get( 'boost_is_business_order', false ) : false;
                        $saved_vat_number  = WC()->session ? WC()->session->get( 'boost_vat_number', '' ) : '';
                        $saved_company     = $checkout->get_value( 'billing_company' );
                        if ( empty( $saved_company ) && WC()->session ) {
                            $saved_company = WC()->session->get( 'boost_vat_company', '' );
                        }
                        ?>
                        <!-- Business Toggle -->
                        <div class="boost-woo-biz-toggle <?php echo $is_business_order ? 'active' : ''; ?>" id="boost-biz-toggle">
                            <div class="boost-woo-toggle-switch"></div>
                            <input type="checkbox" name="boost_is_business" id="boost_is_business" value="1" <?php checked( $is_business_order ); ?> style="display: none;" />
                            <div class="boost-woo-biz-label">
                                <?php echo esc_html( $btw_settings['btw_checkbox_label'] ?? __( 'Dit is een zakelijke bestelling', 'bossier-calculator' ) ); ?>
                                <span><?php esc_html_e( 'BTW-verlegd voor bedrijven met geldig BTW-nummer', 'bossier-calculator' ); ?></span>
                            </div>
                        </div>

                        <!-- Business Fields (ID matches btw-checkout.js expectations) -->
                        <div class="boost-woo-biz-fields" id="boost-business-fields" <?php echo $is_business_order ? 'style="display: block;"' : ''; ?>>
                            <div class="boost-woo-form-row">
                                <div class="boost-woo-form-group">
                                    <label for="billing_company">
                                        <?php echo esc_html( $btw_settings['btw_company_label'] ?? __( 'Bedrijfsnaam', 'bossier-calculator' ) ); ?>
                                        <span class="req">*</span>
                                    </label>
                                    <input type="text" class="input-text" name="billing_company" id="billing_company"
                                           value="<?php echo esc_attr( $saved_company ); ?>"
                                           placeholder="<?php esc_attr_e( 'Bedrijfsnaam B.V.', 'bossier-calculator' ); ?>" />
                                </div>
                                <div class="boost-woo-form-group boost-woo-vat-group">
                                    <label for="boost_vat_number">
                                        <?php echo esc_html( $btw_settings['btw_vat_label'] ?? __( 'BTW-nummer (optioneel)', 'bossier-calculator' ) ); ?>
                                    </label>
                                    <input type="text" class="input-text" name="boost_vat_number" id="boost_vat_number"
                                           value="<?php echo esc_attr( $saved_vat_number ); ?>"
                                           placeholder="<?php echo esc_attr( $btw_settings['btw_vat_placeholder'] ?? 'NL000000000B01' ); ?>" />
                                    <!-- VIES validation result display -->
                                    <div id="boost-vat-validation-result" class="boost-vat-result"></div>
                                </div>
                            </div>
                            <div class="boost-woo-biz-note">
                                <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/></svg>
                                <?php esc_html_e( 'BTW wordt verlegd bij een geldig EU BTW-nummer. Het BTW-nummer wordt automatisch gevalideerd via VIES.', 'bossier-calculator' ); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Name Fields -->
                        <div class="boost-woo-form-row">
                            <div class="boost-woo-form-group">
                                <label for="billing_first_name"><?php esc_html_e( 'Voornaam', 'bossier-calculator' ); ?><span class="req">*</span></label>
                                <input type="text" class="input-text" name="billing_first_name" id="billing_first_name"
                                       value="<?php echo esc_attr( $checkout->get_value( 'billing_first_name' ) ); ?>"
                                       placeholder="<?php esc_attr_e( 'Jan', 'bossier-calculator' ); ?>"
                                       required />
                            </div>
                            <div class="boost-woo-form-group">
                                <label for="billing_last_name"><?php esc_html_e( 'Achternaam', 'bossier-calculator' ); ?><span class="req">*</span></label>
                                <input type="text" class="input-text" name="billing_last_name" id="billing_last_name"
                                       value="<?php echo esc_attr( $checkout->get_value( 'billing_last_name' ) ); ?>"
                                       placeholder="<?php esc_attr_e( 'de Vries', 'bossier-calculator' ); ?>"
                                       required />
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="boost-woo-form-row full">
                            <div class="boost-woo-form-group">
                                <label for="billing_email"><?php esc_html_e( 'E-mailadres', 'bossier-calculator' ); ?><span class="req">*</span></label>
                                <input type="email" class="input-text" name="billing_email" id="billing_email"
                                       value="<?php echo esc_attr( $checkout->get_value( 'billing_email' ) ); ?>"
                                       placeholder="<?php esc_attr_e( 'jan@voorbeeld.nl', 'bossier-calculator' ); ?>"
                                       required />
                            </div>
                        </div>

                        <!-- Phone -->
                        <div class="boost-woo-form-row">
                            <div class="boost-woo-form-group">
                                <label for="billing_phone"><?php esc_html_e( 'Telefoonnummer', 'bossier-calculator' ); ?><span class="req">*</span></label>
                                <input type="tel" class="input-text" name="billing_phone" id="billing_phone"
                                       value="<?php echo esc_attr( $checkout->get_value( 'billing_phone' ) ); ?>"
                                       placeholder="<?php esc_attr_e( '+31 6 12345678', 'bossier-calculator' ); ?>"
                                       required />
                            </div>
                        </div>
                    </div>

                    <!-- Billing Address -->
                    <div class="boost-woo-form-section">
                        <h3>
                            <span class="icon">📍</span>
                            <?php esc_html_e( 'Bezorgadres', 'bossier-calculator' ); ?>
                        </h3>

                        <!-- Street -->
                        <div class="boost-woo-form-row full">
                            <div class="boost-woo-form-group">
                                <label for="billing_address_1"><?php esc_html_e( 'Straat en huisnummer', 'bossier-calculator' ); ?><span class="req">*</span></label>
                                <input type="text" class="input-text" name="billing_address_1" id="billing_address_1"
                                       value="<?php echo esc_attr( $checkout->get_value( 'billing_address_1' ) ); ?>"
                                       placeholder="<?php esc_attr_e( 'Hoofdstraat 12', 'bossier-calculator' ); ?>"
                                       required />
                            </div>
                        </div>

                        <!-- Postcode & City -->
                        <div class="boost-woo-form-row">
                            <div class="boost-woo-form-group">
                                <label for="billing_postcode"><?php esc_html_e( 'Postcode', 'bossier-calculator' ); ?><span class="req">*</span></label>
                                <input type="text" class="input-text" name="billing_postcode" id="billing_postcode"
                                       value="<?php echo esc_attr( $checkout->get_value( 'billing_postcode' ) ); ?>"
                                       placeholder="<?php esc_attr_e( '1234 AB', 'bossier-calculator' ); ?>"
                                       required />
                            </div>
                            <div class="boost-woo-form-group">
                                <label for="billing_city"><?php esc_html_e( 'Plaats', 'bossier-calculator' ); ?><span class="req">*</span></label>
                                <input type="text" class="input-text" name="billing_city" id="billing_city"
                                       value="<?php echo esc_attr( $checkout->get_value( 'billing_city' ) ); ?>"
                                       placeholder="<?php esc_attr_e( 'Amsterdam', 'bossier-calculator' ); ?>"
                                       required />
                            </div>
                        </div>

                        <!-- Country -->
                        <div class="boost-woo-form-row full">
                            <div class="boost-woo-form-group">
                                <label for="billing_country"><?php esc_html_e( 'Land', 'bossier-calculator' ); ?><span class="req">*</span></label>
                                <select name="billing_country" id="billing_country" class="country_to_state country_select" required>
                                    <?php
                                    $countries = WC()->countries->get_shipping_countries();
                                    $current_country = $checkout->get_value( 'billing_country' ) ?: WC()->countries->get_base_country();
                                    foreach ( $countries as $country_code => $country_name ) :
                                    ?>
                                        <option value="<?php echo esc_attr( $country_code ); ?>" <?php selected( $current_country, $country_code ); ?>>
                                            <?php echo esc_html( $country_name ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Order Notes -->
                        <div class="boost-woo-form-row full">
                            <div class="boost-woo-form-group">
                                <label for="order_comments"><?php esc_html_e( 'Opmerkingen bij levering', 'bossier-calculator' ); ?></label>
                                <textarea name="order_comments" id="order_comments" rows="3"
                                          placeholder="<?php esc_attr_e( 'Bijv. leveren aan de achterzijde, bellen bij aankomst...', 'bossier-calculator' ); ?>"><?php echo esc_textarea( $checkout->get_value( 'order_comments' ) ); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="boost-woo-form-section">
                        <h3>
                            <span class="icon">💳</span>
                            <?php esc_html_e( 'Betaalmethode', 'bossier-calculator' ); ?>
                        </h3>

                        <div id="payment" class="woocommerce-checkout-payment boost-woo-payment-fields">
                            <?php if ( WC()->cart->needs_payment() ) : ?>
                                <ul class="wc_payment_methods payment_methods methods">
                                    <?php
                                    $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
                                    if ( ! empty( $available_gateways ) ) :
                                        $first = true;
                                        foreach ( $available_gateways as $gateway ) :
                                            wc_get_template( 'checkout/payment-method.php', array(
                                                'gateway' => $gateway,
                                            ) );
                                        endforeach;
                                    else :
                                        echo '<li>';
                                        wc_print_notice( apply_filters( 'woocommerce_no_available_payment_methods_message', WC()->customer->get_billing_country() ? esc_html__( 'Sorry, it seems that there are no available payment methods for your state. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce' ) : esc_html__( 'Please fill in your details above to see available payment methods.', 'woocommerce' ) ), 'notice' );
                                        echo '</li>';
                                    endif;
                                    ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Back to Cart -->
                <div style="margin-top: 14px;">
                    <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="boost-woo-continue-link">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z"/></svg>
                        <?php esc_html_e( 'Terug naar winkelwagen', 'bossier-calculator' ); ?>
                    </a>
                </div>
            </div>

            <!-- Order Review Sidebar -->
            <div class="boost-woo-sidebar">
                <div class="boost-woo-panel">
                    <div class="boost-woo-panel-header">
                        <h3><?php esc_html_e( 'Bestelling', 'bossier-calculator' ); ?></h3>
                        <span class="boost-woo-panel-count"><?php echo esc_html( $cart_summary['item_count'] ); ?> <?php esc_html_e( 'artikelen', 'bossier-calculator' ); ?></span>
                    </div>

                    <!-- Order Items -->
                    <div class="boost-woo-order-items">
                        <?php foreach ( $order_items as $item ) : ?>
                        <div class="boost-woo-order-item">
                            <div class="boost-woo-order-item-img">
                                <?php echo wp_kses_post( $item['thumbnail'] ); ?>
                            </div>
                            <div class="boost-woo-order-item-info">
                                <div class="name"><?php echo esc_html( $item['name'] ); ?></div>
                                <div class="qty">
                                    <?php echo esc_html( $item['quantity'] ); ?>×
                                    <?php
                                    if ( ! empty( $item['specs'] ) ) {
                                        echo ' · ' . esc_html( implode( ' · ', array_slice( $item['specs'], 0, 2 ) ) );
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="boost-woo-order-item-price"><?php echo wp_kses_post( $item['price']['line_total'] ); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Summary -->
                    <div class="boost-woo-summary">
                        <div class="boost-woo-sum-row">
                            <span class="lbl"><?php esc_html_e( 'Subtotaal', 'bossier-calculator' ); ?></span>
                            <span class="val"><?php echo wp_kses_post( $cart_summary['subtotal'] ); ?></span>
                        </div>

                        <?php if ( WC()->cart->needs_shipping() ) : ?>
                        <div class="boost-woo-sum-row">
                            <span class="lbl"><?php esc_html_e( 'Verzending', 'bossier-calculator' ); ?></span>
                            <span class="val"><?php echo wp_kses_post( $cart_summary['shipping'] ); ?></span>
                        </div>
                        <?php
                        // Show shipping surcharge breakdown (e.g., oversized surcharge)
                        if ( ! empty( $cart_summary['shipping_breakdown'] ) ) :
                            foreach ( $cart_summary['shipping_breakdown'] as $breakdown_item ) :
                                if ( 'oversized' === ( $breakdown_item['type'] ?? '' ) && ! empty( $breakdown_item['cost'] ) ) :
                        ?>
                        <div class="boost-woo-sum-row boost-woo-sum-sub">
                            <span class="lbl" style="padding-left: 12px; font-size: 0.9em; color: #64748b;"><?php echo esc_html( $breakdown_item['description'] ?? __( 'Toeslag lang product', 'bossier-calculator' ) ); ?></span>
                            <span class="val" style="font-size: 0.9em; color: #64748b;"><?php echo wp_kses_post( wc_price( $breakdown_item['cost'] ) ); ?></span>
                        </div>
                        <?php
                                endif;
                            endforeach;
                        endif;
                        ?>
                        <?php endif; ?>

                        <?php
                        // Display one-time fees (e.g., malkosten, opstartkosten)
                        if ( ! empty( $cart_fees ) ) :
                            foreach ( $cart_fees as $fee ) :
                        ?>
                        <div class="boost-woo-sum-row boost-woo-sum-fee">
                            <span class="lbl">
                                <?php echo esc_html( $fee['name'] ); ?>
                                <span style="font-size: 0.85em; color: #64748b; font-weight: normal;"> (<?php esc_html_e( 'eenmalig', 'bossier-calculator' ); ?>)</span>
                            </span>
                            <span class="val"><?php echo wp_kses_post( $fee['formatted'] ); ?></span>
                        </div>
                        <?php
                            endforeach;
                        endif;
                        ?>

                        <?php if ( $cart_summary['discount_raw'] > 0 ) : ?>
                        <div class="boost-woo-sum-row discount">
                            <span class="lbl"><?php esc_html_e( 'Korting', 'bossier-calculator' ); ?></span>
                            <span class="val">-<?php echo wp_kses_post( $cart_summary['discount'] ); ?></span>
                        </div>
                        <?php endif; ?>

                        <div class="boost-woo-sum-divider"></div>

                        <div class="boost-woo-sum-row">
                            <span class="lbl"><?php esc_html_e( 'Excl. BTW', 'bossier-calculator' ); ?></span>
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

                    <!-- Place Order -->
                    <div class="boost-woo-cta-wrap">
                        <?php wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' ); ?>
                        <button type="submit" class="boost-woo-btn boost-woo-btn-blue boost-woo-btn-lg" name="woocommerce_checkout_place_order" id="place_order" value="<?php esc_attr_e( 'Bestelling plaatsen', 'bossier-calculator' ); ?>">
                            <?php esc_html_e( 'Bestelling plaatsen', 'bossier-calculator' ); ?>
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/></svg>
                        </button>
                        <div class="boost-woo-secure-note">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"/></svg>
                            <?php esc_html_e( 'Veilig afrekenen via SSL verbinding', 'bossier-calculator' ); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </form>

    <?php endif; ?>

    <?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>

</div>

<?php
get_footer( 'shop' );
