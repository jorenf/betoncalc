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

$cart_summary = WooPages_Helper::get_cart_summary();
$total_weight = WooPages_Helper::get_cart_total_weight();
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
