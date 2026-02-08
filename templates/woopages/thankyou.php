<?php
/**
 * Boost WooPages - Thank You Template
 *
 * This template overrides the standard WooCommerce order received page.
 *
 * @package Bossier_Calculator_Builder
 */

defined( 'ABSPATH' ) || exit;

use Bossier\Calculator\WooPages\WooPages_Helper;

// Get order ID from URL
$order_id = absint( get_query_var( 'order-received' ) );
$order    = false;

// Order ID may be stored in session after redirect
if ( ! $order_id && isset( WC()->session ) ) {
    $order_id = WC()->session->get( 'order_awaiting_payment' );
}

// Get order if ID exists
if ( $order_id ) {
    $order = wc_get_order( $order_id );
}

// Verify order key if provided
$order_key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
if ( $order && $order_key ) {
    if ( ! hash_equals( $order->get_order_key(), $order_key ) ) {
        $order = false;
    }
}

// Get shop data
$shop_data = \Bossier\Calculator\WooPages\WooPages_Loader::get_shop_header_data();

get_header( 'shop' );
?>

<!-- Page Header -->
<div class="boost-woo-page-header">
    <div class="boost-woo-wrapper">
        <div class="boost-woo-breadcrumb">
            <a href="<?php echo esc_url( home_url() ); ?>"><?php esc_html_e( 'Home', 'bossier-calculator' ); ?></a>
            <span>/</span>
            <span><?php esc_html_e( 'Bevestiging', 'bossier-calculator' ); ?></span>
        </div>
        <h1><?php esc_html_e( 'Bevestiging', 'bossier-calculator' ); ?></h1>
        <p><?php esc_html_e( 'Je bestelling is geplaatst', 'bossier-calculator' ); ?></p>
    </div>
</div>

