<?php
/**
 * Calculator settings meta box view.
 *
 * @package Bossier_Calculator_Builder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Variables available:
 *
 * @var \Bossier\Calculator\Calculator $calculator Calculator instance.
 * @var array                          $settings   Calculator settings.
 */

$currency_symbol = get_woocommerce_currency_symbol();
$weight_unit     = get_option( 'woocommerce_weight_unit', 'kg' );
?>

<div class="bossier-calculator-settings">
    <p>
        <label for="bossier_base_price">
            <?php esc_html_e( 'Base Price', 'bossier-calculator' ); ?>
        </label>
        <input type="number"
               id="bossier_base_price"
               name="bossier_settings[base_price]"
               value="<?php echo esc_attr( $settings['base_price'] ); ?>"
               step="any"
               min="0"
               class="widefat">
        <span class="description"><?php echo esc_html( $currency_symbol ); ?></span>
    </p>

    <p>
        <label for="bossier_base_weight">
            <?php esc_html_e( 'Base Weight', 'bossier-calculator' ); ?>
        </label>
        <input type="number"
               id="bossier_base_weight"
               name="bossier_settings[base_weight]"
               value="<?php echo esc_attr( $settings['base_weight'] ); ?>"
               step="any"
               min="0"
               class="widefat">
        <span class="description"><?php echo esc_html( $weight_unit ); ?></span>
    </p>

    <p>
        <label for="bossier_price_decimals">
            <?php esc_html_e( 'Price Decimal Places', 'bossier-calculator' ); ?>
        </label>
        <input type="number"
               id="bossier_price_decimals"
               name="bossier_settings[price_decimals]"
               value="<?php echo esc_attr( $settings['price_decimals'] ); ?>"
               min="0"
               max="6"
               step="1"
               class="widefat">
    </p>

    <p>
        <label for="bossier_weight_decimals">
            <?php esc_html_e( 'Weight Decimal Places', 'bossier-calculator' ); ?>
        </label>
        <input type="number"
               id="bossier_weight_decimals"
               name="bossier_settings[weight_decimals]"
               value="<?php echo esc_attr( $settings['weight_decimals'] ); ?>"
               min="0"
               max="6"
               step="1"
               class="widefat">
    </p>

    <p>
        <label for="bossier_price_label">
            <?php esc_html_e( 'Price Label (optional)', 'bossier-calculator' ); ?>
        </label>
        <input type="text"
               id="bossier_price_label"
               name="bossier_settings[price_label]"
               value="<?php echo esc_attr( $settings['price_label'] ); ?>"
               class="widefat"
               placeholder="<?php esc_attr_e( 'Calculated Price', 'bossier-calculator' ); ?>">
    </p>

    <p>
        <label for="bossier_weight_label">
            <?php esc_html_e( 'Weight Label (optional)', 'bossier-calculator' ); ?>
        </label>
        <input type="text"
               id="bossier_weight_label"
               name="bossier_settings[weight_label]"
               value="<?php echo esc_attr( $settings['weight_label'] ); ?>"
               class="widefat"
               placeholder="<?php esc_attr_e( 'Calculated Weight', 'bossier-calculator' ); ?>">
    </p>

    <p>
        <label>
            <input type="checkbox"
                   name="bossier_settings[show_preview]"
                   value="1"
                   <?php checked( $settings['show_preview'] ); ?>>
            <?php esc_html_e( 'Show live price preview', 'bossier-calculator' ); ?>
        </label>
    </p>
</div>
