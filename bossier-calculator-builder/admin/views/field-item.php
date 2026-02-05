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
                <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'When enabled, this field will be shown to customers on the product page.', 'bossier-calculator' ); ?>">?</span>
            </label>
            <label>
                <input type="checkbox"
                       name="<?php echo esc_attr( $prefix ); ?>[required]"
                       value="1"
                       <?php checked( $required ); ?>>
                <?php esc_html_e( 'Required', 'bossier-calculator' ); ?>
                <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'When required, customers must fill in this field before adding to cart.', 'bossier-calculator' ); ?>">?</span>
            </label>
        </div>

        <div class="bossier-field-row">
            <label>
                <?php esc_html_e( 'Input Type', 'bossier-calculator' ); ?>
                <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'How this field is displayed to customers: dropdown, radio buttons, number input, color swatches, etc.', 'bossier-calculator' ); ?>">?</span>
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
                <?php esc_html_e( 'Customer Help Text / Tooltip', 'bossier-calculator' ); ?>
                <span class="bossier-admin-tooltip" data-tip="<?php esc_attr_e( 'This text appears as a (?) tooltip next to the field label on the product page. Use it to explain what customers should enter or select.', 'bossier-calculator' ); ?>">?</span>
            </label>
            <textarea name="<?php echo esc_attr( $prefix ); ?>[help_text]"
                      rows="3"
                      class="widefat"
                      placeholder="<?php esc_attr_e( 'Example: Enter the desired length in millimeters. Standard lengths are between 500mm and 3000mm.', 'bossier-calculator' ); ?>"><?php echo esc_textarea( $help_text ); ?></textarea>
            <p class="description">
                <span class="dashicons dashicons-info" style="color: #2271b1;"></span>
                <?php esc_html_e( 'Write helpful information here that will appear as a tooltip (?) icon next to the field label on the frontend. This helps customers understand what to enter.', 'bossier-calculator' ); ?>
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
        endswitch;
        ?>
    </div>
</div>
