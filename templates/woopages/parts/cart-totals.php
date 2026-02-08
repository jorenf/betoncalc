<?php
/**
 * Boost WooPages - Cart Totals Partial
 *
 * This partial renders cart totals for AJAX updates.
 *
 * @package Bossier_Calculator_Builder
 */

defined( 'ABSPATH' ) || exit;

use Bossier\Calculator\WooPages\WooPages_Helper;

if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
    return;
}

$cart_summary  = WooPages_Helper::get_cart_summary();
$total_weight  = WooPages_Helper::get_cart_total_weight();
$delivery_time = WooPages_Helper::get_cart_delivery_time();
?>

<div class="boost-woo-sum-row">
    <span class="lbl"><?php printf( esc_html__( 'Subtotaal (%d artikelen)', 'bossier-calculator' ), $cart_summary['item_count'] ); ?></span>
    <span class="val"><?php echo wp_kses_post( $cart_summary['subtotal'] ); ?></span>
</div>

<?php if ( WC()->cart->needs_shipping() ) : ?>
<div class="boost-woo-sum-row">
    <span class="lbl"><?php esc_html_e( 'Verzendkosten', 'bossier-calculator' ); ?></span>
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

<?php if ( $delivery_time ) : ?>
<div class="boost-woo-weight-row">
    <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
    <span class="lbl"><?php esc_html_e( 'Levertijd', 'bossier-calculator' ); ?></span>
    <span class="val"><?php echo esc_html( $delivery_time['text'] ); ?></span>
</div>
<?php endif; ?>
