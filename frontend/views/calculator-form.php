<?php
/**
 * Calculator form template — bs-calc BEM layout.
 *
 * @package Bossier_Calculator_Builder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Variables available:
 *
 * @var \Bossier\Calculator\Calculator $calculator Calculator instance.
 * @var array                          $fields     Enabled calculator fields.
 * @var array                          $settings   Calculator settings.
 */

use Bossier\Calculator\Frontend\Display;

$currency_symbol = get_woocommerce_currency_symbol();
$weight_unit     = get_option( 'woocommerce_weight_unit', 'kg' );

// If calculator base_weight is 0, fall back to WooCommerce product weight
global $product;
$initial_weight = floatval( $settings['base_weight'] );
if ( $initial_weight <= 0 && $product && $product->get_weight() ) {
    $initial_weight = floatval( $product->get_weight() );
}

$price_label  = ! empty( $settings['price_label'] ) ? $settings['price_label'] : __( 'Berekende prijs', 'bossier-calculator' );
$weight_label = ! empty( $settings['weight_label'] ) ? $settings['weight_label'] : __( 'Berekend gewicht', 'bossier-calculator' );

// Weight calculation is enabled by default if not explicitly disabled
$enable_weight_calculation = ! isset( $settings['enable_weight_calculation'] ) || ! empty( $settings['enable_weight_calculation'] );

// Separate quantity field from regular fields
$quantity_field_id   = null;
$quantity_field_data = null;
$regular_fields      = array();

$pricing_mode = isset( $settings['pricing_mode'] ) ? $settings['pricing_mode'] : 'standard';

foreach ( $fields as $field_id => $field ) {
    // In standard mode, skip length fields (dimension fields handle length).
    // In dimensional mode, render length fields as dimension inputs (Lengte, Breedte, Hoogte).
    if ( 'length' === ( $field['type'] ?? '' ) && 'standard' === $pricing_mode ) {
        continue;
    }

    if ( 'quantity' === ( $field['type'] ?? '' ) ) {
        $quantity_field_id   = $field_id;
        $quantity_field_data = $field;
    } else {
        $regular_fields[ $field_id ] = $field;
    }
}

// Types that should get a full-width row
$full_width_types = array( 'color', 'mitre_angle', 'custom', 'brievenbus' );
?>

