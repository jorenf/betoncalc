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

$colors          = isset( $field['colors'] ) ? $field['colors'] : array();
$currency_symbol = get_woocommerce_currency_symbol();
?>

<div class="bossier-field-section">
    <h4><?php esc_html_e( 'Kleur Opties', 'bossier-calculator' ); ?></h4>

    <p class="description" style="margin-bottom: 15px;">
        <?php esc_html_e( 'De standaard kleur (bijv. Grijs) is inbegrepen in de basisprijs. Andere kleuren kunnen een vaste toeslag of percentage van de grijze prijs hebben.', 'bossier-calculator' ); ?>
    </p>

    <div class="bossier-options-list bossier-color-options-list">
        <table class="bossier-options-table bossier-color-options-table">
            <thead>
                <tr>
                    <th style="width: 30px;"><?php esc_html_e( 'Standaard', 'bossier-calculator' ); ?></th>
                    <th><?php esc_html_e( 'Kleur Naam', 'bossier-calculator' ); ?></th>
                    <th><?php esc_html_e( 'Hex/Afbeelding', 'bossier-calculator' ); ?></th>
                    <th><?php esc_html_e( 'Prijs Type', 'bossier-calculator' ); ?></th>
                    <th><?php esc_html_e( 'Toeslag', 'bossier-calculator' ); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ( ! empty( $colors ) ) :
                    foreach ( $colors as $idx => $color ) :
                        $is_default = isset( $color['is_default'] ) ? $color['is_default'] : false;
                        $price_type = isset( $color['price_type'] ) ? $color['price_type'] : 'fixed';
                        $surcharge  = isset( $color['surcharge'] ) ? $color['surcharge'] : 0;
                        ?>
                        <tr class="bossier-option-row bossier-color-option-row">
                            <td style="text-align: center;">
                                <input type="radio"
                                       name="<?php echo esc_attr( $prefix ); ?>[default_color]"
                                       value="<?php echo esc_attr( $idx ); ?>"
                                       class="bossier-default-color-radio"
                                       <?php checked( $is_default ); ?>>
                                <input type="hidden"
                                       name="<?php echo esc_attr( $prefix ); ?>[colors][<?php echo esc_attr( $idx ); ?>][is_default]"
                                       value="<?php echo $is_default ? '1' : '0'; ?>"
                                       class="bossier-is-default-hidden">
                            </td>
                            <td>
                                <input type="text"
                                       name="<?php echo esc_attr( $prefix ); ?>[colors][<?php echo esc_attr( $idx ); ?>][name]"
                                       value="<?php echo esc_attr( $color['name'] ); ?>"
                                       class="regular-text"
                                       placeholder="<?php esc_attr_e( 'bijv. Grijs', 'bossier-calculator' ); ?>">
                            </td>
                            <td>
                                <div class="bossier-color-hex-image">
                                    <input type="text"
                                           name="<?php echo esc_attr( $prefix ); ?>[colors][<?php echo esc_attr( $idx ); ?>][hex]"
                                           value="<?php echo esc_attr( $color['hex'] ); ?>"
                                           class="bossier-color-picker"
                                           data-default-color="#808080"
                                           style="width: 80px;">
                                    <div class="bossier-image-field" style="display: inline-flex; margin-left: 5px;">
                                        <input type="text"
                                               name="<?php echo esc_attr( $prefix ); ?>[colors][<?php echo esc_attr( $idx ); ?>][image]"
                                               value="<?php echo esc_url( isset( $color['image'] ) ? $color['image'] : '' ); ?>"
                                               class="bossier-image-url"
                                               placeholder="<?php esc_attr_e( 'Afbeelding URL', 'bossier-calculator' ); ?>"
                                               style="width: 100px;">
                                        <button type="button" class="button bossier-upload-image">
                                            <span class="dashicons dashicons-upload"></span>
                                        </button>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <select name="<?php echo esc_attr( $prefix ); ?>[colors][<?php echo esc_attr( $idx ); ?>][price_type]"
                                        class="bossier-color-price-type"
                                        style="width: 100px;"
                                        <?php echo $is_default ? 'disabled' : ''; ?>>
                                    <option value="fixed" <?php selected( $price_type, 'fixed' ); ?>>
                                        <?php echo esc_html( $currency_symbol ); ?> <?php esc_html_e( 'Vast', 'bossier-calculator' ); ?>
                                    </option>
                                    <option value="percentage" <?php selected( $price_type, 'percentage' ); ?>>
                                        % <?php esc_html_e( 'van Grijs', 'bossier-calculator' ); ?>
                                    </option>
                                </select>
                                <?php if ( $is_default ) : ?>
                                    <input type="hidden"
                                           name="<?php echo esc_attr( $prefix ); ?>[colors][<?php echo esc_attr( $idx ); ?>][price_type]"
                                           value="fixed">
                                <?php endif; ?>
                            </td>
                            <td>
                                <input type="number"
                                       name="<?php echo esc_attr( $prefix ); ?>[colors][<?php echo esc_attr( $idx ); ?>][surcharge]"
                                       value="<?php echo esc_attr( $surcharge ); ?>"
                                       step="any"
                                       class="small-text bossier-color-surcharge"
                                       style="width: 70px;"
                                       <?php echo $is_default ? 'disabled' : ''; ?>>
                                <span class="bossier-surcharge-unit">
                                    <?php echo 'percentage' === $price_type ? '%' : esc_html( $currency_symbol ); ?>
                                </span>
                                <?php if ( $is_default ) : ?>
                                    <input type="hidden"
                                           name="<?php echo esc_attr( $prefix ); ?>[colors][<?php echo esc_attr( $idx ); ?>][surcharge]"
                                           value="0">
                                    <span class="description" style="color: #666;"><?php esc_html_e( '(inbegrepen)', 'bossier-calculator' ); ?></span>
                                <?php endif; ?>
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
            <?php esc_html_e( 'Kleur Toevoegen', 'bossier-calculator' ); ?>
        </button>
    </div>

    <p class="description" style="margin-top: 15px;">
        <strong><?php esc_html_e( 'Tip:', 'bossier-calculator' ); ?></strong>
        <?php esc_html_e( 'Markeer één kleur als "Standaard" (meestal Grijs). Deze kleur heeft geen toeslag. Percentage toeslagen worden berekend op de grijze prijs (basis + lengte).', 'bossier-calculator' ); ?>
    </p>
