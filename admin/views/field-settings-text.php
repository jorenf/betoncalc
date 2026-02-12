<?php
/**
 * Text field settings view.
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

$placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
$max_chars   = isset( $field['max_chars'] ) ? $field['max_chars'] : 50;
?>

<div class="bossier-field-section">
    <h4><?php esc_html_e( 'Tekst Instellingen', 'bossier-calculator' ); ?></h4>

    <div class="bossier-field-row">
        <label>
            <?php esc_html_e( 'Placeholder Tekst', 'bossier-calculator' ); ?>
            <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Voorbeeld tekst die in het invoerveld wordt getoond voordat de klant iets typt.', 'bossier-calculator' ); ?>">?</span>
            <input type="text"
                   name="<?php echo esc_attr( $prefix ); ?>[placeholder]"
                   value="<?php echo esc_attr( $placeholder ); ?>"
                   class="widefat"
                   placeholder="<?php esc_attr_e( 'Bijv. Voer uw huisnummer in', 'bossier-calculator' ); ?>">
        </label>
    </div>

    <div class="bossier-field-row">
        <label>
            <?php esc_html_e( 'Maximaal Aantal Tekens', 'bossier-calculator' ); ?>
            <input type="number"
                   name="<?php echo esc_attr( $prefix ); ?>[max_chars]"
                   value="<?php echo esc_attr( $max_chars ); ?>"
                   min="1"
                   max="500"
                   step="1"
                   class="small-text">
        </label>
    </div>

    <p class="description">
        <?php esc_html_e( 'Dit veld is puur informatief en heeft geen invloed op de prijs of het gewicht. De ingevoerde tekst wordt opgeslagen bij de bestelling.', 'bossier-calculator' ); ?>
    </p>
</div>
