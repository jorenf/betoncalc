<?php
/**
 * Calculator form template.
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

$price_label  = ! empty( $settings['price_label'] ) ? $settings['price_label'] : __( 'Berekende Prijs', 'bossier-calculator' );
$weight_label = ! empty( $settings['weight_label'] ) ? $settings['weight_label'] : __( 'Berekend Gewicht', 'bossier-calculator' );

// Get length field settings from sidebar
$min_length = isset( $settings['min_length'] ) ? floatval( $settings['min_length'] ) : 1000;
$max_length = isset( $settings['max_length'] ) ? floatval( $settings['max_length'] ) : 5000;
?>

<div class="bossier-calculator-wrap" id="bossier-calculator-<?php echo esc_attr( $calculator->get_id() ); ?>" data-calculator-id="<?php echo esc_attr( $calculator->get_id() ); ?>">

    <div class="bossier-calculator-fields">
        <!-- Core Length Field (always rendered) -->
        <div class="bossier-calc-field bossier-calc-field-length bossier-calc-input-number bossier-calc-required" data-field-id="length" data-field-type="length">
            <label class="bossier-calc-label">
                <?php esc_html_e( 'Lengte', 'bossier-calculator' ); ?>
                <span class="required">*</span>
                <span class="bossier-calc-tooltip" title="<?php esc_attr_e( 'Voer de gewenste lengte in millimeters in of gebruik de slider.', 'bossier-calculator' ); ?>"><span class="bossier-calc-tooltip-icon">?</span></span>
            </label>
            <div class="bossier-calc-input-wrap">
                <div class="bossier-calc-length-input-group">
                    <div class="bossier-calc-number-input">
                        <input type="number"
                               name="bossier_calc_length"
                               id="bossier_calc_length"
                               min="<?php echo esc_attr( $min_length ); ?>"
                               max="<?php echo esc_attr( $max_length ); ?>"
                               step="1"
                               value="<?php echo esc_attr( $min_length ); ?>"
                               class="bossier-calc-input"
                               required>
                        <span class="bossier-calc-unit">mm</span>
                    </div>
                    <div class="bossier-calc-slider-wrap">
                        <input type="range"
                               id="bossier_calc_length_slider"
                               min="<?php echo esc_attr( $min_length ); ?>"
                               max="<?php echo esc_attr( $max_length ); ?>"
                               step="1"
                               value="<?php echo esc_attr( $min_length ); ?>"
                               class="bossier-calc-slider">
                        <div class="bossier-calc-slider-labels">
                            <span><?php echo esc_html( number_format( $min_length, 0, ',', '.' ) ); ?> mm</span>
                            <span><?php echo esc_html( number_format( $max_length, 0, ',', '.' ) ); ?> mm</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php
        // Render other fields (excluding any legacy length fields)
        foreach ( $fields as $field_id => $field ) {
            // Skip deprecated length fields - length is now handled above
            if ( 'length' === ( $field['type'] ?? '' ) ) {
                continue;
            }
            Display::render_field( $field_id, $field );
        }
        ?>
    </div>

    <?php if ( ! empty( $settings['show_preview'] ) ) : ?>
        <div class="bossier-calculator-summary">
            <div class="bossier-calc-result bossier-calc-price-result">
                <span class="bossier-calc-result-label"><?php echo esc_html( $price_label ); ?>:</span>
                <span class="bossier-calc-result-value" id="bossier-calc-price">
                    <?php echo wp_kses_post( wc_price( $settings['base_price'] ) ); ?>
                </span>
            </div>
            <div class="bossier-calc-result bossier-calc-weight-result">
                <span class="bossier-calc-result-label"><?php echo esc_html( $weight_label ); ?>:</span>
                <span class="bossier-calc-result-value" id="bossier-calc-weight">
                    <?php echo esc_html( wc_format_localized_decimal( $settings['base_weight'] ) . ' ' . $weight_unit ); ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Hidden fields for cart -->
    <input type="hidden" name="bossier_calculator_id" value="<?php echo esc_attr( $calculator->get_id() ); ?>">
    <input type="hidden" name="bossier_calculated_price" id="bossier_calculated_price" value="<?php echo esc_attr( $settings['base_price'] ); ?>">
    <input type="hidden" name="bossier_calculated_weight" id="bossier_calculated_weight" value="<?php echo esc_attr( $settings['base_weight'] ); ?>">
</div>
