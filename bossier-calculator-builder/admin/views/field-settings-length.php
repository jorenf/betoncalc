<?php
/**
 * Length field settings view.
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

$length_mode     = isset( $field['length_mode'] ) ? $field['length_mode'] : 'free';
$min_value       = isset( $field['min_value'] ) ? $field['min_value'] : 100;
$max_value       = isset( $field['max_value'] ) ? $field['max_value'] : 5000;
$step_size       = isset( $field['step_size'] ) ? $field['step_size'] : 1;
$price_per_unit  = isset( $field['price_per_unit'] ) ? $field['price_per_unit'] : 0;
$weight_per_unit = isset( $field['weight_per_unit'] ) ? $field['weight_per_unit'] : 0;
$unit_type       = isset( $field['unit_type'] ) ? $field['unit_type'] : 'mm';
$fixed_options   = isset( $field['fixed_options'] ) ? $field['fixed_options'] : array();

$length_units = \Bossier\Calculator\Field_Types::get_length_units();
?>

<div class="bossier-field-section">
    <h4><?php esc_html_e( 'Lengte Instellingen', 'bossier-calculator' ); ?></h4>

    <div class="bossier-field-row">
        <label>
            <?php esc_html_e( 'Invoer Modus', 'bossier-calculator' ); ?>
            <select name="<?php echo esc_attr( $prefix ); ?>[length_mode]" class="bossier-length-mode-select">
                <option value="free" <?php selected( $length_mode, 'free' ); ?>>
                    <?php esc_html_e( 'Vrije Invoer', 'bossier-calculator' ); ?>
                </option>
                <option value="fixed" <?php selected( $length_mode, 'fixed' ); ?>>
                    <?php esc_html_e( 'Vaste Opties', 'bossier-calculator' ); ?>
                </option>
            </select>
        </label>
    </div>

    <div class="bossier-field-row">
        <label>
            <?php esc_html_e( 'Eenheid Type', 'bossier-calculator' ); ?>
            <select name="<?php echo esc_attr( $prefix ); ?>[unit_type]">
                <?php foreach ( $length_units as $unit_key => $unit_info ) : ?>
                    <option value="<?php echo esc_attr( $unit_key ); ?>" <?php selected( $unit_type, $unit_key ); ?>>
                        <?php echo esc_html( $unit_info['label'] ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>

    <!-- Free Input Mode Settings -->
    <div class="bossier-length-mode-settings bossier-length-mode-free" <?php echo 'free' !== $length_mode ? 'style="display:none;"' : ''; ?>>
        <div class="bossier-field-row bossier-field-row-inline">
            <label>
                <?php esc_html_e( 'Min Waarde', 'bossier-calculator' ); ?>
                <input type="number"
                       name="<?php echo esc_attr( $prefix ); ?>[min_value]"
                       value="<?php echo esc_attr( $min_value ); ?>"
                       step="any"
                       class="small-text">
            </label>
            <label>
                <?php esc_html_e( 'Max Waarde', 'bossier-calculator' ); ?>
                <input type="number"
                       name="<?php echo esc_attr( $prefix ); ?>[max_value]"
                       value="<?php echo esc_attr( $max_value ); ?>"
                       step="any"
                       class="small-text">
            </label>
            <label>
                <?php esc_html_e( 'Stap Grootte', 'bossier-calculator' ); ?>
                <input type="number"
                       name="<?php echo esc_attr( $prefix ); ?>[step_size]"
                       value="<?php echo esc_attr( $step_size ); ?>"
                       step="any"
                       class="small-text">
            </label>
        </div>

        <div class="bossier-field-row bossier-field-row-inline">
            <label>
                <?php esc_html_e( 'Prijs per eenheid', 'bossier-calculator' ); ?>
                <input type="number"
                       name="<?php echo esc_attr( $prefix ); ?>[price_per_unit]"
                       value="<?php echo esc_attr( $price_per_unit ); ?>"
                       step="any"
                       class="small-text">
                <span class="description"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
            </label>
            <label>
                <?php esc_html_e( 'Gewicht per eenheid', 'bossier-calculator' ); ?>
                <input type="number"
                       name="<?php echo esc_attr( $prefix ); ?>[weight_per_unit]"
                       value="<?php echo esc_attr( $weight_per_unit ); ?>"
                       step="any"
                       class="small-text">
                <span class="description"><?php echo esc_html( get_option( 'woocommerce_weight_unit', 'kg' ) ); ?></span>
            </label>
        </div>
    </div>

    <!-- Fixed Options Mode Settings -->
    <div class="bossier-length-mode-settings bossier-length-mode-fixed" <?php echo 'fixed' !== $length_mode ? 'style="display:none;"' : ''; ?>>
        <div class="bossier-options-list bossier-length-options-list">
            <table class="bossier-options-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Waarde', 'bossier-calculator' ); ?></th>
                        <th><?php esc_html_e( 'Label', 'bossier-calculator' ); ?></th>
                        <th><?php esc_html_e( 'Prijs', 'bossier-calculator' ); ?></th>
                        <th><?php esc_html_e( 'Gewicht', 'bossier-calculator' ); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ( ! empty( $fixed_options ) ) :
                        foreach ( $fixed_options as $idx => $option ) :
                            ?>
                            <tr class="bossier-option-row">
                                <td>
                                    <input type="number"
                                           name="<?php echo esc_attr( $prefix ); ?>[fixed_options][<?php echo esc_attr( $idx ); ?>][value]"
                                           value="<?php echo esc_attr( $option['value'] ); ?>"
                                           step="any"
                                           class="small-text">
                                </td>
                                <td>
                                    <input type="text"
                                           name="<?php echo esc_attr( $prefix ); ?>[fixed_options][<?php echo esc_attr( $idx ); ?>][label]"
                                           value="<?php echo esc_attr( $option['label'] ); ?>"
                                           class="regular-text">
                                </td>
                                <td>
                                    <input type="number"
                                           name="<?php echo esc_attr( $prefix ); ?>[fixed_options][<?php echo esc_attr( $idx ); ?>][price]"
                                           value="<?php echo esc_attr( $option['price'] ); ?>"
                                           step="any"
                                           class="small-text">
                                </td>
                                <td>
                                    <input type="number"
                                           name="<?php echo esc_attr( $prefix ); ?>[fixed_options][<?php echo esc_attr( $idx ); ?>][weight]"
                                           value="<?php echo esc_attr( $option['weight'] ); ?>"
                                           step="any"
                                           class="small-text">
                                </td>
                                <td>
                                    <button type="button" class="button bossier-remove-option">
                                        <span class="dashicons dashicons-no-alt"></span>
                                    </button>
                                </td>
                            </tr>
                            <?php
                        endforeach;
                    endif;
                    ?>
                </tbody>
            </table>
            <button type="button" class="button bossier-add-length-option" data-prefix="<?php echo esc_attr( $prefix ); ?>">
                <?php esc_html_e( 'Optie Toevoegen', 'bossier-calculator' ); ?>
            </button>
        </div>
    </div>
</div>
