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

// Ensure defaults for new settings.
$min_length               = isset( $settings['min_length'] ) ? $settings['min_length'] : 1000;
$price_per_mm             = isset( $settings['price_per_mm'] ) ? $settings['price_per_mm'] : 0;
$base_weight_per_mm       = isset( $settings['base_weight_per_mm'] ) ? $settings['base_weight_per_mm'] : 0;
$enable_long_surcharge    = isset( $settings['enable_long_surcharge'] ) ? $settings['enable_long_surcharge'] : false;
$long_surcharge_threshold = isset( $settings['long_surcharge_threshold'] ) ? $settings['long_surcharge_threshold'] : 1500;
$long_surcharge_per_mm    = isset( $settings['long_surcharge_per_mm'] ) ? $settings['long_surcharge_per_mm'] : 0;
?>

<div class="bossier-calculator-settings">
    <h4><?php esc_html_e( 'Display Settings', 'bossier-calculator' ); ?></h4>

    <p>
        <label for="bossier_price_decimals">
            <?php esc_html_e( 'Price Decimal Places', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Number of decimals to show for prices. Use 2 for most currencies (e.g., €12.50).', 'bossier-calculator' ); ?>">?</span>
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
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Number of decimals to show for weight. Use 3 for precise calculations (e.g., 2.500 kg).', 'bossier-calculator' ); ?>">?</span>
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
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Custom label for the calculated price display. Leave empty for default.', 'bossier-calculator' ); ?>">?</span>
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
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Custom label for the calculated weight display. Leave empty for default.', 'bossier-calculator' ); ?>">?</span>
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
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'When enabled, customers see the calculated price update live as they change options.', 'bossier-calculator' ); ?>">?</span>
        </label>
    </p>

    <hr>
    <h4><?php esc_html_e( 'Length Pricing', 'bossier-calculator' ); ?></h4>
    <p class="description" style="margin-bottom: 15px; padding: 12px; background: #f0f6fc; border-radius: 5px; border-left: 4px solid #2271b1;">
        <strong><?php esc_html_e( 'How it works:', 'bossier-calculator' ); ?></strong><br>
        <?php esc_html_e( 'The product base price (set in WooCommerce) covers the minimum length. Any length above the minimum is charged extra per mm.', 'bossier-calculator' ); ?>
    </p>

    <p>
        <label for="bossier_min_length">
            <?php esc_html_e( 'Minimum Length (mm)', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'The length that is included in the product base price. E.g., if set to 1000mm, a product priced at €50 covers lengths up to 1000mm.', 'bossier-calculator' ); ?>">?</span>
        </label>
        <input type="number"
               id="bossier_min_length"
               name="bossier_settings[min_length]"
               value="<?php echo esc_attr( $min_length ); ?>"
               step="1"
               min="0"
               class="widefat">
        <span class="description"><?php esc_html_e( 'Length included in product base price', 'bossier-calculator' ); ?></span>
    </p>

    <p>
        <label for="bossier_price_per_mm">
            <?php esc_html_e( 'Price per mm (extra length)', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Price charged for each mm above the minimum length. E.g., 0.05 means €0.05 per extra mm.', 'bossier-calculator' ); ?>">?</span>
        </label>
        <input type="number"
               id="bossier_price_per_mm"
               name="bossier_settings[price_per_mm]"
               value="<?php echo esc_attr( $price_per_mm ); ?>"
               step="any"
               min="0"
               class="widefat">
        <span class="description"><?php echo esc_html( $currency_symbol ); ?> <?php esc_html_e( 'per mm above minimum', 'bossier-calculator' ); ?></span>
    </p>

    <p>
        <label for="bossier_base_weight_per_mm">
            <?php esc_html_e( 'Weight per mm', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Weight per mm of length. Used for shipping calculations. E.g., 0.001 means 1 gram per mm.', 'bossier-calculator' ); ?>">?</span>
        </label>
        <input type="number"
               id="bossier_base_weight_per_mm"
               name="bossier_settings[base_weight_per_mm]"
               value="<?php echo esc_attr( $base_weight_per_mm ); ?>"
               step="any"
               min="0"
               class="widefat">
        <span class="description"><?php echo esc_html( $weight_unit ); ?> <?php esc_html_e( 'per mm', 'bossier-calculator' ); ?></span>
    </p>

    <hr>
    <h4><?php esc_html_e( 'Long Length Surcharge', 'bossier-calculator' ); ?></h4>
    <p class="description" style="margin-bottom: 15px; padding: 12px; background: #fcf0f1; border-radius: 5px; border-left: 4px solid #d63638;">
        <strong><?php esc_html_e( 'Hidden surcharge:', 'bossier-calculator' ); ?></strong><br>
        <?php esc_html_e( 'This extra charge applies to long items but is NOT shown to customers. It will be visible in admin order details.', 'bossier-calculator' ); ?>
    </p>

    <p>
        <label>
            <input type="checkbox"
                   id="bossier_enable_long_surcharge"
                   name="bossier_settings[enable_long_surcharge]"
                   value="1"
                   <?php checked( $enable_long_surcharge ); ?>>
            <?php esc_html_e( 'Enable long length surcharge', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Enable extra handling fee for items longer than the threshold. This surcharge is hidden from customers.', 'bossier-calculator' ); ?>">?</span>
        </label>
    </p>

    <div class="bossier-long-surcharge-settings" style="<?php echo ! $enable_long_surcharge ? 'opacity: 0.5;' : ''; ?>">
        <p>
            <label for="bossier_long_surcharge_threshold">
                <?php esc_html_e( 'Length Threshold (mm)', 'bossier-calculator' ); ?>
                <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Items longer than this will get the extra surcharge. E.g., 1500mm means items over 1.5 meters.', 'bossier-calculator' ); ?>">?</span>
            </label>
            <input type="number"
                   id="bossier_long_surcharge_threshold"
                   name="bossier_settings[long_surcharge_threshold]"
                   value="<?php echo esc_attr( $long_surcharge_threshold ); ?>"
                   step="1"
                   min="0"
                   class="widefat"
                   <?php echo ! $enable_long_surcharge ? 'disabled' : ''; ?>>
            <span class="description"><?php esc_html_e( 'Surcharge applies above this length', 'bossier-calculator' ); ?></span>
        </p>

        <p>
            <label for="bossier_long_surcharge_per_mm">
                <?php esc_html_e( 'Surcharge per mm', 'bossier-calculator' ); ?>
                <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Extra charge per mm above the threshold. E.g., 0.02 means €0.02 per mm above threshold.', 'bossier-calculator' ); ?>">?</span>
            </label>
            <input type="number"
                   id="bossier_long_surcharge_per_mm"
                   name="bossier_settings[long_surcharge_per_mm]"
                   value="<?php echo esc_attr( $long_surcharge_per_mm ); ?>"
                   step="any"
                   min="0"
                   class="widefat"
                   <?php echo ! $enable_long_surcharge ? 'disabled' : ''; ?>>
            <span class="description"><?php echo esc_html( $currency_symbol ); ?> <?php esc_html_e( 'per mm above threshold', 'bossier-calculator' ); ?></span>
        </p>
    </div>

    <hr>
    <h4><?php esc_html_e( 'Legacy Settings', 'bossier-calculator' ); ?></h4>
    <p class="description" style="margin-bottom: 15px; padding: 12px; background: #f9f9f9; border-radius: 5px;">
        <?php esc_html_e( 'These values are added on top of calculated values. Usually you can leave these at 0.', 'bossier-calculator' ); ?>
    </p>

    <p>
        <label for="bossier_base_price">
            <?php esc_html_e( 'Additional Base Price', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Extra fixed amount added to every calculation. Use for handling fees or other fixed costs.', 'bossier-calculator' ); ?>">?</span>
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
            <?php esc_html_e( 'Additional Base Weight', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Extra fixed weight added to every calculation. Use for packaging weight.', 'bossier-calculator' ); ?>">?</span>
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
</div>

<script>
jQuery(function($) {
    $('#bossier_enable_long_surcharge').on('change', function() {
        var $settings = $('.bossier-long-surcharge-settings');
        var $inputs = $settings.find('input');
        if ($(this).is(':checked')) {
            $settings.css('opacity', '1');
            $inputs.prop('disabled', false);
        } else {
            $settings.css('opacity', '0.5');
            $inputs.prop('disabled', true);
        }
    });
});
</script>
