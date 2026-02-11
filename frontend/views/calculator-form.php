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

$price_label  = ! empty( $settings['price_label'] ) ? $settings['price_label'] : __( 'Berekende prijs', 'bossier-calculator' );
$weight_label = ! empty( $settings['weight_label'] ) ? $settings['weight_label'] : __( 'Berekend gewicht', 'bossier-calculator' );

// Separate quantity field from regular fields
$quantity_field_id   = null;
$quantity_field_data = null;
$regular_fields      = array();

foreach ( $fields as $field_id => $field ) {
    // Skip deprecated length fields
    if ( 'length' === ( $field['type'] ?? '' ) ) {
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
$full_width_types = array( 'color', 'mitre_angle', 'custom' );
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

    <?php if ( ! empty( $settings['show_preview'] ) ) : ?>
        <div class="bs-calc__divider"></div>

        <div class="bs-calc__result">
            <div class="bs-calc__result-row">
                <span class="bs-calc__result-label"><?php echo esc_html( $price_label ); ?>:</span>
                <span class="bs-calc__result-value" id="bossier-calc-price">
                    <?php echo wp_kses_post( wc_price( $settings['base_price'] ) ); ?>
                </span>
            </div>
            <div class="bs-calc__result-row">
                <span class="bs-calc__result-label"><?php echo esc_html( $weight_label ); ?>:</span>
                <span class="bs-calc__result-value bs-calc__result-value--sub" id="bossier-calc-weight">
                    <?php echo esc_html( wc_format_localized_decimal( $settings['base_weight'] ) . ' ' . $weight_unit ); ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ( $quantity_field_id ) : ?>
        <div class="bs-calc__actions">
            <?php Display::render_field( $quantity_field_id, $quantity_field_data ); ?>
        </div>
    <?php endif; ?>

    <!-- Hidden fields for cart -->
    <input type="hidden" name="bossier_calculator_id" value="<?php echo esc_attr( $calculator->get_id() ); ?>">
    <input type="hidden" name="bossier_calculated_price" id="bossier_calculated_price" value="<?php echo esc_attr( $settings['base_price'] ); ?>">
    <input type="hidden" name="bossier_calculated_weight" id="bossier_calculated_weight" value="<?php echo esc_attr( $settings['base_weight'] ); ?>">
</div>
