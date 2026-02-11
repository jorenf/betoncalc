<?php
/**
 * Custom field settings view.
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

$custom_options = isset( $field['custom_options'] ) ? $field['custom_options'] : array();
?>

<div class="bossier-field-section">
    <h4><?php esc_html_e( 'Aangepaste Veld Opties', 'bossier-calculator' ); ?></h4>

    <div class="bossier-options-list bossier-custom-options-list">
        <table class="bossier-options-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Label', 'bossier-calculator' ); ?></th>
                    <th><?php esc_html_e( 'Waarde', 'bossier-calculator' ); ?></th>
                    <th><?php esc_html_e( 'Prijs Toeslag', 'bossier-calculator' ); ?></th>
                    <th><?php esc_html_e( 'Extra Gewicht', 'bossier-calculator' ); ?></th>
                    <th><?php esc_html_e( 'Tekstveld', 'bossier-calculator' ); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ( ! empty( $custom_options ) ) :
                    foreach ( $custom_options as $idx => $option ) :
                        ?>
                        <tr class="bossier-option-row">
                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr( $prefix ); ?>[custom_options][<?php echo esc_attr( $idx ); ?>][label]"
                                       value="<?php echo esc_attr( $option['label'] ); ?>"
                                       class="regular-text">
                            </td>
                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr( $prefix ); ?>[custom_options][<?php echo esc_attr( $idx ); ?>][value]"
                                       value="<?php echo esc_attr( $option['value'] ); ?>"
                                       class="regular-text">
                            </td>
                            <td>
                                <input type="number"
                                       name="<?php echo esc_attr( $prefix ); ?>[custom_options][<?php echo esc_attr( $idx ); ?>][surcharge]"
                                       value="<?php echo esc_attr( $option['surcharge'] ); ?>"
                                       step="any"
                                       class="small-text">
                                <span class="description"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
                            </td>
                            <td>
                                <input type="number"
                                       name="<?php echo esc_attr( $prefix ); ?>[custom_options][<?php echo esc_attr( $idx ); ?>][extra_weight]"
                                       value="<?php echo esc_attr( $option['extra_weight'] ); ?>"
                                       step="any"
                                       class="small-text">
                                <span class="description"><?php echo esc_html( get_option( 'woocommerce_weight_unit', 'kg' ) ); ?></span>
                            </td>
                            <td>
                                <label style="white-space: nowrap;">
                                    <input type="checkbox"
                                           name="<?php echo esc_attr( $prefix ); ?>[custom_options][<?php echo esc_attr( $idx ); ?>][has_text_input]"
                                           value="1"
                                           <?php checked( ! empty( $option['has_text_input'] ) ); ?>>
                                    <?php esc_html_e( 'Ja', 'bossier-calculator' ); ?>
                                </label>
                                <input type="text"
                                       name="<?php echo esc_attr( $prefix ); ?>[custom_options][<?php echo esc_attr( $idx ); ?>][text_placeholder]"
                                       value="<?php echo esc_attr( $option['text_placeholder'] ?? '' ); ?>"
                                       placeholder="<?php esc_attr_e( 'Placeholder...', 'bossier-calculator' ); ?>"
                                       class="small-text"
                                       style="width: 120px; margin-top: 4px; <?php echo empty( $option['has_text_input'] ) ? 'display:none;' : ''; ?>">
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
        <button type="button" class="button bossier-add-custom-option" data-prefix="<?php echo esc_attr( $prefix ); ?>">
            <?php esc_html_e( 'Optie Toevoegen', 'bossier-calculator' ); ?>
        </button>
    </div>
</div>