</div>

<script>
jQuery(function($) {
    // Handle default color radio change
    $(document).on('change', '.bossier-default-color-radio', function() {
        var $table = $(this).closest('.bossier-color-options-table');

        // Reset all is_default hidden fields
        $table.find('.bossier-is-default-hidden').val('0');

        // Set the selected one
        $(this).closest('tr').find('.bossier-is-default-hidden').val('1');

        // Enable/disable surcharge fields
        $table.find('.bossier-color-option-row').each(function() {
            var $row = $(this);
            var isDefault = $row.find('.bossier-default-color-radio').is(':checked');
            var $priceType = $row.find('.bossier-color-price-type');
            var $surcharge = $row.find('.bossier-color-surcharge');

            if (isDefault) {
                $priceType.prop('disabled', true);
                $surcharge.prop('disabled', true).val('0');
            } else {
                $priceType.prop('disabled', false);
                $surcharge.prop('disabled', false);
            }
        });
    });

    // Handle price type change
    $(document).on('change', '.bossier-color-price-type', function() {
        var $row = $(this).closest('tr');
        var $unit = $row.find('.bossier-surcharge-unit');
        if ($(this).val() === 'percentage') {
            $unit.text('%');
        } else {
            $unit.text('<?php echo esc_js( $currency_symbol ); ?>');
        }
    });
});
</script>
