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

// Ensure defaults for settings.
$enable_long_surcharge    = isset( $settings['enable_long_surcharge'] ) ? $settings['enable_long_surcharge'] : false;
$long_surcharge_threshold = isset( $settings['long_surcharge_threshold'] ) ? $settings['long_surcharge_threshold'] : 1500;
$long_surcharge_per_mm    = isset( $settings['long_surcharge_per_mm'] ) ? $settings['long_surcharge_per_mm'] : 0;

$enable_nonstandard_surcharge = ! empty( $settings['enable_nonstandard_surcharge'] );
$nonstandard_surcharge_amount = isset( $settings['nonstandard_surcharge_amount'] ) ? $settings['nonstandard_surcharge_amount'] : 0;

// Pricing mode settings.
$pricing_mode                = isset( $settings['pricing_mode'] ) ? $settings['pricing_mode'] : 'standard';
$dimensional_unit_price      = isset( $settings['dimensional_unit_price'] ) ? $settings['dimensional_unit_price'] : 0;
$dimensional_weight_per_unit = isset( $settings['dimensional_weight_per_unit'] ) ? $settings['dimensional_weight_per_unit'] : 0;
?>

<div class="bossier-calculator-settings">
    <h4><?php esc_html_e( 'Prijsberekenings Modus', 'bossier-calculator' ); ?></h4>
    <p class="description" style="margin-bottom: 15px; padding: 12px; background: #f0f6fc; border-radius: 5px; border-left: 4px solid #2271b1;">
        <strong><?php esc_html_e( 'Kies hoe de prijs wordt berekend:', 'bossier-calculator' ); ?></strong><br>
        <?php esc_html_e( 'Standaard: prijs gebaseerd op lengte (raamdorpels e.d.). Dimensionaal: prijs gebaseerd op L×B×H (muurafdekkers e.d.).', 'bossier-calculator' ); ?>
    </p>

    <p>
        <label for="bossier_pricing_mode">
            <?php esc_html_e( 'Modus', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Standaard: lineaire berekening op basis van lengte + WooCommerce basisprijs. Dimensionaal: berekening op basis van alle dimensie-velden (lengte × breedte × hoogte) × eenheidsprijs.', 'bossier-calculator' ); ?>">?</span>
        </label>
        <select id="bossier_pricing_mode"
                name="bossier_settings[pricing_mode]"
                class="widefat">
            <option value="standard" <?php selected( $pricing_mode, 'standard' ); ?>>
                <?php esc_html_e( 'Standaard (lengte-gebaseerd)', 'bossier-calculator' ); ?>
            </option>
            <option value="dimensional" <?php selected( $pricing_mode, 'dimensional' ); ?>>
                <?php esc_html_e( 'Dimensionaal (L×B×H)', 'bossier-calculator' ); ?>
            </option>
        </select>
    </p>

    <!-- Dimensional pricing settings (only visible when pricing_mode = dimensional) -->
    <div id="bossier-dimensional-settings" style="<?php echo 'dimensional' !== $pricing_mode ? 'display: none;' : ''; ?>">
        <p class="description" style="margin-bottom: 15px; padding: 12px; background: #fcf9e8; border-radius: 5px; border-left: 4px solid #dba617;">
            <strong><?php esc_html_e( 'Dimensionaal:', 'bossier-calculator' ); ?></strong><br>
            <?php esc_html_e( 'Voeg meerdere lengte-velden toe (bijv. Lengte, Breedte, Hoogte). De prijs wordt berekend als: alle dimensies vermenigvuldigd × eenheidsprijs. De WooCommerce productprijs wordt niet gebruikt.', 'bossier-calculator' ); ?>
        </p>

        <p>
            <label for="bossier_dimensional_unit_price">
                <?php esc_html_e( 'Prijs per eenheid', 'bossier-calculator' ); ?>
                <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Prijs per eenheid volume/oppervlakte. Bijv. bij 3 dimensies in mm is dit de prijs per mm³. Voorbeeld: 0,000001 betekent €1 per 1.000.000 mm³ (= 1 dm³).', 'bossier-calculator' ); ?>">?</span>
            </label>
            <input type="number"
                   id="bossier_dimensional_unit_price"
                   name="bossier_settings[dimensional_unit_price]"
                   value="<?php echo esc_attr( $dimensional_unit_price ); ?>"
                   step="any"
                   min="0"
                   class="widefat">
            <span class="description"><?php echo esc_html( $currency_symbol ); ?> <?php esc_html_e( 'per eenheid (product van alle dimensies)', 'bossier-calculator' ); ?></span>
        </p>

        <p>
            <label for="bossier_dimensional_weight_per_unit">
                <?php esc_html_e( 'Gewicht per eenheid', 'bossier-calculator' ); ?>
                <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Gewicht per eenheid volume/oppervlakte. Gebruikt voor verzendberekeningen.', 'bossier-calculator' ); ?>">?</span>
            </label>
            <input type="number"
                   id="bossier_dimensional_weight_per_unit"
                   name="bossier_settings[dimensional_weight_per_unit]"
                   value="<?php echo esc_attr( $dimensional_weight_per_unit ); ?>"
                   step="any"
                   min="0"
                   class="widefat">
            <span class="description"><?php echo esc_html( $weight_unit ); ?> <?php esc_html_e( 'per eenheid', 'bossier-calculator' ); ?></span>
        </p>
    </div>

    <hr>
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

    <!-- Standard pricing settings (only visible when pricing_mode = standard) -->
    <div id="bossier-standard-settings" style="<?php echo 'dimensional' === $pricing_mode ? 'display: none;' : ''; ?>">
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
    </div><!-- /#bossier-standard-settings -->

    <hr>
    <h4><?php esc_html_e( 'Niet-Standaard Lengte Toeslag', 'bossier-calculator' ); ?></h4>
    <p class="description" style="margin-bottom: 15px; padding: 12px; background: #fef3c7; border-radius: 5px; border-left: 4px solid #f59e0b;">
        <strong><?php esc_html_e( 'Vaste toeslag:', 'bossier-calculator' ); ?></strong><br>
        <?php esc_html_e( 'Voegt een vast bedrag toe als de ingevoerde maat afwijkt van de standaard (mm). Zichtbaar voor klanten in de prijsopbouw.', 'bossier-calculator' ); ?>
    </p>

    <p>
        <label>
            <input type="checkbox"
                   id="bossier_enable_nonstandard_surcharge"
                   name="bossier_settings[enable_nonstandard_surcharge]"
                   value="1"
                   <?php checked( $enable_nonstandard_surcharge ); ?>>
            <?php esc_html_e( 'Activeer niet-standaard lengte toeslag', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Voegt een vast bedrag toe wanneer de ingevoerde maat kleiner of groter is dan de standaard (mm) waarde.', 'bossier-calculator' ); ?>">?</span>
        </label>
    </p>

    <div class="bossier-nonstandard-surcharge-settings" style="<?php echo ! $enable_nonstandard_surcharge ? 'opacity: 0.5;' : ''; ?>">
        <p>
            <label for="bossier_nonstandard_surcharge_amount">
                <?php esc_html_e( 'Toeslag bedrag', 'bossier-calculator' ); ?>
                <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Vast bedrag dat wordt toegevoegd als de ingevoerde maat afwijkt van de standaard (mm).', 'bossier-calculator' ); ?>">?</span>
            </label>
            <input type="number"
                   id="bossier_nonstandard_surcharge_amount"
                   name="bossier_settings[nonstandard_surcharge_amount]"
                   value="<?php echo esc_attr( $nonstandard_surcharge_amount ); ?>"
                   step="any"
                   min="0"
                   class="widefat"
                   <?php echo ! $enable_nonstandard_surcharge ? 'disabled' : ''; ?>>
            <span class="description"><?php echo esc_html( $currency_symbol ); ?> <?php esc_html_e( 'vast bedrag', 'bossier-calculator' ); ?></span>
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
    // Long surcharge toggle
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
    $('#bossier_enable_nonstandard_surcharge').on('change', function() {
        var $settings = $('.bossier-nonstandard-surcharge-settings');
        var $inputs = $settings.find('input');
        if ($(this).is(':checked')) {
            $settings.css('opacity', '1');
            $inputs.prop('disabled', false);
        } else {
            $settings.css('opacity', '0.5');
            $inputs.prop('disabled', true);
        }
    });

    // Pricing mode toggle
    $('#bossier_pricing_mode').on('change', function() {
        var mode = $(this).val();
        if (mode === 'dimensional') {
            $('#bossier-standard-settings').hide();
            $('#bossier-dimensional-settings').show();
        } else {
            $('#bossier-standard-settings').show();
            $('#bossier-dimensional-settings').hide();
        }
    });
});
</script>
