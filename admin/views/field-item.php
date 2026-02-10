<?php
/**
 * Single field item view.
 *
 * @package Bossier_Calculator_Builder
 */

defined( 'ABSPATH' ) || exit;

/**
 * Variables available:
 *
 * @var array $field Field configuration data.
 */

$field_id     = isset( $field['id'] ) ? $field['id'] : '';
$field_type   = isset( $field['type'] ) ? $field['type'] : 'custom';
$field_label  = isset( $field['label'] ) ? $field['label'] : '';
$enabled      = isset( $field['enabled'] ) ? $field['enabled'] : true;
$required     = isset( $field['required'] ) ? $field['required'] : false;
$display_order= isset( $field['display_order'] ) ? $field['display_order'] : 0;
$input_type   = isset( $field['input_type'] ) ? $field['input_type'] : 'text';
$help_text    = isset( $field['help_text'] ) ? $field['help_text'] : '';

// Use get_all_types_including_legacy to support deprecated length fields
$field_types  = \Bossier\Calculator\Field_Types::get_all_types_including_legacy();
$type_label   = isset( $field_types[ $field_type ]['label'] ) ? $field_types[ $field_type ]['label'] : $field_type;
$input_types  = \Bossier\Calculator\Field_Types::get_input_types( $field_type );
$is_legacy    = \Bossier\Calculator\Field_Types::is_legacy_type( $field_type );

$prefix = "bossier_fields[{$field_id}]";

$item_classes = 'bossier-field-item';
if ( $is_legacy ) {
    $item_classes .= ' bossier-field-deprecated';
}
?>

<div class="<?php echo esc_attr( $item_classes ); ?>" data-field-id="<?php echo esc_attr( $field_id ); ?>" data-field-type="<?php echo esc_attr( $field_type ); ?>">
    <div class="bossier-field-header">
        <span class="bossier-field-drag dashicons dashicons-move"></span>
        <span class="bossier-field-type-badge<?php echo $is_legacy ? ' bossier-field-type-deprecated' : ''; ?>"><?php echo esc_html( $type_label ); ?></span>
        <input type="text"
               name="<?php echo esc_attr( $prefix ); ?>[label]"
               value="<?php echo esc_attr( $field_label ); ?>"
               class="bossier-field-label-input"
               placeholder="<?php esc_attr_e( 'Veld Label', 'bossier-calculator' ); ?>">
        <span class="bossier-field-actions">
            <button type="button" class="bossier-field-toggle" title="<?php esc_attr_e( 'Uitklappen', 'bossier-calculator' ); ?>">
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </button>
            <button type="button" class="bossier-field-delete" title="<?php esc_attr_e( 'Verwijderen', 'bossier-calculator' ); ?>">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </span>
    </div>

    <div class="bossier-field-body">
        <input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[type]" value="<?php echo esc_attr( $field_type ); ?>">
        <input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[display_order]" value="<?php echo esc_attr( $display_order ); ?>" class="bossier-field-order">

        <?php if ( $is_legacy ) : ?>
        <div class="bossier-deprecation-notice">
            <span class="dashicons dashicons-warning"></span>
            <strong><?php esc_html_e( 'Verouderd veld', 'bossier-calculator' ); ?></strong>
            <p><?php esc_html_e( 'Dit lengteveld wordt niet meer gebruikt. Lengte wordt nu automatisch afgehandeld via de zijbalk instellingen. Dit veld wordt genegeerd op de frontend en kan veilig worden verwijderd.', 'bossier-calculator' ); ?></p>
        </div>
        <?php endif; ?>

        <div class="bossier-field-row bossier-field-row-inline">
            <label>
                <input type="checkbox"
                       name="<?php echo esc_attr( $prefix ); ?>[enabled]"
                       value="1"
                       <?php checked( $enabled ); ?>>
                <?php esc_html_e( 'Actief', 'bossier-calculator' ); ?>
                <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Wanneer actief, wordt dit veld getoond aan klanten op de productpagina.', 'bossier-calculator' ); ?>">?</span>
            </label>
            <label>
                <input type="checkbox"
                       name="<?php echo esc_attr( $prefix ); ?>[required]"
                       value="1"
                       <?php checked( $required ); ?>>
                <?php esc_html_e( 'Verplicht', 'bossier-calculator' ); ?>
                <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Wanneer verplicht, moeten klanten dit veld invullen voordat ze kunnen toevoegen aan winkelwagen.', 'bossier-calculator' ); ?>">?</span>
            </label>
        </div>

        <div class="bossier-field-row">
            <label>
                <?php esc_html_e( 'Invoer Type', 'bossier-calculator' ); ?>
                <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Hoe dit veld wordt getoond aan klanten: dropdown, radio buttons, nummer invoer, kleur swatches, etc.', 'bossier-calculator' ); ?>">?</span>
                <select name="<?php echo esc_attr( $prefix ); ?>[input_type]">
                    <?php foreach ( $input_types as $type_key => $type_name ) : ?>
                        <option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $input_type, $type_key ); ?>>
                            <?php echo esc_html( $type_name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="bossier-field-row bossier-field-helptext-row">
            <label>
                <?php esc_html_e( 'Klant Hulptekst / Tooltip', 'bossier-calculator' ); ?>
                <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'Deze tekst verschijnt als een (?) tooltip naast het veld label op de productpagina. Gebruik dit om uit te leggen wat klanten moeten invoeren of selecteren.', 'bossier-calculator' ); ?>">?</span>
            </label>
            <textarea name="<?php echo esc_attr( $prefix ); ?>[help_text]"
                      rows="3"
                      class="widefat"
                      placeholder="<?php esc_attr_e( 'Voorbeeld: Voer de gewenste lengte in millimeters in. Standaard lengtes zijn tussen 500mm en 3000mm.', 'bossier-calculator' ); ?>"><?php echo esc_textarea( $help_text ); ?></textarea>
            <p class="description">
                <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                <?php esc_html_e( 'Schrijf hier nuttige informatie die als tooltip (?) icoon naast het veld label op de frontend verschijnt. Dit helpt klanten te begrijpen wat ze moeten invoeren.', 'bossier-calculator' ); ?>
            </p>
        </div>

        <?php
        // Field type specific settings
        switch ( $field_type ) :
            case 'length':
                include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/field-settings-length.php';
                break;

            case 'color':
                include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/field-settings-color.php';
                break;

            case 'mitre_angle':
                include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/field-settings-angle.php';
                break;

            case 'quantity':
                include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/field-settings-quantity.php';
                break;

            case 'custom':
                include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/field-settings-custom.php';
                break;

            case 'dimension':
                include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/field-settings-dimension.php';
                break;

            case 'text':
                include BOSSIER_CALC_PLUGIN_DIR . 'admin/views/field-settings-text.php';
                break;
        endswitch;
        ?>
    </div>
</div>
