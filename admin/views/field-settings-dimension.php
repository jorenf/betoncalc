<?php
/**
 * Dimension field settings view.
 *
 * @package Bossier_Calculator_Builder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Variables available:
 *
 * @var array  $field  Field configuration data.
 * @var string $prefix Form field name prefix.
 */

$dimension_kind = isset( $field['dimension_kind'] ) ? $field['dimension_kind'] : 'length';
$min_value      = isset( $field['min_value'] ) ? $field['min_value'] : 100;
$max_value      = isset( $field['max_value'] ) ? $field['max_value'] : 5000;
$default_value  = isset( $field['default_value'] ) ? $field['default_value'] : '';
$step_size      = isset( $field['step_size'] ) ? $field['step_size'] : 1;
$price_per_mm   = isset( $field['price_per_mm'] ) ? $field['price_per_mm'] : 0;
$threshold      = isset( $field['threshold'] ) ? $field['threshold'] : 0;
$weight_per_mm  = isset( $field['weight_per_mm'] ) ? $field['weight_per_mm'] : 0;
$unit_type      = isset( $field['unit_type'] ) ? $field['unit_type'] : 'mm';
?>

<div class="bossier-field-section">
    <h4><?php esc_html_e( 'Dimensie Instellingen', 'bossier-calculator' ); ?></h4>

    <div class="bossier-field-row">
        <label>
            <?php esc_html_e( 'Type Dimensie', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Semantisch type van deze dimensie. Dit bepaalt het label en wordt gebruikt voor de verzendbere­kening.', 'bossier-calculator' ); ?>">?</span>
            <select name="<?php echo esc_attr( $prefix ); ?>[dimension_kind]">
                <option value="length" <?php selected( $dimension_kind, 'length' ); ?>><?php esc_html_e( 'Lengte', 'bossier-calculator' ); ?></option>
                <option value="width" <?php selected( $dimension_kind, 'width' ); ?>><?php esc_html_e( 'Breedte', 'bossier-calculator' ); ?></option>
                <option value="height" <?php selected( $dimension_kind, 'height' ); ?>><?php esc_html_e( 'Hoogte', 'bossier-calculator' ); ?></option>
            </select>
        </label>
    </div>

    <div class="bossier-field-row bossier-field-row-inline">
        <label>
            <?php esc_html_e( 'Minimum (mm)', 'bossier-calculator' ); ?>
            <input type="number"
                   name="<?php echo esc_attr( $prefix ); ?>[min_value]"
                   value="<?php echo esc_attr( $min_value ); ?>"
                   min="0"
                   step="1"
                   class="small-text">
        </label>
        <label>
            <?php esc_html_e( 'Maximum (mm)', 'bossier-calculator' ); ?>
            <input type="number"
                   name="<?php echo esc_attr( $prefix ); ?>[max_value]"
                   value="<?php echo esc_attr( $max_value ); ?>"
                   min="0"
                   step="1"
                   class="small-text">
        </label>
        <label>
            <?php esc_html_e( 'Standaard (mm)', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Laat leeg om het minimum als standaardwaarde te gebruiken.', 'bossier-calculator' ); ?>">?</span>
            <input type="number"
                   name="<?php echo esc_attr( $prefix ); ?>[default_value]"
                   value="<?php echo esc_attr( $default_value ); ?>"
                   min="0"
                   step="1"
                   class="small-text"
                   placeholder="<?php echo esc_attr( $min_value ); ?>">
        </label>
        <label>
            <?php esc_html_e( 'Stap', 'bossier-calculator' ); ?>
            <input type="number"
                   name="<?php echo esc_attr( $prefix ); ?>[step_size]"
                   value="<?php echo esc_attr( $step_size ); ?>"
                   min="1"
                   step="1"
                   class="small-text">
        </label>
    </div>

    <h4><?php esc_html_e( 'Prijs & Gewicht', 'bossier-calculator' ); ?></h4>

    <div class="bossier-field-row bossier-field-row-inline">
        <label>
            <?php esc_html_e( 'Prijs per mm', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Extra prijs per mm boven de drempel. Als drempel 0 is, wordt over de gehele waarde berekend.', 'bossier-calculator' ); ?>">?</span>
            <input type="number"
                   name="<?php echo esc_attr( $prefix ); ?>[price_per_mm]"
                   value="<?php echo esc_attr( $price_per_mm ); ?>"
                   min="0"
                   step="0.0001"
                   class="small-text">
        </label>
        <label>
            <?php esc_html_e( 'Prijs drempel (mm)', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Waarden tot deze drempel zijn inbegrepen in de basisprijs. Zet op 0 om prijs over de gehele waarde te berekenen.', 'bossier-calculator' ); ?>">?</span>
            <input type="number"
                   name="<?php echo esc_attr( $prefix ); ?>[threshold]"
                   value="<?php echo esc_attr( $threshold ); ?>"
                   min="0"
                   step="1"
                   class="small-text">
        </label>
        <label>
            <?php esc_html_e( 'Gewicht per mm', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Gewicht per mm voor verzendberekening.', 'bossier-calculator' ); ?>">?</span>
            <input type="number"
                   name="<?php echo esc_attr( $prefix ); ?>[weight_per_mm]"
                   value="<?php echo esc_attr( $weight_per_mm ); ?>"
                   min="0"
                   step="0.0001"
                   class="small-text">
        </label>
    </div>

    <input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[unit_type]" value="<?php echo esc_attr( $unit_type ); ?>">

    <p class="description">
        <?php esc_html_e( 'Formule: extra prijs = max(0, geselecteerde waarde - drempel) x prijs per mm', 'bossier-calculator' ); ?>
    </p>
</div>
