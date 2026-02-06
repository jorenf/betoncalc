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
$min_length_input         = isset( $settings['min_length_input'] ) ? $settings['min_length_input'] : 100;
$min_length               = isset( $settings['min_length'] ) ? $settings['min_length'] : 1000;
$max_length               = isset( $settings['max_length'] ) ? $settings['max_length'] : 5000;
$price_per_mm             = isset( $settings['price_per_mm'] ) ? $settings['price_per_mm'] : 0;
$base_weight_per_mm       = isset( $settings['base_weight_per_mm'] ) ? $settings['base_weight_per_mm'] : 0;
$enable_long_surcharge    = isset( $settings['enable_long_surcharge'] ) ? $settings['enable_long_surcharge'] : false;
$long_surcharge_threshold = isset( $settings['long_surcharge_threshold'] ) ? $settings['long_surcharge_threshold'] : 1500;
$long_surcharge_per_mm    = isset( $settings['long_surcharge_per_mm'] ) ? $settings['long_surcharge_per_mm'] : 0;
?>

<div class="bossier-calculator-settings">
    <h4><?php esc_html_e( 'Weergave Instellingen', 'bossier-calculator' ); ?></h4>

    <p>
        <label for="bossier_price_decimals">
            <?php esc_html_e( 'Prijs Decimalen', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Aantal decimalen voor prijzen. Gebruik 2 voor de meeste valuta (bijv. €12,50).', 'bossier-calculator' ); ?>">?</span>
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
            <?php esc_html_e( 'Gewicht Decimalen', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Aantal decimalen voor gewicht. Gebruik 3 voor nauwkeurige berekeningen (bijv. 2,500 kg).', 'bossier-calculator' ); ?>">?</span>
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
            <?php esc_html_e( 'Prijs Label (optioneel)', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Aangepast label voor de berekende prijs weergave. Laat leeg voor standaard.', 'bossier-calculator' ); ?>">?</span>
        </label>
        <input type="text"
               id="bossier_price_label"
               name="bossier_settings[price_label]"
               value="<?php echo esc_attr( $settings['price_label'] ); ?>"
               class="widefat"
               placeholder="<?php esc_attr_e( 'Berekende Prijs', 'bossier-calculator' ); ?>">
    </p>

    <p>
        <label for="bossier_weight_label">
            <?php esc_html_e( 'Gewicht Label (optioneel)', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Aangepast label voor het berekende gewicht weergave. Laat leeg voor standaard.', 'bossier-calculator' ); ?>">?</span>
        </label>
        <input type="text"
               id="bossier_weight_label"
               name="bossier_settings[weight_label]"
               value="<?php echo esc_attr( $settings['weight_label'] ); ?>"
               class="widefat"
               placeholder="<?php esc_attr_e( 'Berekend Gewicht', 'bossier-calculator' ); ?>">
    </p>

    <p>
        <label>
            <input type="checkbox"
                   name="bossier_settings[show_preview]"
                   value="1"
                   <?php checked( $settings['show_preview'] ); ?>>
            <?php esc_html_e( 'Toon live prijs preview', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Wanneer ingeschakeld, zien klanten de berekende prijs live updaten terwijl ze opties wijzigen.', 'bossier-calculator' ); ?>">?</span>
        </label>
    </p>

    <hr>
    <h4><?php esc_html_e( 'Lengte Prijzen', 'bossier-calculator' ); ?></h4>
    <p class="description" style="margin-bottom: 15px; padding: 12px; background: #f0f6fc; border-radius: 5px; border-left: 4px solid #2271b1;">
        <strong><?php esc_html_e( 'Hoe het werkt:', 'bossier-calculator' ); ?></strong><br>
        <?php esc_html_e( 'Alle lengtes tot de prijs drempel (standaard 1000mm) hebben dezelfde vaste basisprijs. Pas daarboven wordt extra per mm berekend.', 'bossier-calculator' ); ?>
    </p>

    <p>
        <label for="bossier_min_length_input">
            <?php esc_html_e( 'Minimum Selecteerbare Lengte (mm)', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'De kleinste lengte die klanten kunnen selecteren. Bijv. 100mm als minimum.', 'bossier-calculator' ); ?>">?</span>
        </label>
        <input type="number"
               id="bossier_min_length_input"
               name="bossier_settings[min_length_input]"
               value="<?php echo esc_attr( $min_length_input ); ?>"
               step="1"
               min="0"
               class="widefat">
        <span class="description"><?php esc_html_e( 'Minimum lengte die klanten kunnen kiezen', 'bossier-calculator' ); ?></span>
    </p>

    <p>
        <label for="bossier_min_length">
            <?php esc_html_e( 'Prijs Drempel (mm)', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Tot deze lengte geldt de vaste basisprijs. Bijv. bij 1000mm: alle lengtes van 0-1000mm hebben dezelfde prijs, daarboven wordt per mm extra berekend.', 'bossier-calculator' ); ?>">?</span>
        </label>
        <input type="number"
               id="bossier_min_length"
               name="bossier_settings[min_length]"
               value="<?php echo esc_attr( $min_length ); ?>"
               step="1"
               min="0"
               class="widefat">
        <span class="description"><?php esc_html_e( 'Vaste prijs tot deze lengte (0-1000mm = zelfde prijs)', 'bossier-calculator' ); ?></span>
    </p>

    <p>
        <label for="bossier_max_length">
            <?php esc_html_e( 'Maximum Lengte (mm)', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'De maximale lengte die klanten kunnen selecteren. Bijv. 5000mm betekent maximaal 5 meter.', 'bossier-calculator' ); ?>">?</span>
        </label>
        <input type="number"
               id="bossier_max_length"
               name="bossier_settings[max_length]"
               value="<?php echo esc_attr( $max_length ); ?>"
               step="1"
               min="1"
               class="widefat">
        <span class="description"><?php esc_html_e( 'Maximale lengte die klanten kunnen bestellen', 'bossier-calculator' ); ?></span>
    </p>

    <p>
        <label for="bossier_price_per_mm">
            <?php esc_html_e( 'Prijs per mm (extra lengte)', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Prijs per mm boven de minimum lengte. Bijv. 0,05 betekent €0,05 per extra mm.', 'bossier-calculator' ); ?>">?</span>
        </label>
        <input type="number"
               id="bossier_price_per_mm"
               name="bossier_settings[price_per_mm]"
               value="<?php echo esc_attr( $price_per_mm ); ?>"
               step="any"
               min="0"
               class="widefat">
        <span class="description"><?php echo esc_html( $currency_symbol ); ?> <?php esc_html_e( 'per mm boven minimum', 'bossier-calculator' ); ?></span>
    </p>

    <p>
        <label for="bossier_base_weight_per_mm">
            <?php esc_html_e( 'Gewicht per mm', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Gewicht per mm lengte. Gebruikt voor verzendberekeningen. Bijv. 0,001 betekent 1 gram per mm.', 'bossier-calculator' ); ?>">?</span>
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
    <h4><?php esc_html_e( 'Lange Lengte Toeslag', 'bossier-calculator' ); ?></h4>
    <p class="description" style="margin-bottom: 15px; padding: 12px; background: #fcf0f1; border-radius: 5px; border-left: 4px solid #d63638;">
        <strong><?php esc_html_e( 'Verborgen toeslag:', 'bossier-calculator' ); ?></strong><br>
        <?php esc_html_e( 'Deze extra toeslag geldt voor lange items maar wordt NIET getoond aan klanten. Het is wel zichtbaar in de admin besteldetails.', 'bossier-calculator' ); ?>
    </p>

    <p>
        <label>
            <input type="checkbox"
                   id="bossier_enable_long_surcharge"
                   name="bossier_settings[enable_long_surcharge]"
                   value="1"
                   <?php checked( $enable_long_surcharge ); ?>>
            <?php esc_html_e( 'Activeer lange lengte toeslag', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Activeer extra handling kosten voor items langer dan de drempel. Deze toeslag is verborgen voor klanten.', 'bossier-calculator' ); ?>">?</span>
        </label>
    </p>

    <div class="bossier-long-surcharge-settings" style="<?php echo ! $enable_long_surcharge ? 'opacity: 0.5;' : ''; ?>">
        <p>
            <label for="bossier_long_surcharge_threshold">
                <?php esc_html_e( 'Lengte Drempel (mm)', 'bossier-calculator' ); ?>
                <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Items langer dan dit krijgen de extra toeslag. Bijv. 1500mm betekent items boven 1,5 meter.', 'bossier-calculator' ); ?>">?</span>
            </label>
            <input type="number"
                   id="bossier_long_surcharge_threshold"
                   name="bossier_settings[long_surcharge_threshold]"
                   value="<?php echo esc_attr( $long_surcharge_threshold ); ?>"
                   step="1"
                   min="0"
                   class="widefat"
                   <?php echo ! $enable_long_surcharge ? 'disabled' : ''; ?>>
            <span class="description"><?php esc_html_e( 'Toeslag geldt boven deze lengte', 'bossier-calculator' ); ?></span>
        </p>

        <p>
            <label for="bossier_long_surcharge_per_mm">
                <?php esc_html_e( 'Toeslag per mm', 'bossier-calculator' ); ?>
                <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Extra toeslag per mm boven de drempel. Bijv. 0,02 betekent €0,02 per mm boven de drempel.', 'bossier-calculator' ); ?>">?</span>
            </label>
            <input type="number"
                   id="bossier_long_surcharge_per_mm"
                   name="bossier_settings[long_surcharge_per_mm]"
                   value="<?php echo esc_attr( $long_surcharge_per_mm ); ?>"
                   step="any"
                   min="0"
                   class="widefat"
                   <?php echo ! $enable_long_surcharge ? 'disabled' : ''; ?>>
            <span class="description"><?php echo esc_html( $currency_symbol ); ?> <?php esc_html_e( 'per mm boven drempel', 'bossier-calculator' ); ?></span>
        </p>
    </div>

    <hr>
    <h4><?php esc_html_e( 'Extra Instellingen', 'bossier-calculator' ); ?></h4>
    <p class="description" style="margin-bottom: 15px; padding: 12px; background: #f9f9f9; border-radius: 5px;">
        <?php esc_html_e( 'Deze waarden worden toegevoegd aan berekende waarden. Normaal kunt u deze op 0 laten.', 'bossier-calculator' ); ?>
    </p>

    <p>
        <label for="bossier_base_price">
            <?php esc_html_e( 'Extra Basisprijs', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Extra vast bedrag toegevoegd aan elke berekening. Gebruik voor handling kosten of andere vaste kosten.', 'bossier-calculator' ); ?>">?</span>
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
            <?php esc_html_e( 'Extra Basisgewicht', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Extra vast gewicht toegevoegd aan elke berekening. Gebruik voor verpakkingsgewicht.', 'bossier-calculator' ); ?>">?</span>
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
