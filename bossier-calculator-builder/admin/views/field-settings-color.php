<?php
/**
 * Color field settings view.
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

$colors = isset( $field['colors'] ) ? $field['colors'] : array();
?>

<div class="bossier-field-section">
    <h4><?php esc_html_e( 'Color Options', 'bossier-calculator' ); ?></h4>

    <div class="bossier-options-list bossier-color-options-list">
        <table class="bossier-options-table">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Color Name', 'bossier-calculator' ); ?></th>
                    <th><?php esc_html_e( 'Hex Color', 'bossier-calculator' ); ?></th>
                    <th><?php esc_html_e( 'Image URL', 'bossier-calculator' ); ?></th>
                    <th><?php esc_html_e( 'Price Surcharge', 'bossier-calculator' ); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ( ! empty( $colors ) ) :
                    foreach ( $colors as $idx => $color ) :
                        ?>
                        <tr class="bossier-option-row">
                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr( $prefix ); ?>[colors][<?php echo esc_attr( $idx ); ?>][name]"
                                       value="<?php echo esc_attr( $color['name'] ); ?>"
                                       class="regular-text">
                            </td>
                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr( $prefix ); ?>[colors][<?php echo esc_attr( $idx ); ?>][hex]"
                                       value="<?php echo esc_attr( $color['hex'] ); ?>"
                                       class="bossier-color-picker"
                                       data-default-color="#000000">
                            </td>
                            <td>
                                <div class="bossier-image-field">
                                    <input type="text"
                                           name="<?php echo esc_attr( $prefix ); ?>[colors][<?php echo esc_attr( $idx ); ?>][image]"
                                           value="<?php echo esc_url( $color['image'] ); ?>"
                                           class="regular-text bossier-image-url">
                                    <button type="button" class="button bossier-upload-image">
                                        <span class="dashicons dashicons-upload"></span>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <input type="number"
                                       name="<?php echo esc_attr( $prefix ); ?>[colors][<?php echo esc_attr( $idx ); ?>][surcharge]"
                                       value="<?php echo esc_attr( $color['surcharge'] ); ?>"
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
        <button type="button" class="button bossier-add-color-option" data-prefix="<?php echo esc_attr( $prefix ); ?>">
            <?php esc_html_e( 'Add Color', 'bossier-calculator' ); ?>
        </button>
    </div>
</div>
