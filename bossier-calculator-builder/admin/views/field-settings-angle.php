<?php
/**
 * Mitre angle field settings view.
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

$angles          = isset( $field['angles'] ) ? $field['angles'] : array();
$currency_symbol = get_woocommerce_currency_symbol();
$weight_unit     = get_option( 'woocommerce_weight_unit', 'kg' );
?>

<div class="bossier-field-section">
    <h4><?php esc_html_e( 'Mitre Angle Options (Verstekhoek)', 'bossier-calculator' ); ?></h4>

    <p class="description" style="margin-bottom: 15px;">
        <?php esc_html_e( 'Configure mitre angle options with optional images to help customers visualize each cut type.', 'bossier-calculator' ); ?>
    </p>

    <div class="bossier-options-list bossier-angle-options-list">
        <table class="bossier-options-table bossier-angle-options-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Label', 'bossier-calculator' ); ?></th>
                    <th><?php esc_html_e( 'Image', 'bossier-calculator' ); ?></th>
                    <th><?php esc_html_e( 'Price', 'bossier-calculator' ); ?></th>
                    <th><?php esc_html_e( 'Weight', 'bossier-calculator' ); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ( ! empty( $angles ) ) :
                    foreach ( $angles as $idx => $angle ) :
                        $image = isset( $angle['image'] ) ? $angle['image'] : '';
                        ?>
                        <tr class="bossier-option-row bossier-angle-option-row">
                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr( $prefix ); ?>[angles][<?php echo esc_attr( $idx ); ?>][label]"
                                       value="<?php echo esc_attr( $angle['label'] ); ?>"
                                       class="regular-text"
                                       placeholder="<?php esc_attr_e( 'e.g., 45° left', 'bossier-calculator' ); ?>">
                            </td>
                            <td>
                                <div class="bossier-angle-image-field">
                                    <?php if ( ! empty( $image ) ) : ?>
                                        <img src="<?php echo esc_url( $image ); ?>"
                                             alt=""
                                             class="bossier-angle-image-preview"
                                             style="max-width: 40px; max-height: 40px; vertical-align: middle; margin-right: 5px; border-radius: 3px;">
                                    <?php endif; ?>
                                    <input type="text"
                                           name="<?php echo esc_attr( $prefix ); ?>[angles][<?php echo esc_attr( $idx ); ?>][image]"
                                           value="<?php echo esc_url( $image ); ?>"
                                           class="bossier-image-url bossier-angle-image-url"
                                           placeholder="<?php esc_attr_e( 'Image URL', 'bossier-calculator' ); ?>"
                                           style="width: 120px;">
                                    <button type="button" class="button bossier-upload-image bossier-upload-angle-image">
                                        <span class="dashicons dashicons-upload"></span>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <input type="number"
                                       name="<?php echo esc_attr( $prefix ); ?>[angles][<?php echo esc_attr( $idx ); ?>][surcharge]"
                                       value="<?php echo esc_attr( $angle['surcharge'] ); ?>"
                                       step="any"
                                       class="small-text"
                                       style="width: 70px;">
                                <span class="description"><?php echo esc_html( $currency_symbol ); ?></span>
                            </td>
                            <td>
                                <input type="number"
                                       name="<?php echo esc_attr( $prefix ); ?>[angles][<?php echo esc_attr( $idx ); ?>][extra_weight]"
                                       value="<?php echo esc_attr( isset( $angle['extra_weight'] ) ? $angle['extra_weight'] : 0 ); ?>"
                                       step="any"
                                       class="small-text"
                                       style="width: 70px;">
                                <span class="description"><?php echo esc_html( $weight_unit ); ?></span>
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
        <button type="button" class="button bossier-add-angle-option" data-prefix="<?php echo esc_attr( $prefix ); ?>">
            <?php esc_html_e( 'Add Angle Option', 'bossier-calculator' ); ?>
        </button>
    </div>

    <p class="description" style="margin-top: 15px;">
        <strong><?php esc_html_e( 'Examples:', 'bossier-calculator' ); ?></strong>
        <?php esc_html_e( '"No cut" (0 surcharge), "45° left", "45° right", "45° both sides"', 'bossier-calculator' ); ?>
    </p>
</div>