<div class="boost-woo-wrapper" style="padding-top: 28px;">

    <!-- Steps -->
    <div class="boost-woo-steps">
        <div class="boost-woo-step done" data-step="1">
            <div class="boost-woo-step-num">✓</div>
            <div class="boost-woo-step-label"><?php esc_html_e( 'Winkelwagen', 'bossier-calculator' ); ?></div>
        </div>
        <div class="boost-woo-step done" data-step="2">
            <div class="boost-woo-step-num">✓</div>
            <div class="boost-woo-step-label"><?php esc_html_e( 'Gegevens & Betaling', 'bossier-calculator' ); ?></div>
        </div>
        <div class="boost-woo-step active" data-step="3">
            <div class="boost-woo-step-num">3</div>
            <div class="boost-woo-step-label"><?php esc_html_e( 'Bevestiging', 'bossier-calculator' ); ?></div>
        </div>
    </div>

    <?php if ( $order ) : ?>

        <?php do_action( 'woocommerce_before_thankyou', $order->get_id() ); ?>

        <?php if ( $order->has_status( 'failed' ) ) : ?>
            <!-- Order Failed -->
            <div class="boost-woo-panel" style="max-width: 640px; margin: 0 auto;">
                <div class="boost-woo-confirm-box">
                    <div class="boost-woo-confirm-icon" style="background: #FEF2F2; border-color: #FECACA; color: #DC2626;">✗</div>
                    <h2><?php esc_html_e( 'Betaling mislukt', 'bossier-calculator' ); ?></h2>
                    <p><?php esc_html_e( 'Helaas is je betaling niet gelukt. Probeer het opnieuw of kies een andere betaalmethode.', 'bossier-calculator' ); ?></p>
                    <div class="boost-woo-confirm-order-nr">
                        <?php esc_html_e( 'Bestelnummer:', 'bossier-calculator' ); ?> #<?php echo esc_html( $order->get_order_number() ); ?>
                    </div>
                    <div class="boost-woo-confirm-actions">
                        <a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="boost-woo-btn boost-woo-btn-blue" style="width: auto; padding: 10px 22px;">
                            <?php esc_html_e( 'Opnieuw betalen', 'bossier-calculator' ); ?>
                        </a>
                        <a href="<?php echo esc_url( $shop_data['shop_url'] ); ?>" class="boost-woo-btn boost-woo-btn-outline" style="width: auto; padding: 9px 22px;">
                            <?php esc_html_e( 'Terug naar winkel', 'bossier-calculator' ); ?>
                        </a>
                    </div>
                </div>
            </div>

        <?php else : ?>
            <!-- Order Success -->
            <div class="boost-woo-panel" style="max-width: 800px; margin: 0 auto;">
                <div class="boost-woo-confirm-box">
                    <div class="boost-woo-confirm-icon">✓</div>
                    <h2><?php esc_html_e( 'Bedankt voor je bestelling!', 'bossier-calculator' ); ?></h2>
                    <p><?php esc_html_e( 'Je bestelling is succesvol ontvangen. We sturen een bevestiging naar je e-mailadres.', 'bossier-calculator' ); ?></p>
                    <div class="boost-woo-confirm-order-nr">
                        <?php esc_html_e( 'Bestelnummer:', 'bossier-calculator' ); ?> #<?php echo esc_html( $order->get_order_number() ); ?>
                    </div>

                    <div class="boost-woo-confirm-actions">
                        <?php if ( is_user_logged_in() && $order->get_user_id() === get_current_user_id() ) : ?>
                            <a href="<?php echo esc_url( $order->get_view_order_url() ); ?>" class="boost-woo-btn boost-woo-btn-blue" style="width: auto; padding: 10px 22px;">
                                <?php esc_html_e( 'Bekijk bestelling', 'bossier-calculator' ); ?>
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url( $shop_data['shop_url'] ); ?>" class="boost-woo-btn boost-woo-btn-outline" style="width: auto; padding: 9px 22px;">
                            <?php esc_html_e( 'Verder winkelen', 'bossier-calculator' ); ?>
                        </a>
                    </div>

                    <!-- Order Details -->
                    <div class="boost-woo-order-details">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; text-align: left; margin-top: 32px;">
                            <!-- Order Info -->
                            <div class="boost-woo-order-details-section">
                                <h4><?php esc_html_e( 'Bestelgegevens', 'bossier-calculator' ); ?></h4>
                                <table style="width: 100%; font-size: 13.5px;">
                                    <tr>
                                        <td style="color: #5A6A7E; padding: 4px 0;"><?php esc_html_e( 'Datum:', 'bossier-calculator' ); ?></td>
                                        <td style="font-weight: 600; padding: 4px 0;"><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="color: #5A6A7E; padding: 4px 0;"><?php esc_html_e( 'E-mail:', 'bossier-calculator' ); ?></td>
                                        <td style="font-weight: 600; padding: 4px 0;"><?php echo esc_html( $order->get_billing_email() ); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="color: #5A6A7E; padding: 4px 0;"><?php esc_html_e( 'Betaalmethode:', 'bossier-calculator' ); ?></td>
                                        <td style="font-weight: 600; padding: 4px 0;"><?php echo esc_html( $order->get_payment_method_title() ); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="color: #5A6A7E; padding: 4px 0;"><?php esc_html_e( 'Totaal:', 'bossier-calculator' ); ?></td>
                                        <td style="font-weight: 600; padding: 4px 0; color: #0066FF;"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Shipping Address -->
                            <div class="boost-woo-order-details-section">
                                <h4><?php esc_html_e( 'Bezorgadres', 'bossier-calculator' ); ?></h4>
                                <div class="boost-woo-order-address">
                                    <?php
                                    $shipping_address = $order->get_formatted_shipping_address();
                                    if ( $shipping_address ) {
                                        echo wp_kses_post( $shipping_address );
                                    } else {
                                        echo wp_kses_post( $order->get_formatted_billing_address() );
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Order Items -->
                        <div class="boost-woo-order-details-section" style="margin-top: 24px;">
                            <h4><?php esc_html_e( 'Bestelde producten', 'bossier-calculator' ); ?></h4>
                            <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
                                <thead>
                                    <tr style="border-bottom: 1px solid #E2E8F0;">
                                        <th style="text-align: left; padding: 8px 0; color: #5A6A7E; font-weight: 600;"><?php esc_html_e( 'Product', 'bossier-calculator' ); ?></th>
                                        <th style="text-align: center; padding: 8px 0; color: #5A6A7E; font-weight: 600;"><?php esc_html_e( 'Aantal', 'bossier-calculator' ); ?></th>
                                        <th style="text-align: right; padding: 8px 0; color: #5A6A7E; font-weight: 600;"><?php esc_html_e( 'Totaal', 'bossier-calculator' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $order->get_items() as $item_id => $item ) :
                                        $product = $item->get_product();
                                    ?>
                                    <tr style="border-bottom: 1px solid #E2E8F0;">
                                        <td style="padding: 12px 0;">
                                            <strong><?php echo esc_html( $item->get_name() ); ?></strong>
                                            <?php
                                            // Display meta data
                                            $meta_html = wc_display_item_meta( $item, array( 'echo' => false ) );
                                            if ( $meta_html ) :
                                            ?>
                                                <div style="font-size: 11.5px; color: #5A6A7E; margin-top: 4px;">
                                                    <?php echo wp_kses_post( $meta_html ); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center; padding: 12px 0;"><?php echo esc_html( $item->get_quantity() ); ?></td>
                                        <td style="text-align: right; padding: 12px 0; font-weight: 600;"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr style="border-bottom: 1px solid #E2E8F0;">
                                        <td colspan="2" style="text-align: right; padding: 8px 0; color: #5A6A7E;"><?php esc_html_e( 'Subtotaal', 'bossier-calculator' ); ?></td>
                                        <td style="text-align: right; padding: 8px 0; font-weight: 600;"><?php echo wp_kses_post( $order->get_subtotal_to_display() ); ?></td>
                                    </tr>
                                    <?php if ( $order->get_shipping_total() > 0 || $order->get_shipping_method() ) : ?>
                                    <tr style="border-bottom: 1px solid #E2E8F0;">
                                        <td colspan="2" style="text-align: right; padding: 8px 0; color: #5A6A7E;">
                                            <?php esc_html_e( 'Verzending', 'bossier-calculator' ); ?>
                                            <?php if ( $order->get_shipping_method() ) : ?>
                                                <small>(<?php echo esc_html( $order->get_shipping_method() ); ?>)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right; padding: 8px 0; font-weight: 600;"><?php echo wp_kses_post( $order->get_shipping_to_display() ); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ( $order->get_total_discount() > 0 ) : ?>
                                    <tr style="border-bottom: 1px solid #E2E8F0;">
                                        <td colspan="2" style="text-align: right; padding: 8px 0; color: #5A6A7E;"><?php esc_html_e( 'Korting', 'bossier-calculator' ); ?></td>
                                        <td style="text-align: right; padding: 8px 0; font-weight: 600; color: #059669;">-<?php echo wp_kses_post( wc_price( $order->get_total_discount() ) ); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if ( $order->get_total_tax() > 0 ) : ?>
                                    <tr style="border-bottom: 1px solid #E2E8F0;">
                                        <td colspan="2" style="text-align: right; padding: 8px 0; color: #5A6A7E;"><?php esc_html_e( 'BTW', 'bossier-calculator' ); ?></td>
                                        <td style="text-align: right; padding: 8px 0; font-weight: 600;"><?php echo wp_kses_post( wc_price( $order->get_total_tax() ) ); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td colspan="2" style="text-align: right; padding: 12px 0; font-weight: 800; font-size: 15px;"><?php esc_html_e( 'Totaal', 'bossier-calculator' ); ?></td>
                                        <td style="text-align: right; padding: 12px 0; font-weight: 800; font-size: 17px; color: #0066FF;"><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <?php
                        // Display total weight if available
                        $total_weight = $order->get_meta( '_boost_total_weight' );
                        if ( $total_weight && floatval( $total_weight ) > 0 ) :
                        ?>
                        <div class="boost-woo-weight-row" style="margin-top: 16px;">
                            <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1.323l3.954 1.582 1.599-.8a1 1 0 01.894 1.79l-1.233.616 1.738 5.42a1 1 0 01-.285 1.05A3.989 3.989 0 0115 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.715-5.349L11 6.477V16h2a1 1 0 110 2H7a1 1 0 110-2h2V6.477L6.237 7.582l1.715 5.349a1 1 0 01-.285 1.05A3.989 3.989 0 015 15a3.989 3.989 0 01-2.667-1.019 1 1 0 01-.285-1.05l1.738-5.42-1.233-.617a1 1 0 01.894-1.788l1.599.799L9 4.323V3a1 1 0 011-1z"/></svg>
                            <span class="lbl"><?php esc_html_e( 'Totaal gewicht', 'bossier-calculator' ); ?></span>
                            <span class="val"><?php echo esc_html( WooPages_Helper::format_weight( floatval( $total_weight ) ) ); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php
                        // Check for reverse charge
                        $is_reverse_charge = $order->get_meta( '_boost_btw_reverse_charge' );
                        if ( $is_reverse_charge ) :
                            $btw_settings = \Bossier\Calculator\Modules_Settings::get_settings();
                            $reverse_charge_text = ! empty( $btw_settings['btw_custom_invoice_text'] )
                                ? $btw_settings['btw_custom_invoice_text']
                                : $btw_settings['btw_invoice_text'];
                        ?>
                        <div class="boost-woo-reverse-charge" style="margin: 16px 0 0 0;">
                            <svg viewBox="0 0 20 20" width="16" height="16" fill="currentColor"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/></svg>
                            <span><?php echo esc_html( $reverse_charge_text ); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php endif; ?>

        <?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>

    <?php else : ?>
        <!-- No order found -->
        <div class="boost-woo-panel" style="max-width: 640px; margin: 0 auto;">
            <div class="boost-woo-confirm-box">
                <div class="boost-woo-confirm-icon" style="background: #FEF3CD; border-color: #FBBF24; color: #D97706;">?</div>
                <h2><?php esc_html_e( 'Bestelling niet gevonden', 'bossier-calculator' ); ?></h2>
                <p><?php esc_html_e( 'We konden de bestelling niet vinden. Controleer je e-mail voor de bevestiging of neem contact met ons op.', 'bossier-calculator' ); ?></p>
                <div class="boost-woo-confirm-actions" style="margin-top: 24px;">
                    <a href="<?php echo esc_url( $shop_data['shop_url'] ); ?>" class="boost-woo-btn boost-woo-btn-blue" style="width: auto; padding: 10px 22px;">
                        <?php esc_html_e( 'Naar de winkel', 'bossier-calculator' ); ?>
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
get_footer( 'shop' );
