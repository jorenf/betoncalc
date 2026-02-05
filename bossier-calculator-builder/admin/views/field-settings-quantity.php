<?php
/**
 * Quantity field settings view.
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

$min_qty  = isset( $field['min_qty'] ) ? $field['min_qty'] : 1;
$max_qty  = isset( $field['max_qty'] ) ? $field['max_qty'] : 100;
$step_qty = isset( $field['step_qty'] ) ? $field['step_qty'] : 1;
?>

<div class="bossier-field-section">
    <h4><?php esc_html_e( 'Aantal Instellingen', 'bossier-calculator' ); ?></h4>

    <div class="bossier-field-row bossier-field-row-inline">
        <label>
            <?php esc_html_e( 'Minimum Aantal', 'bossier-calculator' ); ?>
            <input type="number"
                   name="<?php echo esc_attr( $prefix ); ?>[min_qty]"
                   value="<?php echo esc_attr( $min_qty ); ?>"
                   min="1"
                   step="1"
                   class="small-text">
        </label>
        <label>
            <?php esc_html_e( 'Maximum Aantal', 'bossier-calculator' ); ?>
            <input type="number"
                   name="<?php echo esc_attr( $prefix ); ?>[max_qty]"
                   value="<?php echo esc_attr( $max_qty ); ?>"
                   min="1"
                   step="1"
                   class="small-text">
        </label>
        <label>
            <?php esc_html_e( 'Stap', 'bossier-calculator' ); ?>
            <input type="number"
                   name="<?php echo esc_attr( $prefix ); ?>[step_qty]"
                   value="<?php echo esc_attr( $step_qty ); ?>"
                   min="1"
                   step="1"
                   class="small-text">
        </label>
    </div>

    <p class="description">
        <?php esc_html_e( 'Let op: Het aantal veld vermenigvuldigt zowel prijs als gewicht. De basisberekening is voor 1 eenheid.', 'bossier-calculator' ); ?>
    </p>
</div>
