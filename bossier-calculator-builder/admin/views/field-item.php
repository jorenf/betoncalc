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

$field_types  = \Bossier\Calculator\Field_Types::get_types();
$type_label   = isset( $field_types[ $field_type ]['label'] ) ? $field_types[ $field_type ]['label'] : $field_type;
$input_types  = \Bossier\Calculator\Field_Types::get_input_types( $field_type );

$prefix = "bossier_fields[{$field_id}]";
?>

<div class="bossier-field-item" data-field-id="<?php echo esc_attr( $field_id ); ?>" data-field-type="<?php echo esc_attr( $field_type ); ?>">
    <div class="bossier-field-header">
        <span class="bossier-field-drag dashicons dashicons-move"></span>
        <span class="bossier-field-type-badge"><?php echo esc_html( $type_label ); ?></span>
        <input type="text"
               name="<?php echo esc_attr( $prefix ); ?>[label]"
               value="<?php echo esc_attr( $field_label ); ?>"
               class="bossier-field-label-input"
               placeholder="<?php esc_attr_e( 'Field Label', 'bossier-calculator' ); ?>">
        <span class="bossier-field-actions">
            <button type="button" class="bossier-field-toggle" title="<?php esc_attr_e( 'Toggle', 'bossier-calculator' ); ?>">
                <span class="dashicons dashicons-arrow-down-alt2"></span>
            </button>
            <button type="button" class="bossier-field-delete" title="<?php esc_attr_e( 'Delete', 'bossier-calculator' ); ?>">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </span>
    </div>

    <div class="bossier-field-body">
        <input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[type]" value="<?php echo esc_attr( $field_type ); ?>">
        <input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[display_order]" value="<?php echo esc_attr( $display_order ); ?>" class="bossier-field-order">

        <div class="bossier-field-row bossier-field-row-inline">
            <label>
                <input type="checkbox"
                       name="<?php echo esc_attr( $prefix ); ?>[enabled]"
                       value="1"
                       <?php checked( $enabled ); ?>>
                <?php esc_html_e( 'Enabled', 'bossier-calculator' ); ?>
            </label>
            <label>
                <input type="checkbox"
                       name="<?php echo esc_attr( $prefix ); ?>[required]"
                       value="1"
                       <?php checked( $required ); ?>>
                <?php esc_html_e( 'Required', 'bossier-calculator' ); ?>
            </label>
        </div>

        <div class="bossier-field-row">
            <label>
                <?php esc_html_e( 'Input Type', 'bossier-calculator' ); ?>
                <select name="<?php echo esc_attr( $prefix ); ?>[input_type]">
                    <?php foreach ( $input_types as $type_key => $type_name ) : ?>
                        <option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $input_type, $type_key ); ?>>
                            <?php echo esc_html( $type_name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
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
        endswitch;
        ?>
    </div>
</div>
