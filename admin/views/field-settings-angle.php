<?php
/**
 * Mitre angle field settings view - Multiple Groups.
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
$weight_unit     = get_option( 'woocommerce_weight_unit', 'kg' );

// Backward compatibility: migrate old single angles to groups structure
$mitre_groups = array();
if ( isset( $field['mitre_groups'] ) && ! empty( $field['mitre_groups'] ) ) {
    $mitre_groups = $field['mitre_groups'];
} elseif ( isset( $field['angles'] ) && ! empty( $field['angles'] ) ) {
    // Auto-migrate old structure to new groups structure
    $mitre_groups = array(
        array(
            'id'      => 'group_0',
            'label'   => __( 'Verstekhoek', 'bossier-calculator' ),
            'default' => isset( $field['default_angle'] ) ? (int) $field['default_angle'] : 0,
            'angles'  => $field['angles'],
        ),
    );
}
?>

<div class="bossier-field-section bossier-mitre-groups-section">
    <h4><?php esc_html_e( 'Verstekhoek Groepen', 'bossier-calculator' ); ?></h4>

    <p class="description" style="margin-bottom: 15px;">
        <?php esc_html_e( 'Voeg meerdere verstekhoek selecties toe (bijv. "Hoek links" en "Hoek rechts"). Elke groep wordt als apart keuzeveld getoond.', 'bossier-calculator' ); ?>
    </p>

    <div class="bossier-mitre-groups-container" data-prefix="<?php echo esc_attr( $prefix ); ?>">
        <?php
        if ( ! empty( $mitre_groups ) ) :
            foreach ( $mitre_groups as $group_idx => $group ) :
                $group_id     = isset( $group['id'] ) ? $group['id'] : 'group_' . $group_idx;
                $group_label  = isset( $group['label'] ) ? $group['label'] : '';
                $group_angles = isset( $group['angles'] ) ? $group['angles'] : array();
                $group_default = isset( $group['default'] ) ? (int) $group['default'] : 0;
                ?>
                <div class="bossier-mitre-group" data-group-idx="<?php echo esc_attr( $group_idx ); ?>">
                    <div class="bossier-mitre-group-header">
                        <input type="hidden"
                               name="<?php echo esc_attr( $prefix ); ?>[mitre_groups][<?php echo esc_attr( $group_idx ); ?>][id]"
                               value="<?php echo esc_attr( $group_id ); ?>">

                        <label><?php esc_html_e( 'Groep Label:', 'bossier-calculator' ); ?></label>
                        <input type="text"
                               name="<?php echo esc_attr( $prefix ); ?>[mitre_groups][<?php echo esc_attr( $group_idx ); ?>][label]"
                               value="<?php echo esc_attr( $group_label ); ?>"
                               class="regular-text bossier-mitre-group-label"
                               placeholder="<?php esc_attr_e( 'bijv. Hoek links', 'bossier-calculator' ); ?>">

                        <button type="button" class="button bossier-remove-mitre-group" title="<?php esc_attr_e( 'Groep verwijderen', 'bossier-calculator' ); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>

                    <div class="bossier-mitre-group-options">
                        <table class="bossier-options-table bossier-angle-options-table">
                            <thead>
                                <tr>
                                    <th style="width: 50px;"><?php esc_html_e( 'Standaard', 'bossier-calculator' ); ?></th>
                                    <th><?php esc_html_e( 'Label', 'bossier-calculator' ); ?></th>
                                    <th style="width: 70px;" title="<?php esc_attr_e( 'Wanneer geselecteerd worden andere verstekhoek groepen verborgen', 'bossier-calculator' ); ?>"><?php esc_html_e( 'Geen hoek', 'bossier-calculator' ); ?></th>
                                    <th><?php esc_html_e( 'Afbeelding', 'bossier-calculator' ); ?></th>
                                    <th><?php esc_html_e( 'Prijs', 'bossier-calculator' ); ?></th>
                                    <th><?php esc_html_e( 'Gewicht', 'bossier-calculator' ); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                if ( ! empty( $group_angles ) ) :
                                    foreach ( $group_angles as $angle_idx => $angle ) :
                                        $image = isset( $angle['image'] ) ? $angle['image'] : '';
                                        $is_default = ( (int) $group_default === (int) $angle_idx );
                                        ?>
                                        <tr class="bossier-option-row bossier-angle-option-row">
                                            <td style="text-align: center;">
                                                <input type="radio"
                                                       name="<?php echo esc_attr( $prefix ); ?>[mitre_groups][<?php echo esc_attr( $group_idx ); ?>][default]"
                                                       value="<?php echo esc_attr( $angle_idx ); ?>"
                                                       <?php checked( $is_default ); ?>>
                                            </td>
                                            <td>
                                                <input type="text"
                                                       name="<?php echo esc_attr( $prefix ); ?>[mitre_groups][<?php echo esc_attr( $group_idx ); ?>][angles][<?php echo esc_attr( $angle_idx ); ?>][label]"
                                                       value="<?php echo esc_attr( $angle['label'] ?? '' ); ?>"
                                                       class="regular-text"
                                                       placeholder="<?php esc_attr_e( 'bijv. 45°', 'bossier-calculator' ); ?>">
                                            </td>
                                            <td style="text-align: center;">
                                                <input type="checkbox"
                                                       name="<?php echo esc_attr( $prefix ); ?>[mitre_groups][<?php echo esc_attr( $group_idx ); ?>][angles][<?php echo esc_attr( $angle_idx ); ?>][is_no_mitre]"
                                                       value="1"
                                                       <?php checked( ! empty( $angle['is_no_mitre'] ) ); ?>
                                                       title="<?php esc_attr_e( 'Dit is een geen verstekhoek optie', 'bossier-calculator' ); ?>">
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
                                                           name="<?php echo esc_attr( $prefix ); ?>[mitre_groups][<?php echo esc_attr( $group_idx ); ?>][angles][<?php echo esc_attr( $angle_idx ); ?>][image]"
                                                           value="<?php echo esc_url( $image ); ?>"
                                                           class="bossier-image-url bossier-angle-image-url"
                                                           placeholder="<?php esc_attr_e( 'URL', 'bossier-calculator' ); ?>">
                                                    <button type="button" class="button bossier-upload-image bossier-upload-angle-image">
                                                        <span class="dashicons dashicons-upload"></span>
                                                    </button>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="number"
                                                       name="<?php echo esc_attr( $prefix ); ?>[mitre_groups][<?php echo esc_attr( $group_idx ); ?>][angles][<?php echo esc_attr( $angle_idx ); ?>][surcharge]"
                                                       value="<?php echo esc_attr( $angle['surcharge'] ?? 0 ); ?>"
                                                       step="any"
                                                       class="small-text">
                                                <span class="description"><?php echo esc_html( $currency_symbol ); ?></span>
                                            </td>
                                            <td>
                                                <input type="number"
                                                       name="<?php echo esc_attr( $prefix ); ?>[mitre_groups][<?php echo esc_attr( $group_idx ); ?>][angles][<?php echo esc_attr( $angle_idx ); ?>][extra_weight]"
                                                       value="<?php echo esc_attr( $angle['extra_weight'] ?? 0 ); ?>"
                                                       step="any"
                                                       class="small-text">
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
                        <button type="button" class="button bossier-add-group-angle-option" data-group-idx="<?php echo esc_attr( $group_idx ); ?>">
                            <?php esc_html_e( 'Optie Toevoegen', 'bossier-calculator' ); ?>
                        </button>
                    </div>
                </div>
                <?php
            endforeach;
        endif;
        ?>
    </div>

    <div class="bossier-mitre-groups-actions" style="margin-top: 15px;">
        <button type="button" class="button button-primary bossier-add-mitre-group" data-prefix="<?php echo esc_attr( $prefix ); ?>">
            <span class="dashicons dashicons-plus-alt2" style="vertical-align: middle;"></span>
            <?php esc_html_e( 'Verstekhoek Groep Toevoegen', 'bossier-calculator' ); ?>
        </button>
    </div>

    <p class="description" style="margin-top: 15px;">
        <strong><?php esc_html_e( 'Tip:', 'bossier-calculator' ); ?></strong>
        <?php esc_html_e( 'Maak een groep voor elke zijde (bijv. "Hoek links", "Hoek rechts") met opties zoals "Geen", "45°", "90°".', 'bossier-calculator' ); ?>
    </p>
</div>

<style>
.bossier-mitre-group {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    margin-bottom: 15px;
}
.bossier-mitre-group-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #ddd;
}
.bossier-mitre-group-header label {
    font-weight: 600;
}
.bossier-mitre-group-header .bossier-mitre-group-label {
    flex: 1;
}
.bossier-mitre-group-options .bossier-options-table {
    background: #fff;
}
.bossier-remove-mitre-group .dashicons {
    color: #b32d2e;
}
</style>
