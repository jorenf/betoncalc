<?php
/**
 * Boost WooPages - Cart Items Partial
 *
 * This partial renders cart items for AJAX updates.
 *
 * @package Bossier_Calculator_Builder
 */

defined( 'ABSPATH' ) || exit;

use Bossier\Calculator\WooPages\WooPages_Helper;

if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) || WC()->cart->is_empty() ) {
    return;
}

$cart_summary = WooPages_Helper::get_cart_summary();

?>
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