<div class="bs-calc" id="bossier-calculator-<?php echo esc_attr( $calculator->get_id() ); ?>" data-calculator-id="<?php echo esc_attr( $calculator->get_id() ); ?>">

    <?php
    // Group fields into rows: dimension/text get paired (2-per-row), others full-width
    $row_buffer = array();

    foreach ( $regular_fields as $field_id => $field ) {
        $type = isset( $field['type'] ) ? $field['type'] : '';

        if ( in_array( $type, $full_width_types, true ) ) {
            // Flush any buffered half-width fields first
            if ( ! empty( $row_buffer ) ) {
                echo '<div class="bs-calc__row">';
                foreach ( $row_buffer as $buf ) {
                    Display::render_field( $buf[0], $buf[1] );
                }
                echo '</div>';
                $row_buffer = array();
            }

            // Full-width row
            echo '<div class="bs-calc__row bs-calc__row--full">';
            Display::render_field( $field_id, $field );
            echo '</div>';
        } else {
            // Half-width fields (dimension, text, etc.)
            $row_buffer[] = array( $field_id, $field );

            if ( count( $row_buffer ) === 2 ) {
                echo '<div class="bs-calc__row">';
                foreach ( $row_buffer as $buf ) {
                    Display::render_field( $buf[0], $buf[1] );
                }
                echo '</div>';
                $row_buffer = array();
            }
        }
    }

    // Flush remaining buffered fields
    if ( ! empty( $row_buffer ) ) {
        echo '<div class="bs-calc__row">';
        foreach ( $row_buffer as $buf ) {
            Display::render_field( $buf[0], $buf[1] );
        }
        echo '</div>';
    }
    ?>

    <?php
    // Prepare one-time product fee settings before the preview section
    $enable_product_fee = ! empty( $settings['enable_product_fee'] );
    $product_fee_amount = floatval( $settings['product_fee_amount'] ?? 0 );
    $product_fee_label  = ! empty( $settings['product_fee_label'] )
        ? $settings['product_fee_label']
        : __( 'Eenmalige productkosten', 'bossier-calculator' );
    ?>

    <?php if ( ! empty( $settings['show_preview'] ) ) : ?>
        <div class="bs-calc__divider"></div>

        <div class="bs-calc__result">
            <div class="bs-calc__result-row">
                <span class="bs-calc__result-label"><?php echo esc_html( $price_label ); ?>:</span>
                <span class="bs-calc__result-value" id="bossier-calc-price">
                    <?php echo wp_kses_post( wc_price( $settings['base_price'] ) ); ?>
                </span>
            </div>
            <?php if ( $enable_product_fee && $product_fee_amount > 0 ) : ?>
            <div class="bs-calc__result-row bs-calc__result-row--fee">
                <span class="bs-calc__result-label"><?php echo esc_html( $product_fee_label ); ?>:</span>
                <span class="bs-calc__result-value bs-calc__result-value--fee">
                    <?php echo wp_kses_post( wc_price( $product_fee_amount ) ); ?>
                </span>
            </div>
            <?php endif; ?>
            <?php if ( $enable_product_fee && $product_fee_amount > 0 ) : ?>
            <div class="bs-calc__result-row bs-calc__result-row--total">
                <span class="bs-calc__result-label"><?php esc_html_e( 'Totaalprijs', 'bossier-calculator' ); ?>:</span>
                <span class="bs-calc__result-value bs-calc__result-value--total" id="bossier-calc-total-price">
                    <?php echo wp_kses_post( wc_price( $settings['base_price'] + $product_fee_amount ) ); ?>
                </span>
            </div>
            <?php endif; ?>
            <?php if ( $enable_weight_calculation ) : ?>
            <div class="bs-calc__result-row">
                <span class="bs-calc__result-label"><?php echo esc_html( $weight_label ); ?>:</span>
                <span class="bs-calc__result-value bs-calc__result-value--sub" id="bossier-calc-weight">
                    <?php echo esc_html( wc_format_localized_decimal( $initial_weight ) . ' ' . $weight_unit ); ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php
    // Delivery time notice
    global $product;
    $delivery_status = $product ? get_post_meta( $product->get_id(), '_boost_delivery_status', true ) : '';
    $delivery_weeks  = $product ? get_post_meta( $product->get_id(), '_boost_delivery_weeks', true ) : '';
    if ( empty( $delivery_status ) ) {
        $delivery_status = 'in_stock';
    }
    ?>

    <?php if ( $product && '1' === get_post_meta( $product->get_id(), '_boost_show_sample_link', true ) ) : ?>
        <a href="<?php echo esc_url( home_url( '/product/proefdorpel/' ) ); ?>" class="bs-calc__sample">
            <span class="bs-calc__sample-icon">&#x1F4CF;</span>
            <span class="bs-calc__sample-text">
                <strong><?php esc_html_e( 'Twijfel je over de maat?', 'bossier-calculator' ); ?></strong>
                <?php esc_html_e( 'Bestel eerst een proefdorpel', 'bossier-calculator' ); ?> &rarr;
            </span>
        </a>
    <?php endif; ?>

    <div class="bs-calc__notice">
        <span class="bs-calc__notice-icon">&#128336;</span>
        <?php if ( 'in_stock' === $delivery_status ) : ?>
            <?php esc_html_e( 'Op voorraad — snel geleverd.', 'bossier-calculator' ); ?>
        <?php else : ?>
            <?php printf( esc_html__( 'Productietijd momenteel %s weken.', 'bossier-calculator' ), esc_html( $delivery_weeks ? $delivery_weeks : '2-3' ) ); ?>
        <?php endif; ?>
    </div>

    <?php if ( $quantity_field_id ) : ?>
        <div class="bs-calc__actions">
            <?php Display::render_field( $quantity_field_id, $quantity_field_data ); ?>
        </div>
    <?php endif; ?>

    <!-- Hidden fields for cart -->
    <input type="hidden" name="bossier_calculator_id" value="<?php echo esc_attr( $calculator->get_id() ); ?>">
    <input type="hidden" name="bossier_calculated_price" id="bossier_calculated_price" value="<?php echo esc_attr( $settings['base_price'] ); ?>">
    <input type="hidden" name="bossier_calculated_weight" id="bossier_calculated_weight" value="<?php echo esc_attr( $initial_weight ); ?>">
</div>
