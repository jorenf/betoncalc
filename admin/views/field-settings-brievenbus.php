<?php
/**
 * Brievenbus (mailbox) field settings view.
 *
 * Nested yes/no questions with optional text inputs and surcharges.
 * Flow: Huisnummer Ja/Nee → (if Ja) text input + Toevoeging Ja/Nee → (if Ja) text input
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

$currency_symbol = get_woocommerce_currency_symbol();

$main_label       = isset( $field['main_label'] ) ? $field['main_label'] : __( 'Huisnummer', 'bossier-calculator' );
$main_surcharge   = isset( $field['main_surcharge'] ) ? $field['main_surcharge'] : 0;
$main_placeholder = isset( $field['main_placeholder'] ) ? $field['main_placeholder'] : __( 'Voer huisnummer in', 'bossier-calculator' );

$sub_label       = isset( $field['sub_label'] ) ? $field['sub_label'] : __( 'Toevoeging', 'bossier-calculator' );
$sub_surcharge   = isset( $field['sub_surcharge'] ) ? $field['sub_surcharge'] : 0;
$sub_placeholder = isset( $field['sub_placeholder'] ) ? $field['sub_placeholder'] : __( 'Voer toevoeging in', 'bossier-calculator' );
?>

<div class="bossier-field-section">
    <h4><?php esc_html_e( 'Brievenbus Configuratie', 'bossier-calculator' ); ?></h4>
    <p class="description" style="margin-bottom: 15px; padding: 12px; background: #f0f5ff; border-radius: 5px; border-left: 4px solid #0066ff;">
        <?php esc_html_e( 'Dit veld toont een geneste configuratie: eerst wordt gevraagd of de klant een huisnummer wil (Ja/Nee). Bij Ja verschijnt een tekstveld en de vraag of er een toevoeging is. Bij Ja op toevoeging verschijnt nog een tekstveld.', 'bossier-calculator' ); ?>
    </p>

    <table class="bossier-settings-table" style="width: 100%; border-collapse: separate; border-spacing: 0 8px;">
        <!-- Main option: Huisnummer -->
        <tr>
            <td colspan="2" style="padding: 10px; background: #f9f9f9; border-radius: 5px;">
                <strong><?php esc_html_e( 'Hoofdvraag', 'bossier-calculator' ); ?></strong>
            </td>
        </tr>
        <tr>
            <td style="width: 180px;">
                <label for="<?php echo esc_attr( $prefix ); ?>_main_label">
                    <?php esc_html_e( 'Label', 'bossier-calculator' ); ?>
                </label>
            </td>
            <td>
                <input type="text"
                       id="<?php echo esc_attr( $prefix ); ?>_main_label"
                       name="<?php echo esc_attr( $prefix ); ?>[main_label]"
                       value="<?php echo esc_attr( $main_label ); ?>"
                       class="regular-text"
                       placeholder="<?php esc_attr_e( 'Huisnummer', 'bossier-calculator' ); ?>">
            </td>
        </tr>
        <tr>
            <td>
                <label for="<?php echo esc_attr( $prefix ); ?>_main_surcharge">
                    <?php esc_html_e( 'Toeslag bij Ja', 'bossier-calculator' ); ?>
                </label>
            </td>
            <td>
                <input type="number"
                       id="<?php echo esc_attr( $prefix ); ?>_main_surcharge"
                       name="<?php echo esc_attr( $prefix ); ?>[main_surcharge]"
                       value="<?php echo esc_attr( $main_surcharge ); ?>"
                       step="any"
                       min="0"
                       class="small-text">
                <span class="description"><?php echo esc_html( $currency_symbol ); ?></span>
            </td>
        </tr>
        <tr>
            <td>
                <label for="<?php echo esc_attr( $prefix ); ?>_main_placeholder">
                    <?php esc_html_e( 'Placeholder tekstveld', 'bossier-calculator' ); ?>
                </label>
            </td>
            <td>
                <input type="text"
                       id="<?php echo esc_attr( $prefix ); ?>_main_placeholder"
                       name="<?php echo esc_attr( $prefix ); ?>[main_placeholder]"
                       value="<?php echo esc_attr( $main_placeholder ); ?>"
                       class="regular-text"
                       placeholder="<?php esc_attr_e( 'Voer huisnummer in', 'bossier-calculator' ); ?>">
            </td>
        </tr>

        <!-- Sub option: Toevoeging -->
        <tr>
            <td colspan="2" style="padding: 10px; background: #f9f9f9; border-radius: 5px;">
                <strong><?php esc_html_e( 'Subvraag (verschijnt alleen bij Ja op hoofdvraag)', 'bossier-calculator' ); ?></strong>
            </td>
        </tr>
        <tr>
            <td>
                <label for="<?php echo esc_attr( $prefix ); ?>_sub_label">
                    <?php esc_html_e( 'Label', 'bossier-calculator' ); ?>
                </label>
            </td>
            <td>
                <input type="text"
                       id="<?php echo esc_attr( $prefix ); ?>_sub_label"
                       name="<?php echo esc_attr( $prefix ); ?>[sub_label]"
                       value="<?php echo esc_attr( $sub_label ); ?>"
                       class="regular-text"
                       placeholder="<?php esc_attr_e( 'Toevoeging', 'bossier-calculator' ); ?>">
            </td>
        </tr>
        <tr>
            <td>
                <label for="<?php echo esc_attr( $prefix ); ?>_sub_surcharge">
                    <?php esc_html_e( 'Extra toeslag bij Ja', 'bossier-calculator' ); ?>
                </label>
            </td>
            <td>
                <input type="number"
                       id="<?php echo esc_attr( $prefix ); ?>_sub_surcharge"
                       name="<?php echo esc_attr( $prefix ); ?>[sub_surcharge]"
                       value="<?php echo esc_attr( $sub_surcharge ); ?>"
                       step="any"
                       min="0"
                       class="small-text">
                <span class="description"><?php echo esc_html( $currency_symbol ); ?> <?php esc_html_e( '(bovenop hoofdvraag toeslag)', 'bossier-calculator' ); ?></span>
            </td>
        </tr>
        <tr>
            <td>
                <label for="<?php echo esc_attr( $prefix ); ?>_sub_placeholder">
                    <?php esc_html_e( 'Placeholder tekstveld', 'bossier-calculator' ); ?>
                </label>
            </td>
            <td>
                <input type="text"
                       id="<?php echo esc_attr( $prefix ); ?>_sub_placeholder"
                       name="<?php echo esc_attr( $prefix ); ?>[sub_placeholder]"
                       value="<?php echo esc_attr( $sub_placeholder ); ?>"
                       class="regular-text"
                       placeholder="<?php esc_attr_e( 'Voer toevoeging in', 'bossier-calculator' ); ?>">
            </td>
        </tr>
    </table>
</div>
